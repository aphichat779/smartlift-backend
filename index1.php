<?php
// index_testConnec.php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/middleware/CORSMiddleware.php';
require_once __DIR__ . '/config/database.php'; 

CORSMiddleware::handle();

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
$db = new Database();
$conn = $db->getConnection();
$db_status = $conn ? 'connected' : 'error';

// กำหนด base URL ของโปรเจกต์ (แก้ไขตาม URL ที่ใช้จริง)
// ตัวอย่าง: ถ้าโปรเจกต์อยู่ใน http://localhost/smartlift-backend/
$project_base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/smartlift-backend";

// สมมติชื่อไฟล์รูปโปรไฟล์ที่อัปโหลดล่าสุด
$sample_profile_image_name = '688cde1182f5f1.80472310.jpg';
$profile_image_path = '/uploads/profile_images/' . $sample_profile_image_name;

// สร้าง URL เต็มของรูปโปรไฟล์สำหรับทดสอบ
$full_profile_image_url = $project_base_url . $profile_image_path;

// API Information
$api_info = [
    'name' => 'SmartLift API',
    'version' => '1.0.0',
    'description' => 'REST API for SmartLift System with 2FA Authentication',
    'database_connection' => $db_status, 
    // เพิ่มข้อมูลการทดสอบการแสดงผลรูปโปรไฟล์
    'profile_image_test' => [
        'is_file_on_server' => file_exists(__DIR__ . $profile_image_path),
        'test_url_path' => $profile_image_path,
        'test_full_url' => $full_profile_image_url,
        'instructions' => 'คัดลอก test_full_url ไปวางในเบราว์เซอร์เพื่อตรวจสอบว่ารูปภาพแสดงผลได้หรือไม่'
    ],
    'endpoints' => [
        'authentication' => [
            'POST /api/auth/register' => 'Register new user',
            'POST /api/auth/login' => 'User login',
            'POST /api/auth/logout' => 'User logout'
        ],
        '2fa_management' => [
            'POST /api/2fa/setup' => 'Setup 2FA TOTP',
            'POST /api/2fa/verify-setup' => 'Verify 2FA setup',
            'POST /api/2fa/verify' => 'Verify 2FA code for login',
            'POST /api/2fa/disable' => 'Disable 2FA',
            'GET /api/2fa/backup-codes' => 'Get backup codes',
            'POST /api/2fa/generate-backup-codes' => 'Generate new backup codes'
        ],
        '2fa_reset' => [
            'POST /api/2fa/reset-request' => 'Request 2FA reset (send OTP)',
            'POST /api/2fa/reset-verify' => 'Verify OTP and reset 2FA'
        ],
        'user_management' => [
            'GET /api/user/profile' => 'Get user profile',
            'PUT /api/user/profile' => 'Update user profile',
            'PUT /api/user/password' => 'Change password'
        ],
        'admin_management' => [
            'GET /api/admin/users' => 'Get all users (admin only)',
            'POST /api/admin/reset-2fa' => 'Reset user 2FA (admin only)',
            'GET /api/admin/2fa-logs' => 'Get 2FA reset logs (admin only)'
        ]
    ],
    'authentication' => [
        'type' => 'JWT Bearer Token',
        'header' => 'Authorization: Bearer <token>'
    ],
    'features' => [
        'JWT Authentication',
        'TOTP 2FA with QR codes',
        'Backup codes for 2FA',
        'OTP-based 2FA reset',
        'Rate limiting',
        'Account lockout protection',
        'Audit logging',
        'Admin user management',
        'CORS support'
    ]
];

http_response_code(200);
echo json_encode($api_info, JSON_PRETTY_PRINT);
?>