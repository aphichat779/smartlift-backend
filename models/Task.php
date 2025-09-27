<?php
// models/Task.php
require_once __DIR__ . '/../config/database.php';

class Task {
    private $conn;
    private $table_name = "task";

    public function __construct($db) {
        $this->conn = $db;
    }

    // เมธอดสำหรับดึงงานที่ยังอยู่ในสถานะรอดำเนินการ
    public function getPending($limit = 5) {
        $query = "SELECT tk_id, tk_status, tk_data FROM " . $this->table_name . " WHERE tk_status IN ('1','2','3','4') ORDER BY tk_status ASC LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>