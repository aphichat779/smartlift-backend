<?php
// api/2fa/verify-setup.php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
CORSMiddleware::handle();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/BackupCode.php';
require_once __DIR__ . '/../../utils/TOTPHelper.php';
require_once __DIR__ . '/../../utils/OTPHelper.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';

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
    $backupCode = new BackupCode($db);
    
    // Get posted data
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    // Validate required fields
    $required_fields = ['totp_code'];
    $errors = ValidationHelper::validateRequired($required_fields, $data);
    
    if (!empty($errors)) {
        ValidationHelper::sendValidationError($errors);
    }
    
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
    
    // Check if user has a secret key (from setup)
    if (!$user->ga_secret_key) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => '2FA setup not initiated. Please call setup endpoint first.',
            'error_code' => '2FA_SETUP_NOT_INITIATED'
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
    
    // Verify TOTP code (ส่วนนี้จะทำการตรวจสอบความถูกต้องจริงๆ)
    if (!TOTPHelper::verifyCode($user->ga_secret_key, $data['totp_code'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid TOTP code',
            'error_code' => 'INVALID_TOTP_CODE'
        ]);
        exit;
    }
    
    // Enable 2FA
    $user->update2FA($user->ga_secret_key, 1);
    
    // Generate backup codes
    $backupCodes = OTPHelper::generateBackupCodes();
    $backupCode->createMultiple($user->id, $backupCodes);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '2FA enabled successfully',
        'data' => [
            'backup_codes' => $backupCodes,
            'message' => 'Please save these backup codes in a safe place. They can be used to access your account if you lose your authenticator device.'
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