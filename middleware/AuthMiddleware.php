<?php
// middleware/AuthMiddleware.php

// -------------------
// Global Polyfills (สำคัญสำหรับ MAMP)
// -------------------
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$name] = $value;
            }
        }
        // รวม Content-Type และ Content-Length ที่อาจหายไป
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
        return $headers;
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/JWTHelper.php';

class AuthMiddleware {
    public static function authenticate() {
        $headers = getallheaders();
        // ตรวจสอบทั้ง 'Authorization' และ 'authorization' เพื่อความชัวร์
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] :
                     (isset($headers['authorization']) ? $headers['authorization'] : null);
        
        // บางเซิร์ฟเวอร์อาจใช้ HTTP_AUTHORIZATION
        if (!$authHeader && isset($headers['HTTP_AUTHORIZATION'])) {
             $authHeader = $headers['HTTP_AUTHORIZATION'];
        }

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

        return $payload; // ควรมี ['id','role',...]
    }

    public static function requireAdmin() {
        $user = self::authenticate();
        if (($user['role'] ?? '') !== 'admin') {
            self::sendForbidden('Admin access required');
        }
        return $user;
    }

    /* ---------------- NEW: helpers สำหรับบทบาท ---------------- */
    public static function isAdmin(array $u): bool {
        return (($u['role'] ?? '') === 'admin');
    }

    public static function isOrgAdmin(array $u): bool {
        return (($u['role'] ?? '') === 'org_admin');
    }

    public static function requireAdminOrOrgAdmin() {
        $u = self::authenticate();
        if (!self::isAdmin($u) && !self::isOrgAdmin($u)) {
            self::sendForbidden('Forbidden (admin/org_admin only)');
        }
        return $u;
    }
    /* ----------------------------------------------------------- */

    public static function optionalAuth() {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] :
                     (isset($headers['authorization']) ? $headers['authorization'] : null);
        
        if (!$authHeader && isset($headers['HTTP_AUTHORIZATION'])) {
             $authHeader = $headers['HTTP_AUTHORIZATION'];
        }

        if (!$authHeader) {
            return null;
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $jwt = $matches[1];
        return JWTHelper::decode($jwt);
    }

    // --- เมธอดอรรถประโยชน์สำหรับตรวจบทบาทหลายค่า ---
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
// Global helpers
// -------------------
if (!function_exists('requireAuth')) {
    function requireAuth(array $roles = []) {
        $user = AuthMiddleware::authenticate(); // will exit on fail
        if (!empty($roles) && !in_array($user['role'] ?? '', $roles, true)) {
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Forbidden: role not allowed",
                "error_code" => "FORBIDDEN"
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        return $user;
    }
}

if (!function_exists('currentUser')) {
    function currentUser(): ?array {
        $u = AuthMiddleware::optionalAuth();
        return $u ?: null;
    }
}