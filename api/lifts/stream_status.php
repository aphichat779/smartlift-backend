<?php
declare(strict_types=1);

// api/lifts/stream_status.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

CORSMiddleware::handle(); // ห้าม echo body

// ===== helper =====
function sse_header(): void {
  // ป้องกัน output เก่าทั้งหมด (รวม BOM)
  while (ob_get_level() > 0) { @ob_end_clean(); }
  if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
  @ini_set('zlib.output_compression', '0');
  @ini_set('output_buffering', '0');
  @ini_set('implicit_flush', '1');
  @ini_set('output_handler', '');

  header('Content-Type: text/event-stream');
  header('Cache-Control: no-cache, no-transform');
  header('Connection: keep-alive');
  header('X-Accel-Buffering: no'); // nginx

  @ob_implicit_flush(true);
  echo "retry: 1500\n\n";
  flush();
}
function sse_send(string $event, string $json): void {
  echo "event: {$event}\n";
  echo "data: {$json}\n\n";
  if (ob_get_level() > 0) { @ob_flush(); }
  flush();
}
function sse_comment(string $text): void {
  echo ": {$text}\n\n";
  if (ob_get_level() > 0) { @ob_flush(); }
  flush();
}
function mapFromRedisList(array $meta, array $list): array {
  if (count($list) < 9) {
    return [
      'id'=>(int)$meta['id'],'lift_name'=>$meta['lift_name'],
      'org_name'=>$meta['org_name']??'-','building_name'=>$meta['building_name']??'-',
      'floor_name'=>$meta['floor_name']??'1,2,3,4','max_level'=>(int)($meta['max_level']??1),
      'connection'=>'OFFLINE','lift_state_hex'=>'0000000000000000',
      'up_status_hex'=>'00000000','down_status_hex'=>'00000000','car_status_hex'=>'00000000',
      'last_update'=>null,
    ];
  }
  $last=$list[7]??null; $online='OFFLINE';
  if ($last) { try {
    $diff=(new DateTimeImmutable('now'))->getTimestamp()-(new DateTimeImmutable($last))->getTimestamp();
    $online=($diff<=30)?'ONLINE':'OFFLINE';
  } catch (Throwable $e) {} }
  return [
    'id'=>(int)$meta['id'],'lift_name'=>$list[1]??$meta['lift_name'],
    'org_name'=>$list[0]??($meta['org_name']??'-'),'building_name'=>$meta['building_name']??'-',
    'floor_name'=>$list[8]??($meta['floor_name']??'1,2,3,4'),
    'max_level'=>(int)($list[2]??($meta['max_level']??1)),'connection'=>$online,
    'lift_state_hex'=>$list[3]??'0000000000000000','up_status_hex'=>$list[4]??'00000000',
    'down_status_hex'=>$list[5]??'00000000','car_status_hex'=>$list[6]??'00000000',
    'last_update'=>$last,
  ];
}
function lift_light_hash(array $lift): string {
  return md5(
    ($lift['connection']??'').'|'.($lift['lift_state_hex']??'').'|'.
    ($lift['up_status_hex']??'').'|'.($lift['down_status_hex']??'').'|'.
    ($lift['car_status_hex']??'').'|'.($lift['last_update']??'')
  );
}

try {
  // 1) auth ก่อน ส่ง header ใดๆ
  $user = AuthMiddleware::authenticateFromRequest();

  // 2) เตรียม DB/Redis
  $db    = Database::getConnection();
  $redis = RedisClient::getConnection();

  // 3) scope
  [$where, $params] = AuthMiddleware::requireOrgScope($user);

  $sql = "SELECT l.id,l.lift_name,l.max_level,l.floor_name,o.org_name,b.building_name
          FROM lifts l
          LEFT JOIN organizations o ON l.org_id=o.id
          LEFT JOIN buildings b ON l.building_id=b.id";
  if ($where) $sql .= " WHERE {$where}";
  $stmt = $db->prepare($sql);
  $stmt->execute($params ?? []);
  $meta = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $meta[(string)$r['id']] = $r; }

  $allIds = array_keys($meta);
  $idsTo  = $allIds;
  if (!empty($_GET['ids'])) {
    $req = array_filter(array_map('trim', explode(',', (string)$_GET['ids'])));
    $idsTo = array_values(array_intersect($allIds, $req));
  } elseif (!empty($_GET['id'])) {
    $req = trim((string)$_GET['id']);
    $idsTo = array_values(array_intersect($allIds, [$req]));
  }

  // 4) ส่ง header SSE หลังทุกอย่างพร้อม
  sse_header();

  if (!$idsTo) {
    sse_comment('no ids in scope');
    exit;
  }

  // 5) snapshot
  $snapshot = ['timestamp'=>(new DateTimeImmutable('now'))->format(DATE_ATOM),'lifts'=>[]];
  foreach ($idsTo as $id) {
    $key  = 'Lift-'.str_pad((string)$id,4,'0',STR_PAD_LEFT);
    $list = $redis->lRange($key,0,8);
    $snapshot['lifts'][$id] = mapFromRedisList($meta[$id], is_array($list)?$list:[]);
  }
  sse_send('lift_snapshot', json_encode($snapshot, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

  // 6) diff loop
  $lastHashes=[]; foreach ($snapshot['lifts'] as $id=>$lift) $lastHashes[$id]=lift_light_hash($lift);
  ignore_user_abort(true); set_time_limit(0);
  $HEART=12; $lastHb=time();

  while (true) {
    if (connection_aborted()) break;
    $changed=[];
    foreach ($idsTo as $id) {
      $key  = 'Lift-'.str_pad((string)$id,4,'0',STR_PAD_LEFT);
      $list = $redis->lRange($key,0,8);
      $lift = mapFromRedisList($meta[$id], is_array($list)?$list:[]);
      $h = lift_light_hash($lift);
      if (!isset($lastHashes[$id]) || $h !== $lastHashes[$id]) {
        $lastHashes[$id]=$h; $changed[$id]=$lift;
      }
    }
    if ($changed) {
      $payload=['timestamp'=>(new DateTimeImmutable('now'))->format(DATE_ATOM),'changed'=>array_keys($changed),'lifts'=>$changed];
      sse_send('lift_diff', json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      $lastHb=time();
    } else {
      if (time()-$lastHb >= $HEART) { sse_comment('heartbeat'); $lastHb=time(); }
    }
    usleep(100000); // 100ms
  }

} catch (Throwable $e) {
  // อย่าส่ง JSON ปะปนใน SSE
  if (!headers_sent()) sse_header();
  sse_comment('fatal: '.$e->getMessage());
  error_log("SSE error: ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine());
  flush();
}
