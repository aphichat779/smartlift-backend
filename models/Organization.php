<?php
// models/Organization.php
class Organization {
    private $conn;
    private $table = "organizations";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll(int $limit = 20, int $offset = 0, ?string $q = null): array {
        $sql = "SELECT id, org_name, description, created_user_id, created_at, updated_user_id, updated_at
                FROM {$this->table}";
        $params = [];
        if ($q) {
            $sql .= " WHERE org_name LIKE :q OR description LIKE :q";
            $params[':q'] = "%{$q}%";
        }
        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(?string $q = null): int {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table}";
        $params = [];
        if ($q) {
            $sql .= " WHERE org_name LIKE :q OR description LIKE :q";
            $params[':q'] = "%{$q}%";
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['c'];
    }

    public function getById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $name, ?string $desc, int $userId): ?int {
        // ป้องกันซ้ำชื่อ
        $exists = $this->conn->prepare("SELECT id FROM {$this->table} WHERE org_name = :name");
        $exists->execute([':name' => $name]);
        if ($exists->fetch(PDO::FETCH_ASSOC)) return null;

        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table} (org_name, description, created_user_id, created_at, updated_user_id, updated_at)
            VALUES (:name, :desc, :uid, NOW(), :uid, NOW())
        ");
        $ok = $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':uid'  => $userId
        ]);
        if (!$ok) return null;
        return (int)$this->conn->lastInsertId();
    }

    public function update(int $id, string $name, ?string $desc, int $userId): bool {
        // ชื่อซ้ำกับ org อื่น
        $exists = $this->conn->prepare("SELECT id FROM {$this->table} WHERE org_name = :name AND id <> :id");
        $exists->execute([':name' => $name, ':id' => $id]);
        if ($exists->fetch(PDO::FETCH_ASSOC)) return false;

        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET org_name = :name, description = :desc, updated_user_id = :uid, updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':uid'  => $userId,
            ':id'   => $id
        ]);
    }

    public function delete(int $id): bool {
        // ระวัง FK (buildings.org_id, lifts.org_id, users.org_id ฯลฯ)
        // หากต้องการ soft delete ให้เพิ่มคอลัมน์ is_active แทน
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
