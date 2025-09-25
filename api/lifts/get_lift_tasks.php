<?php
// require("inc_db.php"); // ตรวจสอบให้แน่ใจว่าไฟล์นี้เชื่อมต่อกับฐานข้อมูลอย่างถูกต้อง

header('Content-Type: application/json');

$lift_id = $_GET['lift_id'];

if (!isset($lift_id) || !is_numeric($lift_id)) {
    echo json_encode(array("error" => "Invalid lift_id"), JSON_FORCE_OBJECT);
    exit();
}

$sql = "SELECT t.*, u.user_name FROM task AS t ";
$sql .= "LEFT JOIN users AS u ON t.created_by = u.id ";
$sql .= "WHERE t.lift_id = " . intval($lift_id) . " ORDER BY t.created_at DESC";

$result = mysqli_query($cn, $sql);
$tasks = array();
while ($row = mysqli_fetch_assoc($result)) {
    $tasks[] = $row;
}

echo json_encode(array("tasks" => $tasks), JSON_FORCE_OBJECT);

mysqli_close($cn);
?>