<?php
// api/work/reports.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

// Handle CORS
CORSMiddleware::handle();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Authentication required']);
                exit();
            }
            handlePost($db, $user);
            break;
        case 'PUT':
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Authentication required']);
                exit();
            }
            handlePut($db, $user);
            break;
        case 'DELETE':
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Authentication required']);
                exit();
            }
            handleDelete($db, $user); // ส่งข้อมูลผู้ใช้เข้าไปในฟังก์ชัน
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

function handleGet($db) {
    try {
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $query = "SELECT r.*, 
                             o.org_name, 
                             b.building_name, 
                             l.lift_name,
                             u.first_name, u.last_name
                      FROM report r 
                      LEFT JOIN organizations o ON r.org_id = o.id 
                      LEFT JOIN building b ON r.building_id = b.id 
                      LEFT JOIN lifts l ON r.lift_id = l.id
                      LEFT JOIN users u ON r.user_id = u.id
                      WHERE r.rp_id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($report) {
                $report['reporter_name'] = trim($report['first_name'] . ' ' . $report['last_name']);
                echo json_encode(['success' => true, 'data' => $report]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรายงาน']);
            }
        } elseif (isset($_GET['org_id'])) {
            $org_id = intval($_GET['org_id']);
            $query = "SELECT r.*, 
                             o.org_name, 
                             b.building_name, 
                             l.lift_name,
                             u.first_name, u.last_name
                      FROM report r 
                      LEFT JOIN organizations o ON r.org_id = o.id 
                      LEFT JOIN building b ON r.building_id = b.id 
                      LEFT JOIN lifts l ON r.lift_id = l.id
                      LEFT JOIN users u ON r.user_id = u.id
                      WHERE r.org_id = :org_id 
                      ORDER BY r.date_rp DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':org_id', $org_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($reports as &$report) {
                $report['reporter_name'] = trim($report['first_name'] . ' ' . $report['last_name']);
            }
            
            echo json_encode(['success' => true, 'data' => $reports]);
        } else {
            $query = "SELECT r.*, 
                             o.org_name, 
                             b.building_name, 
                             l.lift_name,
                             u.first_name, u.last_name
                      FROM report r 
                      LEFT JOIN organizations o ON r.org_id = o.id 
                      LEFT JOIN building b ON r.building_id = b.id 
                      LEFT JOIN lifts l ON r.lift_id = l.id
                      LEFT JOIN users u ON r.user_id = u.id
                      ORDER BY r.date_rp DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($reports as &$report) {
                $report['reporter_name'] = trim($report['first_name'] . ' ' . $report['last_name']);
            }
            
            echo json_encode(['success' => true, 'data' => $reports]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()
        ]);
    }
}

function handlePost($db, $user) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validation
        if (!isset($input['org_id']) || !isset($input['building_id']) || 
            !isset($input['lift_id']) || !isset($input['detail']) || 
            empty($input['org_id']) || empty($input['building_id']) || 
            empty($input['lift_id']) || empty(trim($input['detail']))) { // เพิ่ม trim()
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            return;
        }
        
        $user_id = $user['id'] ?? $user['user_id'] ?? $user['sub'] ?? null;
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้ กรุณาเข้าสู่ระบบใหม่']);
            return;
        }
        
        $org_id = intval($input['org_id']);
        $building_id = intval($input['building_id']);
        $lift_id = intval($input['lift_id']);
        $detail = trim($input['detail']);
        $date_rp = isset($input['date_rp']) ? $input['date_rp'] : date('Y-m-d');
        
        $checkQuery = "SELECT COUNT(*) as count FROM lifts l 
                       WHERE l.id = :lift_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':lift_id', $lift_id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()['count'] == 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ข้อมูลลิฟต์ไม่ถูกต้อง']);
            return;
        }
        
        $query = "INSERT INTO report (date_rp, user_id, org_id, building_id, lift_id, detail) 
                  VALUES (:date_rp, :user_id, :org_id, :building_id, :lift_id, :detail)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':date_rp', $date_rp);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':org_id', $org_id, PDO::PARAM_INT);
        $stmt->bindParam(':building_id', $building_id, PDO::PARAM_INT);
        $stmt->bindParam(':lift_id', $lift_id, PDO::PARAM_INT);
        $stmt->bindParam(':detail', $detail);
        
        if ($stmt->execute()) {
            $report_id = $db->lastInsertId();
            
            $selectQuery = "SELECT r.*, 
                                     o.org_name, 
                                     b.building_name, 
                                     l.lift_name,
                                     u.first_name, u.last_name
                             FROM report r 
                             LEFT JOIN organizations o ON r.org_id = o.id 
                             LEFT JOIN building b ON r.building_id = b.id 
                             LEFT JOIN lifts l ON r.lift_id = l.id
                             LEFT JOIN users u ON r.user_id = u.id
                             WHERE r.rp_id = :id";
            $selectStmt = $db->prepare($selectQuery);
            $selectStmt->bindParam(':id', $report_id, PDO::PARAM_INT);
            $selectStmt->execute();
            
            $newReport = $selectStmt->fetch(PDO::FETCH_ASSOC);
            $newReport['reporter_name'] = trim($newReport['first_name'] . ' ' . $newReport['last_name']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'เพิ่มรายงานใหม่สำเร็จ',
                'data' => $newReport
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเพิ่มข้อมูล']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'เกิดข้อผิดพลาดในการเพิ่มข้อมูล: ' . $e->getMessage()
        ]);
    }
}

