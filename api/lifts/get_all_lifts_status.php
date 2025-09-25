<?php
// api/get_all_lifts_status.php
require_once __DIR__ . '/../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../config/database.php';

CORSMiddleware::handle();

$database = new Database();
$db = $database->getConnection();

$query = "SELECT l.*, fn.floor_name
          FROM lifts l
          LEFT JOIN floor_names fn ON l.id = fn.lift_id
          ORDER BY l.id ASC";

$stmt = $db->prepare($query);
$stmt->execute();

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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

    $results[$row['id']] = $row;
}

header('Content-Type: application/json');
echo json_encode($results);
?>