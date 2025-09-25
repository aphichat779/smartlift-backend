<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/TOTPHelper.php';

CORSMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Authenticate user
    $authUser = AuthMiddleware::authenticate();
    if (!$authUser) {
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $user = new User($db);
    
    // Get user details
    if (!$user->findById($authUser['user_id'])) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found',
            'error_code' => 'USER_NOT_FOUND'
        ]);
        exit;
    }
    
    // Check if 2FA is already enabled
    if ($user->ga_enabled) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => '2FA is already enabled',
            'error_code' => '2FA_ALREADY_ENABLED'
        ]);
        exit;
    }
    
    // Generate new secret key
    $secretKey = TOTPHelper::generateSecret();
    
    // Generate QR code URL
    $qrCodeUrl = TOTPHelper::generateQRCodeURL($secretKey, $user->username);
    
    // Store secret key temporarily (not enabled yet)
    $user->update2FA($secretKey, 0);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '2FA setup initiated',
        'data' => [
            'secret_key' => $secretKey,
            'qr_code_url' => $qrCodeUrl,
            'manual_entry_key' => $secretKey,
            'issuer' => TOTP_ISSUER,
            'account_name' => $user->username
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'SERVER_ERROR'
    ]);
}
?>

