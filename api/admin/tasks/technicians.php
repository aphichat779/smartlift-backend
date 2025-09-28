<?php
// api/admin/tasks/technicians.php
require_once __DIR__ . '/../../../middleware/CORSMiddleware.php';
handleCORS();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = requireAuth(['admin']); // << แก้จุดนี้

    $pdo = Database::getConnection();
    $q = $_GET['q'] ?? null;

    $sql = "SELECT id, username, first_name, last_name, email, phone, org_id
            FROM users WHERE role='technician' AND is_active=1";
    $args = [];
    if ($q) {
        $sql .= " AND (username LIKE :q OR first_name LIKE :q OR last_name LIKE :q OR email LIKE :q)";
        $args[':q'] = "%$q%";
    }
    $sql .= " ORDER BY id DESC LIMIT 200";

    $stm = $pdo->prepare($sql);
    $stm->execute($args);
    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}