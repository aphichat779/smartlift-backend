<?php
// api/elevator/lifts.php
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
    
    // ใช้ AuthMiddleware สำหรับ authentication
    $user = AuthMiddleware::authenticate();
    if (!$user) {
        exit(); // AuthMiddleware จะส่ง response แล้ว
    }
    
    $user_id = $user['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // ดึงรายการลิฟต์
            $lift_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            $org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : null;
            $building_id = isset($_GET['building_id']) ? (int)$_GET['building_id'] : null;
            
            if ($lift_id) {
                // ดึงข้อมูลลิฟต์ตาม ID
                $query = "SELECT l.*, o.org_name, b.building_name 
                         FROM lifts l 
                         LEFT JOIN organizations o ON l.org_id = o.id 
                         LEFT JOIN building b ON l.building_id = b.id 
                         WHERE l.id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$lift_id]);
                $lift = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lift) {
                    $lift['id'] = (int)$lift['id'];
                    $lift['org_id'] = (int)$lift['org_id'];
                    $lift['building_id'] = (int)$lift['building_id'];
                    $lift['max_level'] = (int)$lift['max_level'];
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $lift
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Lift not found'
                    ]);
                }
            } else {
                // ดึงรายการลิฟต์ทั้งหมด หรือ filter ตามเงื่อนไข
                $whereConditions = [];
                $params = [];
                
                if ($org_id) {
                    $whereConditions[] = "l.org_id = ?";
                    $params[] = $org_id;
                }
                
                if ($building_id) {
                    $whereConditions[] = "l.building_id = ?";
                    $params[] = $building_id;
                }
                
                $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
                
                $query = "SELECT l.*, o.org_name, b.building_name 
                         FROM lifts l 
                         LEFT JOIN organizations o ON l.org_id = o.id 
                         LEFT JOIN building b ON l.building_id = b.id 
                         $whereClause
                         ORDER BY o.org_name, b.building_name, l.lift_name";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                
                $lifts = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $row['id'] = (int)$row['id'];
                    $row['org_id'] = (int)$row['org_id'];
                    $row['building_id'] = (int)$row['building_id'];
                    $row['max_level'] = (int)$row['max_level'];
                    $lifts[] = $row;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $lifts
                ]);
            }
            break;
            
        case 'POST':
            // เพิ่มลิฟต์ใหม่
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $required_fields = ['org_id', 'building_id', 'lift_name', 'max_level', 'mac_address', 'floor_name'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Field '$field' is required"
                    ]);
                    exit();
                }
            }
            
            // Check if MAC address already exists
            $checkQuery = "SELECT id FROM lifts WHERE mac_address = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$input['mac_address']]);
            
            if ($checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'MAC address already exists'
                ]);
                exit();
            }
            
            $query = "INSERT INTO lifts (
                        org_id, building_id, lift_name, max_level, mac_address, 
                        floor_name, description, lift_state, up_status, down_status, 
                        car_status, created_user_id, created_at, updated_user_id, updated_at
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())";
            
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $input['org_id'],
                $input['building_id'],
                $input['lift_name'],
                $input['max_level'],
                $input['mac_address'],
                $input['floor_name'],
                $input['description'] ?? '',
                $input['lift_state'] ?? '000000000000',
                $input['up_status'] ?? '00000000',
                $input['down_status'] ?? '00000000',
                $input['car_status'] ?? '00000000',
                $user_id,
                $user_id
            ]);
            
            if ($result) {
                $lift_id = $db->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'message' => 'Lift created successfully',
                    'data' => ['id' => (int)$lift_id]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create lift'
                ]);
            }
            break;
            
        case 'PUT':
            // แก้ไขข้อมูลลิฟต์
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id']) || empty($input['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Lift ID is required'
                ]);
                exit();
            }
            
            $lift_id = (int)$input['id'];
            
            // Check if lift exists
            $checkQuery = "SELECT id FROM lifts WHERE id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$lift_id]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Lift not found'
                ]);
                exit();
            }
            
            // Check if MAC address already exists (exclude current lift)
            if (isset($input['mac_address'])) {
                $checkMacQuery = "SELECT id FROM lifts WHERE mac_address = ? AND id != ?";
                $checkMacStmt = $db->prepare($checkMacQuery);
                $checkMacStmt->execute([$input['mac_address'], $lift_id]);
                
                if ($checkMacStmt->fetch()) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'MAC address already exists'
                    ]);
                    exit();
                }
            }
            
            // Build update query dynamically
            $updateFields = [];
            $params = [];
            
            $allowedFields = ['org_id', 'building_id', 'lift_name', 'max_level', 'mac_address', 'floor_name', 'description'];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = ?";
                    $params[] = $input[$field];
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
            
            $updateFields[] = "updated_user_id = ?";
            $updateFields[] = "updated_at = NOW()";
            $params[] = $user_id;
            $params[] = $lift_id;
            
            $query = "UPDATE lifts SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Lift updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update lift'
                ]);
            }
            break;
            
        case 'DELETE':
            // ลบลิฟต์
            $lift_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            
            if (!$lift_id) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Lift ID is required'
                ]);
                exit();
            }
            
            // Check if lift exists
            $checkQuery = "SELECT id FROM lifts WHERE id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$lift_id]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Lift not found'
                ]);
                exit();
            }
            
            $query = "DELETE FROM lifts WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([$lift_id]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Lift deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete lift'
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

