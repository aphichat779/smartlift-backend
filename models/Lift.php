<?php
// models/Lift.php
require_once __DIR__ . '/../config/database.php';

class Lift {
    private $conn;
    private $table_name = "lifts";

    public function __construct($db) {
        $this->conn = $db;
    }

    // เมธอดสำหรับนับจำนวนลิฟต์ทั้งหมด
    public function countAll() {
        $query = "SELECT COUNT(*) AS total_lifts FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_lifts'];
    }
}
?>