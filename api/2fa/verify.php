<?php
// api/2fa/verify.php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
CORSMiddleware::handle();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/BackupCode.php';
require_once __DIR__ . '/../../utils/JWTHelper.php';
require_once __DIR__ . '/../../utils/TOTPHelper.php';
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
    $backupCode = new BackupCode($db);
    
    // Get posted data
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    // Validate required fields
    $required_fields = ['temp_token', 'code'];
    $errors = ValidationHelper::validateRequired($required_fields, $data);
    
    if (!empty($errors)) {
        ValidationHelper::sendValidationError($errors);
    }
    
    // Verify temporary token
    $tempPayload = JWTHelper::decode($data['temp_token']);
    if (!$tempPayload || !isset($tempPayload['temp_auth']) || !$tempPayload['temp_auth']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired temporary token',
            'error_code' => 'INVALID_TEMP_TOKEN'
        ]);
        exit;
    }
    
    // Get user details
    if (!$user->findById($tempPayload['user_id'])) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found',
            'error_code' => 'USER_NOT_FOUND'
        ]);
        exit;
    }
    
    // Check if account is locked
    if ($user->isAccountLocked()) {
        http_response_code(423);
        echo json_encode([
            'success' => false,
            'message' => ERROR_MESSAGES['ACCOUNT_LOCKED'],
            'error_code' => 'ACCOUNT_LOCKED',
            'locked_until' => $user->locked_until
        ]);
        exit;
    }
    
    $isValidCode = false;
    $codeType = '';
    
    // Check if it's a backup code
    if (strlen($data['code']) === 10 && ctype_alnum($data['code'])) {
        if ($backupCode->verifyCode($user->id, strtoupper($data['code']))) {
            $backupCode->markAsUsed();
            $isValidCode = true;
            $codeType = 'backup_code';
        }
    } 
    // Check if it's a TOTP code
    else if (ValidationHelper::validateTOTPCode($data['code'])) {
        if (TOTPHelper::verifyCode($user->ga_secret_key, $data['code'])) {
            $isValidCode = true;
            $codeType = 'totp';
        }
    }
    
    if (!$isValidCode) {
        // Increment failed attempts
        $user->failed_2fa_attempts++;
        
        // Lock account if too many failed attempts
        if ($user->failed_2fa_attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
            $user->lockAccount($lockUntil);
            
            http_response_code(423);
            echo json_encode([
                'success' => false,
                'message' => ERROR_MESSAGES['ACCOUNT_LOCKED'],
                'error_code' => 'ACCOUNT_LOCKED',
                'locked_until' => $lockUntil
            ]);
            exit;
        } else {
            $user->updateFailedAttempts($user->failed_2fa_attempts);
            
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => ERROR_MESSAGES['INVALID_2FA_CODE'],
                'error_code' => 'INVALID_2FA_CODE',
                'remaining_attempts' => MAX_LOGIN_ATTEMPTS - $user->failed_2fa_attempts
            ]);
            exit;
        }
    }
    
    // Reset failed attempts on successful verification
    if ($user->failed_2fa_attempts > 0) {
        $user->updateFailedAttempts(0);
    }
    
    // Generate final JWT token
    $payload = [
        'user_id' => $user->id,
        'username' => $user->username,
        'role' => $user->role,
        'org_id' => $user->org_id
    ];
    
    $jwt = JWTHelper::encode($payload);
    
    $response = [
        'success' => true,
        'message' => '2FA verification successful',
        'token' => $jwt,
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
            'ga_enabled' => $user->ga_enabled
        ]
    ];
    
    // Add warning if backup code was used
    if ($codeType === 'backup_code') {
        $remainingCodes = $backupCode->getUnusedCount($user->id);
        $response['warning'] = "You used a backup code. You have {$remainingCodes} backup codes remaining.";
        $response['remaining_backup_codes'] = $remainingCodes;
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'SERVER_ERROR'
    ]);
}
?>

