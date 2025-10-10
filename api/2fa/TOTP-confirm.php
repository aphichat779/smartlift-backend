<?php
// api/2fa/TOTP-confirm.php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/TOTPHelper.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';

CORSMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // ตรวจสอบ JWT Token
    $payload = AuthMiddleware::authenticate();

    // ดึงข้อมูลผู้ใช้จาก JWT
    $userId   = $payload['user_id'];
    $userRole = $payload['role'];

    // เชื่อมต่อฐานข้อมูล
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    // รับข้อมูลจาก body
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || !isset($data['totp'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing TOTP code']);
        exit;
    }

    $totpCode = trim($data['totp']);

    // ดึงข้อมูลผู้ใช้จากฐานข้อมูล
    if (!$user->findById($userId)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // ✅ ตรวจสอบสิทธิ์ (Role)
    $allowedRoles = ['admin', 'technician', 'super_admin'];
    if (!in_array($userRole, $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized role. Only admin, technician, and super_admin can perform this action.'
        ]);
        exit;
    }

    // ตรวจสอบว่าเปิดใช้ 2FA แล้วหรือยัง
    if (!$user->ga_enabled) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '2FA is not enabled for this user']);
        exit;
    }

    // ตรวจสอบรหัส TOTP
    $isValidCode = TOTPHelper::verifyCode($user->ga_secret_key, $totpCode);

    if ($isValidCode) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'TOTP code is valid']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'TOTP ไม่ถูกต้อง']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
