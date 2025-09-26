<?php
// config/database.php
class Database {
    // ------------------- MySQL Config -------------------
    private $host = "localhost";
    private $db_name = "smartlift";
    private $username = "root";
    private $password = "root";
    public $conn;

    // ------------------- Redis Config -------------------
    // private $redis_host = "52.221.67.113";
    // private $redis_port = 6379;
    // private $redis_auth = "kuse@fse2023";
    // public $redis;

    // ------------------- MySQL Connection -------------------
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "MySQL Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }

    // ------------------- Redis Connection -------------------
    // public function getRedis() {
    //     $this->redis = null;
    //     try {
    //         $this->redis = new Redis();
    //         $this->redis->connect($this->redis_host, $this->redis_port, 2.5);
    //         $this->redis->auth($this->redis_auth);
    //     } catch(Exception $e) {
    //         echo "Redis Connection error: " . $e->getMessage();
    //     }
    //     return $this->redis;
    // }
}
?>
