<?php
// api/elevator/buildings.php
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

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // ดึงรายการอาคาร (ไม่ต้อง authentication สำหรับ dropdown)
            $building_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            $org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : null;
            
            if ($building_id) {
                // ดึงข้อมูลอาคารตาม ID
                $query = "SELECT b.*, o.org_name 
                          FROM buildings b 
                          LEFT JOIN organizations o ON b.org_id = o.id 
                          WHERE b.id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$building_id]);
                $building = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($building) {
                    $building['id'] = (int)$building['id'];
                    $building['org_id'] = (int)$building['org_id'];
                    echo json_encode([
                        'success' => true,
                        'data' => $building
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Building not found'
                    ]);
                }
            } else {
                // ดึงรายการอาคาร โดยสามารถ filter ตาม org_id ได้
                if ($org_id) {
                    // ดึงอาคารตาม org_id ที่ระบุ
                    $query = "SELECT b.*, o.org_name 
                              FROM buildings b 
                              LEFT JOIN organizations o ON b.org_id = o.id 
                              WHERE b.org_id = ? 
                              ORDER BY b.building_name";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$org_id]);
                } else {
                    // ดึงอาคารทั้งหมด
                    $query = "SELECT b.*, o.org_name 
                              FROM buildings b 
                              LEFT JOIN organizations o ON b.org_id = o.id 
                              ORDER BY o.org_name, b.building_name";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                }
                
                $buildings = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $row['id'] = (int)$row['id'];
                    $row['org_id'] = (int)$row['org_id'];
                    $buildings[] = $row;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $buildings
                ]);
            }
            break;
            
        case 'POST':
            // เพิ่มอาคารใหม่ (ต้อง authentication)
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON format'
                ]);
                exit();
            }
            
            $required_fields = ['org_id', 'building_name'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Field '$field' is required"
                    ]);
                    exit();
                }
            }
            
            $checkOrgQuery = "SELECT id FROM organizations WHERE id = ?";
            $checkOrgStmt = $db->prepare($checkOrgQuery);
            $checkOrgStmt->execute([$input['org_id']]);
            
            if (!$checkOrgStmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Organization not found'
                ]);
                exit();
            }
            
            $checkQuery = "SELECT id FROM buildings WHERE building_name = ? AND org_id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([trim($input['building_name']), $input['org_id']]);
            
            if ($checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Building name already exists in this organization'
                ]);
                exit();
            }
            
            $query = "INSERT INTO buildings (org_id, building_name, description, address, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $input['org_id'],
                trim($input['building_name']),
                trim($input['description'] ?? ''),
                trim($input['address'] ?? '')
            ]);
            
            if ($result) {
                $building_id = $db->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'message' => 'Building created successfully',
                    'data' => ['id' => (int)$building_id]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create building'
                ]);
            }
            break;
            
        case 'PUT':
            // แก้ไขข้อมูลอาคาร (ต้อง authentication)
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON format'
                ]);
                exit();
            }
            
            if (!isset($input['id']) || empty($input['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Building ID is required'
                ]);
                exit();
            }
            
            $building_id = (int)$input['id'];
            
            $checkQuery = "SELECT id, org_id FROM buildings WHERE id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$building_id]);
            $existingBuilding = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingBuilding) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Building not found'
                ]);
                exit();
            }
            
            if (isset($input['org_id'])) {
                $checkOrgQuery = "SELECT id FROM organizations WHERE id = ?";
                $checkOrgStmt = $db->prepare($checkOrgQuery);
                $checkOrgStmt->execute([$input['org_id']]);
                
                if (!$checkOrgStmt->fetch()) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Organization not found'
                    ]);
                    exit();
                }
            }
            
            if (isset($input['building_name']) && !empty(trim($input['building_name']))) {
                $org_id_to_check = isset($input['org_id']) ? $input['org_id'] : $existingBuilding['org_id'];
                $checkNameQuery = "SELECT id FROM buildings WHERE building_name = ? AND org_id = ? AND id != ?";
                $checkNameStmt = $db->prepare($checkNameQuery);
                $checkNameStmt->execute([trim($input['building_name']), $org_id_to_check, $building_id]);
                
                if ($checkNameStmt->fetch()) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Building name already exists in this organization'
                    ]);
                    exit();
                }
            }
            
            $updateFields = [];
            $params = [];
            
            $allowedFields = ['org_id', 'building_name', 'description', 'address'];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    if ($field === 'org_id') {
                        $updateFields[] = "$field = ?";
                        $params[] = $input[$field];
                    } else {
                        $updateFields[] = "$field = ?";
                        $params[] = trim($input[$field]);
                    }
                }
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No fields to update'
                ]);
                exit();
            }
            
            $updateFields[] = "updated_at = NOW()";
            $params[] = $building_id;
            
            $query = "UPDATE buildings SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Building updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update building'
                ]);
            }
            break;
            
        case 'DELETE':
            // ลบอาคาร (ต้อง authentication)
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                exit();
            }
            
            $building_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            
            if (!$building_id) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Building ID is required'
                ]);
                exit();
            }
            
            $checkQuery = "SELECT id FROM buildings WHERE id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$building_id]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Building not found'
                ]);
                exit();
            }
            
            $checkLiftsQuery = "SELECT COUNT(*) as count FROM lifts WHERE building_id = ?";
            $checkLiftsStmt = $db->prepare($checkLiftsQuery);
            $checkLiftsStmt->execute([$building_id]);
            $liftCount = $checkLiftsStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($liftCount > 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot delete building. It has associated lifts.'
                ]);
                exit();
            }
            
            $query = "DELETE FROM buildings WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([$building_id]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Building deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete building'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>