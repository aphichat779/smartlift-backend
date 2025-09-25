<?php
// api/add_call.php
require_once __DIR__ . '/../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../config/database.php';

CORSMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->lift_id) || !isset($data->floor_no) || !isset($data->direction) || !isset($data->client_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$lift_id = htmlspecialchars(strip_tags($data->lift_id));
$floor_no = htmlspecialchars(strip_tags($data->floor_no));
$direction = htmlspecialchars(strip_tags($data->direction));
$client_id = htmlspecialchars(strip_tags($data->client_id));

$query = "INSERT INTO app_calls(lift_id, direction, floor_no, client_id, is_processed, created_user_id, created_at, updated_user_id, updated_at) 
          VALUES(:lift_id, :direction, :floor_no, :client_id, 'N', 1, NOW(), 1, NOW())";

$stmt = $db->prepare($query);

$stmt->bindParam(':lift_id', $lift_id, PDO::PARAM_INT);
$stmt->bindParam(':direction', $direction, PDO::PARAM_STR);
$stmt->bindParam(':floor_no', $floor_no, PDO::PARAM_STR);
$stmt->bindParam(':client_id', $client_id, PDO::PARAM_STR);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Call added successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add call.']);
}
?>