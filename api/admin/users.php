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
    // อนุญาตทั้ง admin และ org_admin (ใช้ helper ที่มีอยู่จริง)
    $authUser = requireAuth(['admin', 'org_admin']); // จะ exit ให้เองถ้า token/role ไม่ผ่าน
    if (!$authUser) { exit; }

    $database = new Database();
    $db = $database->getConnection();

    // อ็อบเจ็กต์ User หลัก (ใช้สำหรับเมธอดของโมเดล)
    $user = new User($db);

    // บทบาทปัจจุบัน
    $role       = $authUser['role'] ?? '';
    $isAdmin    = ($role === 'admin');
    $isOrgAdmin = ($role === 'org_admin');
    $scopeOrgId = $isOrgAdmin ? (int)($authUser['org_id'] ?? 0) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $page   = isset($_GET['page'])  ? max(1, (int)$_GET['page'])  : 1;
        $limit  = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
        $offset = ($page - 1) * $limit;

        if ($isOrgAdmin) {
            // กรณี org_admin: ดึงเฉพาะผู้ใช้ใน org ตัวเอง (ไม่ไปแก้ Model เดิม)
            $sql  = "SELECT id, username, first_name, last_name, email, phone, role,
                            ga_enabled, org_id, user_img, last_2fa_reset, failed_2fa_attempts, locked_until, is_active
                     FROM users
                     WHERE org_id = :org_id
                     ORDER BY id DESC
                     LIMIT :limit OFFSET :offset";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':org_id', $scopeOrgId, PDO::PARAM_INT);
            $stmt->bindValue(':limit',  $limit,      PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt2 = $db->prepare("SELECT COUNT(*) AS c FROM users WHERE org_id = :org_id");
            $stmt2->bindValue(':org_id', $scopeOrgId, PDO::PARAM_INT);
            $stmt2->execute();
            $totalRow = $stmt2->fetch(PDO::FETCH_ASSOC);
            $total    = (int)($totalRow['c'] ?? 0);
        } else {
            // admin: ใช้เมธอดเดิมของโมเดล
            $users = $user->getAllUsers($limit, $offset);
            $total = $user->countAll();
        }

        http_response_code(200);
        echo json_encode([
            'success'    => true,
            'data'       => $users,
            'pagination' => [
                'page'  => $page,
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
        $action         = (string)$data->action;

        // ห้ามแก้บัญชีตัวเอง
        if ((int)$authUser['id'] === $userIdToUpdate) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถแก้ไขบัญชีของตัวเองได้']);
            exit;
        }

        // โหลดข้อมูล "ผู้ใช้เป้าหมาย" ด้วยอ็อบเจ็กต์ใหม่ เพื่อไม่ให้ทับค่าของ $user หลัก
        $targetUser = new User($db);
        $found      = $targetUser->findById($userIdToUpdate); // คืน true/false และตั้ง properties ในอ็อบเจ็กต์
        if (!$found) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบบัญชีผู้ใช้']);
            exit;
        }

        // ถ้าเป็น org_admin แต่คนเป้าหมายอยู่อีก org -> ห้าม
        if ($isOrgAdmin && (int)$targetUser->org_id !== (int)$scopeOrgId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ต่างองค์กร']);
            exit;
        }

        if ($action === 'update_role') {
            $allowedRolesAdmin    = ['admin','org_admin','technician','user'];
            $allowedRolesOrgAdmin = ['org_admin','technician','user']; // org_admin ห้ามตั้งเป็น admin

            if (!isset($data->role)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ต้องระบุ role']);
                exit;
            }
            $newRole     = (string)$data->role;
            $allowedPool = $isAdmin ? $allowedRolesAdmin : $allowedRolesOrgAdmin;

            if (!in_array($newRole, $allowedPool, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'บทบาทไม่ถูกต้องหรือไม่มีสิทธิ์ตั้งค่า']);
                exit;
            }

            if ($user->updateRole($userIdToUpdate, $newRole)) {
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

            $isActive = (int)!!$data->is_active; // 1 หรือ 0

            // org_admin จัดการได้เฉพาะใน org ตัวเอง (เช็คแล้วด้านบนด้วย targetUser->org_id)
            if ($user->toggleActiveStatus($userIdToUpdate, $isActive)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'เปลี่ยนสถานะบัญชีสำเร็จ']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถเปลี่ยนสถานะบัญชีได้']);
            }
            exit;
        }

        if ($action === 'update_user_org') {
            if (!isset($data->org_id) || !is_numeric($data->org_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'org_id ไม่ถูกต้อง']);
                exit;
            }
            $newOrgId = (int)$data->org_id;

            // org_admin ไม่อนุญาตให้ย้ายข้ามองค์กร (ต้องอยู่ org ตัวเองเท่านั้น)
            if ($isOrgAdmin && $newOrgId !== (int)$scopeOrgId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'org-admin ไม่สามารถย้ายผู้ใช้ออกนอกองค์กรได้']);
                exit;
            }

            if ($user->updateUserOrg($userIdToUpdate, $newOrgId)) {
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
        'success'    => false,
        'message'    => 'Internal server error',
        'error_code' => 'SERVER_ERROR'
    ]);
}
