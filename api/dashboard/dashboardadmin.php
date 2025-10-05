<?php
/**
 * File: api/dashboard/dashboardadmin.php
 * Scope: ไม่จำกัด org (all orgs) — role: admin หรือ super_admin
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
    if (!in_array($user['role'], ['admin','super_admin'], true)) {
        http_response_code(403);
        echo json_encode(['success'=>false,'error'=>'FORBIDDEN','message'=>'admin or super_admin only']);
        exit;
    }

    echo json_encode(buildDashboardPayload($pdo, 'admin', null, true), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'SERVER_ERROR','message'=>$e->getMessage()]);
}
