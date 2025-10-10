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

  // รับได้ทั้ง JSON และ multipart/form-data
  $isJson = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
  if ($isJson) {
    $payload        = json_decode(file_get_contents('php://input'), true) ?: [];
    $tk_id          = (int)($payload['tk_id'] ?? 0);
    $status         = trim((string)($payload['tk_status'] ?? ''));
    $detail         = trim((string)($payload['detail'] ?? ''));
    $toolsJson      = trim((string)($payload['tools'] ?? ''));
    $toolsTotalCost = (float)($payload['tools_total_cost'] ?? 0);
  } else {
    $tk_id          = (int)($_POST['tk_id'] ?? 0);
    $status         = trim((string)($_POST['tk_status'] ?? ''));
    $detail         = trim((string)($_POST['detail'] ?? ''));
    $toolsJson      = trim((string)($_POST['tools'] ?? ''));
    $toolsTotalCost = (float)($_POST['tools_total_cost'] ?? 0);
  }

  if ($tk_id <= 0 || !in_array($status, ['assign', 'preparing', 'progress', 'complete'], true)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid input data'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo = Database::getConnection();

  // ยืนยันว่าเป็นงานของช่างคนนี้
  $stm = $pdo->prepare("SELECT user_id FROM task WHERE tk_id = :id LIMIT 1");
  $stm->execute([':id' => $tk_id]);
  $task = $stm->fetch(PDO::FETCH_ASSOC);
  if (!$task || (int)$task['user_id'] !== $uid) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Access denied or Task not found'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 1) อัปเดตสถานะหลัก
  $pdo->prepare("UPDATE task SET tk_status = :s WHERE tk_id = :id")
      ->execute([':s' => $status, ':id' => $tk_id]);

  // 2) เครื่องมือ (รับคีย์ tool_id, tool_name, qty, cost)
  if ($toolsJson) {
    $toolsData = json_decode($toolsJson, true) ?: [];

    $pdo->prepare("DELETE FROM task_tools WHERE tk_id = :tk AND status = :st")
        ->execute([':tk' => $tk_id, ':st' => $status]);

    if (!empty($toolsData)) {
      $ins = $pdo->prepare("
        INSERT INTO task_tools (tk_id, tool_id, name, amount, cost, status, total_cost)
        VALUES (:tk, :tool_id, :name, :amount, :cost, :status, :total_cost)
      ");
      foreach ($toolsData as $t) {
        $amount = (int)($t['qty'] ?? 1);
        $cost   = (float)($t['cost'] ?? 0);
        $ins->execute([
          ':tk'        => $tk_id,
          ':tool_id'   => (int)($t['tool_id'] ?? 0),
          ':name'      => (string)($t['tool_name'] ?? ''),
          ':amount'    => $amount,
          ':cost'      => $cost,
          ':status'    => $status,
          ':total_cost'=> $amount * $cost,
        ]);
      }
    }
  }

  // 3) แนบไฟล์ (ถ้ามี)
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

  // 4) log การอัปเดต
  $pdo->prepare("
    INSERT INTO task_status (tk_id, status, time, detail, tk_status_tool, tk_img, section)
    VALUES (:tk, :st, NOW(), :detail, :tool_cost, :file_url, 'status_update')
  ")->execute([
    ':tk'        => $tk_id,
    ':st'        => $status,
    ':detail'    => $detail,
    ':tool_cost' => $toolsTotalCost,
    ':file_url'  => $fileUrl,
  ]);

  echo json_encode(['success'=>true,'message'=>'Task status updated successfully.'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'An error occurred: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
