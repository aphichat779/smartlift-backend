<?php
// api/work/reports.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

// Handle CORS
CORSMiddleware::handle();

// Preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
            handleDelete($db, $user);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: '.$e->getMessage()]);
}

function handleGet(PDO $db) {
    try {
        // ---------- OPTIONS MODE (for selects) ----------
        if (isset($_GET['options']) && $_GET['options'] === '1') {
            $type = $_GET['type'] ?? '';

            if ($type === 'orgs') {
                $sql = "SELECT id, org_name FROM organizations ORDER BY org_name ASC";
                $st  = $db->prepare($sql);
                $st->execute();
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => ['orgs' => $rows]]);
                return;
            }

            if ($type === 'buildings') {
                if (empty($_GET['org_id'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'org_id is required']);
                    return;
                }
                $org_id = (int)$_GET['org_id'];
                $sql = "SELECT id, building_name FROM buildings WHERE org_id = :org_id ORDER BY building_name ASC";
                $st  = $db->prepare($sql);
                $st->bindParam(':org_id', $org_id, PDO::PARAM_INT);
                $st->execute();
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => ['buildings' => $rows]]);
                return;
            }

            if ($type === 'lifts') {
                if (empty($_GET['building_id'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'building_id is required']);
                    return;
                }
                $building_id = (int)$_GET['building_id'];
                $sql = "SELECT id, lift_name FROM lifts WHERE building_id = :bid ORDER BY lift_name ASC";
                $st  = $db->prepare($sql);
                $st->bindParam(':bid', $building_id, PDO::PARAM_INT);
                $st->execute();
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => ['lifts' => $rows]]);
                return;
            }

            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown options type']);
            return;
        }
        // ---------- END OPTIONS MODE ----------

        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $query = "SELECT r.*,
                             o.org_name,
                             b.building_name,
                             l.lift_name,
                             u.first_name, u.last_name
                      FROM report r
                      LEFT JOIN organizations o ON r.org_id = o.id
                      LEFT JOIN buildings b     ON r.building_id = b.id
                      LEFT JOIN lifts l         ON r.lift_id = l.id
                      LEFT JOIN users u         ON r.user_id = u.id
                      WHERE r.rp_id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($report) {
                $report['reporter_name'] = trim(($report['first_name'] ?? '').' '.($report['last_name'] ?? ''));
                echo json_encode(['success' => true, 'data' => $report]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรายงาน']);
            }
            return;
        }

        if (isset($_GET['org_id'])) {
            $org_id = (int)$_GET['org_id'];
            $query = "SELECT r.*,
                             o.org_name,
                             b.building_name,
                             l.lift_name,
                             u.first_name, u.last_name
                      FROM report r
                      LEFT JOIN organizations o ON r.org_id = o.id
                      LEFT JOIN buildings b     ON r.building_id = b.id
                      LEFT JOIN lifts l         ON r.lift_id = l.id
                      LEFT JOIN users u         ON r.user_id = u.id
                      WHERE r.org_id = :org_id
                      ORDER BY r.date_rp DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':org_id', $org_id, PDO::PARAM_INT);
            $stmt->execute();
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($reports as &$r) {
                $r['reporter_name'] = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
            }
            echo json_encode(['success' => true, 'data' => $reports]);
            return;
        }

        // default: list all
        $query = "SELECT r.*,
                         o.org_name,
                         b.building_name,
                         l.lift_name,
                         u.first_name, u.last_name
                  FROM report r
                  LEFT JOIN organizations o ON r.org_id = o.id
                  LEFT JOIN buildings b     ON r.building_id = b.id
                  LEFT JOIN lifts l         ON r.lift_id = l.id
                  LEFT JOIN users u         ON r.user_id = u.id
                  ORDER BY r.date_rp DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reports as &$r) {
            $r['reporter_name'] = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
        }
        echo json_encode(['success' => true, 'data' => $reports]);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: '.$e->getMessage()]);
    }
}

