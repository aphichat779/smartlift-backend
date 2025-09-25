<?php
// models/TwoFAResetLog.php
require_once __DIR__ . '/../config/config.php';

class TwoFAResetLog {
    private $conn;
    private $table_name = "twofa_reset_logs";
    
    public $id;
    public $user_id;
    public $reset_method;
    public $old_secret_key;
    public $new_secret_key;
    public $ip_address;
    public $user_agent;
    public $reset_reason;
    public $admin_user_id;
    public $created_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                    SET user_id=:user_id, reset_method=:reset_method, 
                        old_secret_key=:old_secret_key, new_secret_key=:new_secret_key,
                        ip_address=:ip_address, user_agent=:user_agent, 
                        reset_reason=:reset_reason, admin_user_id=:admin_user_id, 
                        created_at=:created_at";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":reset_method", $this->reset_method);
        $stmt->bindParam(":old_secret_key", $this->old_secret_key);
        $stmt->bindParam(":new_secret_key", $this->new_secret_key);
        $stmt->bindParam(":ip_address", $this->ip_address);
        $stmt->bindParam(":user_agent", $this->user_agent);
        $stmt->bindParam(":reset_reason", $this->reset_reason);
        $stmt->bindParam(":admin_user_id", $this->admin_user_id);
        $stmt->bindParam(":created_at", $this->created_at);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    public function getUserLogs($user_id, $limit = 10) {
        $query = "SELECT rl.*, u.username as admin_username 
                  FROM " . $this->table_name . " rl
                  LEFT JOIN users u ON rl.admin_user_id = u.id
                  WHERE rl.user_id = :user_id 
                  ORDER BY rl.created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllLogs($limit = 50, $offset = 0) {
        $query = "SELECT rl.*, u.username, u.first_name, u.last_name,
                          a.username as admin_username
                  FROM " . $this->table_name . " rl
                  JOIN users u ON rl.user_id = u.id
                  LEFT JOIN users a ON rl.admin_user_id = a.id
                  ORDER BY rl.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getLogCount() {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }
    
    public static function logReset($db, $user_id, $reset_method, $old_secret = null, $new_secret = null, $reason = null, $ip_address = null, $user_agent = null, $admin_id = null) {
        $log = new self($db);
        $log->user_id = $user_id;
        $log->reset_method = $reset_method;
        $log->old_secret_key = $old_secret;
        $log->new_secret_key = $new_secret;
        $log->ip_address = $ip_address;
        $log->user_agent = $user_agent;
        $log->reset_reason = $reason;
        $log->admin_user_id = $admin_id;
        $log->created_at = date('Y-m-d H:i:s');
        
        return $log->create();
    }
}
?>