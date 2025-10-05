<?php
// api/lifts/send_command.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }

 $jsonInput = file_get_contents('php://input');
 $data = json_decode($jsonInput, true);
if (!$data || !isset($data['liftId']) || !isset($data['targetFloor'])) {
    http_response_code(400); echo json_encode(['error' => 'Invalid input']); exit;
}

// --- ส่วนสำคัญ: ส่งคำสั่งไปยังลิฟต์จริง ---
// ตัวอย่าง: ส่งเข้า Redis List ให้อุปกรณ์ลิฟต์คอยอ่าน
try {
    require_once __DIR__ . '/../../config/database.php';
    $redis = RedisClient::getConnection();
    $commandData = [ 'liftId' => $data['liftId'], 'command' => $data['command'] ?? 'GOTO_FLOOR', 'targetFloor' => $data['targetFloor'], 'timestamp' => time() ];
    $redis->lPush("lift_commands:{$data['liftId']}", json_encode($commandData));
    echo json_encode(['success' => true, 'message' => "Command sent to lift {$data['liftId']} for floor {$data['targetFloor']}"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send command', 'detail' => $e->getMessage()]);
}