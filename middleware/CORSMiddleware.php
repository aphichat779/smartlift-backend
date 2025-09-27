<?php
// middleware/CORSMiddleware.php
require_once __DIR__ . '/../config/config.php';

class CORSMiddleware {

    public static function handle() {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if (defined('ALLOWED_ORIGINS') && is_array(ALLOWED_ORIGINS) && in_array($origin, ALLOWED_ORIGINS, true)) {
            header("Access-Control-Allow-Origin: " . $origin);
        } else {
            // ถ้าอยากอนุญาตทั้งหมดชั่วคราว (ไม่แนะนำในโปรดักชัน) ปลดคอมเมนต์บรรทัดถัดไป
            // header("Access-Control-Allow-Origin: *");
        }

        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400");

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    public static function isAllowedOrigin($origin) {
        return defined('ALLOWED_ORIGINS') && is_array(ALLOWED_ORIGINS) && in_array($origin, ALLOWED_ORIGINS, true);
    }
}

// --------------
// Global alias
// --------------
if (!function_exists('handleCORS')) {
    /**
     * ให้เรียกใช้ได้แบบ handleCORS([...]) ตามโค้ดเดิม
     * พารามิเตอร์ $methods ไม่ถูกใช้จริง (จัดการที่เมธอด handle() แล้ว)
     */
    function handleCORS(array $methods = ['GET','POST','PUT','DELETE','OPTIONS']): void {
        CORSMiddleware::handle();
    }
}
