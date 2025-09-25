<?php
// api/work/tasks.php
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
            handlePost($db, $user);
            break;
        case 'PUT':
            $user = AuthMiddleware::authenticate();
            handlePut($db, $user);
            break;
        case 'DELETE':
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

/**
 * Handles GET requests to retrieve task data.
 * Supports fetching a single task by ID, tasks for a specific user, tasks by status, or all tasks.
 * @param PDO $db The database connection.
 */
function handleGet($db) {
    try {
        $query = "SELECT t.*, 
                         r.detail as report_detail, r.date_rp,
                         u1.first_name as assignee_first_name, u1.last_name as assignee_last_name,
                         u2.first_name as maintainer_first_name, u2.last_name as maintainer_last_name
                  FROM task t 
                  LEFT JOIN report r ON t.rp_id = r.rp_id 
                  LEFT JOIN users u1 ON t.user_id = u1.id
                  LEFT JOIN users u2 ON t.mainten_id = u2.id
                  ";
        
        $params = [];
        $condition = '';
        
        if (isset($_GET['id'])) {
            $condition = "WHERE t.tk_id = :id";
            $params[':id'] = intval($_GET['id']);
        } elseif (isset($_GET['user_id'])) {
            $condition = "WHERE t.user_id = :user_id OR t.mainten_id = :user_id";
            $params[':user_id'] = intval($_GET['user_id']);
        } elseif (isset($_GET['status'])) {
            $condition = "WHERE t.tk_status = :status";
            $params[':status'] = $_GET['status'];
        }

        $query .= $condition . " ORDER BY t.task_start_date DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        if (isset($_GET['id'])) {
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($task) {
                processTaskData($task);
                echo json_encode(['success' => true, 'data' => $task]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลงาน']);
            }
        } else {
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tasks as &$task) {
                processTaskData($task);
            }
            echo json_encode(['success' => true, 'data' => $tasks]);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()
        ]);
    }
}

/**
 * Helper function to process task data before sending to the client.
 * @param array $task The task array to process.
 */
function processTaskData(&$task) {
    $task['assignee_name'] = trim($task['assignee_first_name'] . ' ' . $task['assignee_last_name']);
    $task['maintainer_name'] = trim($task['maintainer_first_name'] . ' ' . $task['maintainer_last_name']);
    
    // ตรวจสอบและแปลง tools จาก JSON string เป็น array
    $toolsData = json_decode($task['tools'], true);
    $task['tools_array'] = is_array($toolsData) ? $toolsData : [];
}

/**
 * Handles POST requests to create a new task.
 * @param PDO $db The database connection.
 * @param array $user The authenticated user.
 */
