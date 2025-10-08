<?php
// test_redis_connection.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

echo "=== Redis Connection Test ===\n\n";

try {
    echo "1. Testing basic Redis connection...\n";
    $redis = RedisClient::getConnection();
    $pong = $redis->ping();
    echo "   ✓ Basic connection successful. Ping response: " . $pong . "\n\n";
    
    echo "2. Testing subscriber Redis connection...\n";
    $subscriber = RedisClient::getSubscriberConnection();
    $pong2 = $subscriber->ping();
    echo "   ✓ Subscriber connection successful. Ping response: " . $pong2 . "\n\n";
    
    echo "3. Testing Redis operations...\n";
    $redis->set('test_key', 'test_value');
    $value = $redis->get('test_key');
    echo "   ✓ Set/Get test successful. Value: " . $value . "\n";
    $redis->del('test_key');
    echo "   ✓ Delete test successful.\n\n";
    
    echo "4. Testing Redis info...\n";
    $info = $redis->info('server');
    if (is_array($info) && isset($info['redis_version'])) {
        echo "   ✓ Redis version: " . $info['redis_version'] . "\n";
    } else {
        echo "   ⚠ Could not get Redis version info\n";
    }
    
    echo "\n=== All tests passed! ===\n";
    
} catch (Throwable $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n\n";
    
    echo "=== Debugging Information ===\n";
    echo "Redis Host: " . (getenv('REDIS_HOST') ?: '52.221.67.113') . "\n";
    echo "Redis Port: " . (getenv('REDIS_PORT') ?: 6379) . "\n";
    echo "Redis Pass: " . (getenv('REDIS_PASS') ? '[SET]' : '[NOT SET]') . "\n";
    
    // ทดสอบการเชื่อมต่อแบบ manual
    echo "\n=== Manual Connection Test ===\n";
    try {
        $host = getenv('REDIS_HOST') ?: '52.221.67.113';
        $port = (int)(getenv('REDIS_PORT') ?: 6379);
        
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($socket) {
            echo "✓ TCP connection to {$host}:{$port} successful\n";
            fclose($socket);
        } else {
            echo "✗ TCP connection failed: {$errstr} (Error: {$errno})\n";
        }
    } catch (Throwable $e) {
        echo "✗ Socket test failed: " . $e->getMessage() . "\n";
    }
}
