<?php
// api/admin/tasks/reports_open.php
require_once __DIR__ . '/../../../middleware/CORSMiddleware.php';
handleCORS();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../utils/JWTHelper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // เปลี่ยนจาก ['admin'] เป็น ['super_admin', 'admin', 'technician']
    $user = requireAuth(['super_admin', 'admin', 'technician']);
    
    $pdo = Database::getConnection();
    
    // ตรวจสอบบทบาท
    $role = $user['role'] ?? '';
    $isAdmin = ($role === 'admin');
    $isSuperAdmin = ($role === 'super_admin');
    $isTechnician = ($role === 'technician');

    $date = $_GET['date'] ?? null; // 'YYYY-MM-DD'
    $q    = $_GET['q'] ?? null;

    $where = [];
    $args  = [];

    if ($date) {
        $where[] = "r.date_rp = :d";
        $args[':d'] = $date;
    }
    if ($q) {
        $where[] = "(r.detail LIKE :q OR o.org_name LIKE :q OR b.building_name LIKE :q OR l.lift_name LIKE :q)";
        $args[':q'] = "%$q%";
    }

    // admin, super_admin, technician ดูได้ทุกรายงาน (ไม่จำกัด org)
    $sql = "SELECT r.rp_id, r.date_rp, r.user_id, r.org_id, r.building_id, r.lift_id, r.detail,
                   o.org_name, b.building_name, l.lift_name,
                   (SELECT COUNT(*) FROM task t WHERE t.rp_id = r.rp_id) AS assigned_count
            FROM report r
            LEFT JOIN organizations o ON o.id = r.org_id
            LEFT JOIN buildings b ON b.id = r.building_id
            LEFT JOIN lifts l ON l.id = r.lift_id";
            
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY r.rp_id DESC LIMIT 200";

    $stm = $pdo->prepare($sql);
    $stm->execute($args);
    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}