function handlePost(PDO $db, array $user) {
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($input['org_id']) || empty($input['building_id']) ||
            empty($input['lift_id']) || empty(trim((string)$input['detail']))) {
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

        $org_id      = (int)$input['org_id'];
        $building_id = (int)$input['building_id'];
        $lift_id     = (int)$input['lift_id'];
        $detail      = trim((string)$input['detail']);
        $date_rp     = !empty($input['date_rp']) ? $input['date_rp'] : date('Y-m-d');

        // validate lift exists (and optionally relationship)
        $checkQuery = "SELECT l.id, l.building_id, b.org_id
                       FROM lifts l
                       LEFT JOIN buildings b ON b.id = l.building_id
                       WHERE l.id = :lift_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':lift_id', $lift_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $liftRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$liftRow) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ข้อมูลลิฟต์ไม่ถูกต้อง']);
            return;
        }
        // optional: relationship guard
        if ((int)$liftRow['building_id'] !== $building_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ลิฟต์ไม่อยู่ในอาคารที่เลือก']);
            return;
        }
        if ((int)$liftRow['org_id'] !== $org_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'อาคารไม่อยู่ในองค์กรที่เลือก']);
            return;
        }

        $ins = "INSERT INTO report (date_rp, user_id, org_id, building_id, lift_id, detail)
                VALUES (:date_rp, :user_id, :org_id, :building_id, :lift_id, :detail)";
        $stmt = $db->prepare($ins);
        $stmt->bindParam(':date_rp', $date_rp);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':org_id', $org_id, PDO::PARAM_INT);
        $stmt->bindParam(':building_id', $building_id, PDO::PARAM_INT);
        $stmt->bindParam(':lift_id', $lift_id, PDO::PARAM_INT);
        $stmt->bindParam(':detail', $detail);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเพิ่มข้อมูล']);
            return;
        }

        $report_id = (int)$db->lastInsertId();
        $select = "SELECT r.*,
                          o.org_name,
                          b.building_name,
                          l.lift_name,
                          u.first_name, u.last_name
                   FROM report r
                   LEFT JOIN organizations o ON r.org_id = o.id
                   LEFT JOIN buildings b     ON r.building_id = b.id
                   LEFT JOIN lifts l         ON r.lift_id = l.id
                   LEFT JOIN users u         ON r.user_id = u.id
                   WHERE r.rp_id = :id";
        $st2 = $db->prepare($select);
        $st2->bindParam(':id', $report_id, PDO::PARAM_INT);
        $st2->execute();
        $newReport = $st2->fetch(PDO::FETCH_ASSOC);
        if ($newReport) {
            $newReport['reporter_name'] = trim(($newReport['first_name'] ?? '').' '.($newReport['last_name'] ?? ''));
        }

        echo json_encode(['success' => true, 'message' => 'เพิ่มรายงานใหม่สำเร็จ', 'data' => $newReport]);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเพิ่มข้อมูล: '.$e->getMessage()]);
    }
}

