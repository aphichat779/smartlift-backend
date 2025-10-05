<?php
/**
 * File: api/dashboard/dashboarduser.php
 * Scope: เฉพาะ org ที่สังกัด — role: user (หรือ admin/super_admin ระบุ org_id)
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
            http_response_code(403);
            echo json_encode(['success'=>false,'error'=>'FORBIDDEN','message'=>'user has no org']);
            exit;
        }
        echo json_encode(buildDashboardPayload($pdo, 'user', $orgId, false), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Allow admin/super_admin to call user-scope by specifying org_id (impersonation/testing)
    if (in_array($user['role'], ['admin','super_admin'], true)) {
        $orgId = isset($_GET['org_id']) ? (int) $_GET['org_id'] : 0;
        if ($orgId <= 0) {
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
