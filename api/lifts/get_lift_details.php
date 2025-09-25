<?php
// require("inc_db.php"); // ตรวจสอบให้แน่ใจว่าไฟล์นี้เชื่อมต่อกับฐานข้อมูลอย่างถูกต้อง

header('Content-Type: application/json');

$lift_id = $_GET['lift_id'];

if (!isset($lift_id) || !is_numeric($lift_id)) {
    echo json_encode(array("error" => "Invalid lift_id"), JSON_FORCE_OBJECT);
    exit();
}

// คำสั่ง SQL เพื่อดึงข้อมูลลิฟต์หลัก
$sql = "SELECT l.*, o.org_name, b.building_name, fn.floor_name FROM lifts AS l ";
$sql .= "LEFT JOIN organizations AS o ON l.org_id = o.org_id ";
$sql .= "LEFT JOIN building AS b ON l.building_id = b.building_id ";
$sql .= "LEFT JOIN floor_names AS fn ON l.id = fn.lift_id ";
$sql .= "WHERE l.id = " . intval($lift_id);

$result = mysqli_query($cn, $sql);
$lift_details = mysqli_fetch_assoc($result);

if (!$lift_details) {
    echo json_encode(array("error" => "Lift not found"), JSON_FORCE_OBJECT);
    exit();
}

// ดึงบันทึกสถานะล่าสุดจากตาราง status_logs
$sql_logs = "SELECT * FROM status_logs WHERE lift_id = " . intval($lift_id) . " ORDER BY created_at DESC LIMIT 10";
$result_logs = mysqli_query($cn, $sql_logs);
$status_logs = array();
while ($row = mysqli_fetch_assoc($result_logs)) {
    $status_logs[] = $row;
}
$lift_details['status_logs'] = $status_logs;

// ดึงการเรียกใช้ลิฟต์ที่ยังไม่ได้ประมวลผล
$sql_calls = "SELECT * FROM app_calls WHERE lift_id = " . intval($lift_id) . " AND is_processed = 'N' ORDER BY created_at DESC";
$result_calls = mysqli_query($cn, $sql_calls);
$app_calls = array();
while ($row = mysqli_fetch_assoc($result_calls)) {
    $app_calls[] = $row;
}
$lift_details['pending_calls'] = $app_calls;

echo json_encode(array("lift" => $lift_details), JSON_FORCE_OBJECT);

mysqli_close($cn);
?>