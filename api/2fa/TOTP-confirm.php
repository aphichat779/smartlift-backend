<?php
// api/system/TOTP-confirm.php
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
    // ใช้เมธอด authenticate() แบบ static เพื่อตรวจสอบ JWT
    $payload = AuthMiddleware::authenticate();

    // ดึง user_id และ role จาก JWT payload
    $userId = $payload['user_id'];
    $userRole = $payload['role'];

    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    // Get posted data
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['totp'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing TOTP code']);
        exit;
    }

    $totpCode = $data['totp'];

    // Get user details to retrieve ga_secret_key
    if (!$user->findById($userId)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // เพิ่ม: ตรวจสอบสิทธิ์ (Role) ของผู้ใช้
    if ($userRole !== 'admin' && $userRole !== 'technician') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized role. Only admin and technician can perform this action.']);
        exit;
    }

    // Check if user has 2FA enabled
    if (!$user->ga_enabled) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '2FA is not enabled for this user']);
        exit;
    }

    // Use TOTPHelper to verify the code
    $isValidCode = TOTPHelper::verifyCode($user->ga_secret_key, $totpCode);

    if ($isValidCode) {
        // Respond with success. The frontend will then proceed with the reset action.
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'TOTP code is valid']);
    } else {
        // Respond with failure. The frontend will not proceed.
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'TOTP ไม่ถูกต้อง']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>