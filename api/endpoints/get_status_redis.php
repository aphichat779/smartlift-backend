<?php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
CORSMiddleware::handle();
require_once __DIR__ . '/../../config/database.php';

$lift_id = $_GET["lift_id"];
$data1 = $redis->lRange('Lift-' . str_pad($lift_id, 4, "0", STR_PAD_LEFT), 0, 8);
$data2 = $redis->lRange('Name-' . str_pad($lift_id, 4, "0", STR_PAD_LEFT), 0, 1);
//print($data[0]." ".$data[1]." ".$data[2]." ".$data[3]." ".$data[4]." ".$data[5]." ".$data[6]);
$array = array();
$array["org_name"] = $data1[0];
$array["lift_name"] = $data1[1];
$array["max_level"] = $data1[2];
$array["lift_state"] = $data1[3];
$array["up_status"] = $data1[4];
$array["down_status"] = $data1[5];
$array["car_status"] = $data1[6];
$array["last_update"] = $data1[7];
$array["level_name"] = $data2[0];
$date = new DateTime($array["last_update"]);
$date2 = new DateTime();
$diffInSeconds = $date2->getTimestamp() - $date->getTimestamp();
$array['connection_status'] = $diffInSeconds > 30 ? "Offline" : "Online";
echo json_encode($array, JSON_FORCE_OBJECT);
