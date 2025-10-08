<?php
// api/lifts/update_status.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

// ==== headers ====
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *'); // ปรับ origin ให้เหมาะในโปรดักชัน

try {
  // ===== connections (สำคัญ) =====
  $db    = Database::getConnection();      // PDO
  $redis = RedisClient::getConnection();   // Redis

  // ==== รับพารามิเตอร์ (GET/POST) ====
  $lift_id     = isset($_REQUEST['lift_id'])     ? trim((string)$_REQUEST['lift_id'])     : '';
  $lift_state  = isset($_REQUEST['lift_state'])  ? trim((string)$_REQUEST['lift_state'])  : '';
  $up_status   = isset($_REQUEST['up_status'])   ? trim((string)$_REQUEST['up_status'])   : '';
  $down_status = isset($_REQUEST['down_status']) ? trim((string)$_REQUEST['down_status']) : '';
  $car_status  = isset($_REQUEST['car_status'])  ? trim((string)$_REQUEST['car_status'])  : '';
  $last_update = date('Y-m-d H:i:s');

  if ($lift_id === '') {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'missing lift_id'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $key = 'Lift-' . str_pad($lift_id, 4, '0', STR_PAD_LEFT);

  // ==== อ่าน list ปัจจุบัน ====
  $data = $redis->lRange($key, 0, 8);
  if (!is_array($data)) $data = [];

  // เติมค่า default ให้ครบ 0..8 (กัน LSET error)
  $defaults = [
    0 => '-',                  // org_name
    1 => '-',                  // lift_name
    2 => '1',                  // max_level
    3 => '000000000000',   // lift_state_hex
    4 => '00000000',           // up_status_hex
    5 => '00000000',           // down_status_hex
    6 => '00000000',           // car_status_hex
    7 => '',                   // last_update (string ว่าง แทน null)
    8 => '1,2,3,4',            // floor_name
  ];

  // ใช้ความยาวปัจจุบัน ลดการเรียก lLen บ่อย ๆ
  $len = (int)$redis->lLen($key);
  for ($i = 0; $i <= 8; $i++) {
    if (!array_key_exists($i, $data) || $data[$i] === null) {
      while ($len <= $i) {
        $redis->rPush($key, $defaults[$len] ?? '');
        $len++;
      }
      $data[$i] = $defaults[$i];
    }
  }

  // ==== เช็กเปลี่ยนจริงไหม ====
  $changed = (
    (string)($data[3] ?? '') !== $lift_state  ||
    (string)($data[4] ?? '') !== $up_status   ||
    (string)($data[5] ?? '') !== $down_status ||
    (string)($data[6] ?? '') !== $car_status
  );

  // ==== เขียน Redis ====
  $redis->lSet($key, 3, $lift_state);
  $redis->lSet($key, 4, $up_status);
  $redis->lSet($key, 5, $down_status);
  $redis->lSet($key, 6, $car_status);
  $redis->lSet($key, 7, $last_update);

  // ==== log DB เฉพาะตอนเปลี่ยนจริง (ใช้ PDO) ====
  if ($changed) {
    $stmt = $db->prepare("
      INSERT INTO status_logs
        (lift_id, lift_state, up_status, down_status, car_status,
         created_user_id, created_at, updated_user_id, updated_at)
      VALUES
        (:lift_id, :lift_state, :up_status, :down_status, :car_status,
         1, NOW(), 1, NOW())
    ");
    $stmt->execute([
      ':lift_id'     => $lift_id,
      ':lift_state'  => $lift_state,
      ':up_status'   => $up_status,
      ':down_status' => $down_status,
      ':car_status'  => $car_status,
    ]);
  }

  // ==== อ่านกลับเพื่อตอบ ====
  $data = $redis->lRange($key, 0, 8) ?: $defaults;

  // ==== Publish ให้ stream.php ส่งต่อแบบ real-time ====
  $redis->publish('lift_updates', json_encode([
    'lift_id' => (string)$lift_id,
    'ts'      => (new DateTimeImmutable('now'))->format(DATE_ATOM),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

  // ==== ตอบกลับ ====
  $result = [
    'org_name'    => $data[0] ?? '-',
    'lift_name'   => $data[1] ?? '-',
    'max_level'   => $data[2] ?? '1',
    'lift_state'  => $data[3] ?? '000000000000',
    'up_status'   => $data[4] ?? '00000000',
    'down_status' => $data[5] ?? '00000000',
    'car_status'  => $data[6] ?? '00000000',
    'last_update' => $data[7] ?? $last_update,
    'level_name'  => $data[8] ?? '1,2,3,4',
  ];

  echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'error' => true,
    'message' => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
