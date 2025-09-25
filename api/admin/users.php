<?php
// api/admin/users.php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/TwoFAResetLog.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';

CORSMiddleware::handle();

try {
    // Require admin authentication
    $authUser = AuthMiddleware::requireAdmin();
    if (!$authUser) {
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $user = new User($db);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all users
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $users = $user->getAllUsers($limit, $offset);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($users)
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // รับค่า JSON จาก Body ของ Request
        $data = json_decode(file_get_contents("php://input"));
        
        // ตรวจสอบว่ามี user_id และ action ส่งมาหรือไม่
        if (!isset($data->user_id) || !isset($data->action)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน: user_id และ action จำเป็นต้องมี']);
            exit;
        }

        $userIdToUpdate = $data->user_id;
        $action = $data->action;

        // ป้องกันไม่ให้ admin แก้ไขบัญชีของตัวเอง
        if ($authUser['id'] == $userIdToUpdate) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'คุณไม่สามารถแก้ไขบัญชีของตัวเองได้']);
            exit;
        }
        
        if ($action === 'update_role') {
            if (!isset($data->role) || !in_array($data->role, ['admin', 'technician', 'user'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'บทบาทไม่ถูกต้อง']);
                exit;
            }
            if ($user->updateRole($userIdToUpdate, $data->role)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'แก้ไขบทบาทสำเร็จ']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถแก้ไขบทบาทได้']);
            }
        } elseif ($action === 'toggle_status') {
            if (!isset($data->is_active)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ข้อมูลสถานะไม่ครบถ้วน']);
                exit;
            }
            if ($user->toggleActiveStatus($userIdToUpdate, $data->is_active)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'เปลี่ยนสถานะบัญชีสำเร็จ']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถเปลี่ยนสถานะบัญชีได้']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'คำสั่งไม่ถูกต้อง']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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