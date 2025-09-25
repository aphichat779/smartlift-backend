<?php
// api/2fa/reset-request.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/RecoveryOTP.php';
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
    
    // Get posted data
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    // Validate required fields
    $required_fields = ['username', 'method'];
    $errors = ValidationHelper::validateRequired($required_fields, $data);
    
    if (!in_array($data['method'], ['email', 'sms'])) {
        $errors[] = 'Method must be either email or sms';
    }
    
    if (!empty($errors)) {
        ValidationHelper::sendValidationError($errors);
    }
    
    // Find user by username
    if (!$user->findByUsername($data['username'])) {
        // Don't reveal if user exists or not for security
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'If the user exists and has recovery information, an OTP has been sent.',
            'method' => $data['method']
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
    
    // Rate limiting - check recent OTP requests
    if ($recoveryOTP->getRecentOTPs($user->id, 60) >= 3) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many OTP requests. Please wait before requesting again.',
            'error_code' => 'RATE_LIMITED'
        ]);
        exit;
    }
    
    // Get contact information based on method
    $contactInfo = '';
    if ($data['method'] === 'email') {
        $contactInfo = $user->recovery_email ?: $user->email;
        if (!$contactInfo) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No recovery email found for this account',
                'error_code' => 'NO_RECOVERY_EMAIL'
            ]);
            exit;
        }
    } else if ($data['method'] === 'sms') {
        $contactInfo = $user->recovery_phone ?: $user->phone;
        if (!$contactInfo) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No recovery phone found for this account',
                'error_code' => 'NO_RECOVERY_PHONE'
            ]);
            exit;
        }
    }
    
    // Generate OTP
    $otpCode = OTPHelper::generateOTP();
    $createdAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRATION_TIME);
    
    // Save OTP to database
    $recoveryOTP->user_id = $user->id;
    $recoveryOTP->otp_code = $otpCode;
    $recoveryOTP->otp_type = $data['method'];
    $recoveryOTP->contact_info = $contactInfo;
    $recoveryOTP->created_at = $createdAt;
    $recoveryOTP->expires_at = $expiresAt;
    
    if (!$recoveryOTP->create()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate OTP',
            'error_code' => 'OTP_GENERATION_FAILED'
        ]);
        exit;
    }
    
    // Send OTP
    $sent = false;
    if ($data['method'] === 'email') {
        $sent = OTPHelper::sendEmailOTP($contactInfo, $otpCode, '2FA reset');
    } else if ($data['method'] === 'sms') {
        $sent = OTPHelper::sendSMSOTP($contactInfo, $otpCode, '2FA reset');
    }
    
    if (!$sent) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send OTP',
            'error_code' => 'OTP_SEND_FAILED'
        ]);
        exit;
    }
    
    // Mask contact information for response
    $maskedContact = '';
    if ($data['method'] === 'email') {
        $parts = explode('@', $contactInfo);
        $maskedContact = substr($parts[0], 0, 2) . '***@' . $parts[1];
    } else {
        $maskedContact = substr($contactInfo, 0, 3) . '***' . substr($contactInfo, -2);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'OTP sent successfully',
        'method' => $data['method'],
        'contact' => $maskedContact,
        'expires_in' => OTP_EXPIRATION_TIME
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'SERVER_ERROR',
        'error_detail' => $e->getMessage(),
        'trace' => $e->getTraceAsString()   
    ]);
}

?>

