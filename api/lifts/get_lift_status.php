<?php
// api/lifts/get_lift_status.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';

CORSMiddleware::handle();
header('Content-Type: application/json; charset=utf-8');

/**
 * แปลงข้อมูลการเรียก (Call Data) จาก Bitfield เป็น Array ของชั้นที่ถูกเรียก
 * @param int $bitfield ค่า Bitfield 32 บิต
 * @return array รายการชั้นที่ถูกเรียก
 */
function parseCallData(int $bitfield): array
{
    $calledFloors = [];
    for ($i = 0; $i < 32; $i++) {
        if (($bitfield & (1 << $i)) !== 0) {
            // สมมติว่า Bit 0 คือชั้น 1, Bit 1 คือชั้น 2, ...
            $calledFloors[] = $i + 1;
        }
    }
    return $calledFloors;
}

/**
 * แปลงข้อมูลดิบ 18 ไบต์จากลิฟต์ให้เป็น Array ที่เข้าใจง่าย
 * @param string $data ข้อมูลดิบ 18 ไบต์
 * @return array ข้อมูลที่แปลงแล้ว หรือ array ว่างถ้าข้อมูลผิดพลาด
 */
function parseLiftDataPacket(string $data): array
{
    if (strlen($data) !== 18) {
        return []; // ข้อมูลไม่ครบ 18 ไบต์
    }

    // แปลง string เป็น array ของเลขฐาน 10 (unsigned char)
    $unpacked = unpack('C*', $data);

    $state2 = $unpacked[2];
    $state3 = $unpacked[3];

    // รวมความเร็ว High byte และ Low byte
    $speed_raw = ($unpacked[5] << 8) | $unpacked[6];
    // สมมติว่าความเร็วเก็บเป็น mm/s ให้แปลงเป็น m/s
    $speed_mps = $speed_raw / 1000.0;

    // รวมข้อมูลการเรียก 4 ไบต์ (32 บิต)
    $up_call_raw = ($unpacked[7] << 24) | ($unpacked[8] << 16) | ($unpacked[9] << 8) | $unpacked[10];
    $dn_call_raw = ($unpacked[11] << 24) | ($unpacked[12] << 16) | ($unpacked[13] << 8) | $unpacked[14];
    $car_call_raw = ($unpacked[15] << 24) | ($unpacked[16] << 16) | ($unpacked[17] << 8) | $unpacked[18];

    return [
        'actual_floor' => $unpacked[1],
        'door_a_status' => ($state2 & 0x07), // BIT0-2
        'door_b_status' => ($state2 & 0x38) >> 3, // BIT3-5
        'direction_down_arrow' => ($state2 & 0x40) >> 6, // BIT6
        'direction_up_arrow' => ($state2 & 0x80) >> 7, // BIT7
        'working_status' => ($state3 & 0x0F), // BIT0-3
        'is_full_load' => ($state3 & 0x40) >> 6, // BIT6
        'is_over_load' => ($state3 & 0x80) >> 7, // BIT7
        'fault_number' => $unpacked[4],
        'current_speed_mps' => round($speed_mps, 3),
        'up_calls' => parseCallData($up_call_raw),
        'down_calls' => parseCallData($dn_call_raw),
        'car_calls' => parseCallData($car_call_raw),
    ];
}


try {
    // เชื่อมต่อฐานข้อมูล MySQL
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn === null) {
        http_response_code(500);
        echo json_encode(["error" => "Connection to database failed."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // เชื่อมต่อ Redis
    $redis = RedisClient::getConnection();

    // รับ id ถ้ามี
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    $sql = "SELECT
                l.id,
                l.lift_name,
                l.mac_address,
                l.max_level,
                l.floor_name,
                l.lift_state,
                l.up_status,
                l.down_status,
                l.car_status,
                l.org_id,
                o.org_name,
                l.building_id,
                b.building_name,
                l.created_at,
                l.updated_at
            FROM lifts AS l
            LEFT JOIN organizations AS o ON l.org_id = o.id
            LEFT JOIN buildings AS b ON l.building_id = b.id";

    if ($id) {
        $sql .= " WHERE l.id = :id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    } else {
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();

    if ($id) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(["error" => "ไม่พบข้อมูลลิฟต์"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ดึงข้อมูลแบบ real-time จาก Redis
        $rawData = $redis->get($row['mac_address']);
        if ($rawData) {
            $row['realtime_status'] = parseLiftDataPacket($rawData);
        } else {
            $row['realtime_status'] = ['error' => 'No real-time data from Redis'];
        }

        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // กรณี list ทั้งหมด
    $elevators = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // วนลูปเพื่อดึงข้อมูลจาก Redis มาเพิ่มในแต่ละรายการ
    foreach ($elevators as &$lift) { // ใช้ & เพื่อแก้ไขค่าใน array โดยตรง
        $rawData = $redis->get($lift['mac_address']);
        if ($rawData) {
            $lift['realtime_status'] = parseLiftDataPacket($rawData);
        } else {
            $lift['realtime_status'] = ['error' => 'No real-time data from Redis'];
        }
    }
    unset($lift); // ล้าง reference หลังใช้งานเสร็จ

    echo json_encode($elevators, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error", "detail" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}