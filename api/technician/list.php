<?php
// api/technician/list.php
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
handleCORS();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // ต้องเป็น technician
    $user = requireAuth(['technician']);

    // รองรับหลายรูปแบบ payload id
    $uid = (int)($user['id'] ?? 0);
    if ($uid <= 0) $uid = (int)($user['user_id'] ?? 0);
    if ($uid <= 0) $uid = (int)($user['uid'] ?? 0);

    if ($uid <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot resolve technician id from token payload.',
            'debug_payload_keys' => array_keys($user)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Database::getConnection();

    $sql = "
        SELECT 
            t.tk_id,
            t.rp_id,
            t.user_id,
            t.tk_data,
            t.tk_status,                        -- enum (assign, preparing, progress, complete)
            -- t.start_date,                     -- ลบออก
            -- t.expected_end_date,              -- ลบออก
            r.detail        AS report_detail,
            r.date_rp,
            o.org_name,
            b.building_name,
            l.lift_name,
            l.id            AS lift_real_id
        FROM task t
        LEFT JOIN report r        ON r.rp_id      = t.rp_id
        LEFT JOIN organizations o ON o.id         = r.org_id
        LEFT JOIN buildings b     ON b.id         = r.building_id
        LEFT JOIN lifts l         ON l.id         = r.lift_id
        WHERE t.user_id = :uid
        ORDER BY t.tk_id DESC
        LIMIT 500
    ";

    $stm = $pdo->prepare($sql);
    $stm->execute([':uid' => $uid]);
    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);

    // เพื่อความเข้ากันได้กับ frontend เดิมที่อาจดู tk_status_text
    foreach ($rows as &$row) {
        $row['tk_status_text'] = $row['tk_status'];
    }

    echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'An error occurred: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}