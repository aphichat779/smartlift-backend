<?php
// api/work/tools.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

// ---------------- CORS & headers ----------------
CORSMiddleware::handle();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
header('Content-Type: application/json; charset=utf-8');

// ---------------- Bootstrap DB ------------------
$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ---------------- Helpers -----------------------
function jsonResponse(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/** รับค่า _method override */
function resolveMethod(string $method): string {
    if ($method === 'POST' && isset($_POST['_method'])) {
        $ov = strtoupper(trim((string)$_POST['_method']));
        if (in_array($ov, ['PUT', 'DELETE'], true)) return $ov;
    }
    return $method;
}

/** โฟลเดอร์/URL สำหรับเก็บรูป */
function uploadDir(): string {
    return realpath(__DIR__ . '/../../') . '/uploads/tools/';
}
function ensureUploadDir(): void {
    $dir = uploadDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}
function publicPathOf(string $filename): string {
    return '/uploads/tools/' . ltrim($filename, '/');
}

/** ตรวจว่ามีคอลัมน์ในตารางหรือไม่ (ใช้ INFORMATION_SCHEMA; เสถียรกว่า SHOW COLUMNS) */
function columnExists(PDO $db, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];

    $sql = "SELECT COUNT(*)
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :t
               AND COLUMN_NAME  = :c";
    $st = $db->prepare($sql);
    $st->execute([':t' => $table, ':c' => $column]);
    $cache[$key] = ((int)$st->fetchColumn() > 0);
    return $cache[$key];
}

