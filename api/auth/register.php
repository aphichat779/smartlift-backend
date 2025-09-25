<?php
// api/auth/register.php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
CORSMiddleware::handle();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
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
    $required_fields = ['username', 'password', 'first_name', 'last_name', 'email', 'phone', 'birthdate', 'address'];
    $errors = ValidationHelper::validateRequired($required_fields, $data);
    
    // Additional validations
    if (!ValidationHelper::validateUsername($data['username'])) {
        $errors[] = 'Username must be 3-20 characters, alphanumeric and underscore only';
    }
    
    if (!ValidationHelper::validatePassword($data['password'])) {
        $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, and number';
    }
    
    if (!ValidationHelper::validateEmail($data['email'])) {
        $errors[] = 'Invalid email format';
    }
    
    if (!ValidationHelper::validatePhone($data['phone'])) {
        $errors[] = 'Invalid phone number format';
    }
    
    if (!ValidationHelper::validateName($data['first_name'])) {
        $errors[] = 'Invalid first name format';
    }
    
    if (!ValidationHelper::validateName($data['last_name'])) {
        $errors[] = 'Invalid last name format';
    }
    
    if (!ValidationHelper::validateDate($data['birthdate'])) {
        $errors[] = 'Invalid birthdate format (YYYY-MM-DD)';
    }
    
    if (!empty($errors)) {
        ValidationHelper::sendValidationError($errors);
    }
    
    // Check if username already exists
    if ($user->findByUsername($data['username'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Username already exists',
            'error_code' => 'USERNAME_EXISTS'
        ]);
        exit;
    }
    
    // Set user properties
    $user->username = $data['username'];
    $user->password = $data['password'];
    $user->first_name = $data['first_name'];
    $user->last_name = $data['last_name'];
    $user->email = $data['email'];
    $user->phone = $data['phone'];
    $user->birthdate = $data['birthdate'];
    $user->address = $data['address'];
    $user->role = 'user'; // Default role
    $user->org_id = 1; // Default organization
    $user->recovery_email = $data['recovery_email'] ?? $data['email'];
    $user->recovery_phone = $data['recovery_phone'] ?? $data['phone'];
    
    // Create user
    if ($user->create()) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to register user',
            'error_code' => 'REGISTRATION_FAILED'
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

