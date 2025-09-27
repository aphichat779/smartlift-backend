<?php
/**
 * api/dashboard.php
 *
 * Role-aware dashboard summary API
 * - admin: global view
 * - org_admin: org-scoped view
 * - technician: org-scoped technician widgets
 * - user: org-scoped basic widgets
 */

declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

// ----- Includes (ตามโครงโปรเจกต์ของคุณ) -----
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
// (ไม่จำเป็นต้องใช้ models/utils อื่น ๆ ใน endpoint นี้ หากไม่ได้อ้างถึง)

// ----- CORS -----
if (function_exists('handleCORS')) {
  handleCORS(['GET', 'OPTIONS']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// ----- PDO Connection -----
// รองรับทั้งรูปแบบที่ database.php สร้าง $pdo ไว้แล้ว หรือมีคลาส Database::getConnection()
if (isset($pdo) && $pdo instanceof PDO) {
  // ok
} elseif (class_exists('Database') && method_exists('Database', 'getConnection')) {
  $pdo = Database::getConnection();
} else {
  http_response_code(500);
  echo json_encode(["error" => "PDO connection not available"], JSON_UNESCAPED_UNICODE);
  exit;
}

// แนะนำให้เปิด ERRMODE_EXCEPTION (ถ้าคอนฟิกคุณยังไม่ได้ตั้ง)
try {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
  // ถ้าตั้งค่าแล้วหรือไม่รองรับ ข้ามได้
}

// ----- Auth -----
// คาดหวังว่า AuthMiddleware จะมีฟังก์ชัน requireAuth(array $roles = []): array
if (!function_exists('requireAuth')) {
  http_response_code(500);
  echo json_encode(["error" => "Auth middleware not wired (requireAuth not found)."], JSON_UNESCAPED_UNICODE);
  exit;
}

$payload = requireAuth(['admin', 'org_admin', 'technician', 'user']); // -> ควรได้ {id, role, org_id, ...}
$role    = $payload['role'] ?? 'user';
$orgId   = isset($payload['org_id']) ? (int)$payload['org_id'] : null;

// ----- Helpers -----
function orgScopeWhere(array $payload, string $alias = ''): string {
  $role = $payload['role'] ?? 'user';
  $prefix = $alias ? rtrim($alias, '.') . '.' : '';
  if ($role === 'admin') return '1=1';
  $orgId = (int)($payload['org_id'] ?? 0);
  return "{$prefix}org_id = {$orgId}";
}

function fetchOne(PDO $pdo, string $sql, array $params = []) {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchColumn();
}

function fetchAllAssoc(PDO $pdo, string $sql, array $params = []) {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ----- Input -----
$section = isset($_GET['section']) ? trim((string)$_GET['section']) : null;

// ----- Builders -----
function buildCards(PDO $pdo, array $payload): array {
  $role  = $payload['role'] ?? 'user';
  $cards = [];

  // Total lifts (scoped)
  $sqlTotalLifts = "SELECT COUNT(*) FROM lifts l WHERE " . orgScopeWhere($payload, 'l.');
  $totalLifts = (int)fetchOne($pdo, $sqlTotalLifts);
  $cards[] = ["label" => "จำนวนลิฟต์ทั้งหมด | Total Lifts", "value" => $totalLifts];

  // Active users (scoped)
  if ($role === 'admin') {
    $activeUsers = (int)fetchOne($pdo, "SELECT COUNT(*) FROM users WHERE is_active=1");
  } else {
    $activeUsers = (int)fetchOne(
      $pdo,
      "SELECT COUNT(*) FROM users WHERE is_active=1 AND org_id=:orgId",
      [':orgId' => (int)$payload['org_id']]
    );
  }
  $cards[] = ["label" => "ผู้ใช้ที่ใช้งานอยู่ | Active Users", "value" => $activeUsers];

  if ($role === 'admin') {
    $orgCount = (int)fetchOne($pdo, "SELECT COUNT(*) FROM organizations");
    $buildingCount = (int)fetchOne($pdo, "SELECT COUNT(*) FROM buildings");
    $cards[] = ["label" => "จำนวนองค์กร | Orgs", "value" => $orgCount];
    $cards[] = ["label" => "อาคารทั้งหมด | Buildings", "value" => $buildingCount];
  }

  return $cards;
}

function buildCharts(PDO $pdo, array $payload): array {
  $role   = $payload['role'] ?? 'user';
  $charts = [];

  if ($role === 'admin') {
    $sql = "SELECT o.org_name, COUNT(l.id) AS lifts
            FROM organizations o
            LEFT JOIN lifts l ON l.org_id = o.id
            GROUP BY o.id
            ORDER BY lifts DESC, o.org_name ASC
            LIMIT 10";
    $charts['liftsByOrg'] = fetchAllAssoc($pdo, $sql);
  }

  if ($role === 'org_admin') {
    $sql = "SELECT b.building_name, COUNT(l.id) AS lifts
            FROM buildings b
            LEFT JOIN lifts l ON l.building_id = b.id
            WHERE b.org_id = :orgId
            GROUP BY b.id
            ORDER BY lifts DESC, b.building_name ASC";
    $charts['liftsByBuilding'] = fetchAllAssoc($pdo, $sql, [':orgId' => (int)$payload['org_id']]);
  }

  return $charts;
}

function buildTables(PDO $pdo, array $payload): array {
  $role   = $payload['role'] ?? 'user';
  $tables = [];

  if ($role === 'org_admin') {
    // หมายเหตุ: โครง table task ของคุณใช้ org_name (ข้อความ) แนะนำภายหลังค่อย refactor เป็น org_id
    $sql = "SELECT tk_id, tk_status, org_name, building_name, lift_id, task_start_date
            FROM task
            WHERE org_name IN (SELECT org_name FROM organizations WHERE id = :orgId)
            ORDER BY task_start_date DESC
            LIMIT 10";
    $tables['recentTasks'] = fetchAllAssoc($pdo, $sql, [':orgId' => (int)$payload['org_id']]);
  }

  if ($role === 'technician') {
    // คิวงานสถานะ 1/2/3 (ปรับ mapping ได้ตามระบบจริง)
    $sqlQueue = "SELECT tk_id, tk_status, building_name, lift_id, task_start_date
                 FROM task
                 WHERE org_name IN (SELECT org_name FROM organizations WHERE id = :orgId)
                   AND tk_status IN ('1','2','3')
                 ORDER BY task_start_date ASC
                 LIMIT 20";
    $tables['myQueue'] = fetchAllAssoc($pdo, $sqlQueue, [':orgId' => (int)$payload['org_id']]);

    // Recent lift logs (scope ตาม org ผ่าน join กับ lifts)
    $sqlLogs = "SELECT sl.id, sl.lift_id, sl.lift_state, sl.up_status, sl.down_status, sl.car_status, sl.created_at
                FROM status_logs sl
                JOIN lifts l ON l.id = sl.lift_id
                WHERE l." . orgScopeWhere($payload) . "
                ORDER BY sl.created_at DESC
                LIMIT 10";
    $tables['recentLiftLogs'] = fetchAllAssoc($pdo, $sqlLogs);
  }

  if ($role === 'user') {
    // ลิฟต์ที่เข้าถึงตาม org
    $sql = "SELECT l.id, l.lift_name, l.max_level, b.building_name
            FROM lifts l
            LEFT JOIN buildings b ON b.id = l.building_id
            WHERE l." . orgScopeWhere($payload) . "
            ORDER BY l.lift_name ASC";
    $tables['myLifts'] = fetchAllAssoc($pdo, $sql);
  }

  return $tables;
}

// ----- Controller -----
try {
  $response = ["role" => $role];

  if ($section) {
    switch ($section) {
      case 'cards':
        $response['cards']  = buildCards($pdo, $payload);
        break;
      case 'charts':
        $response['charts'] = buildCharts($pdo, $payload);
        break;
      case 'tables':
        $response['tables'] = buildTables($pdo, $payload);
        break;
      default:
        http_response_code(400);
        echo json_encode(["error" => "Unknown section parameter"], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  // Full payload
  $response['cards']  = buildCards($pdo, $payload);
  $response['charts'] = buildCharts($pdo, $payload);
  $response['tables'] = buildTables($pdo, $payload);

  echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "error"   => "Internal Server Error",
    "message" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
