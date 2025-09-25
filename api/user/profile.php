<?php
// api/user/profile.php

// เปิดการแสดง error เพื่อการ debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ตรวจสอบและโหลดไฟล์ที่จำเป็น
$requiredFiles = [
    'CORSMiddleware.php' => __DIR__ . '/../../middleware/CORSMiddleware.php',
    'AuthMiddleware.php' => __DIR__ . '/../../middleware/AuthMiddleware.php', 
    'database.php' => __DIR__ . '/../../config/database.php',
    'User.php' => __DIR__ . '/../../models/User.php',
    'ValidationHelper.php' => __DIR__ . '/../../utils/ValidationHelper.php'
];

foreach ($requiredFiles as $name => $path) {
    if (!file_exists($path)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => "Required file not found: {$name}",
            'debug_path' => $path,
            'error_code' => 'FILE_NOT_FOUND'
        ]);
        exit;
    }
    require_once $path;
}

// จัดการ CORS
CORSMiddleware::handle();

// กำหนดโฟลเดอร์สำหรับเก็บรูปภาพ
$uploadDir = __DIR__ . '/../../uploads/profile_images/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
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
    $user->id = $authUser['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user profile
        if (!$user->findById($user->id)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'User not found',
                'error_code' => 'USER_NOT_FOUND'
            ]);
            exit;
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'birthdate' => $user->birthdate,
                'address' => $user->address,
                'role' => $user->role,
                'ga_enabled' => (bool)$user->ga_enabled,
                'recovery_email' => $user->recovery_email,
                'recovery_phone' => $user->recovery_phone,
                'last_2fa_reset' => $user->last_2fa_reset,
                'user_img' => $user->user_img 
            ]
        ]);
        exit;
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update user profile (text data)
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid JSON data',
                'error_code' => 'INVALID_JSON'
            ]);
            exit;
        }
        
        if (!$user->findById($user->id)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'User not found',
                'error_code' => 'USER_NOT_FOUND'
            ]);
            exit;
        }
        
        // Validation
        $errors = [];
        
        if (isset($data['first_name']) && !empty($data['first_name']) && !ValidationHelper::validateName($data['first_name'])) {
            $errors[] = 'Invalid first name format';
        }
        
        if (isset($data['last_name']) && !empty($data['last_name']) && !ValidationHelper::validateName($data['last_name'])) {
            $errors[] = 'Invalid last name format';
        }
        
        if (isset($data['email']) && !empty($data['email']) && !ValidationHelper::validateEmail($data['email'])) {
            $errors[] = 'Invalid email format';
        }
        
        if (isset($data['phone']) && !empty($data['phone']) && !ValidationHelper::validatePhone($data['phone'])) {
            $errors[] = 'Invalid phone number format';
        }
        
        if (isset($data['birthdate']) && !empty($data['birthdate']) && !ValidationHelper::validateDate($data['birthdate'])) {
            $errors[] = 'Invalid birthdate format (YYYY-MM-DD)';
        }
        
        if (isset($data['recovery_email']) && !empty($data['recovery_email']) && !ValidationHelper::validateEmail($data['recovery_email'])) {
            $errors[] = 'Invalid recovery email format';
        }
        
        if (isset($data['recovery_phone']) && !empty($data['recovery_phone']) && !ValidationHelper::validatePhone($data['recovery_phone'])) {
            $errors[] = 'Invalid recovery phone format';
        }
        
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors,
                'error_code' => 'VALIDATION_ERROR'
            ]);
            exit;
        }
        
        // อัปเดตข้อมูลผู้ใช้
        $user->first_name = isset($data['first_name']) ? $data['first_name'] : $user->first_name;
        $user->last_name = isset($data['last_name']) ? $data['last_name'] : $user->last_name;
        $user->email = isset($data['email']) ? $data['email'] : $user->email;
        $user->phone = isset($data['phone']) ? $data['phone'] : $user->phone;
        $user->birthdate = isset($data['birthdate']) ? $data['birthdate'] : $user->birthdate;
        $user->address = isset($data['address']) ? $data['address'] : $user->address;
        $user->recovery_email = isset($data['recovery_email']) ? $data['recovery_email'] : $user->recovery_email;
        $user->recovery_phone = isset($data['recovery_phone']) ? $data['recovery_phone'] : $user->recovery_phone;
        
        if ($user->update()) {
            // ดึงข้อมูลล่าสุดเพื่อตอบกลับ
            $user->findById($user->id);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'birthdate' => $user->birthdate,
                    'address' => $user->address,
                    'role' => $user->role,
                    'ga_enabled' => (bool)$user->ga_enabled,
                    'recovery_email' => $user->recovery_email,
                    'recovery_phone' => $user->recovery_phone,
                    'last_2fa_reset' => $user->last_2fa_reset,
                    'user_img' => $user->user_img 
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update profile',
                'error_code' => 'UPDATE_FAILED'
            ]);
        }
        exit;
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Upload user profile image
        if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No image uploaded or upload error occurred.',
                'error_code' => 'NO_IMAGE_UPLOADED'
            ]);
            exit;
        }

        $file = $_FILES['profile_image'];
        $fileSize = $file['size'];
        $fileTmpName = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // ตรวจสอบประเภทไฟล์ที่อนุญาต
        $allowedExt = ['jpeg', 'jpg', 'png', 'gif'];
        if (!in_array($fileExt, $allowedExt)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid file type. Only JPG, PNG, GIF are allowed.',
                'error_code' => 'INVALID_FILE_TYPE'
            ]);
            exit;
        }

        // ตรวจสอบขนาดไฟล์ (เช่น ไม่เกิน 2MB)
        $maxFileSize = 2 * 1024 * 1024; // 2MB
        if ($fileSize > $maxFileSize) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'File size exceeds the limit (2MB).',
                'error_code' => 'FILE_TOO_LARGE'
            ]);
            exit;
        }
        
        // สร้างชื่อไฟล์ที่ไม่ซ้ำกัน
        $fileNameNew = uniqid('', true) . "." . $fileExt;
        $fileDestination = $uploadDir . $fileNameNew;
        $publicImageUrl = '/uploads/profile_images/' . $fileNameNew;

        // ย้ายไฟล์ที่อัปโหลดไปยังโฟลเดอร์ปลายทาง
        if (move_uploaded_file($fileTmpName, $fileDestination)) {
            // ดึงข้อมูลผู้ใช้ปัจจุบัน
            if (!$user->findById($user->id)) {
                // ลบไฟล์ที่อัปโหลดไปแล้วถ้าหา user ไม่เจอ
                unlink($fileDestination);
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'User not found',
                    'error_code' => 'USER_NOT_FOUND'
                ]);
                exit;
            }

            // ถ้าผู้ใช้มีรูปภาพเก่าอยู่ ให้ลบรูปภาพเก่าทิ้ง
            if ($user->user_img && file_exists(__DIR__ . '/../..' . $user->user_img)) {
                unlink(__DIR__ . '/../..' . $user->user_img);
            }

            // อัปเดต URL ของรูปภาพใหม่ในฐานข้อมูล
            $user->user_img = $publicImageUrl;
            if ($user->updateImage()) {
                // ดึงข้อมูลผู้ใช้ล่าสุดอีกครั้งเพื่อยืนยันการเปลี่ยนแปลง
                $user->findById($user->id); 
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Profile image uploaded successfully',
                    'data' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'birthdate' => $user->birthdate,
                        'address' => $user->address,
                        'role' => $user->role,
                        'ga_enabled' => (bool)$user->ga_enabled,
                        'recovery_email' => $user->recovery_email,
                        'recovery_phone' => $user->recovery_phone,
                        'last_2fa_reset' => $user->last_2fa_reset,
                        'user_img' => $user->user_img
                    ]
                ]);
            } else {
                // หากอัปเดต DB ไม่สำเร็จ ให้ลบไฟล์ที่อัปโหลดไปแล้วทิ้ง
                unlink($fileDestination);
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save profile image URL to database.',
                    'error_code' => 'DATABASE_ERROR'
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to move uploaded file.',
                'error_code' => 'UPLOAD_FAILED'
            ]);
        }
        exit;
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false, 
            'message' => 'Method not allowed',
            'error_code' => 'METHOD_NOT_ALLOWED'
        ]);
        exit;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'error_code' => 'SERVER_ERROR',
        'debug_trace' => $e->getTraceAsString()
    ]);
    exit;
}
?>

