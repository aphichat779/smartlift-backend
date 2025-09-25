<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

// Enable CORS
CORSMiddleware::handle();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db, $input);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function handleGet($db) {
    if (isset($_GET['lift_id'])) {
        // ดึงข้อมูลลิฟต์สำหรับสร้าง QR Code
        getLiftInfo($db, $_GET['lift_id']);
    } elseif (isset($_GET['qr_code'])) {
        // ตรวจสอบ QR Code และดึงข้อมูลลิฟต์
        validateQRCode($db, $_GET['qr_code']);
    } elseif (isset($_GET['generate_all'])) {
        // สร้าง QR Code สำหรับลิฟต์ทั้งหมด
        generateAllQRCodes($db);
    } else {
        // ดึงรายการ QR Code ทั้งหมด
        getAllQRCodes($db);
    }
}

function handlePost($db, $input) {
    // ตรวจสอบ authentication สำหรับการสร้าง QR Code
    $user = AuthMiddleware::authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        return;
    }

    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'generate_qr':
                generateQRCode($db, $input, $user);
                break;
            case 'scan_report':
                handleQRScanReport($db, $input, $user);
                break;
            case 'scan_task_update':
                handleQRTaskUpdate($db, $input, $user);
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                break;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action required']);
    }
}

function getLiftInfo($db, $liftId) {
    try {
        $query = "SELECT l.*, o.org_name, b.building_name 
                  FROM lifts l 
                  LEFT JOIN organizations o ON l.org_id = o.id 
                  LEFT JOIN building b ON l.building_id = b.id 
                  WHERE l.id = :lift_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':lift_id', $liftId);
        $stmt->execute();
        
        $lift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lift) {
            // สร้าง QR Code data
            $qrData = [
                'type' => 'elevator',
                'lift_id' => $lift['id'],
                'org_id' => $lift['org_id'],
                'building_id' => $lift['building_id'],
                'timestamp' => time(),
                'hash' => generateQRHash($lift['id'])
            ];
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'lift' => $lift,
                    'qr_data' => base64_encode(json_encode($qrData)),
                    'qr_url' => generateQRCodeURL($qrData)
                ]
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Lift not found']);
        }
    } catch (Exception $e) {
        throw $e;
    }
}

function validateQRCode($db, $qrCode) {
    try {
        // Decode QR Code data
        $qrData = json_decode(base64_decode($qrCode), true);
        
        if (!$qrData || !isset($qrData['lift_id']) || !isset($qrData['hash'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid QR Code format']);
            return;
        }
        
        // ตรวจสอบ hash
        $expectedHash = generateQRHash($qrData['lift_id']);
        if ($qrData['hash'] !== $expectedHash) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid QR Code']);
            return;
        }
        
        // ดึงข้อมูลลิฟต์
        $query = "SELECT l.*, o.org_name, b.building_name 
                  FROM lifts l 
                  LEFT JOIN organizations o ON l.org_id = o.id 
                  LEFT JOIN building b ON l.building_id = b.id 
                  WHERE l.id = :lift_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':lift_id', $qrData['lift_id']);
        $stmt->execute();
        
        $lift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lift) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'lift' => $lift,
                    'qr_data' => $qrData,
                    'valid' => true
                ]
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Lift not found']);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid QR Code data']);
    }
}

function generateAllQRCodes($db) {
    try {
        $query = "SELECT l.*, o.org_name, b.building_name 
                  FROM lifts l 
                  LEFT JOIN organizations o ON l.org_id = o.id 
                  LEFT JOIN building b ON l.building_id = b.id 
                  ORDER BY o.org_name, b.building_name, l.lift_name";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $lifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $qrCodes = [];
        
        foreach ($lifts as $lift) {
            $qrData = [
                'type' => 'elevator',
                'lift_id' => $lift['id'],
                'org_id' => $lift['org_id'],
                'building_id' => $lift['building_id'],
                'timestamp' => time(),
                'hash' => generateQRHash($lift['id'])
            ];
            
            $qrCodes[] = [
                'lift' => $lift,
                'qr_data' => base64_encode(json_encode($qrData)),
                'qr_url' => generateQRCodeURL($qrData)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $qrCodes
        ]);
    } catch (Exception $e) {
        throw $e;
    }
}

function getAllQRCodes($db) {
    try {
        $query = "SELECT l.id, l.lift_name, o.org_name, b.building_name 
                  FROM lifts l 
                  LEFT JOIN organizations o ON l.org_id = o.id 
                  LEFT JOIN building b ON l.building_id = b.id 
                  ORDER BY o.org_name, b.building_name, l.lift_name";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $lifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $lifts
        ]);
    } catch (Exception $e) {
        throw $e;
    }
}

