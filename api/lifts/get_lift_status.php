<?php
// api/get_lift_status.php
require_once __DIR__ . '/../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../config/database.php';

CORSMiddleware::handle();

if (!isset($_GET['lift_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing lift_id parameter.']);
    exit;
}

$lift_id = intval($_GET['lift_id']);

$database = new Database();
$db = $database->getConnection();

$query = "SELECT l.*, fn.floor_name, b.building_name, o.org_name
          FROM lifts l
          LEFT JOIN floor_names fn ON l.id = fn.lift_id
          LEFT JOIN buildings b ON l.building_id = b.id
          LEFT JOIN organizations o ON l.org_id = o.id
          WHERE l.id = :lift_id
          LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':lift_id', $lift_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Lift not found.']);
    exit;
}

// คำนวณสถานะการเชื่อมต่อ
$lastUpdate = new DateTime($row['updated_at']);
$now = new DateTime();
$diffInSeconds = $now->getTimestamp() - $lastUpdate->getTimestamp();
$row['connection'] = $diffInSeconds < 60 ? 'ONLINE' : 'OFFLINE';

// แปลง lift_state จากเลขฐานสิบเป็น binary string
$liftStateBits = str_pad(decbin($row['lift_state']), 16, '0', STR_PAD_LEFT);
$row['flags'] = [
    'fault' => (int)($liftStateBits[10] === '1'),
    'maintenance' => (int)($liftStateBits[9] === '1'),
    'moving' => (int)($liftStateBits[7] === '1'),
    'outOfService' => (int)($liftStateBits[12] === '1'),
];
$row['door'] = $liftStateBits[1] === '1' ? 'OPEN' : 'CLOSED';
$row['mode'] = $liftStateBits[9] === '1' ? 'MANUAL' : 'AUTO';
$row['floorPosition'] = $row['current_level'];

header('Content-Type: application/json');
echo json_encode($row);
?>