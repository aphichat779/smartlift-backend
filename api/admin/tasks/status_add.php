<?php
// api/admin/tasks/status_add.php
require_once __DIR__ . '/../../../middleware/CORSMiddleware.php';
handleCORS();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../utils/ValidationHelper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = requireAuth(['admin']); // << แก้จุดนี้

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $tk_id     = $input['tk_id']    ?? null;
    $statusTxt = $input['status']   ?? null; // 'preparing'|'working'|'finish'|'prepared'|'assign'
    $detail    = $input['detail']   ?? '';
    $section   = $input['section']  ?? 'update';
    $tools     = $input['tk_status_tool'] ?? null; // string|array|null
    $imgBase64 = $input['tk_img'] ?? null;        // base64|null

    if (!ValidationHelper::validId($tk_id)) {
        throw new Exception('Invalid tk_id');
    }
    if (!in_array($statusTxt, ['preparing','working','finish','prepared','assign'], true)) {
        throw new Exception('Invalid status');
    }

    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    $imgData = null;
    if (is_string($imgBase64) && $imgBase64 !== '') {
        if (strpos($imgBase64, 'base64,') !== false) {
            $imgBase64 = substr($imgBase64, strpos($imgBase64, 'base64,') + 7);
        }
        $imgData = base64_decode($imgBase64);
    }

    $sql = "INSERT INTO task_status (tk_id, `status`, `time`, `detail`, `tk_status_tool`, `tk_img`, `section`)
            VALUES (:tk_id, :status, NOW(), :detail, :tool, :img, :section)";
    $stm = $pdo->prepare($sql);
    $stm->bindValue(':tk_id',   (int)$tk_id, PDO::PARAM_INT);
    $stm->bindValue(':status',  $statusTxt, PDO::PARAM_STR);
    $stm->bindValue(':detail',  (string)$detail, PDO::PARAM_STR);
    $stm->bindValue(':tool',    $tools !== null ? (is_string($tools) ? $tools : json_encode($tools, JSON_UNESCAPED_UNICODE)) : null, $tools !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    if ($imgData !== null) {
        $stm->bindValue(':img', $imgData, PDO::PARAM_LOB);
    } else {
        $stm->bindValue(':img', null, PDO::PARAM_NULL);
    }
    $stm->bindValue(':section', $section, PDO::PARAM_STR);
    $stm->execute();

    // sync สถานะหลัก
    $map = ['assign'=>'1','preparing'=>'2','working'=>'3','finish'=>'4','prepared'=>'5'];
    if (isset($map[$statusTxt])) {
        $up = $pdo->prepare("UPDATE task SET tk_status = :s WHERE tk_id = :tid");
        $up->execute([':s' => $map[$statusTxt], ':tid' => $tk_id]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Status appended']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
