<?php
// api/technician/add_progress.php
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

    $isJson = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    if ($isJson) {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $tk_id   = (int)($payload['tk_id'] ?? 0);
        $detail  = trim((string)($payload['detail'] ?? ''));
        $section = trim((string)($payload['section'] ?? 'progress'));
    } else {
        $tk_id   = (int)($_POST['tk_id'] ?? 0);
        $detail  = trim((string)($_POST['detail'] ?? ''));
        $section = trim((string)($_POST['section'] ?? 'progress'));
    }

    if ($tk_id <= 0) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Missing tk_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Database::getConnection();

    // ยืนยันว่าเป็นงานของช่างคนนี้
    $chk = $pdo->prepare("SELECT tk_id FROM task WHERE tk_id = :id AND user_id = :uid LIMIT 1");
    $chk->execute([':id'=>$tk_id, ':uid'=>$uid]);
    if (!$chk->fetch()) {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // แนบไฟล์ (ถ้ามี)
    $fileUrl = null;
    if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $name = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . basename($_FILES['file']['name']);
        $dir  = __DIR__ . '/../../uploads/task_status/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $dest = $dir . $name;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            $fileUrl = '/uploads/task_status/' . $name;
        }
    }

    // ใช้สถานะปัจจุบันของ task เป็นค่าที่จะบันทึกใน log เพื่อความสอดคล้อง
    $cur = $pdo->prepare("SELECT tk_status FROM task WHERE tk_id = :id LIMIT 1");
    $cur->execute([':id'=>$tk_id]);
    $row = $cur->fetch(PDO::FETCH_ASSOC);
    $statusForLog = $row ? $row['tk_status'] : 'progress'; // fallback

    $ins = $pdo->prepare("
        INSERT INTO task_status (tk_id, status, time, detail, tk_status_tool, tk_img, section)
        VALUES (:tk_id, :status, NOW(), :detail, :tool, NULL, :section)
    ");
    $ins->execute([
        ':tk_id'  => $tk_id,
        ':status' => $statusForLog,
        ':detail' => $detail,
        ':tool'   => $fileUrl,
        ':section'=> $section ?: 'progress',
    ]);

    echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}