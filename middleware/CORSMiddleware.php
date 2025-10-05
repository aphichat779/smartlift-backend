<?php
// middleware/CORSMiddleware.php
require_once __DIR__ . '/../config/config.php';

class CORSMiddleware {

    public static function handle(): void {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri    = $_SERVER['REQUEST_URI'] ?? '';
        $isSSE  = str_contains($accept, 'text/event-stream') || str_contains($uri, 'stream_status.php');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($isSSE) {
            // EventSource ปกติไม่ใช้ credentials
            header('Vary: Origin');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Credentials: false');
            header('Access-Control-Allow-Headers: Cache-Control, X-Requested-With, Content-Type');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            
            // เพิ่ม header เพิ่มเติมสำหรับ SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
        } else {
            if (defined('ALLOWED_ORIGINS') && is_array(ALLOWED_ORIGINS) && in_array($origin, ALLOWED_ORIGINS, true)) {
                header("Access-Control-Allow-Origin: {$origin}");
                header('Vary: Origin');
            } else {
                error_log("CORS blocked origin: " . ($origin ?: 'NULL'));
                // สำหรับการพัฒนา ให้อนุญาตทุก origin ชั่วคราว
                header("Access-Control-Allow-Origin: *");
            }
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    public static function isAllowedOrigin($origin): bool {
        return defined('ALLOWED_ORIGINS') && is_array(ALLOWED_ORIGINS) && in_array($origin, ALLOWED_ORIGINS, true);
    }
}

if (!function_exists('handleCORS')) {
    function handleCORS(array $methods = ['GET','POST','PUT','DELETE','OPTIONS']): void {
        CORSMiddleware::handle();
    }
}