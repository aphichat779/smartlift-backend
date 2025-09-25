<?php
// models/RecoveryOTP.php
require_once __DIR__ . '/../config/config.php';

class RecoveryOTP {
    private $conn;
    private $table_name = "recovery_otps";
    
    public $id;
    public $user_id;
    public $otp_code;
    public $otp_type;
    public $contact_info;
    public $is_used;
    public $expires_at;
    public $created_at;
    public $used_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, otp_code=:otp_code, otp_type=:otp_type, 
                      contact_info=:contact_info, expires_at=:expires_at, created_at=:created_at";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":otp_code", $this->otp_code);
        $stmt->bindParam(":otp_type", $this->otp_type);
        $stmt->bindParam(":contact_info", $this->contact_info);
        $stmt->bindParam(":expires_at", $this->expires_at);
        $stmt->bindParam(":created_at", $this->created_at);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    public function findValidOTP($user_id, $otp_code) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND otp_code = :otp_code 
                  AND is_used = 0 AND expires_at > NOW() 
                  ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":otp_code", $otp_code);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->otp_code = $row['otp_code'];
            $this->otp_type = $row['otp_type'];
            $this->contact_info = $row['contact_info'];
            $this->is_used = $row['is_used'];
            $this->expires_at = $row['expires_at'];
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
    
    public function deleteExpiredOTPs() {
        $query = "DELETE FROM " . $this->table_name . " WHERE expires_at < NOW()";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }
    
    public function deleteUserOTPs($user_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        return $stmt->execute();
    }
    
    public function getRecentOTPs($user_id, $minutes = 60) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND created_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":minutes", $minutes);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }
}
?>

