<?php
require_once __DIR__ . '/../config/config.php';

class OTPToken {
    private $conn;
    private $table_name = "otp_tokens";
    
    public $id;
    public $user_id;
    public $method;
    public $otp_code;
    public $created_at;
    public $expires_at;
    public $is_verified;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, method=:method, otp_code=:otp_code, 
                      created_at=:created_at, expires_at=:expires_at";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":method", $this->method);
        $stmt->bindParam(":otp_code", $this->otp_code);
        $stmt->bindParam(":created_at", $this->created_at);
        $stmt->bindParam(":expires_at", $this->expires_at);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    public function findValidOTP($user_id, $otp_code) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND otp_code = :otp_code 
                  AND is_verified = 0 AND expires_at > NOW() 
                  ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":otp_code", $otp_code);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->method = $row['method'];
            $this->otp_code = $row['otp_code'];
            $this->created_at = $row['created_at'];
            $this->expires_at = $row['expires_at'];
            $this->is_verified = $row['is_verified'];
            return true;
        }
        
        return false;
    }
    
    public function markAsVerified() {
        $query = "UPDATE " . $this->table_name . " 
                  SET is_verified = 1 
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
}
?>

