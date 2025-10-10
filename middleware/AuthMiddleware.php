<?php
declare(strict_types=1);

// middleware/AuthMiddleware.php

// -------------------
// Global Polyfills (สำคัญสำหรับ MAMP/Apache บางตัว)
// -------------------
if (!function_exists('getallheaders')) {
    function getallheaders(): array {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $norm = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$norm] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE']))   $headers['Content-Type']   = $_SERVER['CONTENT_TYPE'];
        if (isset($_SERVER['CONTENT_LENGTH'])) $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && !isset($headers['Authorization'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION']; // บาง env จะอยู่ตัวแปรนี้
        }
        return $headers;
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/JWTHelper.php';

class AuthMiddleware {

    /* --------------------------------------------------------------------
     * Base auth: อ่านจาก Authorization header ("Bearer <JWT>")
     * ------------------------------------------------------------------ */
    public static function authenticate(): array {
        $headers    = getallheaders();
        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);
        if (!$authHeader) self::sendUnauthorized('Authorization header missing');

        if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
            self::sendUnauthorized('Invalid authorization header format');
        }

        $jwt     = $m[1];
        $payload = JWTHelper::decode($jwt);
        if (!$payload) self::sendUnauthorized('Invalid or expired token');

        return $payload; // expected: ['role', 'org_id', ...]
    }

    /* --------------------------------------------------------------------
     * Auth สำหรับ REST และ SSE:
     * - รับได้ทั้ง Authorization header และ query ?access_token=
     * - เผื่อกรณีเก็บ JWT เป็นคุกกี้ชื่อยอดฮิต (token/auth_token/jwt/access_token)
     * ------------------------------------------------------------------ */
    public static function authenticateFromRequest(): array {
        $headers    = getallheaders();
        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);

        $token = null;
        if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
            $token = $m[1];
        } elseif (!empty($_GET['access_token'])) {
            $token = (string)$_GET['access_token']; // สำหรับ SSE
        } else {
            // fallback: common cookie names
            $cookieKeys = ['token', 'auth_token', 'jwt', 'access_token', 'SMARTLIFT_TOKEN'];
            foreach ($cookieKeys as $ck) {
                if (!empty($_COOKIE[$ck])) { $token = (string)$_COOKIE[$ck]; break; }
            }
        }

        if (!$token) self::sendUnauthorized('Missing token');

        $payload = JWTHelper::decode($token);
        if (!$payload) self::sendUnauthorized('Invalid or expired token');

        return $payload;
    }

    /* --------------------------------------------------------------------
     * Role helpers
     * ------------------------------------------------------------------ */
    public static function isAdmin(array $u): bool {
        return (($u['role'] ?? '') === 'admin');
    }

    public static function requireAdmin(): array {
        $user = self::authenticate();
        if (($user['role'] ?? '') !== 'admin') {
            self::sendForbidden('Admin access required');
        }
        return $user;
    }

    public static function optionalAuth(): ?array {
        $headers    = getallheaders();
        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);
        if (!$authHeader || !preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) return null;
        $payload = JWTHelper::decode($m[1]);
        return $payload ?: null;
    }

    public static function requireRoles(array $roles): array {
        $user = self::authenticate();
        if (!in_array($user['role'] ?? '', $roles, true)) {
            self::sendForbidden('Forbidden: role not allowed');
        }
        return $user;
    }

    /* --------------------------------------------------------------------
     * ขอบเขตการมองเห็นลิฟต์ตามนโยบาย:
     * - super_admin / admin / technician → เห็นทุก org (ไม่มี WHERE)
     * - user → เห็นเฉพาะ org ของตัวเอง (ต้องมี org_id)
     * คืนค่าเป็น [ $whereSql, $paramsArray ] โดย $whereSql เป็น '' เมื่อไม่จำกัด
     * ------------------------------------------------------------------ */
    public static function requireOrgScope(array $user): array {
        $role  = (string)($user['role'] ?? '');
        $orgId = (int)($user['org_id'] ?? 0);

        // ① super_admin, admin, technician → unlimited scope
        if (in_array($role, ['super_admin', 'admin', 'technician'], true)) {
            return ['', []]; // ไม่มี WHERE
        }

        // ② user → ต้องจำกัดตาม org_id; หากไม่มี org_id → ห้ามเห็นอะไรเลย
        if ($role === 'user') {
            if ($orgId > 0) {
                return ['l.org_id = :org_id', [':org_id' => $orgId]];
            }
            // สำคัญ: ปิดผลลัพธ์ทั้งหมด แทนการปล่อยหลุดเห็นทุก org
            return ['1=0', []];
        }

        // ③ role อื่นไม่อนุญาต
        self::sendForbidden('Forbidden: role not allowed');
    }

    /* --------------------------------------------------------------------
     * Error responses
     * ------------------------------------------------------------------ */
    private static function sendUnauthorized(string $message): void {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'    => false,
            'message'    => $message,
            'error_code' => 'UNAUTHORIZED',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function sendForbidden(string $message): void {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'    => false,
            'message'    => $message,
            'error_code' => 'FORBIDDEN',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/* ------------------- Global helpers ------------------- */
if (!function_exists('requireAuth')) {
    function requireAuth(array $roles = []): array {
        $user = AuthMiddleware::authenticate(); // will exit on fail
        if (!empty($roles) && !in_array($user['role'] ?? '', $roles, true)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'    => false,
                'message'    => 'Forbidden: role not allowed',
                'error_code' => 'FORBIDDEN',
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
