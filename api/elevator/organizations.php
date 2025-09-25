<?php
// api/elevator/organizations.php 
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
            // ดึงรายการองค์กรทั้งหมด (ไม่ต้อง authentication สำหรับ dropdown)
            $org_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            
            if ($org_id) {
                // ดึงข้อมูลองค์กรตาม ID
                $query = "SELECT id, org_name, description, created_at, updated_at FROM organizations WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$org_id]);
                $org = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($org) {
                    $org['id'] = (int)$org['id'];
                    echo json_encode([
                        'success' => true,
                        'data' => $org
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Organization not found'
                    ]);
                }
            } else {
                // ดึงรายการองค์กรทั้งหมด
                $query = "SELECT id, org_name, description, created_at, updated_at FROM organizations ORDER BY org_name";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $organizations = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $row['id'] = (int)$row['id'];
                    $organizations[] = $row;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $organizations
                ]);
            }
            break;
            
        case 'POST':
            // เพิ่มองค์กรใหม่ (ต้อง authentication)
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                exit(); // AuthMiddleware จะส่ง response แล้ว
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Check if JSON decode was successful
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON format'
                ]);
                exit();
            }
            
            // Validate required fields
            if (!isset($input['org_name']) || empty(trim($input['org_name']))) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Organization name is required'
                ]);
                exit();
            }
            
            // Check if organization name already exists
            $checkQuery = "SELECT id FROM organizations WHERE org_name = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([trim($input['org_name'])]);
            
            if ($checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Organization name already exists'
                ]);
                exit();
            }
            
            $query = "INSERT INTO organizations (org_name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                trim($input['org_name']),
                trim($input['description'] ?? '')
            ]);
            
            if ($result) {
                $org_id = $db->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'message' => 'Organization created successfully',
                    'data' => ['id' => (int)$org_id]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create organization'
                ]);
            }
            break;
            
        case 'PUT':
            // แก้ไขข้อมูลองค์กร (ต้อง authentication)
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                exit(); // AuthMiddleware จะส่ง response แล้ว
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Check if JSON decode was successful
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
                    'message' => 'Organization ID is required'
                ]);
                exit();
            }
            
            $org_id = (int)$input['id'];
            
            // Check if organization exists
            $checkQuery = "SELECT id FROM organizations WHERE id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$org_id]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Organization not found'
                ]);
                exit();
            }
            
            // Check if organization name already exists (exclude current org)
            if (isset($input['org_name']) && !empty(trim($input['org_name']))) {
                $checkNameQuery = "SELECT id FROM organizations WHERE org_name = ? AND id != ?";
                $checkNameStmt = $db->prepare($checkNameQuery);
                $checkNameStmt->execute([trim($input['org_name']), $org_id]);
                
                if ($checkNameStmt->fetch()) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Organization name already exists'
                    ]);
                    exit();
                }
            }
            
            // Build update query dynamically
            $updateFields = [];
            $params = [];
            
            if (isset($input['org_name']) && !empty(trim($input['org_name']))) {
                $updateFields[] = "org_name = ?";
                $params[] = trim($input['org_name']);
            }
            
            if (isset($input['description'])) {
                $updateFields[] = "description = ?";
                $params[] = trim($input['description']);
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
            $params[] = $org_id;
            
            $query = "UPDATE organizations SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Organization updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update organization'
                ]);
            }
            break;
            
        case 'DELETE':
            // ลบองค์กร (ต้อง authentication)
            $user = AuthMiddleware::authenticate();
            if (!$user) {
                exit(); // AuthMiddleware จะส่ง response แล้ว
            }
            
            $org_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            
            if (!$org_id) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Organization ID is required'
                ]);
                exit();
            }
            
            // Check if organization exists
            $checkQuery = "SELECT id FROM organizations WHERE id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$org_id]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Organization not found'
                ]);
                exit();
            }
            
            // Check if organization has buildings
            $checkBuildingsQuery = "SELECT COUNT(*) as count FROM building WHERE org_id = ?";
            $checkBuildingsStmt = $db->prepare($checkBuildingsQuery);
            $checkBuildingsStmt->execute([$org_id]);
            $buildingCount = $checkBuildingsStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($buildingCount > 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot delete organization. It has associated buildings.'
                ]);
                exit();
            }
            
            $query = "DELETE FROM organizations WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([$org_id]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Organization deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete organization'
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

