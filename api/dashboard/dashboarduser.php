<?php
/**
 * File: api/dashboard/dashboarduser.php
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/DashboardService.php';

if (function_exists('handleCORS')) { handleCORS(['GET', 'OPTIONS']); }
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

try {
    /** @var PDO $pdo */
    $pdo = Database::getConnection();

    $user = getAuthUserOrFail();

    if ($user['role'] === 'user') {
        $orgId = (int) ($user['org_id'] ?? 0);
        
        if ($orgId <= 0) {
            // **[แก้ไข]** หาก user ไม่มี org ให้ส่ง 200 OK และ success: true พร้อม orgId: 0
            // เพื่อให้ Frontend จัดการแสดงผล "ยังไม่มีองค์กรณ์" ได้อย่างราบรื่น
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'role' => 'user',
                'orgId' => 0, // ค่าสำคัญสำหรับ Frontend ในการตรวจสอบ
                'message' => 'User is not associated with an organization.',
                // ส่งโครงสร้างข้อมูลว่างไปเพื่อความเข้ากันได้กับ Frontend UI
                'kpis' => [],
                'liftBits' => [],
                'reports' => [],
                'activity' => [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // ถ้ามี orgId ให้ดึงข้อมูลตามปกติ
        echo json_encode(buildDashboardPayload($pdo, 'user', $orgId, false), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Allow admin/super_admin to call user-scope by specifying org_id (impersonation/testing)
    if (in_array($user['role'], ['admin','super_admin'], true)) {
        $orgId = isset($_GET['org_id']) ? (int) $_GET['org_id'] : 0;
        if ($orgId <= 0) {
            // Admin/SuperAdmin ต้องระบุ org_id
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'BAD_REQUEST','message'=>'org_id is required']);
            exit;
        }
        echo json_encode(buildDashboardPayload($pdo, 'user', $orgId, false), JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'FORBIDDEN','message'=>'user|admin|super_admin only']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'SERVER_ERROR','message'=>$e->getMessage()]);
}