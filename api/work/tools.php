<?php
// api/work/tools.php
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
            // ต้อง authentication สำหรับการเพิ่มข้อมูล
            $user = AuthMiddleware::authenticate();
            handlePost($db, $user);
            break;
        case 'PUT':
            // ต้อง authentication สำหรับการแก้ไขข้อมูล
            $user = AuthMiddleware::authenticate();
            handlePut($db, $user);
            break;
        case 'DELETE':
            // ต้อง authentication สำหรับการลบข้อมูล
            $user = AuthMiddleware::authenticate();
            handleDelete($db);
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
            // ดึงข้อมูลเครื่องมือตาม ID
            $id = intval($_GET['id']);
            $query = "SELECT * FROM tools WHERE tool_id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $tool = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tool) {
                echo json_encode(['success' => true, 'data' => $tool]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลเครื่องมือ']);
            }
        } elseif (isset($_GET['search'])) {
            // ค้นหาเครื่องมือตามชื่อ
            $search = '%' . trim($_GET['search']) . '%';
            $query = "SELECT * FROM tools WHERE tool_name LIKE :search ORDER BY tool_name";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':search', $search);
            $stmt->execute();
            
            $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $tools]);
        } else {
            // ดึงรายการเครื่องมือทั้งหมด
            $query = "SELECT * FROM tools ORDER BY tool_name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $tools]);
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
        if (empty($input['tool_name']) || !isset($input['cost'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            return;
        }
        
        $tool_name = trim($input['tool_name']);
        $cost = intval($input['cost']);
        
        // ตรวจสอบว่าชื่อเครื่องมือซ้ำหรือไม่
        $checkQuery = "SELECT tool_id FROM tools WHERE tool_name = :tool_name";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':tool_name', $tool_name);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ชื่อเครื่องมือนี้มีอยู่แล้ว']);
            return;
        }
        
        // เพิ่มข้อมูลเครื่องมือใหม่
        $query = "INSERT INTO tools (tool_name, cost) VALUES (:tool_name, :cost)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':tool_name', $tool_name);
        $stmt->bindParam(':cost', $cost);
        
        if ($stmt->execute()) {
            $tool_id = $db->lastInsertId();
            
            // ดึงข้อมูลเครื่องมือที่เพิ่งสร้าง
            $selectQuery = "SELECT * FROM tools WHERE tool_id = :id";
            $selectStmt = $db->prepare($selectQuery);
            $selectStmt->bindParam(':id', $tool_id);
            $selectStmt->execute();
            
            $newTool = $selectStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'message' => 'เพิ่มเครื่องมือใหม่สำเร็จ',
                'data' => $newTool
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
        
        // Validation
        if (empty($input['tool_id']) || empty($input['tool_name']) || !isset($input['cost'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            return;
        }
        
        $tool_id = intval($input['tool_id']);
        $tool_name = trim($input['tool_name']);
        $cost = intval($input['cost']);
        
        // ตรวจสอบว่าเครื่องมือมีอยู่จริง
        $checkQuery = "SELECT tool_name FROM tools WHERE tool_id = :tool_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':tool_id', $tool_id);
        $checkStmt->execute();
        
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลเครื่องมือ']);
            return;
        }
        
        // ตรวจสอบว่าชื่อเครื่องมือซ้ำหรือไม่ (ยกเว้นตัวเอง)
        $checkDuplicateQuery = "SELECT tool_id FROM tools WHERE tool_name = :tool_name AND tool_id != :tool_id";
        $checkDuplicateStmt = $db->prepare($checkDuplicateQuery);
        $checkDuplicateStmt->bindParam(':tool_name', $tool_name);
        $checkDuplicateStmt->bindParam(':tool_id', $tool_id);
        $checkDuplicateStmt->execute();
        
        if ($checkDuplicateStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ชื่อเครื่องมือนี้มีอยู่แล้ว']);
            return;
        }
        
        // อัปเดตข้อมูลเครื่องมือ
        $query = "UPDATE tools SET tool_name = :tool_name, cost = :cost WHERE tool_id = :tool_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':tool_id', $tool_id);
        $stmt->bindParam(':tool_name', $tool_name);
        $stmt->bindParam(':cost', $cost);
        
        if ($stmt->execute()) {
            // ดึงข้อมูลเครื่องมือที่อัปเดต
            $selectQuery = "SELECT * FROM tools WHERE tool_id = :id";
            $selectStmt = $db->prepare($selectQuery);
            $selectStmt->bindParam(':id', $tool_id);
            $selectStmt->execute();
            
            $updatedTool = $selectStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'message' => 'แก้ไขข้อมูลเครื่องมือสำเร็จ',
                'data' => $updatedTool
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

function handleDelete($db) {
    try {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบ ID ของเครื่องมือที่ต้องการลบ']);
            return;
        }
        
        $id = intval($_GET['id']);
        
        // ตรวจสอบว่าเครื่องมือมีอยู่จริง
        $checkQuery = "SELECT tool_name FROM tools WHERE tool_id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        $tool = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$tool) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลเครื่องมือ']);
            return;
        }
        
        // ตรวจสอบว่าเครื่องมือถูกใช้งานในงานหรือไม่
        // เนื่องจาก tools ใน task เก็บเป็น JSON string เราต้องค้นหาใน JSON
        $usageCheckQuery = "SELECT COUNT(*) as count FROM task WHERE tools LIKE :tool_search";
        $usageCheckStmt = $db->prepare($usageCheckQuery);
        $tool_search = '%"' . $id . '"%'; // ค้นหา tool_id ใน JSON
        $usageCheckStmt->bindParam(':tool_search', $tool_search);
        $usageCheckStmt->execute();
        
        $usageCount = $usageCheckStmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($usageCount > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'ไม่สามารถลบเครื่องมือได้ เนื่องจากมีการใช้งานในงาน ' . $usageCount . ' งาน'
            ]);
            return;
        }
        
        // ลบเครื่องมือ
        $deleteQuery = "DELETE FROM tools WHERE tool_id = :id";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':id', $id);
        
        if ($deleteStmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'ลบเครื่องมือ "' . $tool['tool_name'] . '" สำเร็จ'
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

