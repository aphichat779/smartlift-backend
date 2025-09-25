<?php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../config/database.php';


$lift_id = $_GET["lift_id"];
$sql = "SELECT * FROM lifts WHERE id=$lift_id";
$rs = mysqli_query($cn, $sql);
$array = array();
while ($row = mysqli_fetch_assoc($rs)) {
	$array["lift_name"] = $row["lift_name"];
	$array["max_level"] = $row["max_level"];
	$array["lift_state"] = $row["lift_state"];
	$array["up_status"] = $row["up_status"];
	$array["down_status"] = $row["down_status"];
	$array["car_status"] = $row["car_status"];
	$array["last_update"] = $row["updated_at"];
}
$sql = "SELECT * FROM floor_names WHERE id=$lift_id";
$rs = mysqli_query($cn, $sql);
while ($row = mysqli_fetch_assoc($rs)) {
	$array["level_name"] = $row["floor_name"];
}
echo json_encode($array, JSON_FORCE_OBJECT);
