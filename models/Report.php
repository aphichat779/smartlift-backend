<?php
// models/Report.php
require_once __DIR__ . '/../config/database.php';

class Report {
    private $conn;
    private $table_name = "report";

    public function __construct($db) {
        $this->conn = $db;
    }

    // เมธอดสำหรับดึงรายงานล่าสุด (จำนวนจำกัด)
    public function getLatest($limit = 5) {
        $query = "SELECT rp_id, date_rp, detail FROM " . $this->table_name . " ORDER BY date_rp DESC LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>