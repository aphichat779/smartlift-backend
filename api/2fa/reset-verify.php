<?php
// api/2fa/reset-verify.php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/RecoveryOTP.php';
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
    $database = new Database();
    $db = $database->getConnection();
    
    $user = new User($db);
    $recoveryOTP = new RecoveryOTP($db);
    $backupCode = new BackupCode($db);
    
    // Get posted data
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    // Validate required fields
    $required_fields = ['username', 'otp_code'];
    $errors = ValidationHelper::validateRequired($required_fields, $data);
    
    if (!ValidationHelper::validateOTPCode($data['otp_code'])) {
        $errors[] = 'Invalid OTP code format';
    }
    
    if (!empty($errors)) {
        ValidationHelper::sendValidationError($errors);
    }
    
    // Find user by username
    if (!$user->findByUsername($data['username'])) {
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
            'message' => '2FA is not enabled for this account',
            'error_code' => '2FA_NOT_ENABLED'
        ]);
        exit;
    }
    
    // Verify OTP
    if (!$recoveryOTP->findValidOTP($user->id, $data['otp_code'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired OTP',
            'error_code' => 'INVALID_OTP'
        ]);
        exit;
    }
    
    // Mark OTP as used
    $recoveryOTP->markAsUsed();
    
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
    
    // เพิ่มการบันทึก IP Address และ User Agent
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    
    // Log the reset with IP Address and User Agent
    TwoFAResetLog::logReset(
        $db, 
        $user->id, 
        $recoveryOTP->otp_type, 
        $oldSecretKey, 
        $newSecretKey, 
        'User requested 2FA reset via ' . $recoveryOTP->otp_type,
        $ipAddress, 
        $userAgent
    );
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '2FA reset successful. Please set up 2FA again.',
        'data' => [
            'secret_key' => $newSecretKey,
            'qr_code_url' => $qrCodeUrl,
            'manual_entry_key' => $newSecretKey,
            'issuer' => TOTP_ISSUER,
            'account_name' => $user->username,
            'setup_required' => true,
            'message' => 'Your 2FA has been reset. Please scan the QR code or enter the secret key manually in your authenticator app, then verify the setup.'
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