<?php
/**
 * File: api/dashboard/DashboardService.php
 * Description: Shared service for building dashboard payloads across roles & scopes.
 */

declare(strict_types=1);

/** Utils **/
function getAuthUserOrFail(): array {
    if (!function_exists('requireAuth')) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Auth middleware not loaded']);
        exit;
    }
    $user = requireAuth();
    if (!$user || !isset($user['role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED']);
        exit;
    }
    return $user;
}

function fetchOne(PDO $pdo, string $sql, array $args = []): ?array {
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

function fetchAll(PDO $pdo, string $sql, array $args = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function bitPad(string $bits, int $len): string {
    $bits = preg_replace('/[^01]/', '0', $bits);
    $bits = substr($bits, 0, $len);
    return str_pad($bits, $len, '0');
}

function currentFloorFromCarBits(string $carBits): int {
    $len = strlen($carBits);
    for ($i = 0; $i < $len; $i++) if ($carBits[$i] === '1') return $i + 1;
    return 1;
}

function directionFromBits(string $up, string $down): string {
    $u = strpos($up, '1') !== false; $d = strpos($down, '1') !== false;
    if ($u && !$d) return '↑'; if ($d && !$u) return '↓'; return '·';
}

/**
 * Scope helper
 * - $allOrgs = true  -> system-wide (no org filter)
 * - $allOrgs = false -> restricted to given $orgId
 */
function kpisForScope(PDO $pdo, string $role, ?int $orgId, bool $allOrgs): array {
    if ($allOrgs) {
        $orgs = (int) (fetchOne($pdo, 'SELECT COUNT(*) c FROM organizations')['c'] ?? 0);
        $buildings = (int) (fetchOne($pdo, 'SELECT COUNT(*) c FROM buildings')['c'] ?? 0);
        $lifts = (int) (fetchOne($pdo, 'SELECT COUNT(*) c FROM lifts')['c'] ?? 0);
        $users = (int) (fetchOne($pdo, 'SELECT COUNT(*) c FROM users')['c'] ?? 0);
        // For admin/technician all-org view we still surface same set for consistency
        if (in_array($role, ['admin','technician','super_admin'], true)) {
            return [
                ['label' => 'Organizations', 'value' => $orgs],
                ['label' => 'Buildings', 'value' => $buildings],
                ['label' => 'Elevators', 'value' => $lifts],
                ['label' => 'Users', 'value' => $users],
            ];
        }
    }
    // org-scoped (user/org-admin view)
    $buildings = (int) (fetchOne($pdo, 'SELECT COUNT(*) c FROM buildings WHERE org_id = :o', ['o' => $orgId])['c'] ?? 0);
    $lifts = (int) (fetchOne($pdo, 'SELECT COUNT(*) c FROM lifts WHERE org_id = :o', ['o' => $orgId])['c'] ?? 0);
    $techs = (int) (fetchOne($pdo, 'SELECT COUNT(*) c FROM users WHERE org_id = :o AND role = "technician"', ['o' => $orgId])['c'] ?? 0);
    $openTasks = (int) (fetchOne($pdo, 'SELECT COUNT(*) c FROM task WHERE org_id = :o AND tk_status IN ("assign","preparing","progress")', ['o' => $orgId])['c'] ?? 0);
    return [
        ['label' => 'Buildings', 'value' => $buildings],
        ['label' => 'Elevators', 'value' => $lifts],
        ['label' => 'Technicians', 'value' => $techs],
        ['label' => 'Open Tasks', 'value' => $openTasks],
    ];
}

function unassignedReports(PDO $pdo, ?int $orgId, bool $allOrgs): array {
    $where = $allOrgs ? '' : 'WHERE r.org_id = :o';
    $sql = "
        SELECT r.rp_id AS id,
               DATE_FORMAT(CONVERT_TZ(r.created_at, '+00:00', @@session.time_zone), '%Y-%m-%d %H:%i') AS date,
               org.org_name AS org,
               b.building_name AS building,
               l.lift_name AS lift,
               r.detail
        FROM report r
        JOIN organizations org ON org.id = r.org_id
        JOIN buildings b ON b.id = r.building_id
        JOIN lifts l ON l.id = r.lift_id
        LEFT JOIN task t ON t.rp_id = r.rp_id
        $where AND t.tk_id IS NULL
        ORDER BY r.created_at DESC
        LIMIT 20";
    $args = $allOrgs ? [] : ['o' => $orgId];
    return fetchAll($pdo, $sql, $args);
}

function ongoingTasks(PDO $pdo, ?int $orgId, bool $allOrgs): array {
    $where = $allOrgs ? '' : 'WHERE t.org_id = :o';
    $sql = "
        SELECT t.tk_id AS id,
               l.lift_name AS lift,
               CONCAT(org.org_name, ' ', b.building_name) AS site,
               CONCAT(u.first_name, ' ', u.last_name) AS tech,
               t.tk_status AS status,
               DATE_FORMAT(CONVERT_TZ(t.task_start_date, '+00:00', @@session.time_zone), '%H:%i') AS started
        FROM task t
        JOIN lifts l ON l.id = t.lift_id
        LEFT JOIN buildings b ON b.id = t.building_id
        LEFT JOIN organizations org ON org.id = t.org_id
        LEFT JOIN users u ON u.id = t.user_id
        $where AND t.tk_status IN ('assign','preparing','progress')
        ORDER BY t.task_start_date DESC
        LIMIT 20";
    $args = $allOrgs ? [] : ['o' => $orgId];
    return fetchAll($pdo, $sql, $args);
}

function liftBitBoard(PDO $pdo, ?int $orgId, bool $allOrgs): array {
    $where = $allOrgs ? '' : 'WHERE l.org_id = :o';
    $sql = "
        SELECT l.lift_name AS name,
               COALESCE(NULLIF(l.max_level, 0), 8) AS floors,
               l.up_status, l.down_status, l.car_status
        FROM lifts l
        $where
        ORDER BY l.updated_at DESC
        LIMIT 30";
    $args = $allOrgs ? [] : ['o' => $orgId];
    $rows = fetchAll($pdo, $sql, $args);
    $out = [];
    foreach ($rows as $r) {
        $floors = (int) $r['floors'];
        $up = bitPad((string)$r['up_status'], 8);
        $down = bitPad((string)$r['down_status'], 8);
        $car = bitPad((string)$r['car_status'], 8);
        $out[] = [
            'name' => $r['name'],
            'floors' => $floors,
            'current' => currentFloorFromCarBits($car),
            'dir' => directionFromBits($up, $down),
            'up' => $up,
            'down' => $down,
            'car' => $car,
        ];
    }
    return $out;
}

function recentActivity(PDO $pdo, ?int $orgId, bool $allOrgs): array {
    $where = $allOrgs ? '' : 'WHERE l.org_id = :o';
    $sql = "
        SELECT DATE_FORMAT(CONVERT_TZ(s.created_at, '+00:00', @@session.time_zone), '%H:%i') AS time,
               CONCAT('LIFT ', l.lift_name, ' state changed (up=', s.up_status, ', down=', s.down_status, ', car=', s.car_status, ')') AS text
        FROM status_logs s
        JOIN lifts l ON l.id = s.lift_id
        $where
        ORDER BY s.created_at DESC
        LIMIT 15";
    $args = $allOrgs ? [] : ['o' => $orgId];
    return fetchAll($pdo, $sql, $args);
}

function buildDashboardPayload(PDO $pdo, string $roleLabel, ?int $orgId, bool $allOrgs): array {
    return [
        'success' => true,
        'role' => $roleLabel,
        'kpis' => kpisForScope($pdo, $roleLabel, $orgId, $allOrgs),
        'reportsUnassigned' => unassignedReports($pdo, $orgId, $allOrgs),
        'tasksOngoing' => ongoingTasks($pdo, $orgId, $allOrgs),
        'liftBits' => liftBitBoard($pdo, $orgId, $allOrgs),
        'activity' => recentActivity($pdo, $orgId, $allOrgs),
    ];
}
?>