function handlePut(PDO $db, array $user) {
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($input['rp_id']) || empty($input['org_id']) ||
            empty($input['building_id']) || empty($input['lift_id']) ||
            empty(trim((string)$input['detail']))) {
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

        $rp_id       = (int)$input['rp_id'];
        $org_id      = (int)$input['org_id'];
        $building_id = (int)$input['building_id'];
        $lift_id     = (int)$input['lift_id'];
        $detail      = trim((string)$input['detail']);
        $date_rp     = !empty($input['date_rp']) ? $input['date_rp'] : date('Y-m-d');

        $checkQuery = "SELECT user_id FROM report WHERE rp_id = :rp_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':rp_id', $rp_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $existingReport = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingReport) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรายงาน']);
            return;
        }

        if ((int)$existingReport['user_id'] !== (int)$user_id &&
            !in_array(($user['role'] ?? ''), ['admin', 'technician'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์แก้ไขรายงานนี้']);
            return;
        }

        // relationship validation (เหมือนใน POST)
        $rel = "SELECT l.id, l.building_id, b.org_id
                FROM lifts l
                LEFT JOIN buildings b ON b.id = l.building_id
                WHERE l.id = :lift_id";
        $relSt = $db->prepare($rel);
        $relSt->bindParam(':lift_id', $lift_id, PDO::PARAM_INT);
        $relSt->execute();
        $liftRow = $relSt->fetch(PDO::FETCH_ASSOC);
        if (!$liftRow || (int)$liftRow['building_id'] !== $building_id || (int)$liftRow['org_id'] !== $org_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ความสัมพันธ์ของ องค์กร/อาคาร/ลิฟต์ ไม่ถูกต้อง']);
            return;
        }

        $upd = "UPDATE report
                SET date_rp = :date_rp,
                    org_id = :org_id,
                    building_id = :building_id,
                    lift_id = :lift_id,
                    detail = :detail
                WHERE rp_id = :rp_id";
        $stmt = $db->prepare($upd);
        $stmt->bindParam(':rp_id', $rp_id, PDO::PARAM_INT);
        $stmt->bindParam(':date_rp', $date_rp);
        $stmt->bindParam(':org_id', $org_id, PDO::PARAM_INT);
        $stmt->bindParam(':building_id', $building_id, PDO::PARAM_INT);
        $stmt->bindParam(':lift_id', $lift_id, PDO::PARAM_INT);
        $stmt->bindParam(':detail', $detail);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล']);
            return;
        }

        $select = "SELECT r.*,
                          o.org_name,
                          b.building_name,
                          l.lift_name,
                          u.first_name, u.last_name
                   FROM report r
                   LEFT JOIN organizations o ON r.org_id = o.id
                   LEFT JOIN buildings b     ON r.building_id = b.id
                   LEFT JOIN lifts l         ON r.lift_id = l.id
                   LEFT JOIN users u         ON r.user_id = u.id
                   WHERE r.rp_id = :id";
        $st2 = $db->prepare($select);
        $st2->bindParam(':id', $rp_id, PDO::PARAM_INT);
        $st2->execute();
        $updated = $st2->fetch(PDO::FETCH_ASSOC);
        if ($updated) {
            $updated['reporter_name'] = trim(($updated['first_name'] ?? '').' '.($updated['last_name'] ?? ''));
        }

        echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลรายงานสำเร็จ', 'data' => $updated]);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล: '.$e->getMessage()]);
    }
}

function handleDelete(PDO $db, array $user) {
    try {
        if (empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบ ID ของรายงานที่ต้องการลบ']);
            return;
        }

        $id = (int)$_GET['id'];

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

        $user_id = $user['id'] ?? $user['user_id'] ?? $user['sub'] ?? null;
        if ((int)$existingReport['user_id'] !== (int)$user_id &&
            !in_array(($user['role'] ?? ''), ['admin', 'technician'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ลบรายงานนี้']);
            return;
        }

        // block delete if linked tasks exist
        $taskCheckQuery = "SELECT COUNT(*) AS cnt FROM task WHERE rp_id = :rp_id";
        $taskCheckStmt  = $db->prepare($taskCheckQuery);
        $taskCheckStmt->bindParam(':rp_id', $id, PDO::PARAM_INT);
        $taskCheckStmt->execute();
        $taskCount = (int)($taskCheckStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        if ($taskCount > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบรายงานได้ เนื่องจากมีงาน '.$taskCount.' งานที่เกี่ยวข้อง']);
            return;
        }

        $del = "DELETE FROM report WHERE rp_id = :id";
        $st  = $db->prepare($del);
        $st->bindParam(':id', $id, PDO::PARAM_INT);
        if ($st->execute()) {
            echo json_encode(['success' => true, 'message' => 'ลบรายงานสำเร็จ']);
            return;
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล']);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล: '.$e->getMessage()]);
    }
}
