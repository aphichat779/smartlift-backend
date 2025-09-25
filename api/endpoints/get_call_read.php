<?php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

$lift_id = $_GET["lift_id"];
$sql = "SELECT * FROM app_calls WHERE lift_id=$lift_id and is_processed='N'";
$rs = mysqli_query($cn, $sql);
$cmd = array();
while ($row = mysqli_fetch_assoc($rs)) {
	$arr = [];
	$arr["direction"] = $row["direction"];
	$arr["floor_no"] = $row["floor_no"];
	//$sql = "UPDATE app_calls SET is_processed='Y',updated_user_id=2,updated_at=NOW() WHERE id=".$row["id"];
	//mysqli_query($cn,$sql);
	$cmd[] = $arr;
}
echo json_encode(array('cmd' => $cmd));
