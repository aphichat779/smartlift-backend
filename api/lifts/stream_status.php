<?php
// api/lifts/stream_status.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORSMiddleware.php';

CORSMiddleware::handle();
ignore_user_abort(true);
set_time_limit(0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
echo "retry: 3000\n\n";
@ob_flush(); flush();

$db = Database::getConnection();
$redis = RedisClient::getConnection();

// โหลดข้อมูล meta ของลิฟต์
$stmt = $db->query("SELECT l.id, l.lift_name, l.max_level, l.floor_name,
                           o.org_name, b.building_name
                    FROM lifts l
                    LEFT JOIN organizations o ON l.org_id = o.id
                    LEFT JOIN buildings b     ON l.building_id = b.id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$metaById = [];
foreach ($rows as $r) {
    $metaById[(string)$r['id']] = $r;
}

function mapFromRedisList($meta, $list)
{
    if (count($list) < 9) {
        return [
            'id' => (int)$meta['id'],
            'lift_name' => $meta['lift_name'],
            'org_name' => $meta['org_name'] ?? '-',
            'building_name' => $meta['building_name'] ?? '-',
            'floor_name' => $meta['floor_name'] ?? '1,2,3,4',
            'max_level' => (int)$meta['max_level'] ?? 1,
            'connection' => 'OFFLINE',
            'lift_state_hex' => '0000000000000000',
            'up_status_hex'  => '00000000',
            'down_status_hex' => '00000000',
            'car_status_hex' => '00000000',
            'last_update' => null,
        ];
    }

    $lastUpdate = $list[7] ?? '0';
    $date = new DateTime($lastUpdate);
    $now  = new DateTime();
    $online = (($now->getTimestamp() - $date->getTimestamp()) <= 30) ? 'ONLINE' : 'OFFLINE';

    return [
        'id' => (int)$meta['id'],
        'lift_name' => $list[1] ?? $meta['lift_name'],
        'org_name' => $list[0] ?? $meta['org_name'],
        'building_name' => $meta['building_name'] ?? '-',
        'floor_name' => $list[8] ?? ($meta['floor_name'] ?? '1,2,3,4'),
        'max_level' => (int)($list[2] ?? ($meta['max_level'] ?? 1)),
        'connection' => $online,
        'lift_state_hex' => $list[3] ?? '0000000000000000',
        'up_status_hex'  => $list[4] ?? '00000000',
        'down_status_hex' => $list[5] ?? '00000000',
        'car_status_hex' => $list[6] ?? '00000000',
        'last_update' => $lastUpdate,
    ];
}

// --- ส่ง snapshot ครั้งแรก (รวมทั้งหมด) ---
$snapshot = [
    'type' => 'snapshot',
    'timestamp' => (new DateTime())->format(DATE_ATOM),
    'lifts' => [],
];
foreach ($metaById as $id => $meta) {
    $key = 'Lift-' . str_pad($id, 4, '0', STR_PAD_LEFT);
    $list = $redis->lRange($key, 0, 9);
    $snapshot['lifts'][$id] = mapFromRedisList($meta, $list);
}

echo "event: snapshot\n";
echo "id: " . time() . "\n";
echo "data: " . json_encode($snapshot, JSON_UNESCAPED_UNICODE) . "\n\n";
@ob_flush();
flush();

// --- loop อัปเดต real-time (poll redis) ---
while (true) {
    if (connection_aborted()) break;
    sleep(1);

    $payload = [
        'type' => 'delta',
        'timestamp' => (new DateTime())->format(DATE_ATOM),
        'lifts' => [],
    ];

    foreach ($metaById as $id => $meta) {
        $key = 'Lift-' . str_pad($id, 4, '0', STR_PAD_LEFT);
        $list = $redis->lRange($key, 0, 9);
        $payload['lifts'][$id] = mapFromRedisList($meta, $list);
    }

    echo "event: delta\n";
    echo "id: " . time() . "\n";
    echo "data: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush();
    flush();
}
