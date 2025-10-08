<?php
// api/lifts/stream_status.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';

// ---------- CORS ----------
CORSMiddleware::handle(); // ห้าม echo/exit

// ---------- SSE headers ----------
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // เผื่ออยู่หลัง nginx

// ---------- ปิดบัฟเฟอร์/บีบอัด ----------
if (function_exists('apache_setenv')) {
  @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
@ini_set('output_handler', '');
while (ob_get_level() > 0) { @ob_end_flush(); }
@ob_implicit_flush(true);

// ---------- กัน timeout / ทำงานต่อแม้ client ปิด ----------
ignore_user_abort(true);
set_time_limit(0);

// ---------- ให้ client reconnect ไวหน่อย ----------
echo "retry: 1500\n\n";
flush();

// ---------- Helpers ----------
function sse_send(string $event, string $json): void {
  echo "event: {$event}\n";
  echo "data: {$json}\n\n";
  if (ob_get_level() > 0) { @ob_flush(); }
  flush();
}

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
      $diff = (new DateTimeImmutable('now'))->getTimestamp() - (new DateTimeImmutable($lastUpdate))->getTimestamp();
      $online = ($diff <= 30) ? 'ONLINE' : 'OFFLINE';
    } catch (Throwable $e) { $online = 'OFFLINE'; }
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

// สร้าง "hash เบา" ต่อ 1 ลิฟต์ จากฟิลด์ที่เปลี่ยนบ่อย/สำคัญ
function lift_light_hash(array $lift): string {
  $base =
    ($lift['connection']     ?? '') . '|' .
    ($lift['lift_state_hex'] ?? '') . '|' .
    ($lift['up_status_hex']  ?? '') . '|' .
    ($lift['down_status_hex']?? '') . '|' .
    ($lift['car_status_hex'] ?? '') . '|' .
    ($lift['last_update']    ?? '');
  return md5($base);
}

// ---------- Main ----------
try {
  $db    = Database::getConnection();
  $redis = RedisClient::getConnection();

  // meta คงที่ โหลดครั้งเดียว
  $stmt = $db->query(
    "SELECT l.id, l.lift_name, l.max_level, l.floor_name,
            o.org_name, b.building_name
     FROM lifts l
     LEFT JOIN organizations o ON l.org_id = o.id
     LEFT JOIN buildings b     ON l.building_id = b.id"
  );
  $allMeta = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $allMeta[(string)$row['id']] = $row;
  }

  // filter id ที่ต้องการ
  $allIds    = array_keys($allMeta);
  $idsToSend = $allIds;
  if (!empty($_GET['ids'])) {
    $req = array_filter(array_map('trim', explode(',', (string)$_GET['ids'])));
    $idsToSend = array_values(array_intersect($allIds, $req));
  } elseif (!empty($_GET['id'])) {
    $req = trim((string)$_GET['id']);
    $idsToSend = array_values(array_intersect($allIds, [$req]));
  }

  if (!$idsToSend) {
    echo ": no valid lift ids to stream\n\n";
    flush();
    exit;
  }

  // ---------- ส่ง snapshot ครั้งแรก ----------
  $snapshot = [
    'timestamp' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'lifts'     => [],
  ];
  foreach ($idsToSend as $id) {
    $meta = $allMeta[$id];
    $key  = 'Lift-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
    $list = $redis->lRange($key, 0, 8);
    $lift = mapFromRedisList($meta, is_array($list) ? $list : []);
    $snapshot['lifts'][$id] = $lift;
  }
  sse_send('lift_snapshot', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

  // เก็บ light hash ต่อ id เพื่อเทียบ diff
  $lastHashes = [];
  foreach ($snapshot['lifts'] as $id => $lift) {
    $lastHashes[$id] = lift_light_hash($lift);
  }

  // ---------- loop diff-only ----------
  $HEARTBEAT_EVERY = 12;               // วินาที
  $lastHeartbeat   = time();
  $LOOP_USLEEP     = 100000;           // 200ms

  while (true) {
    if (connection_aborted()) break;

    $changed = [];
    foreach ($idsToSend as $id) {
      $meta = $allMeta[$id] ?? null;
      if (!$meta) continue;

      $key  = 'Lift-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
      $list = $redis->lRange($key, 0, 8);
      $lift = mapFromRedisList($meta, is_array($list) ? $list : []);

      $h = lift_light_hash($lift);
      if (!isset($lastHashes[$id]) || $h !== $lastHashes[$id]) {
        $changed[$id]   = $lift;
        $lastHashes[$id]= $h;
      }
    }

    if ($changed) {
      $diffPayload = [
        'timestamp' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        'changed'   => array_keys($changed),  // รายการ id ที่เปลี่ยน
        'lifts'     => $changed,              // รายละเอียดเฉพาะตัวที่เปลี่ยน
      ];
      sse_send('lift_diff', json_encode($diffPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      $lastHeartbeat = time(); // ถือว่าเพิ่งส่งข้อมูลแล้ว
    } else {
      // heartbeat กัน proxy
      $now = time();
      if ($now - $lastHeartbeat >= $HEARTBEAT_EVERY) {
        echo ": heartbeat\n\n";
        flush();
        $lastHeartbeat = $now;
      }
    }

    usleep($LOOP_USLEEP);
  }

} catch (Throwable $e) {
  error_log("SSE Stream Error: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
  echo ": fatal error, closing\n\n";
  flush();
}
