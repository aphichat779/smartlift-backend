<?php
// models/User.php
require_once __DIR__ . '/../config/database.php';

class User
{
    private $conn;
    private $table_name = "users";

    public $id;
    public $username;
    public $password;
    public $first_name;
    public $last_name;
    public $email;
    public $phone;
    public $birthdate;
    public $address;
    public $role;
    public $ga_secret_key;
    public $ga_enabled;
    public $org_id;
    public $user_img;
    public $recovery_email;
    public $recovery_phone;
    public $last_2fa_reset;
    public $failed_2fa_attempts;
    public $locked_until;
    public $is_active;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /* ---------------- utility: แปลง row เป็น public array ---------------- */
    private function rowToPublic(array $row): array {
        return [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'birthdate' => $row['birthdate'],
            'address' => $row['address'],
            'role' => $row['role'],
            'ga_enabled' => (int)$row['ga_enabled'],
            'org_id' => isset($row['org_id']) ? (int)$row['org_id'] : null,
            'user_img' => $row['user_img'],
            'recovery_email' => $row['recovery_email'],
            'recovery_phone' => $row['recovery_phone'],
            'last_2fa_reset' => $row['last_2fa_reset'],
            'failed_2fa_attempts' => $row['failed_2fa_attempts'],
            'locked_until' => $row['locked_until'],
            'is_active' => isset($row['is_active']) ? (int)$row['is_active'] : null,
        ];
    }
    /* --------------------------------------------------------------------- */

