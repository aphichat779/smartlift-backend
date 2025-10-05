<?php
// api/lifts/stream_status.php

// โหลดไฟล์ที่จำเป็นสำหรับการเชื่อมต่อฐานข้อมูลและจัดการ CORS
require_once __DIR__ . 
'/../../config/database.php';
require_once __DIR__ . 
'/../../middleware/CORSMiddleware.php';

CORSMiddleware::handle();
set_time_limit(0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Headers: Cache-Control');

// เพิ่ม Log เพื่อให้แน่ใจว่าสคริปต์เริ่มทำงาน
error_log("SSE Script: Script started successfully.");

function mapRedisListDataToFrontendState(array $liftFromDb, array $redisDataList): array {
    // ถ้าข้อมูลไม่ครบ 9 ช่อง ให้คืนค่าข้อมูลพื้นฐานพร้อมสถานะ OFFLINE
    if (count($redisDataList) < 9) {
        return [
            'id' => $liftFromDb['id'],
            'lift_name' => $liftFromDb['lift_name'],
            'org_name' => $liftFromDb['org_name'],
            'building_name' => $liftFromDb['building_name'],
            'floor_name' => $liftFromDb['floor_name'],
            'max_level' => (int)($liftFromDb['max_level']),
            'connection' => 'OFFLINE',
            'lift_state_hex' => '0000000000000000',
            'up_status_hex' => '00000000',
            'down_status_hex' => '00000000',
            'car_status_hex' => '00000000',
        ];
    }

    $lastUpdate = $redisDataList[7] ?? '0';
    $date = new DateTime($lastUpdate);
    $date2 = new DateTime();
    $diffInSeconds = $date2->getTimestamp() - $date->getTimestamp();
    $connectionStatus = $diffInSeconds > 30 ? "OFFLINE" : "ONLINE";

    $state = [
        'id' => $liftFromDb['id'],
        'lift_name' => $redisDataList[1] ?? 'Unknown Lift',
        'org_name' => $redisDataList[0] ?? 'Unknown Org',
        'building_name' => $liftFromDb['building_name'] ?? 'Unknown Building',
        'floor_name' => $redisDataList[8] ?? '1,2,3,4,5',
        'max_level' => (int)($redisDataList[2] ?? 1),
        'connection' => $connectionStatus,

        // ข้อมูล Hex String ที่จะให้ Frontend เป็นคนแปลง
        'lift_state_hex' => $redisDataList[3] ?? '0000000000000000',
        'up_status_hex' => $redisDataList[4] ?? '00000000',
        'down_status_hex' => $redisDataList[5] ?? '00000000',
        'car_status_hex' => $redisDataList[6] ?? '00000000',
        
        'last_update' => $lastUpdate,
    ];

    return $state;
}

// --- Main Loop ---
try {
    error_log("SSE Script: Entering main try block.");
    
    $db = Database::getConnection();
    error_log("SSE Script: Database connection successful.");
    
    $redis = RedisClient::getConnection();
    error_log("SSE Script: Redis connection successful.");

    $stmt = $db->query("SELECT l.id, l.lift_name, l.mac_address, l.max_level, l.floor_name, o.org_name, b.building_name FROM lifts AS l LEFT JOIN organizations AS o ON l.org_id = o.id LEFT JOIN buildings AS b ON l.building_id = b.id");
    $lifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("SSE Script: Found " . count($lifts) . " lifts in the database.");
    
    $liftsById = [];
    foreach ($lifts as $lift) {
        $liftsById[$lift['id']] = $lift;
    }

    error_log("SSE Script: Starting the infinite while loop.");
    $loopCount = 0;

    while (true) {
        $loopCount++;
        if ($loopCount % 10 === 0) { // ทำ Log ทุก 10 รอบเพื่อไม่ให้ Log รกเกิน
            error_log("SSE Script: Loop iteration #" . $loopCount);
        }

        if (connection_aborted()) {
            error_log("SSE Script: Client aborted connection. Exiting loop.");
            break;
        }

        foreach ($liftsById as $liftId => $liftData) {
            $redisKey = 'Lift-' . str_pad($liftId, 4, "0", STR_PAD_LEFT);
            $dataList = $redis->lRange($redisKey, 0, 9);

            $frontendState = mapRedisListDataToFrontendState($liftData, $dataList);
            echo "data: " . json_encode($frontendState) . "\n\n";
        }
        
        // Ensure output is sent immediately
        ob_flush();
        flush();
        
        // Wait for a short period before the next iteration
        usleep(100000); // 100ms
    }
} catch (Throwable $e) {
    // ข้อความนี้จะถูกเขียนลงใน PHP Error Log แน่นอน
    error_log("SSE Script FATAL ERROR: " . $e->getMessage());
    
    // Send error message to client as well
    echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
    ob_flush();
    flush();
}

