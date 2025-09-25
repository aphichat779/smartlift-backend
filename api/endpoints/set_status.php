<?php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

$lift_id = $_GET["lift_id"];
$lift_state = $_GET["lift_state"];
$up_status = $_GET["up_status"];
$down_status = $_GET["down_status"];
$car_status = $_GET["car_status"];
$last_update = date("Y-m-d H:i:s");

$data = $redis->lRange('Lift-' . str_pad($lift_id, 4, "0", STR_PAD_LEFT), 0, 9);

if (
    $data[3] != $lift_state
    || $data[4] != $up_status
    || $data[5] != $down_status
    || $data[6] != $car_status
) {
    $sql = "INSERT INTO status_logs(lift_id,lift_state,up_status,down_status,car_status,created_user_id,created_at,updated_user_id,updated_at)";
    $sql .= " VALUES('$lift_id','$lift_state','$up_status','$down_status','$car_status',1,NOW(),1,NOW())";
    mysqli_query($cn, $sql);
}

$redis->lSet('Lift-' . str_pad($lift_id, 4, "0", STR_PAD_LEFT), 3, $lift_state);
$redis->lSet('Lift-' . str_pad($lift_id, 4, "0", STR_PAD_LEFT), 4, $up_status);
$redis->lSet('Lift-' . str_pad($lift_id, 4, "0", STR_PAD_LEFT), 5, $down_status);
$redis->lSet('Lift-' . str_pad($lift_id, 4, "0", STR_PAD_LEFT), 6, $car_status);
$redis->lSet('Lift-' . str_pad($lift_id, 4, "0", STR_PAD_LEFT), 7, $last_update);

$data = $redis->lRange('Lift-' . str_pad($lift_id, 4, "0", STR_PAD_LEFT), 0, 9);
//print($data[0]." ".$data[1]." ".$data[2]." ".$data[3]." ".$data[4]." ".$data[5]." ".$data[6]." ".$data[7]);
$array = array();
$array["org_name"] = $data[0];
$array["lift_name"] = $data[1];
$array["max_level"] = $data[2];
$array["lift_state"] = $data[3];
$array["up_status"] = $data[4];
$array["down_status"] = $data[5];
$array["car_status"] = $data[6];
$array["last_update"] = $data[7];
$array["level_name"] = $data[8];
echo json_encode($array, JSON_FORCE_OBJECT);
