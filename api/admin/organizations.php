<?php
// api/admin/organizations.php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Organization.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';

CORSMiddleware::handle();

try {
    $authUser = AuthMiddleware::requireAdmin();
    if (!$authUser) { exit; }

    $database = new Database();
    $db = $database->getConnection();
    $org = new Organization($db);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // /api/admin/organizations.php?id=...  -> get single
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $row = $org->getById($id);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'ไม่พบองค์กร']);
                exit;
            }
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $row]);
            exit;
        }

        // list with pagination & search
        $page  = isset($_GET['page'])  ? max(1, (int)$_GET['page'])  : 1;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
        $offset = ($page - 1) * $limit;
        $q = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

        $rows = $org->getAll($limit, $offset, $q);
        $total = $org->countAll($q);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total
            ]
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->action)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบ: ต้องมี action']);
            exit;
        }

        $action = (string)$data->action;

        if ($action === 'create') {
            if (!isset($data->org_name) || !ValidationHelper::minMaxLength($data->org_name, 1, 100)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'org_name ไม่ถูกต้อง']);
                exit;
            }
            $orgName = trim($data->org_name);
            $desc = isset($data->description) ? trim((string)$data->description) : null;

            $newId = $org->create($orgName, $desc, (int)$authUser['id']);
            if ($newId === null) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'มีชื่อองค์กรนี้อยู่แล้ว']);
                exit;
            }
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'สร้างองค์กรสำเร็จ', 'id' => $newId]);
            exit;
        }

        if ($action === 'update') {
            if (!isset($data->id) || !is_numeric($data->id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'id ไม่ถูกต้อง']);
                exit;
            }
            if (!isset($data->org_name) || !ValidationHelper::minMaxLength($data->org_name, 1, 100)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'org_name ไม่ถูกต้อง']);
                exit;
            }
            $id = (int)$data->id;
            $orgName = trim($data->org_name);
            $desc = isset($data->description) ? trim((string)$data->description) : null;

            $ok = $org->update($id, $orgName, $desc, (int)$authUser['id']);
            if (!$ok) {
                // อาจเพราะชื่อซ้ำ
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'อัปเดตไม่สำเร็จ (อาจชื่อซ้ำ)']);
                exit;
            }
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'อัปเดตองค์กรสำเร็จ']);
            exit;
        }

        if ($action === 'delete') {
            if (!isset($data->id) || !is_numeric($data->id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'id ไม่ถูกต้อง']);
                exit;
            }
            $id = (int)$data->id;

            // ถ้ามี FK ที่เกี่ยวข้อง อาจลบไม่ผ่าน -> แนะนำใช้ soft delete ตามที่คุยกัน
            $ok = $org->delete($id);
            if (!$ok) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'ลบไม่สำเร็จ (อาจมีข้อมูลเชื่อมโยง)']);
                exit;
            }
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'ลบองค์กรสำเร็จ']);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'action ไม่ถูกต้อง']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'SERVER_ERROR'
    ]);
}
