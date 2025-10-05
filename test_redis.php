<?php
// test_redis.php

// แสดงข้อผิดพลาดทั้งหมดเพื่อการดีบัก
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// โหลดไฟล์ config ที่มีคลาส RedisClient
require_once __DIR__ . '/config/database.php';

// กำหนด header เป็น text/plain เพื่อให้อ่านง่ายใน browser
header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "      ทดสอบการเชื่อมต่อ Redis\n";
echo "========================================\n\n";

try {
    // 1. พยายามเชื่อมต่อ
    echo "1. กำลังพยายามเชื่อมต่อกับ Redis Server...\n";
    $redis = RedisClient::getConnection();
    echo "   ✅ เชื่อมต่อสำเร็จ!\n\n";

    // 2. ทดสอบคำสั่ง PING
    echo "2. ทดสอบคำสั่ง PING...\n";
    $pong = $redis->ping();
    echo "   ตอบกลับ: " . $pong . " (ควรจะเป็น +PONG)\n\n";

    // 3. ทดสอบการเขียนและอ่านข้อมูล (SET/GET)
    echo "3. ทดสอบการเขียนและอ่านข้อมูล (SET/GET)...\n";
    $testKey = 'smartlift_test_key_' . time();
    $testValue = 'Hello from PHP! ' . date('Y-m-d H:i:s');
    
    $redis->set($testKey, $testValue);
    echo "   ✅ สร้างคีย์: " . $testKey . "\n";
    
    $retrievedValue = $redis->get($testKey);
    echo "   ✅ อ่านค่ากลับมาได้: " . $retrievedValue . "\n";
    
    // ลบข้อมูลทดสอบทิ้ง
    $redis->del($testKey);
    echo "   ✅ ลบคีย์ทดสอบเรียบร้อย\n\n";

    // 4. แสดงข้อมูลเซิร์ฟเวอร์
    echo "4. ดึงข้อมูลทั่วไปของ Redis Server...\n";
    $info = $redis->info();
    echo "   - Redis Version: " . ($info['redis_version'] ?? 'N/A') . "\n";
    echo "   - OS: " . ($info['os'] ?? 'N/A') . "\n";
    echo "   - Uptime (วัน): " . ($info['uptime_in_days'] ?? 'N/A') . "\n";
    echo "   - จำนวน Client ที่เชื่อมต่อ: " . ($info['connected_clients'] ?? 'N/A') . "\n";
    echo "   - หน่วยความจำที่ใช้: " . ($info['used_memory_human'] ?? 'N/A') . "\n\n";

    echo "========================================\n";
    echo "      การทดสอบสำเร็จทั้งหมด!\n";
    echo "========================================\n";

} catch (Throwable $e) {
    echo "❌ เกิดข้อผิดพลาด!\n\n";
    echo "รายละเอียดข้อผิดพลาด:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "โปรดตรวจสอบการตั้งค่าในไฟล์ config/database.php และสถานะของ Redis Server\n";
    echo "   - REDIS_HOST: " . (getenv('REDIS_HOST') ?: '52.221.67.113') . "\n";
    echo "   - REDIS_PORT: " . (getenv('REDIS_PORT') ?: '6379') . "\n";
    echo "   - REDIS_PASS: " . (getenv('REDIS_PASS') ? '[มีการตั้งค่า]' : '[ไม่มีการตั้งค่า]') . "\n";
}