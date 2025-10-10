<?php
// models/PasswordReset.php
require_once __DIR__ . '/../config/database.php';

class PasswordReset {
    private $conn;
    private $table_name = "password_resets";

    public $id;
    public $user_id;
    public $token_hash;
    public $expires_at;
    public $created_at;

    public function __construct($db=null) {
        $this->conn = $db ?? (new Database())->getConnection();
    }

    public function create(int $userId, string $tokenHash, string $expiresAt): bool {
        $query = "INSERT INTO {$this->table_name} (user_id, token_hash, expires_at, created_at)
                  VALUES (:user_id, :token_hash, :expires_at, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        $stmt->bindParam(":token_hash", $tokenHash);
        $stmt->bindParam(":expires_at", $expiresAt);
        return $stmt->execute();
    }

    public function findValidByHash(string $tokenHash) {
        $query = "SELECT * FROM {$this->table_name} WHERE token_hash = :token_hash AND expires_at > NOW() LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":token_hash", $tokenHash);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function revokeById(int $id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE id = :id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function revokeByUserId(int $userId) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE user_id = :user_id");
        $stmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function pruneExpired() {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE expires_at <= NOW()");
        return $stmt->execute();
    }
}
?>