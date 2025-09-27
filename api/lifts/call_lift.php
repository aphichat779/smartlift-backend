<?php
// api/lifts/call_lift.php
// เพิ่มการเรียกใช้ไฟล์คลาส
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';

// เรียกใช้ CORS Middleware
CORSMiddleware::handle();

// กำหนด Content-Type เป็น application/json
header('Content-Type: application/json');

// สร้าง instance ของ Database และเรียก getConnection()
$database = new Database();
$conn = $database->getConnection();

if ($conn === null) {
    http_response_code(500);
    die(json_encode(["error" => "Connection to database failed."]));
}

// อ่านข้อมูล JSON จาก Body ของ request
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// ตรวจสอบว่ามีข้อมูล POST เข้ามาหรือไม่
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($data['lift_id']) && isset($data['floor_no']) && isset($data['client_id'])) {
    // คำสั่ง SQL โดยใช้ Prepared Statement เพื่อป้องกัน SQL Injection
    $sql = "INSERT INTO app_calls (lift_id, floor_no, direction, client_id, created_user_id, created_at, updated_user_id, updated_at) 
            VALUES (:lift_id, :floor_no, :direction, :client_id, :created_user_id, NOW(), :updated_user_id, NOW())";

    $stmt = $conn->prepare($sql);

    // กำหนดค่าตัวแปร
    $direction = isset($data['direction']) ? $data['direction'] : 'U';
    $created_user_id = 1;
    $updated_user_id = 1;

    // Bind parameters
    $stmt->bindParam(':lift_id', $data['lift_id']);
    $stmt->bindParam(':floor_no', $data['floor_no']);
    $stmt->bindParam(':direction', $direction);
    $stmt->bindParam(':client_id', $data['client_id']);
    $stmt->bindParam(':created_user_id', $created_user_id);
    $stmt->bindParam(':updated_user_id', $updated_user_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Call to lift recorded successfully."]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Failed to execute statement."]);
    }
} else {
    http_response_code(400); // Bad Request
    echo json_encode(["success" => false, "error" => "Invalid request method or missing data."]);
}
?>