<?php
// api/admin/tasks/list.php
require_once __DIR__ . '/../../../middleware/CORSMiddleware.php';
handleCORS();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = requireAuth(['admin']); // << แก้จุดนี้

    $pdo = Database::getConnection();
    $status = $_GET['status'] ?? null;  // '1'..'5'
    $q      = $_GET['q'] ?? null;

    $where = [];
    $args  = [];

    if ($status !== null && in_array($status, ['1','2','3','4','5'], true)) {
        $where[] = "t.tk_status = :s";
        $args[':s'] = $status;
    }
    if ($q) {
        $where[] = "(t.tk_data LIKE :q OR t.org_name LIKE :q OR t.building_name LIKE :q OR t.`user` LIKE :q)";
        $args[':q'] = "%$q%";
    }

    $sql = "SELECT t.tk_id, t.tk_status, t.tk_data, t.task_start_date, t.rp_id,
                   t.user_id, t.`user`, t.mainten_id, t.org_name, t.building_name,
                   t.lift_id, t.tools,
                   (SELECT ts.status FROM task_status ts WHERE ts.tk_id=t.tk_id ORDER BY ts.time DESC, ts.tk_status_id DESC LIMIT 1) AS last_status,
                   (SELECT ts.time   FROM task_status ts WHERE ts.tk_id=t.tk_id ORDER BY ts.time DESC, ts.tk_status_id DESC LIMIT 1) AS last_status_time
            FROM task t";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY t.tk_id DESC LIMIT 200";

    $stm = $pdo->prepare($sql);
    $stm->execute($args);
    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
