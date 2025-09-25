<?php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

$lift_id = $_GET["lift_id"];
$data = $redis->lRange('Lift-' . str_pad($lift_id, 4, "0", STR_PAD_LEFT), 0, 9);
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
$date = new DateTime($array["last_update"]);
$date2 = new DateTime();
$diffInSeconds = $date2->getTimestamp() - $date->getTimestamp();
$array['connection_status'] = $diffInSeconds > 30 ? "Offline" : "Online";
echo json_encode($array, JSON_FORCE_OBJECT);