function generateQRCode($db, $input, $user) {
    try {
        if (!isset($input['lift_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Lift ID required']);
            return;
        }
        
        // ตรวจสอบว่าลิฟต์มีอยู่จริง
        $query = "SELECT * FROM lifts WHERE id = :lift_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':lift_id', $input['lift_id']);
        $stmt->execute();
        
        $lift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lift) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Lift not found']);
            return;
        }
        
        // สร้าง QR Code data
        $qrData = [
            'type' => 'elevator',
            'lift_id' => $lift['id'],
            'org_id' => $lift['org_id'],
            'building_id' => $lift['building_id'],
            'timestamp' => time(),
            'hash' => generateQRHash($lift['id'])
        ];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'qr_data' => base64_encode(json_encode($qrData)),
                'qr_url' => generateQRCodeURL($qrData),
                'lift' => $lift
            ],
            'message' => 'QR Code generated successfully'
        ]);
    } catch (Exception $e) {
        throw $e;
    }
}

function handleQRScanReport($db, $input, $user) {
    try {
        if (!isset($input['qr_code']) || !isset($input['detail'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'QR Code and detail required']);
            return;
        }
        
        // ตรวจสอบ QR Code
        $qrData = json_decode(base64_decode($input['qr_code']), true);
        
        if (!$qrData || !isset($qrData['lift_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid QR Code']);
            return;
        }
        
        // ดึงข้อมูลลิฟต์
        $query = "SELECT * FROM lifts WHERE id = :lift_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':lift_id', $qrData['lift_id']);
        $stmt->execute();
        
        $lift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lift) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Lift not found']);
            return;
        }
        
        // สร้างรายงานปัญหาใหม่
        $insertQuery = "INSERT INTO report (org_id, building_id, lift_id, detail, date_rp, reporter_id) 
                        VALUES (:org_id, :building_id, :lift_id, :detail, :date_rp, :reporter_id)";
        
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':org_id', $lift['org_id']);
        $insertStmt->bindParam(':building_id', $lift['building_id']);
        $insertStmt->bindParam(':lift_id', $lift['id']);
        $insertStmt->bindParam(':detail', $input['detail']);
        $insertStmt->bindParam(':date_rp', date('Y-m-d H:i:s'));
        $insertStmt->bindParam(':reporter_id', $user['id']);
        
        if ($insertStmt->execute()) {
            $reportId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'report_id' => $reportId,
                    'lift' => $lift
                ],
                'message' => 'Report created successfully via QR scan'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create report']);
        }
    } catch (Exception $e) {
        throw $e;
    }
}

function handleQRTaskUpdate($db, $input, $user) {
    try {
        if (!isset($input['qr_code']) || !isset($input['task_id']) || !isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'QR Code, task ID and status required']);
            return;
        }
        
        // ตรวจสอบ QR Code
        $qrData = json_decode(base64_decode($input['qr_code']), true);
        
        if (!$qrData || !isset($qrData['lift_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid QR Code']);
            return;
        }
        
        // ตรวจสอบงาน
        $query = "SELECT t.*, r.lift_id FROM task t 
                  LEFT JOIN report r ON t.rp_id = r.rp_id 
                  WHERE t.tk_id = :task_id AND r.lift_id = :lift_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':task_id', $input['task_id']);
        $stmt->bindParam(':lift_id', $qrData['lift_id']);
        $stmt->execute();
        
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Task not found or not related to this lift']);
            return;
        }
        
        // อัปเดตสถานะงาน
        $updateQuery = "UPDATE task SET tk_status = :status, updated_at = :updated_at 
                        WHERE tk_id = :task_id";
        
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':status', $input['status']);
        $updateStmt->bindParam(':updated_at', date('Y-m-d H:i:s'));
        $updateStmt->bindParam(':task_id', $input['task_id']);
        
        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'task_id' => $input['task_id'],
                    'new_status' => $input['status']
                ],
                'message' => 'Task status updated successfully via QR scan'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update task status']);
        }
    } catch (Exception $e) {
        throw $e;
    }
}

function generateQRHash($liftId) {
    // สร้าง hash สำหรับความปลอดภัย
    $secret = 'elevator_qr_secret_key_2024'; // ควรเก็บใน config
    return hash('sha256', $liftId . $secret);
}

function generateQRCodeURL($qrData) {
    // สร้าง URL สำหรับ QR Code โดยใช้ Google Charts API
    $qrContent = base64_encode(json_encode($qrData));
    $size = '200x200';
    return "https://chart.googleapis.com/chart?chs={$size}&cht=qr&chl=" . urlencode($qrContent);
}
?>

