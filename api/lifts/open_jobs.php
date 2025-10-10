<?php
// api/lifts/open_jobs.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

CORSMiddleware::handle();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    // ✅ ตรวจสอบสิทธิ์ผู้ใช้ด้วย JWT
    $payload = AuthMiddleware::authenticate();

    // ✅ ดึง lift_id จากพารามิเตอร์
    $liftId = isset($_GET['lift_id']) ? intval($_GET['lift_id']) : 0;
    if ($liftId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing or invalid lift_id']);
        exit;
    }

    // ✅ เชื่อมต่อฐานข้อมูล
    $database = new Database();
    $db = $database->getConnection();

    // ✅ เตรียมคำสั่ง SQL: หางานที่ยังไม่ complete
    $sql = "
        SELECT COUNT(*) AS cnt
        FROM task
        WHERE lift_id = :lift_id
          AND tk_status IN ('assign', 'preparing', 'progress')
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':lift_id', $liftId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $count = intval($row['cnt'] ?? 0);
    $hasOpenJob = $count > 0;

    // ✅ ส่งผลลัพธ์กลับ
    echo json_encode([
        'success' => true,
        'lift_id' => $liftId,
        'hasOpenJob' => $hasOpenJob,
        'count' => $count,
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ]);
    exit;
}
