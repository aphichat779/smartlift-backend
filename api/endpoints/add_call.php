<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';

$lift_id = $_GET["lift_id"];
$direction = $_GET["direction"];
$floor_no = $_GET["floor_no"];
$client_id = $_GET["client_id"];

$sql = "INSERT INTO app_calls(lift_id,direction,floor_no,client_id,is_processed,created_user_id,created_at,updated_user_id,updated_at) ";
$sql .= " VALUES($lift_id,'$direction','$floor_no','$client_id','N',2,NOW(),2,NOW())";
mysqli_query($cn, $sql);

$sql = "SELECT * FROM lifts WHERE id=$lift_id";
$rs = mysqli_query($cn, $sql);
$array = array();
//$array["sql"] = $sql;
while ($row = mysqli_fetch_assoc($rs)) {
	$array["lift_name"] = $row["lift_name"];
	$array["max_level"] = $row["max_level"];
	$array["up_status"] = $row["up_status"];
	$array["lift_state"] = $row["lift_state"];
	$array["down_status"] = $row["down_status"];
	$array["car_status"] = $row["car_status"];
}
echo json_encode($array, JSON_FORCE_OBJECT);