    public function create()
    {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET username=:username, password=:password, first_name=:first_name, 
                      last_name=:last_name, email=:email, phone=:phone, 
                      birthdate=:birthdate, address=:address, role=:role, org_id=:org_id,
                      recovery_email=:recovery_email, recovery_phone=:recovery_phone";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->recovery_email = htmlspecialchars(strip_tags($this->recovery_email));
        $this->recovery_phone = htmlspecialchars(strip_tags($this->recovery_phone));

        // Bind values
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":birthdate", $this->birthdate);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":org_id", $this->org_id);
        $stmt->bindParam(":recovery_email", $this->recovery_email);
        $stmt->bindParam(":recovery_phone", $this->recovery_phone);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function findByUsername($username)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->hydrate($row);
            return true;
        }
        return false;
    }

    public function findById($id)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->hydrate($row);
            return true;
        }
        return false;
    }

    /* --------------- NEW: ดึงแบบ associative ง่าย ๆ สำหรับ API -------------- */
    public function findAssocById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    /* --------------------------------------------------------------------- */

    private function hydrate(array $row): void {
        $this->id = $row['id'];
        $this->username = $row['username'];
        $this->password = $row['password'];
        $this->first_name = $row['first_name'];
        $this->last_name = $row['last_name'];
        $this->email = $row['email'];
        $this->phone = $row['phone'];
        $this->birthdate = $row['birthdate'];
        $this->address = $row['address'];
        $this->role = $row['role'];
        $this->ga_secret_key = $row['ga_secret_key'];
        $this->ga_enabled = $row['ga_enabled'];
        $this->org_id = $row['org_id'];
        $this->user_img = $row['user_img'];
        $this->recovery_email = $row['recovery_email'];
        $this->recovery_phone = $row['recovery_phone'];
        $this->last_2fa_reset = $row['last_2fa_reset'];
        $this->failed_2fa_attempts = $row['failed_2fa_attempts'];
        $this->locked_until = $row['locked_until'];
        $this->is_active = $row['is_active'];
    }

    public function update()
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET first_name=:first_name, last_name=:last_name, email=:email, 
                      phone=:phone, birthdate=:birthdate, address=:address,
                      recovery_email=:recovery_email, recovery_phone=:recovery_phone
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->recovery_email = htmlspecialchars(strip_tags($this->recovery_email));
        $this->recovery_phone = htmlspecialchars(strip_tags($this->recovery_phone));

        // Bind values
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":birthdate", $this->birthdate);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":recovery_email", $this->recovery_email);
        $stmt->bindParam(":recovery_phone", $this->recovery_phone);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    public function updatePassword($newPassword)
    {
        $query = "UPDATE " . $this->table_name . " SET password=:password WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt->bindParam(":password", $hashedPassword);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    public function update2FA($secretKey, $enabled)
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET ga_secret_key=:secret_key, ga_enabled=:enabled 
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":secret_key", $secretKey);
        $stmt->bindParam(":enabled", $enabled);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    public function updateFailedAttempts($attempts)
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET failed_2fa_attempts=:attempts 
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":attempts", $attempts);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    public function lockAccount($lockUntil)
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET locked_until=:locked_until 
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":locked_until", $lockUntil);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    public function isAccountLocked()
    {
        if ($this->locked_until && strtotime($this->locked_until) > time()) {
            return true;
        }
        return false;
    }

    /* --------------- UPDATED: รองรับกรองตาม org ----------------- */
    public function getAllUsers($limit = 50, $offset = 0, ?int $scopeOrgId = null)
    {
        $where = '';
        if ($scopeOrgId !== null) {
            $where = "WHERE org_id = :org_id";
        }

        $query = "SELECT id, username, first_name, last_name, email, phone, role, 
                         ga_enabled, org_id, user_img, last_2fa_reset, failed_2fa_attempts, locked_until, is_active
                  FROM " . $this->table_name . " 
                  {$where}
                  ORDER BY id DESC 
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        if ($scopeOrgId !== null) {
            $stmt->bindValue(":org_id", $scopeOrgId, PDO::PARAM_INT);
        }
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // เพิ่ม method สำหรับแก้ไขบทบาท
    public function updateRole($id, $role)
    {
        $query = "UPDATE " . $this->table_name . " SET role = :role WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    /* --------------- UPDATED: toggle สถานะแบบมี scope -------------- */
    public function toggleActiveStatus($id, $is_active, ?int $scopeOrgId = null)
    {
        if ($scopeOrgId !== null) {
            $query = "UPDATE " . $this->table_name . " SET is_active = :is_active WHERE id = :id AND org_id = :org_id";
        } else {
            $query = "UPDATE " . $this->table_name . " SET is_active = :is_active WHERE id = :id";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        if ($scopeOrgId !== null) {
            $stmt->bindParam(':org_id', $scopeOrgId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    /* ---------------------------------------------------------------- */

    public function deleteUser($id)
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    public function updateImage()
    {
        $query = "UPDATE " . $this->table_name . " SET user_img = :user_img WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->user_img = htmlspecialchars(strip_tags($this->user_img));

        $stmt->bindParam(":user_img", $this->user_img);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    // ดึงข้อมูลผู้ใช้แบบ public
    public function getPublicData()
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birthdate' => $this->birthdate,
            'address' => $this->address,
            'role' => $this->role,
            'ga_enabled' => $this->ga_enabled,
            'org_id' => $this->org_id,
            'user_img' => $this->user_img,
            'recovery_email' => $this->recovery_email,
            'recovery_phone' => $this->recovery_phone,
            'is_active' => $this->is_active
        ];
    }

    /* --------------- UPDATED: รองรับ count ตาม org ---------------- */
    public function countAll(?int $scopeOrgId = null): int
    {
        if ($scopeOrgId !== null) {
            $sql = "SELECT COUNT(*) AS c FROM {$this->table_name} WHERE org_id = :org_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':org_id', $scopeOrgId, PDO::PARAM_INT);
        } else {
            $sql = "SELECT COUNT(*) AS c FROM {$this->table_name}";
            $stmt = $this->conn->prepare($sql);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['c'];
    }
    /* ---------------------------------------------------------------- */

    /* --------------- UPDATED: update org แบบมี scope ---------------- */
    public function updateUserOrg(int $userId, int $orgId, ?int $scopeOrgId = null): bool
    {
        // ตรวจว่ามี org นี้จริง
        $check = $this->conn->prepare("SELECT id FROM organizations WHERE id = :org_id");
        $check->execute([':org_id' => $orgId]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) return false;

        if ($scopeOrgId !== null && $orgId !== $scopeOrgId) {
            // admin ห้ามย้ายออกนอก org ตัวเอง
            return false;
        }

        if ($scopeOrgId !== null) {
            // ป้องกันแก้ข้าม org (target ต้องอยู่ org เดียวกัน)
            $sql = "UPDATE {$this->table_name} SET org_id = :org_id WHERE id = :id AND org_id = :scope_org";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':scope_org', $scopeOrgId, PDO::PARAM_INT);
        } else {
            $sql = "UPDATE {$this->table_name} SET org_id = :org_id WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
        }

        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    /* ---------------------------------------------------------------- */
}