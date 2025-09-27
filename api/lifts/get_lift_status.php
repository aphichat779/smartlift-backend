<?php
// api/lifts/get_lift_status.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';

CORSMiddleware::handle();
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($conn === null) {
        http_response_code(500);
        echo json_encode(["error" => "Connection to database failed."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // รับ id ถ้ามี เพื่อลดปริมาณข้อมูล
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    // NOTE: แก้ชื่อ table ให้ถูกต้อง: buildings (ไม่ใช่ building)
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

        // ===== Optional: ETag/If-None-Match ลด data transfer เมื่อข้อมูลเดิมเป๊ะ =====
        $etagPayload = $row['id'] . '|' . $row['lift_state'] . '|' . $row['up_status'] . '|' . $row['down_status'] . '|' . $row['car_status'] . '|' . $row['updated_at'];
        $etag = '"' . md5($etagPayload) . '"';
        header('ETag: ' . $etag);

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNoneMatch === $etag) {
            http_response_code(304);
            exit; // ไม่ต้องส่ง body เลย
        }

        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // กรณี list ทั้งหมด
    $elevators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($elevators, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error", "detail" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
