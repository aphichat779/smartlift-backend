<?php
// middleware/AuthMiddleware.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/JWTHelper.php';

class AuthMiddleware {
    public static function authenticate() {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] :
                     (isset($headers['authorization']) ? $headers['authorization'] : null);

        if (!$authHeader) {
            self::sendUnauthorized('Authorization header missing');
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            self::sendUnauthorized('Invalid authorization header format');
        }

        $jwt = $matches[1];
        $payload = JWTHelper::decode($jwt);

        if (!$payload) {
            self::sendUnauthorized('Invalid or expired token');
        }

        return $payload;
    }

    public static function requireAdmin() {
        $user = self::authenticate();
        if (($user['role'] ?? '') !== 'admin') {
            self::sendForbidden('Admin access required');
        }
        return $user;
    }

    public static function optionalAuth() {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] :
                     (isset($headers['authorization']) ? $headers['authorization'] : null);

        if (!$authHeader) {
            return null;
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $jwt = $matches[1];
        return JWTHelper::decode($jwt);
    }

    // --- เพิ่มเมธอดอรรถประโยชน์สำหรับตรวจบทบาทหลายค่า ---
    public static function requireRoles(array $roles) {
        $user = self::authenticate(); // will exit on fail
        if (!in_array($user['role'] ?? '', $roles, true)) {
            self::sendForbidden('Forbidden: role not allowed');
        }
        return $user;
    }

    private static function sendUnauthorized($message) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function sendForbidden($message) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// -------------------
// เพิ่ม Global helpers
// -------------------

// ใช้ในไฟล์ API ต่าง ๆ ได้ทันที (เหมือนที่คุณเรียกอยู่)
if (!function_exists('requireAuth')) {
    /**
     * requireAuth(['admin','org_admin',...]) -> คืน payload ผู้ใช้ (array)
     * ถ้า token ไม่ถูกต้อง/ไม่มีสิทธิ์ จะส่ง response และ exit ภายใน
     */
    function requireAuth(array $roles = []) {
        $user = AuthMiddleware::authenticate(); // will exit on fail
        if (!empty($roles) && !in_array($user['role'] ?? '', $roles, true)) {
            http_response_code(403);
            echo json_encode([
                "success"    => false,
                "message"    => "Forbidden: role not allowed",
                "error_code" => "FORBIDDEN"
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        return $user;
    }
}

if (!function_exists('currentUser')) {
    /**
     * ดึง payload ปัจจุบัน (nullable) โดยไม่บังคับต้องมี token
     */
    function currentUser(): ?array {
        $u = AuthMiddleware::optionalAuth();
        return $u ?: null;
    }
}
