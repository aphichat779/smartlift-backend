<?php
// config/database.php
declare(strict_types=1);

class Database {
    // เก็บค่าคอนฟิก (อ่านจาก ENV ก่อน ถ้าไม่มีจะใช้ค่า fallback ด้านล่าง)
    private static ?PDO $conn = null;

    /**
     * ใช้ครั้งเดียวแล้วแชร์ทั้งแอป (singleton)
     * - ใช้ DSN charset=utf8mb4
     * - ERRMODE_EXCEPTION
     * - ปิด emulate prepares เพื่อใช้ native prepares
     */
    public static function getConnection(): PDO {
        if (self::$conn instanceof PDO) {
            return self::$conn;
        }

        // อ่านค่าได้จากตัวแปรแวดล้อม ถ้าไม่ตั้งจะใช้ค่าด้านล่าง
        $host    = getenv('DB_HOST') ?: 'localhost';
        $port    = getenv('DB_PORT') ?: '3306';
        $db      = getenv('DB_NAME') ?: 'smartlift';
        $user    = getenv('DB_USER') ?: 'root';
        $pass    = getenv('DB_PASS') ?: 'root';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // เปิด persistent ได้ถ้าต้องการ:
            // PDO::ATTR_PERSISTENT      => true,
        ];

        try {
            self::$conn = new PDO($dsn, $user, $pass, $options);
            // ตั้ง timezone ให้ DB (เลือกใช้ได้)
            // self::$conn->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            // หลีกเลี่ยง echo ข้อความผิดพลาดออกหน้าเว็บเพื่อความปลอดภัย
            throw new RuntimeException('MySQL connection failed: '.$e->getMessage(), previous: $e);
        }

        return self::$conn;
    }
}
