<?php
class User
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function login($username, $password)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $this->updateLastLogin($user['id']);
            return $user;
        }
        return false;
    }

    private function updateLastLogin($userId)
    {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }

    public function changePassword($userId, $currentPassword, $newPassword)
    {
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($currentPassword, $user['password_hash'])) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            return $stmt->execute([$newHash, $userId]);
        }
        return false;
    }

    public function getUserById($userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public function createUser($data)
    {
        // First check if username exists
        $check = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$data['username']]);

        if ($check->fetch()) {
            throw new Exception("Il nome utente esiste già");
        }

        $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash, email, role, is_active) VALUES (?, ?, ?, ?, 1)");
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        return $stmt->execute([
            $data['username'],
            $hash,
            $data['email'], // Now required
            $data['role']
        ]);
    }

    public function updateUser($data)
    {
        // Check if password is being updated
        if (!empty($data['password'])) {
            $stmt = $this->pdo->prepare("UPDATE users SET username = ?, role = ?, email = ?, is_active = ?, password = ? WHERE id = ?");
            return $stmt->execute([
                $data['username'],
                $data['role'],
                $data['email'],
                $data['is_active'],
                $data['password'],
                $data['id']
            ]);
        } else {
            // Update without password
            $stmt = $this->pdo->prepare("UPDATE users SET username = ?, role = ?, email = ?, is_active = ? WHERE id = ?");
            return $stmt->execute([
                $data['username'],
                $data['role'],
                $data['email'],
                $data['is_active'],
                $data['id']
            ]);
        }
    }

    public function getAllUsers()
    {
        $stmt = $this->pdo->prepare("SELECT id, username, email, role, is_active FROM users WHERE id != ? ORDER BY username");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll();
    }

    public function hasPermission($userId, $requiredRole)
    {
        $user = $this->getUserById($userId);
        if (!$user || !$user['is_active']) return false;

        $roleHierarchy = [
            'viewer' => 1,
            'manager' => 2,
            'super_admin' => 3
        ];

        return isset($roleHierarchy[$user['role']]) &&
            $roleHierarchy[$user['role']] >= $roleHierarchy[$requiredRole];
    }

    public function getUserByEmail($email)
    {
        $stmt = $this->pdo->prepare("SELECT id, username, email FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function savePasswordResetToken($userId, $token, $expiresAt)
    {
        // First, ensure the table exists
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(255) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX (token),
                    INDEX (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Exception $e) {
            error_log("Error creating password_resets table: " . $e->getMessage());
        }

        // Then insert the token
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = NOW()
            ");
            $result = $stmt->execute([$userId, $token, $expiresAt]);
            error_log("Password reset token saved for user $userId, token: $token, expires: $expiresAt");
            return $result;
        } catch (Exception $e) {
            error_log("Error saving password reset token: " . $e->getMessage());
            return false;
        }
    }

    public function getPasswordResetToken($token)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT pr.*, u.id, u.email, u.username 
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE pr.token = ? AND pr.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch();

            if (!$result) {
                // Check if token exists but is expired
                $checkStmt = $this->pdo->prepare("SELECT expires_at FROM password_resets WHERE token = ?");
                $checkStmt->execute([$token]);
                $checkResult = $checkStmt->fetch();

                if ($checkResult) {
                    error_log("Token found but expired: {$checkResult['expires_at']}");
                } else {
                    error_log("Token not found in database: $token");
                }
            } else {
                error_log("Token verified successfully for user {$result['username']}");
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error getting password reset token: " . $e->getMessage());
            return null;
        }
    }

    public function updatePasswordByToken($token, $newPassword)
    {
        try {
            // Get user id from token
            $stmt = $this->pdo->prepare("SELECT user_id FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            $result = $stmt->fetch();

            if (!$result) {
                return false;
            }

            $userId = $result['user_id'];
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            return $stmt->execute([$newHash, $userId]);
        } catch (Exception $e) {
            error_log("Error updating password by token: " . $e->getMessage());
            return false;
        }
    }

    public function deletePasswordResetToken($token)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            return $stmt->execute([$token]);
        } catch (Exception $e) {
            error_log("Error deleting password reset token: " . $e->getMessage());
            return false;
        }
    }
}
