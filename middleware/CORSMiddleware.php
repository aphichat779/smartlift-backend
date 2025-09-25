<?php
// middleware/CORSMiddleware.php
require_once __DIR__ . '/../config/config.php';

class CORSMiddleware {

    public static function handle() {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if (in_array($origin, ALLOWED_ORIGINS)) {
            header("Access-Control-Allow-Origin: " . $origin);
        } else {
        }

        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400");

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    // ฟังก์ชันนี้จะยังคงอยู่ แต่การเรียกใช้หลักจะอยู่ใน handle() แล้ว
    public static function isAllowedOrigin($origin) {
        return in_array($origin, ALLOWED_ORIGINS); 
    }
}
?>