<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

echo "<pre>";

try {
    // ✅ Test MySQL
    $db = Database::getConnection();
    $stmt = $db->query("SELECT NOW() as now");
    $row = $stmt->fetch();
    echo "✅ MySQL connected successfully\n";
    echo "MySQL NOW(): " . $row['now'] . "\n\n";
} catch (Throwable $e) {
    echo "❌ MySQL connection failed: " . $e->getMessage() . "\n\n";
}

try {
    // ✅ Test Redis
    $redis = RedisClient::getConnection();

    $key = "test_key";
    $value = "Hello Redis " . date('Y-m-d H:i:s');

    $redis->set($key, $value, 10);
    $fetched = $redis->get($key);

    echo "✅ Redis connected successfully\n";
    echo "SET {$key} = {$value}\n";
    echo "GET {$key} = {$fetched}\n";
    echo "TTL for {$key}: " . $redis->ttl($key) . " seconds\n";
} catch (Throwable $e) {
    echo "❌ Redis connection failed: " . $e->getMessage() . "\n";
}

echo "</pre>";
