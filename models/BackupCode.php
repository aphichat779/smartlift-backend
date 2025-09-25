<?php
require_once __DIR__ . '/../config/config.php';

class BackupCode {
    private $conn;
    private $table_name = "backup_codes";
    
    public $id;
    public $user_id;
    public $code;
    public $is_used;
    public $created_at;
    public $used_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, code=:code, created_at=:created_at";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":code", $this->code);
        $stmt->bindParam(":created_at", $this->created_at);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    public function createMultiple($user_id, $codes) {
        $this->conn->beginTransaction();
        
        try {
            // Delete existing backup codes
            $this->deleteUserCodes($user_id);
            
            // Insert new codes
            $query = "INSERT INTO " . $this->table_name . " 
                      (user_id, code, created_at) VALUES (?, ?, NOW())";
            $stmt = $this->conn->prepare($query);
            
            foreach ($codes as $code) {
                $stmt->execute([$user_id, $code]);
            }
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
    
    public function verifyCode($user_id, $code) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND code = :code AND is_used = 0 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":code", $code);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->code = $row['code'];
            $this->is_used = $row['is_used'];
            $this->created_at = $row['created_at'];
            $this->used_at = $row['used_at'];
            return true;
        }
        
        return false;
    }
    
    public function markAsUsed() {
        $query = "UPDATE " . $this->table_name . " 
                  SET is_used = 1, used_at = NOW() 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }
    
    public function getUserCodes($user_id) {
        $query = "SELECT code, is_used, created_at, used_at 
                  FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function deleteUserCodes($user_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        return $stmt->execute();
    }
    
    public function getUnusedCount($user_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND is_used = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }
}
?>

