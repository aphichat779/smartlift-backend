<?php
// config/database.php
declare(strict_types=1);

class Database {
    private static ?PDO $conn = null;

    public static function getConnection(): PDO {
        if (self::$conn instanceof PDO) {
            return self::$conn;
        }

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
        ];

        try {
            self::$conn = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('MySQL connection failed: '.$e->getMessage(), previous: $e);
        }

        return self::$conn;
    }
}

class RedisClient {
    private static ?Redis $conn = null;

    public static function getConnection(): Redis {
        if (self::$conn instanceof Redis) {
            return self::$conn;
        }

        $host = getenv('REDIS_HOST') ?: '52.221.67.113';
        $port = (int)(getenv('REDIS_PORT') ?: 6379);
        $pass = getenv('REDIS_PASS') ?: 'kuse@fse2023';
        $timeout = 1.5;

        try {
            $redis = new Redis();
            $redis->connect($host, $port, $timeout);

            if (!empty($pass)) {
                $redis->auth($pass);
            }

            self::$conn = $redis;
        } catch (RedisException $e) {
            throw new RuntimeException('Redis connection failed: ' . $e->getMessage(), previous: $e);
        }

        return self::$conn;
    }
}
