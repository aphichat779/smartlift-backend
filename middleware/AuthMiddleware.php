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
            return false;
        }
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            self::sendUnauthorized('Invalid authorization header format');
            return false;
        }
        
        $jwt = $matches[1];
        $payload = JWTHelper::decode($jwt);
        
        if (!$payload) {
            self::sendUnauthorized('Invalid or expired token');
            return false;
        }
        
        return $payload;
    }
    
    public static function requireAdmin() {
        $user = self::authenticate();
        if (!$user) {
            return false;
        }
        
        if ($user['role'] !== 'admin') {
            self::sendForbidden('Admin access required');
            return false;
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
    
    private static function sendUnauthorized($message) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED'
        ]);
        exit;
    }
    
    private static function sendForbidden($message) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN'
        ]);
        exit;
    }
}
?>

