<?php
// api/admin/tasks/technicians.php
require_once __DIR__ . '/../../../middleware/CORSMiddleware.php';
handleCORS();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // กำหนดบทบาทที่ได้รับอนุญาตให้เข้าถึง API นี้
    $user = requireAuth(['super_admin', 'admin', 'technician']);
    
    $pdo = Database::getConnection();
    $q = $_GET['q'] ?? null;
    
    $sql = "SELECT 
                u.id AS id,           
                u.username, 
                u.first_name, 
                u.last_name, 
                u.email, 
                u.phone, 
                u.org_id,
                o.org_name
            FROM users u
            LEFT JOIN organizations o ON o.id = u.org_id
            WHERE u.role = 'technician' AND u.is_active = 1";
    
    $args = [];
    if ($q) {
        // เพิ่มเงื่อนไขการค้นหาในคอลัมน์ที่เกี่ยวข้อง
        $sql .= " AND (u.username LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q OR u.email LIKE :q OR o.org_name LIKE :q)";
        $args[':q'] = "%$q%";
    }
    
    $sql .= " ORDER BY u.first_name, u.last_name LIMIT 200";

    $stm = $pdo->prepare($sql);
    $stm->execute($args);
    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);

} catch (Throwable $e) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}