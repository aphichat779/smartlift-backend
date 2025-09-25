<?php
require_once "database.php";

// สร้างอ็อบเจกต์ (Object) ของคลาส Database
$database = new Database();
// เรียกใช้เมธอด getRedis() เพื่อสร้างการเชื่อมต่อจริง ๆ
$redis = $database->getRedis();

// โค้ดส่วนที่เหลือยังคงเดิม
if ($redis === null) {
    echo "✗ การเชื่อมต่อ Redis ล้มเหลว. โปรดตรวจสอบการตั้งค่าใน database.php.\n";
    exit();
}

echo "กำลังทดสอบการเชื่อมต่อ Redis...\n";

try {
    $ping_result = $redis->ping();
    if ($ping_result !== "+PONG") {
        throw new Exception("คำสั่ง PING ล้มเหลว: " . $ping_result);
    }
    echo "✓ เชื่อมต่อ Redis สำเร็จ (PING).\n";

    $test_key = "test_key_gemini";
    $test_value = "Hello, Redis!";
    $redis->set($test_key, $test_value);
    echo "✓ เขียนข้อมูลสำเร็จ: คีย์ '" . $test_key . "' ค่า '" . $test_value . "'.\n";

    $retrieved_value = $redis->get($test_key);
    echo "✓ อ่านข้อมูลสำเร็จ: ค่าที่ดึงได้คือ '" . $retrieved_value . "'.\n";

    if ($retrieved_value === $test_value) {
        echo "✓ ข้อมูลที่เขียนและอ่านมีความถูกต้องตรงกัน.\n";
    } else {
        echo "✗ ข้อมูลที่อ่านไม่ตรงกับข้อมูลที่เขียน.\n";
    }

    $redis->del($test_key);
    echo "✓ ลบคีย์ทดสอบสำเร็จ.\n";

    echo "\nการทดสอบ Redis เสร็จสิ้น: ทุกอย่างทำงานได้อย่างถูกต้อง\n";

} catch (Exception $e) {
    echo "\n✗ เกิดข้อผิดพลาดในการเชื่อมต่อ Redis: " . $e->getMessage() . "\n";
    echo "โปรดตรวจสอบการตั้งค่า database.php และสถานะเซิร์ฟเวอร์ Redis ของคุณ\n";
}
?>