/** ดึง 1 แถวของเครื่องมือ */
function fetchTool(PDO $db, int $id): ?array {
    $sql = "SELECT tool_id, tool_name, cost, " .
           (columnExists($db, 'tools', 'tool_img') ? "tool_img" : "NULL AS tool_img") .
           " FROM tools WHERE tool_id = :id LIMIT 1";
    $st = $db->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** bind ค่าอัตโนมัติตามชนิด (รองรับ NULL/INT/STRING) */
function pdoBindAuto(PDOStatement $st, string $name, $value): void {
    if ($value === null) {
        $st->bindValue($name, null, PDO::PARAM_NULL);
    } elseif (is_int($value)) {
        $st->bindValue($name, $value, PDO::PARAM_INT);
    } else {
        $st->bindValue($name, (string)$value, PDO::PARAM_STR);
    }
}

/** บันทึกรูปจาก $_FILES['tool_img'] */
function saveImage(array $file): ?string {
    if (empty($file['name']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    // ตรวจ MIME (ต้องเปิด extension=fileinfo ใน php.ini)
    if (!class_exists('finfo')) {
        jsonResponse(500, ['ok' => false, 'error' => 'server_config', 'message' => 'PHP fileinfo extension ไม่ได้เปิดใช้งาน']);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allow = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];
    if (!isset($allow[$mime])) {
        jsonResponse(422, ['ok' => false, 'error' => 'invalid_image_type', 'message' => 'รองรับเฉพาะ jpg/png/gif/webp']);
    }

    // จำกัดขนาดไฟล์ (5MB)
    $maxBytes = 5 * 1024 * 1024;
    if ((int)$file['size'] > $maxBytes) {
        jsonResponse(422, ['ok' => false, 'error' => 'image_too_large', 'message' => 'ขนาดไฟล์เกิน 5MB']);
    }

    ensureUploadDir();
    $ext  = $allow[$mime];
    $name = 'tool_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = uploadDir() . $name;

    if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(500, ['ok' => false, 'error' => 'upload_failed', 'message' => 'อัปโหลดรูปไม่สำเร็จ']);
    }

    return publicPathOf($name);
}

/** ลบไฟล์รูปเดิม (ถ้าอยู่ใต้ /uploads/tools เท่านั้น) */
function deleteImageIfLocal(?string $url): void {
    if (!$url) return;
    $url = (string)$url;
    if (strpos($url, '/uploads/tools/') !== 0) return; // ข้ามถ้าเป็น URL ภายนอก
    $abs = realpath(__DIR__ . '/../../') . $url;
    if (is_file($abs)) {
        @unlink($abs);
    }
}

/** อ่าน JSON body */
function readJson(): array {
    $raw  = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** ชื่อไม่ซ้ำ (case-insensitive) */
function isNameDuplicated(PDO $db, string $name, ?int $ignoreId = null): bool {
    $sql = "SELECT tool_id FROM tools WHERE LOWER(tool_name)=LOWER(:name)";
    $params = [':name' => $name];
    if ($ignoreId !== null) {
        $sql .= " AND tool_id <> :id";
        $params[':id'] = $ignoreId;
    }
    $sql .= " LIMIT 1";
    $st = $db->prepare($sql);
    $st->execute($params);
    return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

// ---------------- Resolve method (with override) -------------
$method = resolveMethod($method);

// ---------------- Routing -----------------------------------
try {
    switch ($method) {

        // ---------- GET /tools.php (list/search/by ids) ----------
        case 'GET': {
            $q        = isset($_GET['q'])   ? trim((string)$_GET['q']) : '';
            $idsParam = isset($_GET['ids']) ? trim((string)$_GET['ids']) : '';
            $page     = max(1, (int)($_GET['page'] ?? 1));
            $perPage  = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
            $offset   = ($page - 1) * $perPage;

            $where   = [];
            $params  = [];
            $idList  = [];

            if ($q !== '') {
                $where[]      = "tool_name LIKE :q";
                $params[':q'] = '%'.$q.'%';
            }

            if ($idsParam !== '') {
                foreach (explode(',', $idsParam) as $s) {
                    $v = (int)trim($s);
                    if ($v > 0) $idList[] = $v;
                }
                if ($idList) {
                    // ใช้ชื่อ placeholder แบบ :id0, :id1 เพื่อความชัดเจน
                    $phs = [];
                    foreach ($idList as $i => $_) $phs[] = ":id{$i}";
                    $where[] = "tool_id IN (".implode(',', $phs).")";
                    foreach ($idList as $i => $val) $params[":id{$i}"] = $val;
                }
            }

            $sqlBase = "FROM tools";
            if ($where) $sqlBase .= " WHERE " . implode(' AND ', $where);

            // count ทั้งหมด
            $stCount = $db->prepare("SELECT COUNT(*) ".$sqlBase);
            foreach ($params as $k => $v) {
                pdoBindAuto($stCount, $k, $v);
            }
            $stCount->execute();
            $total = (int)$stCount->fetchColumn();

            // query หลัก
            $sql = "SELECT tool_id, tool_name, cost, " .
                   (columnExists($db, 'tools', 'tool_img') ? "tool_img" : "NULL AS tool_img") .
                   " ".$sqlBase." ORDER BY tool_id DESC LIMIT :limit OFFSET :offset";

            $st = $db->prepare($sql);
            foreach ($params as $k => $v) {
                pdoBindAuto($st, $k, $v);
            }
            pdoBindAuto($st, ':limit',  $perPage);
            pdoBindAuto($st, ':offset', $offset);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(200, [
                'ok'   => true,
                'data' => $rows,
                'pagination'=> [
                    'page'       => $page,
                    'per_page'   => $perPage,
                    'total'      => $total,
                    'total_page' => (int)ceil($total / max(1, $perPage))
                ]
            ]);
        }

        // ---------- POST /tools.php (create + optional image) ----------
        case 'POST': {
            // สิทธิ์
            $user = requireAuth(['super_admin','admin']);

            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
            $body = $isMultipart ? [] : readJson();

            $tool_name = trim((string)($isMultipart ? ($_POST['tool_name'] ?? '') : ($body['tool_name'] ?? '')));
            $costRaw   = $isMultipart ? ($_POST['cost'] ?? null) : ($body['cost'] ?? null);

            if ($tool_name === '' || mb_strlen($tool_name) > 255) {
                jsonResponse(422, ['ok' => false, 'error' => 'validation', 'fields' => ['tool_name' => 'ต้องไม่ว่าง และยาวไม่เกิน 255 ตัวอักษร']]);
            }
            if (!is_numeric($costRaw) || (int)$costRaw < 0) {
                jsonResponse(422, ['ok' => false, 'error' => 'validation', 'fields' => ['cost' => 'ต้องเป็นจำนวนเต็ม 0 ขึ้นไป']]);
            }
            $cost = (int)$costRaw;

            if (isNameDuplicated($db, $tool_name, null)) {
                jsonResponse(409, ['ok' => false, 'error' => 'duplicated', 'message' => 'มีชื่อเครื่องมือนี้อยู่แล้ว']);
            }

            $hasToolImgColumn = columnExists($db, 'tools', 'tool_img');
            $imgUrl = null;

            if ($hasToolImgColumn) {
                if ($isMultipart && !empty($_FILES['tool_img']['name'])) {
                    // อัปโหลดไฟล์ใหม่
                    $imgUrl = saveImage($_FILES['tool_img']);
                } else {
                    // รองรับกรณีส่ง URL มาตรง ๆ
                    $imgField = $isMultipart ? ($_POST['tool_img'] ?? null) : ($body['tool_img'] ?? null);
                    if (is_string($imgField)) {
                        $imgField = trim($imgField);
                        if ($imgField !== '') $imgUrl = $imgField;
                    }
                }
            }

            $sql = "INSERT INTO tools (tool_name, cost" . ($hasToolImgColumn ? ", tool_img" : "") . ")
                    VALUES (:name, :cost" . ($hasToolImgColumn ? ", :img" : "") . ")";
            $st  = $db->prepare($sql);

            pdoBindAuto($st, ':name', $tool_name);
            pdoBindAuto($st, ':cost', $cost);
            if ($hasToolImgColumn) {
                pdoBindAuto($st, ':img', $imgUrl); // null → NULL จริง
            }
            $st->execute();
            $id = (int)$db->lastInsertId();

            jsonResponse(201, [
                'ok'   => true,
                'data' => [
                    'tool_id'   => $id,
                    'tool_name' => $tool_name,
                    'cost'      => $cost,
                    'tool_img'  => $imgUrl
                ],
                'message' => 'เพิ่มข้อมูลเครื่องมือสำเร็จ'
            ]);
        }

        // ---------- PUT /tools.php?id=XX (update; supports image replace) ----------
        case 'PUT': {
            $user = requireAuth(['super_admin','admin']);
            $id   = (int)($_GET['id'] ?? 0);
            if ($id <= 0) jsonResponse(400, ['ok' => false, 'error' => 'bad_request', 'message' => 'ต้องระบุ id']);

            $cur = fetchTool($db, $id);
            if (!$cur) jsonResponse(404, ['ok' => false, 'error' => 'not_found', 'message' => 'ไม่พบรายการ']);

            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
            $body = $isMultipart ? [] : readJson();

            $tool_name = null;
            $costRaw   = null;

            if ($isMultipart) {
                if (isset($_POST['tool_name'])) $tool_name = trim((string)$_POST['tool_name']);
                if (isset($_POST['cost']))      $costRaw   = $_POST['cost'];
            } else {
                if (array_key_exists('tool_name', $body)) $tool_name = trim((string)$body['tool_name']);
                if (array_key_exists('cost', $body))      $costRaw   = $body['cost'];
            }

            if ($tool_name !== null) {
                if ($tool_name === '' || mb_strlen($tool_name) > 255) {
                    jsonResponse(422, ['ok' => false, 'error' => 'validation', 'fields' => ['tool_name' => 'ต้องไม่ว่าง และยาวไม่เกิน 255 ตัวอักษร']]);
                }
                if (isNameDuplicated($db, $tool_name, $id)) {
                    jsonResponse(409, ['ok' => false, 'error' => 'duplicated', 'message' => 'มีชื่อเครื่องมือนี้อยู่แล้ว']);
                }
            }
            $cost = null;
            if ($costRaw !== null) {
                if (!is_numeric($costRaw) || (int)$costRaw < 0) {
                    jsonResponse(422, ['ok' => false, 'error' => 'validation', 'fields' => ['cost' => 'ต้องเป็นจำนวนเต็ม 0 ขึ้นไป']]);
                }
                $cost = (int)$costRaw;
            }

            $hasToolImgColumn = columnExists($db, 'tools', 'tool_img');
            $newImg = null;   // string|null ตั้งค่ารูปใหม่
            $remove = false;  // true → SET NULL

            if ($isMultipart && !empty($_FILES['tool_img']['name'])) {
                $newImg = saveImage($_FILES['tool_img']);
            } else {
                $imgField   = $isMultipart ? ($_POST['tool_img'] ?? null) : ($body['tool_img'] ?? null);
                $removeFlag = $isMultipart ? ($_POST['remove_image'] ?? null) : ($body['remove_image'] ?? null);

                if (is_string($imgField)) {
                    $imgField = trim($imgField);
                    if ($imgField !== '') $newImg = $imgField;
                }
                if ($removeFlag === '1' || $removeFlag === 1 || $removeFlag === true) {
                    $remove = true;
                    $newImg = ''; // สัญญาณให้ SET NULL
                }
            }

            $sets   = [];
            $params = [':id' => $id];

            if ($tool_name !== null) { $sets[] = "tool_name = :name"; $params[':name'] = $tool_name; }
            if ($cost !== null)      { $sets[] = "cost = :cost";     $params[':cost'] = $cost; }

            if ($hasToolImgColumn && ($newImg !== null || $remove)) {
                $sets[] = "tool_img = :img";
                $params[':img'] = ($newImg === '') ? null : $newImg;
            }

            if (!$sets) {
                jsonResponse(200, ['ok' => true, 'data' => $cur, 'message' => 'ไม่มีข้อมูลที่ต้องอัปเดต']);
            }

            $sql = "UPDATE tools SET " . implode(', ', $sets) . " WHERE tool_id = :id";
            $st  = $db->prepare($sql);
            foreach ($params as $k => $v) {
                pdoBindAuto($st, $k, $v);
            }
            $st->execute();

            // ลบไฟล์เก่าถ้าอัปโหลดใหม่และ path เปลี่ยน
            if (!empty($cur['tool_img']) && $newImg !== null && $newImg !== '' && $newImg !== $cur['tool_img']) {
                deleteImageIfLocal($cur['tool_img']);
            }

            $updated = fetchTool($db, $id);
            jsonResponse(200, ['ok' => true, 'data' => $updated, 'message' => 'อัปเดตข้อมูลสำเร็จ']);
        }

        // ---------- DELETE /tools.php?id=XX ----------
        case 'DELETE': {
            $user = requireAuth(['super_admin','admin']);
            $id   = (int)($_GET['id'] ?? 0);
            if ($id <= 0) jsonResponse(400, ['ok' => false, 'error' => 'bad_request', 'message' => 'ต้องระบุ id']);

            $cur = fetchTool($db, $id);
            if (!$cur) jsonResponse(404, ['ok' => false, 'error' => 'not_found', 'message' => 'ไม่พบรายการ']);

            $st = $db->prepare("DELETE FROM tools WHERE tool_id = :id");
            $st->execute([':id' => $id]);

            if (!empty($cur['tool_img'])) {
                deleteImageIfLocal($cur['tool_img']);
            }

            jsonResponse(200, ['ok' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
        }

        default:
            header('Allow: GET, POST, PUT, DELETE, OPTIONS');
            jsonResponse(405, ['ok' => false, 'error' => 'method_not_allowed', 'message' => 'รองรับเฉพาะ GET, POST, PUT, DELETE']);
    }
} catch (PDOException $e) {
    jsonResponse(500, [
        'ok' => false,
        'error' => 'db_error',
        'code' => $e->getCode(),
        'message' => 'ข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage()
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'ok' => false,
        'error' => 'server_error',
        'message' => 'ข้อผิดพลาดภายในเซิร์ฟเวอร์ที่ไม่คาดคิด: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()
    ]);
}
