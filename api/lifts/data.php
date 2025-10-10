<?php
// api/lifts/get_latest_status.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';

CORSMiddleware::handle();
header('Content-Type: application/json; charset=utf-8');
// กัน browser/proxy cache เก่า แต่ให้ใช้ ETag เป็นตัวตัดสิน
header('Cache-Control: no-cache, must-revalidate');

try {
  $db    = Database::getConnection();
  $redis = RedisClient::getConnection();

  // โหลด meta ลิฟต์จาก DB
  $stmt = $db->query("SELECT l.id, l.lift_name, l.max_level, l.floor_name,
                             o.org_name, b.building_name
                      FROM lifts l
                      LEFT JOIN organizations o ON l.org_id = o.id
                      LEFT JOIN buildings b     ON l.building_id = b.id");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // เก็บคีย์เป็นสตริงล้วน
  $metaById = [];
  foreach ($rows as $r) {
    $metaById[(string)$r['id']] = $r;
  }

  // เลือก id ที่ต้องดึง (คีย์เป็นสตริง)
  $idsToFetch = array_keys($metaById);
  if (isset($_GET['id'])) {
    $ask = trim((string)$_GET['id']);            // string
    $idsToFetch = array_values(array_intersect($idsToFetch, [$ask]));
  } elseif (isset($_GET['ids'])) {
    $ask = array_filter(array_map('trim', explode(',', (string)$_GET['ids']))); // string[]
    $idsToFetch = array_values(array_intersect($idsToFetch, $ask));
  }

  $debug = isset($_GET['debug']) && $_GET['debug'] !== '0';

  $result = [
    'timestamp' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'lifts'     => [],
  ];
  if ($debug) {
    $result['_debug'] = [
      'db_count'     => count($rows),
      'ids_from_db'  => array_keys($metaById),
      'ids_to_fetch' => $idsToFetch,
      'keys'         => [],
    ];
  }

  foreach ($idsToFetch as $id) {
    $idStr = (string)$id;                  // ✅ ใช้สตริงเสมอ
    $meta  = $metaById[$idStr] ?? null;
    if (!$meta) continue;

    $key = 'Lift-' . str_pad($idStr, 4, '0', STR_PAD_LEFT); // ✅ คีย์มาตรฐาน
    if ($debug) $result['_debug']['keys'][] = $key;

    // list index 0..8 (9 ช่อง)
    $list = $redis->lRange($key, 0, 8);
    $result['lifts'][$idStr] = mapFromRedisList($meta, is_array($list) ? $list : []);
  }

  // ====== ⬇⬇⬇ เพิ่ม ETag/If-None-Match เพื่อลดทราฟฟิก (ทางเลือก A) ⬇⬇⬇ ======
  $payload = json_encode(
    $result,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
  );

  // ใช้ hash จาก payload (หรือจะผสมเวลาจาก Redis ก็ได้)
  $etag = '"' . md5($payload) . '"';
  header('ETag: ' . $etag);

  // ถ้า client ส่ง If-None-Match ตรงกับ ETag เดิม → ตอบ 304 ไม่มี body
  $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
  if ($ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
  }
  // ====== ⬆⬆⬆ END: ETag/304 logic ⬆⬆⬆ ======

  echo $payload;
} catch (Throwable $e) {
  http_response_code(200);
  echo json_encode([
    'error'   => true,
    'message' => $e->getMessage(),
    'file'    => $e->getFile(),
    'line'    => $e->getLine(),
    'trace'   => (isset($_GET['debug']) ? $e->getTraceAsString() : null),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

// ===== helper =====
function mapFromRedisList(array $meta, array $list): array {
  if (count($list) < 9) {
    return [
      'id'             => (int)$meta['id'],
      'lift_name'      => $meta['lift_name'],
      'org_name'       => $meta['org_name'] ?? '-',
      'building_name'  => $meta['building_name'] ?? '-',
      'floor_name'     => $meta['floor_name'] ?? '1,2,3,4',
      'max_level'      => (int)($meta['max_level'] ?? 1),
      'connection'     => 'OFFLINE',
      'lift_state_hex' => '0000000000000000',
      'up_status_hex'  => '00000000',
      'down_status_hex'=> '00000000',
      'car_status_hex' => '00000000',
      'last_update'    => null,
    ];
  }

  $lastUpdate = $list[7] ?? null;
  $online = 'OFFLINE';
  if ($lastUpdate) {
    try {
      $now  = new DateTimeImmutable('now');
      $date = new DateTimeImmutable($lastUpdate);
      $diff = $now->getTimestamp() - $date->getTimestamp();
      $online = ($diff <= 30) ? 'ONLINE' : 'OFFLINE';
    } catch (Throwable $e) {
      $online = 'OFFLINE';
    }
  }

  return [
    'id'             => (int)$meta['id'],
    'lift_name'      => $list[1] ?? $meta['lift_name'],
    'org_name'       => $list[0] ?? ($meta['org_name'] ?? '-'),
    'building_name'  => $meta['building_name'] ?? '-',
    'floor_name'     => $list[8] ?? ($meta['floor_name'] ?? '1,2,3,4'),
    'max_level'      => (int)($list[2] ?? ($meta['max_level'] ?? 1)),
    'connection'     => $online,
    'lift_state_hex' => $list[3] ?? '0000000000000000',
    'up_status_hex'  => $list[4] ?? '00000000',
    'down_status_hex'=> $list[5] ?? '00000000',
    'car_status_hex' => $list[6] ?? '00000000',
    'last_update'    => $lastUpdate,
  ];
}
