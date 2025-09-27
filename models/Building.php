<?php
// models/Building.php
require_once __DIR__ . '/../config/database.php';

class Building {
    private $conn;
    private $table_name = "buildings";

    public function __construct($db) {
        $this->conn = $db;
    }

    // เมธอดสำหรับนับจำนวนอาคารทั้งหมด
    public function countAll() {
        $query = "SELECT COUNT(*) AS total_buildings FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_buildings'];
    }
}
?>