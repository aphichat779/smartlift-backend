<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';

allow_cors();
header('Content-Type: application/json; charset=utf-8');

$liftId = isset($_GET['lift_id']) ? (int)$_GET['lift_id'] : 0;
if ($liftId <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid lift_id']); exit; }

$sql = "SELECT tk_id, tk_status, tk_data, task_start_date, building_name, org_name
        FROM task
        WHERE lift_id = :lift_id
        ORDER BY tk_id DESC";
$stmt = DB::conn()->prepare($sql);
$stmt->execute([':lift_id' => $liftId]);
echo json_encode($stmt->fetchAll());
