<?php
// api/admin/tasks/assign.php
require_once __DIR__ . '/../../../middleware/CORSMiddleware.php';
handleCORS();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../utils/ValidationHelper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // อนุญาต super_admin และ admin
    $user = requireAuth(['super_admin', 'admin']);

    $role    = trim((string)($user['role'] ?? ''));
    $adminId = (int)($user['id'] ?? $user['user_id'] ?? $user['sub'] ?? 0);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $rp_id        = $input['rp_id']           ?? null;
    $technicianId = $input['user_id']         ?? null;
    $task_detail  = $input['tk_data']         ?? '';
    $tools        = $input['tools']           ?? [];
    $start_in     = $input['task_start_date'] ?? ($input['start_date'] ?? null); // รองรับทั้ง 2 ฟิลด์

    if (!ValidationHelper::validId($rp_id))        throw new Exception('Invalid rp_id');
    if (!ValidationHelper::validId($technicianId)) throw new Exception('Invalid user_id (technician)');
    if (!is_array($tools)) $tools = [];

    // แปลงวันที่เริ่มงาน
    $start_at = null;
    if (!empty($start_in)) {
        $start_at = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_in)
            ? $start_in . ' 00:00:00'
            : $start_in;
    }

    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    // ดึงชื่อ-นามสกุลของ "ผู้มอบหมาย" ให้แน่ใจว่าไม่พึ่ง token
    $admin = null;
    if ($adminId > 0) {
        $stAdmin = $pdo->prepare("SELECT first_name, username FROM users WHERE id = :id LIMIT 1");
        $stAdmin->execute([':id' => $adminId]);
        $admin = $stAdmin->fetch(PDO::FETCH_ASSOC);
    }
    $firstName = trim((string)($admin['first_name'] ?? ''));
    $username  = trim((string)($admin['username'] ?? ''));
    $assignedBy = trim(($role ? $role : '') . ' ' . ($firstName !== '' ? $firstName : $username));

    // ดึง report
    $stmt = $pdo->prepare("SELECT r.*, o.org_name, b.building_name
                             FROM report r
                        LEFT JOIN organizations o ON o.id = r.org_id
                        LEFT JOIN buildings b     ON b.id = r.building_id
                            WHERE r.rp_id = :rp");
    $stmt->execute([':rp' => $rp_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) throw new Exception('Report not found');

    // ตรวจช่าง
    $ut = $pdo->prepare("SELECT id, username, first_name, last_name 
                           FROM users 
                          WHERE id = :uid AND role = 'technician' AND is_active = 1");
    $ut->execute([':uid' => $technicianId]);
    $tech = $ut->fetch(PDO::FETCH_ASSOC);
    if (!$tech) throw new Exception('Technician not found or inactive');

    $tk_status = 'assign';
    $toolsJson = json_encode($tools, JSON_UNESCAPED_UNICODE);

    // บันทึก task (เก็บ assigned_by = "role first_name")
    $ins = $pdo->prepare("INSERT INTO task
        (tk_status, tk_data, task_start_date, rp_id, user_id, `user`, mainten_id,
         org_name, building_name, lift_id, tools, assigned_by)
        VALUES
        (:tk_status, :tk_data, :task_start_date, :rp_id, :user_id, :user, :mainten_id,
         :org_name, :building_name, :lift_id, :tools, :assigned_by)");
    $ins->execute([
        ':tk_status'       => $tk_status,
        ':tk_data'         => (string)$task_detail,
        ':task_start_date' => $start_at,
        ':rp_id'           => (int)$rp_id,
        ':user_id'         => (int)$technicianId,
        ':user'            => (string)$tech['username'],
        ':mainten_id'      => (int)$technicianId,
        ':org_name'        => (string)($report['org_name'] ?? ''),
        ':building_name'   => (string)($report['building_name'] ?? ''),
        ':lift_id'         => (int)($report['lift_id'] ?? 0),
        ':tools'           => $toolsJson,
        ':assigned_by'     => $assignedBy,
    ]);
    $tk_id = (int)$pdo->lastInsertId();

    // ไทม์ไลน์แรก
    $ss = $pdo->prepare("INSERT INTO task_status (tk_id, `status`, `time`, `detail`, `section`)
                         VALUES (:tk_id, 'assign', NOW(), :detail, 'assignment')");
    $ss->execute([':tk_id' => $tk_id, ':detail' => 'Assigned by ' . $assignedBy]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Task assigned', 'data' => ['tk_id' => $tk_id]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
