<?php
// api/technician/update_status.php
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

  // รับทั้ง JSON และ multipart/form-data
  $isJson = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
  if ($isJson) {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $tk_id   = (int)($payload['tk_id'] ?? 0);
    $status  = trim((string)($payload['tk_status'] ?? ''));
    $detail  = trim((string)($payload['detail'] ?? ''));
  } else {
    $tk_id   = (int)($_POST['tk_id'] ?? 0);
    $status  = trim((string)($_POST['tk_status'] ?? ''));
    $detail  = trim((string)($_POST['detail'] ?? ''));
  }

  // ตรวจสอบค่า status กับ enum ใหม่
  $validStatuses = ['assign','preparing','progress','test','complete'];
  if ($tk_id <= 0 || !in_array($status, $validStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid tk_id or tk_status'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo = Database::getConnection();

  // ตรวจว่าเป็นงานของช่างคนนี้
  $chk = $pdo->prepare("SELECT tk_id FROM task WHERE tk_id = :id AND user_id = :uid LIMIT 1");
  $chk->execute([':id'=>$tk_id, ':uid'=>$uid]);
  if (!$chk->fetch()) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo->beginTransaction();

  // update task
  $upd = $pdo->prepare("UPDATE task SET tk_status = :s WHERE tk_id = :id");
  $upd->execute([':s'=>$status, ':id'=>$tk_id]);

  // แนบไฟล์ถ้ามี
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

  // insert log ด้วยค่าสถานะเดียวกัน
  $ins = $pdo->prepare("
    INSERT INTO task_status (tk_id, status, time, detail, tk_status_tool, tk_img, section)
    VALUES (:tk_id, :status, NOW(), :detail, :tool, NULL, 'progress')
  ");
  $ins->execute([
    ':tk_id'  => $tk_id,
    ':status' => $status,
    ':detail' => $detail,
    ':tool'   => $fileUrl,
  ]);

  $pdo->commit();

  echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
