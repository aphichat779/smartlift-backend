// api/dashboard/dashboardsuperadmin.php
<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

// ----- Includes -----
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

// ----- CORS -----
if (function_exists('handleCORS')) {
    handleCORS(['GET', 'OPTIONS']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ----- PDO Connection -----
if (isset($pdo) && $pdo instanceof PDO) {
    // ok
} elseif (class_exists('Database') && method_exists('Database', 'getConnection')) {
    $pdo = Database::getConnection();
} else {
    http_response_code(500);
    echo json_encode(["error" => "PDO connection not available"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    // ถ้าตั้งค่าแล้วหรือไม่รองรับ ข้ามได้
}

// ----- Auth -----
if (!function_exists('requireAuth')) {
    http_response_code(500);
    echo json_encode(["error" => "Auth middleware not wired (requireAuth not found)."], JSON_UNESCAPED_UNICODE);
    exit;
}

// อนุญาตทุกบทบาท
$payload = requireAuth(['super_admin', 'admin', 'technician', 'user']);
$role    = $payload['role'] ?? 'user';
$orgId   = isset($payload['org_id']) ? (int)$payload['org_id'] : null;

// ----- Helpers -----
function orgScopeWhere(array $payload, string $alias = ''): string {
    $role = $payload['role'] ?? 'user';
    $prefix = $alias ? rtrim($alias, '.') . '.' : '';
    
    // super_admin ดูได้ทั้งหมด
    if ($role === 'super_admin') return '1=1';
    
    // admin, technician, user ดูได้เฉพาะ org ของตัวเอง
    $orgId = (int)($payload['org_id'] ?? 0);
    // return "{$prefix}org_id = {$orgId}"; // org_id ไม่ได้อยู่ในทุกตาราง (เช่น status_logs)
    
    // 💡 การใช้ Prepared Statement เพื่อความปลอดภัย (แต่ในที่นี้ใช้แค่ int เลยฝังไปก่อน)
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
    if ($role === 'super_admin') {
        $activeUsers = (int)fetchOne($pdo, "SELECT COUNT(*) FROM users WHERE is_active=1");
    } else {
        $activeUsers = (int)fetchOne(
            $pdo,
            "SELECT COUNT(*) FROM users WHERE is_active=1 AND org_id=:orgId",
            [':orgId' => (int)$payload['org_id']]
        );
    }
    $cards[] = ["label" => "ผู้ใช้ที่ใช้งานอยู่ | Active Users", "value" => $activeUsers];

    // Organization/Building counts
    if ($role === 'super_admin') {
        $orgCount = (int)fetchOne($pdo, "SELECT COUNT(*) FROM organizations");
        $buildingCount = (int)fetchOne($pdo, "SELECT COUNT(*) FROM buildings");
        $cards[] = ["label" => "จำนวนองค์กร | Orgs", "value" => $orgCount];
        $cards[] = ["label" => "อาคารทั้งหมด | Buildings", "value" => $buildingCount];
    } elseif ($role === 'admin') {
        // admin ดูได้เฉพาะอาคารใน org ของตัวเอง
        $buildingCount = (int)fetchOne(
            $pdo,
            "SELECT COUNT(*) FROM buildings WHERE org_id=:orgId",
            [':orgId' => (int)$payload['org_id']]
        );
        $cards[] = ["label" => "อาคารในองค์กร | Buildings", "value" => $buildingCount];
    }

    // Task statistics for admin and technician
    if (in_array($role, ['super_admin', 'admin'])) {
        if ($role === 'super_admin') {
            $pendingTasks = (int)fetchOne(
                $pdo,
                "SELECT COUNT(*) FROM task WHERE tk_status IN ('assign', 'preparing')"
            );
        } else {
            // task ตารางมี org_name ไม่ใช่ org_id
            $pendingTasks = (int)fetchOne(
                $pdo,
                "SELECT COUNT(*) FROM task t JOIN organizations o ON t.org_name = o.org_name WHERE o.id = :orgId AND t.tk_status IN ('assign', 'preparing')",
                [':orgId' => (int)$payload['org_id']]
            );
        }
        $cards[] = ["label" => "งานที่รอดำเนินการ | Pending Tasks", "value" => $pendingTasks];
    }

    // Technician specific cards
    if ($role === 'technician') {
        $myTasks = (int)fetchOne(
            $pdo,
            "SELECT COUNT(*) FROM task WHERE user_id = :userId AND tk_status IN ('assign', 'preparing', 'progress')",
            [':userId' => (int)$payload['id']]
        );
        $cards[] = ["label" => "งานของฉัน | My Tasks", "value" => $myTasks];
    }

    return $cards;
}

function buildCharts(PDO $pdo, array $payload): array {
    $role   = $payload['role'] ?? 'user';
    $charts = [];

    if ($role === 'super_admin') {
        // สถิติลิฟต์ตามองค์กร
        $sql = "SELECT o.org_name, COUNT(l.id) AS lifts
                FROM organizations o
                LEFT JOIN lifts l ON l.org_id = o.id
                GROUP BY o.id
                ORDER BY lifts DESC, o.org_name ASC
                LIMIT 10";
        $charts['liftsByOrg'] = fetchAllAssoc($pdo, $sql);

        // สถิติผู้ใช้ตามบทบาท
        $sqlRoles = "SELECT role, COUNT(*) AS count
                     FROM users
                     GROUP BY role
                     ORDER BY count DESC";
        $charts['usersByRole'] = fetchAllAssoc($pdo, $sqlRoles);
    }

    if ($role === 'admin') {
        // สถิติลิฟต์ตามอาคารใน org
        $sql = "SELECT b.building_name, COUNT(l.id) AS lifts
                FROM buildings b
                LEFT JOIN lifts l ON l.building_id = b.id
                WHERE b.org_id = :orgId
                GROUP BY b.id
                ORDER BY lifts DESC, b.building_name ASC";
        $charts['liftsByBuilding'] = fetchAllAssoc($pdo, $sql, [':orgId' => (int)$payload['org_id']]);

        // สถิติงานตามสถานะ
        $sqlTasks = "SELECT tk_status, COUNT(*) AS count
                     FROM task
                     -- WHERE org_id = :orgId -- ❌ ต้อง JOIN กับ organizations เพราะ task เก็บ org_name
                     WHERE org_name IN (SELECT org_name FROM organizations WHERE id = :orgId)
                     GROUP BY tk_status
                     ORDER BY count DESC";
        $charts['tasksByStatus'] = fetchAllAssoc($pdo, $sqlTasks, [':orgId' => (int)$payload['org_id']]);
    }

    return $charts;
}

function buildTables(PDO $pdo, array $payload): array {
    $role   = $payload['role'] ?? 'user';
    $tables = [];

    // Super admin: ดูงานล่าสุดทั้งหมด
    if ($role === 'super_admin') {
        $sql = "SELECT t.tk_id, t.tk_status, o.org_name, b.building_name, t.lift_id, t.task_start_date, u.first_name, u.last_name
                FROM task t
                LEFT JOIN organizations o ON o.org_name = t.org_name
                LEFT JOIN buildings b ON b.building_name = t.building_name
                LEFT JOIN users u ON u.username = t.user
                ORDER BY t.task_start_date DESC
                LIMIT 10";
        $tables['recentTasks'] = fetchAllAssoc($pdo, $sql);
    }

    // Admin: ดูงานล่าสุดใน org
    if ($role === 'admin') {
        $sql = "SELECT t.tk_id, t.tk_status, t.org_name, t.building_name, t.lift_id, t.task_start_date, u.first_name, u.last_name
                FROM task t
                LEFT JOIN users u ON u.username = t.user
                WHERE t.org_name IN (SELECT org_name FROM organizations WHERE id = :orgId)
                ORDER BY t.task_start_date DESC
                LIMIT 10";
        $tables['recentTasks'] = fetchAllAssoc($pdo, $sql, [':orgId' => (int)$payload['org_id']]);
    }

    // Technician: คิวงานของตัวเอง
    if ($role === 'technician') {
        // คิวงานสถานะ 1/2/3
        $sqlQueue = "SELECT tk_id, tk_status, building_name, lift_id, task_start_date
                     FROM task
                     WHERE user_id = :userId
                       AND tk_status IN ('assign', 'preparing', 'progress')
                     ORDER BY task_start_date ASC
                     LIMIT 20";
        $tables['myQueue'] = fetchAllAssoc($pdo, $sqlQueue, [':userId' => (int)$payload['id']]);

        // Recent lift logs (scope ตาม org)
        $sqlLogs = "SELECT sl.id, sl.lift_id, sl.lift_state, sl.up_status, sl.down_status, sl.car_status, sl.created_at
                     FROM status_logs sl
                     JOIN lifts l ON l.id = sl.lift_id
                     WHERE " . orgScopeWhere($payload, 'l.') . " -- 🎯 แก้ไข: ส่ง Alias 'l.' เข้าไปในฟังก์ชัน
                     ORDER BY sl.created_at DESC
                     LIMIT 10";
        $tables['recentLiftLogs'] = fetchAllAssoc($pdo, $sqlLogs, []); // ไม่มี params เพราะ orgScopeWhere ฝังค่า orgId แล้ว
    }

    // User: ลิฟต์ที่เข้าถึงตาม org
    if ($role === 'user') {
        $sql = "SELECT l.id, l.lift_name, l.max_level, b.building_name
                FROM lifts l
                LEFT JOIN buildings b ON b.id = l.building_id
                WHERE " . orgScopeWhere($payload, 'l.') . " -- 🎯 แก้ไข: ส่ง Alias 'l.' เข้าไปในฟังก์ชัน
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