function handlePost($db, $user) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!in_array($user['role'], ['admin', 'technician'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ในการดำเนินการนี้']);
            return;
        }

        if (empty($input['rp_id']) || empty($input['tk_data']) || 
            empty($input['user_id']) || empty($input['mainten_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            return;
        }
        
        $rp_id = intval($input['rp_id']);
        $tk_status = isset($input['tk_status']) ? $input['tk_status'] : '1';
        $tk_data = trim($input['tk_data']);
        $task_start_date = isset($input['task_start_date']) ? $input['task_start_date'] : date('Y-m-d H:i:s');
        $assigned_user_id = intval($input['user_id']);
        $mainten_id = intval($input['mainten_id']);
        $tools = isset($input['tools']) ? json_encode($input['tools']) : '[]';
        
        $reportQuery = "SELECT org_name, building_name, lift_id FROM report WHERE rp_id = :rp_id";
        $reportStmt = $db->prepare($reportQuery);
        $reportStmt->bindParam(':rp_id', $rp_id);
        $reportStmt->execute();
        
        $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
        if (!$report) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรายงาน']);
            return;
        }
        
        $userQuery = "SELECT first_name, last_name FROM users WHERE id = :user_id";
        $userStmt = $db->prepare($userQuery);
        $userStmt->bindParam(':user_id', $assigned_user_id);
        $userStmt->execute();
        
        $assignedUser = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$assignedUser) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้ที่ได้รับมอบหมาย']);
            return;
        }
        
        $user_name = trim($assignedUser['first_name'] . ' ' . $assignedUser['last_name']);
        
        $query = "INSERT INTO task (tk_status, tk_data, task_start_date, rp_id, user_id, user, 
                                     mainten_id, org_name, building_name, lift_id, tools) 
                  VALUES (:tk_status, :tk_data, :task_start_date, :rp_id, :user_id, :user, 
                          :mainten_id, :org_name, :building_name, :lift_id, :tools)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':tk_status', $tk_status);
        $stmt->bindParam(':tk_data', $tk_data);
        $stmt->bindParam(':task_start_date', $task_start_date);
        $stmt->bindParam(':rp_id', $rp_id);
        $stmt->bindParam(':user_id', $assigned_user_id);
        $stmt->bindParam(':user', $user_name);
        $stmt->bindParam(':mainten_id', $mainten_id);
        $stmt->bindParam(':org_name', $report['org_name']);
        $stmt->bindParam(':building_name', $report['building_name']);
        $stmt->bindParam(':lift_id', $report['lift_id']);
        $stmt->bindParam(':tools', $tools);
        
        if ($stmt->execute()) {
            $task_id = $db->lastInsertId();
            
            $selectQuery = "SELECT t.*, 
                                     r.detail as report_detail, r.date_rp,
                                     u1.first_name as assignee_first_name, u1.last_name as assignee_last_name,
                                     u2.first_name as maintainer_first_name, u2.last_name as maintainer_last_name
                            FROM task t 
                            LEFT JOIN report r ON t.rp_id = r.rp_id 
                            LEFT JOIN users u1 ON t.user_id = u1.id
                            LEFT JOIN users u2 ON t.mainten_id = u2.id
                            WHERE t.tk_id = :id";
            $selectStmt = $db->prepare($selectQuery);
            $selectStmt->bindParam(':id', $task_id);
            $selectStmt->execute();
            
            $newTask = $selectStmt->fetch(PDO::FETCH_ASSOC);
            if ($newTask) {
                processTaskData($newTask);
                echo json_encode([
                    'success' => true, 
                    'message' => 'เพิ่มงานใหม่สำเร็จ',
                    'data' => $newTask
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'เพิ่มงานสำเร็จ แต่ไม่สามารถดึงข้อมูลที่สร้างได้']);
            }
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

/**
 * Handles PUT requests to update an existing task.
 * @param PDO $db The database connection.
 * @param array $user The authenticated user.
 */
function handlePut($db, $user) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!in_array($user['role'], ['admin', 'technician'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ในการดำเนินการนี้']);
            return;
        }

        if (empty($input['tk_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบ ID ของงาน']);
            return;
        }
        
        $tk_id = intval($input['tk_id']);
        
        $checkQuery = "SELECT * FROM task WHERE tk_id = :tk_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':tk_id', $tk_id);
        $checkStmt->execute();
        
        $existingTask = $checkStmt->fetch();
        if (!$existingTask) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลงาน']);
            return;
        }
        
        $updateFields = [];
        $params = [':tk_id' => $tk_id];
        
        if (isset($input['tk_status'])) {
            $updateFields[] = "tk_status = :tk_status";
            $params[':tk_status'] = $input['tk_status'];
        }
        
        if (isset($input['tk_data'])) {
            $updateFields[] = "tk_data = :tk_data";
            $params[':tk_data'] = trim($input['tk_data']);
        }
        
        if (isset($input['task_start_date'])) {
            $updateFields[] = "task_start_date = :task_start_date";
            $params[':task_start_date'] = $input['task_start_date'];
        }
        
        if (isset($input['user_id'])) {
            $updateFields[] = "user_id = :user_id";
            $params[':user_id'] = intval($input['user_id']);
            
            $userQuery = "SELECT first_name, last_name FROM users WHERE id = :user_id";
            $userStmt = $db->prepare($userQuery);
            $userStmt->bindParam(':user_id', $params[':user_id']);
            $userStmt->execute();
            
            $assignedUser = $userStmt->fetch(PDO::FETCH_ASSOC);
            if ($assignedUser) {
                $updateFields[] = "user = :user";
                $params[':user'] = trim($assignedUser['first_name'] . ' ' . $assignedUser['last_name']);
            }
        }
        
        if (isset($input['mainten_id'])) {
            $updateFields[] = "mainten_id = :mainten_id";
            $params[':mainten_id'] = intval($input['mainten_id']);
        }
        
        if (isset($input['tools'])) {
            $updateFields[] = "tools = :tools";
            $params[':tools'] = json_encode($input['tools']);
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่มีข้อมูลที่ต้องการอัปเดต']);
            return;
        }
        
        $query = "UPDATE task SET " . implode(', ', $updateFields) . " WHERE tk_id = :tk_id";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute($params)) {
            $selectQuery = "SELECT t.*, 
                                     r.detail as report_detail, r.date_rp,
                                     u1.first_name as assignee_first_name, u1.last_name as assignee_last_name,
                                     u2.first_name as maintainer_first_name, u2.last_name as maintainer_last_name
                            FROM task t 
                            LEFT JOIN report r ON t.rp_id = r.rp_id 
                            LEFT JOIN users u1 ON t.user_id = u1.id
                            LEFT JOIN users u2 ON t.mainten_id = u2.id
                            WHERE t.tk_id = :id";
            $selectStmt = $db->prepare($selectQuery);
            $selectStmt->bindParam(':id', $tk_id);
            $selectStmt->execute();
            
            $updatedTask = $selectStmt->fetch(PDO::FETCH_ASSOC);
            if ($updatedTask) {
                processTaskData($updatedTask);
                echo json_encode([
                    'success' => true, 
                    'message' => 'แก้ไขข้อมูลงานสำเร็จ',
                    'data' => $updatedTask
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'แก้ไขงานสำเร็จ แต่ไม่สามารถดึงข้อมูลที่อัปเดตได้']);
            }
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

/**
 * Handles DELETE requests to delete a task.
 * @param PDO $db The database connection.
 * @param array $user The authenticated user.
 */
function handleDelete($db) {
    try {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบ ID ของงานที่ต้องการลบ']);
            return;
        }
        
        $id = intval($_GET['id']);
        
        $checkQuery = "SELECT tk_data FROM task WHERE tk_id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        $task = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลงาน']);
            return;
        }
        
        $relatedCheckQuery = "SELECT 
                                     (SELECT COUNT(*) FROM task_status WHERE tk_id = :tk_id) as status_count,
                                     (SELECT COUNT(*) FROM work WHERE tk_id = :tk_id) as work_count";
        $relatedCheckStmt = $db->prepare($relatedCheckQuery);
        $relatedCheckStmt->bindParam(':tk_id', $id);
        $relatedCheckStmt->execute();
        
        $relatedData = $relatedCheckStmt->fetch(PDO::FETCH_ASSOC);
        if ($relatedData['status_count'] > 0 || $relatedData['work_count'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'ไม่สามารถลบงานได้ เนื่องจากมีข้อมูลที่เกี่ยวข้อง'
            ]);
            return;
        }
        
        $deleteQuery = "DELETE FROM task WHERE tk_id = :id";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':id', $id);
        
        if ($deleteStmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'ลบงานสำเร็จ'
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