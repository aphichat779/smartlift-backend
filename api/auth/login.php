<?php
// api/auth/login.php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
CORSMiddleware::handle();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/JWTHelper.php';
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
    
    // Get posted data
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    // Validate required fields
    $required_fields = ['username', 'password'];
    $errors = ValidationHelper::validateRequired($required_fields, $data);
    
    if (!empty($errors)) {
        ValidationHelper::sendValidationError($errors);
    }
    
    // Find user by username
    if (!$user->findByUsername($data['username'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => ERROR_MESSAGES['INVALID_CREDENTIALS'],
            'error_code' => 'INVALID_CREDENTIALS'
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
    
    // Verify password
    if (!password_verify($data['password'], $user->password)) {
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
            
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => ERROR_MESSAGES['INVALID_CREDENTIALS'],
                'error_code' => 'INVALID_CREDENTIALS',
                'remaining_attempts' => MAX_LOGIN_ATTEMPTS - $user->failed_2fa_attempts
            ]);
            exit;
        }
    }
    
    // Reset failed attempts on successful password verification
    if ($user->failed_2fa_attempts > 0) {
        $user->updateFailedAttempts(0);
    }
    
    // Check if 2FA is enabled
    if ($user->ga_enabled) {
        // Return temporary token for 2FA verification
        $tempPayload = [
            'user_id' => $user->id,
            'username' => $user->username,
            'temp_auth' => true,
            'exp' => time() + 300 // 5 minutes
        ];
        
        $tempToken = JWTHelper::encode($tempPayload);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => '2FA verification required',
            'requires_2fa' => true,
            'temp_token' => $tempToken
        ]);
    } else {
        // Generate JWT token
        $payload = [
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'org_id' => $user->org_id
        ];
        
        $jwt = JWTHelper::encode($payload);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'requires_2fa' => false,
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
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'SERVER_ERROR'
    ]);
}
?>

