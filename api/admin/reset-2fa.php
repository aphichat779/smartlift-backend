<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/BackupCode.php';
require_once __DIR__ . '/../../models/TwoFAResetLog.php';
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
    // Require admin authentication
    $authUser = AuthMiddleware::requireAdmin();
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
    $required_fields = ['user_id', 'reason'];
    $errors = ValidationHelper::validateRequired($required_fields, $data);
    
    if (!empty($errors)) {
        ValidationHelper::sendValidationError($errors);
    }
    
    // Find target user
    if (!$user->findById($data['user_id'])) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found',
            'error_code' => 'USER_NOT_FOUND'
        ]);
        exit;
    }
    
    // Check if 2FA is enabled
    if (!$user->ga_enabled) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => '2FA is not enabled for this user',
            'error_code' => '2FA_NOT_ENABLED'
        ]);
        exit;
    }
    
    // Store old secret key for logging
    $oldSecretKey = $user->ga_secret_key;
    
    // Generate new secret key
    $newSecretKey = TOTPHelper::generateSecret();
    
    // Generate QR code URL for new secret
    $qrCodeUrl = TOTPHelper::generateQRCodeURL($newSecretKey, $user->username);
    
    // Update user's 2FA settings (disable temporarily)
    $user->update2FA($newSecretKey, 0);
    
    // Delete old backup codes
    $backupCode->deleteUserCodes($user->id);
    
    // Update last reset time
    $user->last_2fa_reset = date('Y-m-d H:i:s');
    $user->update();
    
    // Log the reset
    TwoFAResetLog::logReset(
        $db, 
        $user->id, 
        'admin', 
        $oldSecretKey, 
        $newSecretKey, 
        $data['reason'], 
        $authUser['user_id']
    );
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '2FA reset successful for user: ' . $user->username,
        'data' => [
            'user_id' => $user->id,
            'username' => $user->username,
            'secret_key' => $newSecretKey,
            'qr_code_url' => $qrCodeUrl,
            'manual_entry_key' => $newSecretKey,
            'issuer' => TOTP_ISSUER,
            'account_name' => $user->username,
            'reset_by' => $authUser['username'],
            'reset_reason' => $data['reason'],
            'message' => 'User must set up 2FA again using the new secret key.'
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

