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
    if (!$authUser) { exit; }

    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $page  = isset($_GET['page'])  ? max(1, (int)$_GET['page'])  : 1;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
        $offset = ($page - 1) * $limit;

        $users = $user->getAllUsers($limit, $offset);
        $total = $user->countAll(); // <-- ต้องมี method นี้ใน models/User.php

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total
            ]
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->user_id) || !isset($data->action)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบ: ต้องมี user_id และ action']);
            exit;
        }

        $userIdToUpdate = (int)$data->user_id;
        $action = (string)$data->action;

        // ห้ามแก้บัญชีตัวเอง
        if ((int)$authUser['id'] === $userIdToUpdate) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถแก้ไขบัญชีของตัวเองได้']);
            exit;
        }

        if ($action === 'update_role') {
            $allowedRoles = ['admin','technician','user']; // ปรับตามที่คุณใช้งานจริง
            if (!isset($data->role) || !in_array($data->role, $allowedRoles, true)) {
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
            exit;
        }

        if ($action === 'toggle_status') {
            if (!isset($data->is_active)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ข้อมูลสถานะไม่ครบถ้วน']);
                exit;
            }
            $isActive = (int)!!$data->is_active;
            if ($user->toggleActiveStatus($userIdToUpdate, $isActive)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'เปลี่ยนสถานะบัญชีสำเร็จ']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถเปลี่ยนสถานะบัญชีได้']);
            }
            exit;
        }

        // (ตัวเลือก) โยกผู้ใช้อยู่ในองค์กรใหม่
        if ($action === 'update_user_org') {
            if (!isset($data->org_id) || !is_numeric($data->org_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'org_id ไม่ถูกต้อง']);
                exit;
            }
            if ($user->updateUserOrg($userIdToUpdate, (int)$data->org_id)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'อัปเดตองค์กรของผู้ใช้สำเร็จ']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถอัปเดตองค์กรของผู้ใช้ได้']);
            }
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'คำสั่งไม่ถูกต้อง']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'SERVER_ERROR'
    ]);
}
