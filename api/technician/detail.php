<?php
// api/technician/detail.php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
handleCORS();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = requireAuth(['technician']);
    $uid  = (int)($user['id'] ?? 0);
    if ($uid <= 0) $uid = (int)($user['user_id'] ?? 0);
    if ($uid <= 0) $uid = (int)($user['uid'] ?? 0);

    if ($uid <= 0) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Cannot resolve technician id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tk_id = (int)($_GET['tk_id'] ?? 0);
    if ($tk_id <= 0) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Missing tk_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Database::getConnection();

    // ✅ เพิ่ม task_start_date, assigned_by, user (responsible_user)
    $sql = "
        SELECT 
            t.tk_id,
            t.rp_id,
            t.user_id,
            t.tk_data,
            t.tk_status,
            t.task_start_date,
            t.assigned_by,
            t.`user`             AS responsible_user,
            -- องค์ประกอบสำหรับโชว์
            r.detail             AS report_detail,
            r.date_rp,
            o.org_name,
            b.building_name,
            l.lift_name,
            l.id                 AS lift_real_id
        FROM task t
        LEFT JOIN report r        ON r.rp_id      = t.rp_id
        LEFT JOIN organizations o ON o.id         = r.org_id
        LEFT JOIN buildings b     ON b.id         = r.building_id
        LEFT JOIN lifts l         ON l.id         = r.lift_id
        WHERE t.tk_id = :id AND t.user_id = :uid
        LIMIT 1
    ";
    $stm = $pdo->prepare($sql);
    $stm->execute([':id' => $tk_id, ':uid' => $uid]);
    $data = $stm->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Task not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // timeline (log) ของงาน
    $hsql = "
        SELECT tk_status_id, tk_id, status, time, detail, tk_status_tool AS file_url, section
        FROM task_status
        WHERE tk_id = :id
        ORDER BY time DESC, tk_status_id DESC
        LIMIT 1000
    ";
    $stmH = $pdo->prepare($hsql);
    $stmH->execute([':id' => $tk_id]);
    $history = $stmH->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'tk_id'            => $data['tk_id'],
            'tk_data'          => $data['tk_data'],
            'tk_status'        => $data['tk_status'],
            'task_start_date'  => $data['task_start_date'],
            'assigned_by'      => $data['assigned_by'],
            'responsible_user' => $data['responsible_user'],
            'report_detail'    => $data['report_detail'],
            'date_rp'          => $data['date_rp'],
            'org_name'         => $data['org_name'],
            'building_name'    => $data['building_name'],
            'lift_name'        => $data['lift_name'],
            'history'          => $history,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'An error occurred: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