function handlePut($db, $user) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['rp_id']) || !isset($input['org_id']) || 
            !isset($input['building_id']) || !isset($input['lift_id']) || 
            !isset($input['detail']) || empty($input['rp_id']) ||
            empty($input['org_id']) || empty($input['building_id']) ||
            empty($input['lift_id']) || empty(trim($input['detail']))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            return;
        }
        
        $user_id = $user['id'] ?? $user['user_id'] ?? $user['sub'] ?? null;
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้ กรุณาเข้าสู่ระบบใหม่']);
            return;
        }
        
        $rp_id = intval($input['rp_id']);
        $org_id = intval($input['org_id']);
        $building_id = intval($input['building_id']);
        $lift_id = intval($input['lift_id']);
        $detail = trim($input['detail']);
        $date_rp = isset($input['date_rp']) ? $input['date_rp'] : date('Y-m-d');
        
        $checkQuery = "SELECT user_id FROM report WHERE rp_id = :rp_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':rp_id', $rp_id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        $existingReport = $checkStmt->fetch();
        if (!$existingReport) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรายงาน']);
            return;
        }
        
        if ($existingReport['user_id'] != $user_id && 
            !in_array($user['role'], ['admin', 'technician'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์แก้ไขรายงานนี้']);
            return;
        }
        
        $query = "UPDATE report 
                  SET date_rp = :date_rp, org_id = :org_id, building_id = :building_id, 
                      lift_id = :lift_id, detail = :detail
                  WHERE rp_id = :rp_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':rp_id', $rp_id, PDO::PARAM_INT);
        $stmt->bindParam(':date_rp', $date_rp);
        $stmt->bindParam(':org_id', $org_id, PDO::PARAM_INT);
        $stmt->bindParam(':building_id', $building_id, PDO::PARAM_INT);
        $stmt->bindParam(':lift_id', $lift_id, PDO::PARAM_INT);
        $stmt->bindParam(':detail', $detail);
        
        if ($stmt->execute()) {
            $selectQuery = "SELECT r.*, 
                                     o.org_name, 
                                     b.building_name, 
                                     l.lift_name,
                                     u.first_name, u.last_name
                             FROM report r 
                             LEFT JOIN organizations o ON r.org_id = o.id 
                             LEFT JOIN building b ON r.building_id = b.id 
                             LEFT JOIN lifts l ON r.lift_id = l.id
                             LEFT JOIN users u ON r.user_id = u.id
                             WHERE r.rp_id = :id";
            $selectStmt = $db->prepare($selectQuery);
            $selectStmt->bindParam(':id', $rp_id, PDO::PARAM_INT);
            $selectStmt->execute();
            
            $updatedReport = $selectStmt->fetch(PDO::FETCH_ASSOC);
            $updatedReport['reporter_name'] = trim($updatedReport['first_name'] . ' ' . $updatedReport['last_name']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'แก้ไขข้อมูลรายงานสำเร็จ',
                'data' => $updatedReport
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . $e->getMessage()
        ]);
    }
}

function handleDelete($db, $user) { // เพิ่ม $user
    try {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบ ID ของรายงานที่ต้องการลบ']);
            return;
        }
        
        $id = intval($_GET['id']);
        
        $checkQuery = "SELECT user_id FROM report WHERE rp_id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        $existingReport = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingReport) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรายงาน']);
            return;
        }

        // ตรวจสอบสิทธิ์การลบ: เฉพาะเจ้าของรายงานหรือ admin/technician เท่านั้น
        $user_id = $user['id'] ?? $user['user_id'] ?? $user['sub'] ?? null;
        if ($existingReport['user_id'] != $user_id && 
            !in_array($user['role'], ['admin', 'technician'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ลบรายงานนี้']);
            return;
        }
        
        $taskCheckQuery = "SELECT COUNT(*) as count FROM task WHERE rp_id = :rp_id";
        $taskCheckStmt = $db->prepare($taskCheckQuery);
        $taskCheckStmt->bindParam(':rp_id', $id, PDO::PARAM_INT);
        $taskCheckStmt->execute();
        
        $taskCount = $taskCheckStmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($taskCount > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'ไม่สามารถลบรายงานได้ เนื่องจากมีงาน ' . $taskCount . ' งานที่เกี่ยวข้อง'
            ]);
            return;
        }
        
        $deleteQuery = "DELETE FROM report WHERE rp_id = :id";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($deleteStmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'ลบรายงานสำเร็จ'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage()
        ]);
    }
}
?>