<?php
/**
 * API Key Service
 * 
 * Manages API keys for third-party integrations and programmatic access.
 * Supports scoped permissions, rate limiting, and revocation.
 */

class ApiKeyService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Key types
    const TYPE_PERSONAL = 'personal';
    const TYPE_APPLICATION = 'application';
    const TYPE_WEBHOOK = 'webhook';
    
    // Scopes
    const SCOPE_READ = 'read';
    const SCOPE_WRITE = 'write';
    const SCOPE_DELETE = 'delete';
    const SCOPE_ADMIN = 'admin';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Create new API key
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $name Key name
     * @param array $scopes Permitted scopes
     * @param string $type Key type
     * @param int $expiresIn Days until expiration (0 for never)
     * @return array Key details
     */
    public function createApiKey($portfolioId, $name, $scopes = [], $type = self::TYPE_PERSONAL, $expiresIn = 0) {
        try {
            // Generate key
            $key = $this->generateSecureKey();
            $keyHash = hash('sha256', $key);
            
            // Calculate expiration
            $expiresAt = null;
            if ($expiresIn > 0) {
                $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresIn days"));
            }
            
            // Insert key
            $stmt = $this->pdo->prepare("
                INSERT INTO api_keys (
                    portfolio_id, 
                    name, 
                    key_hash, 
                    scopes, 
                    type,
                    expires_at,
                    created_by,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $portfolioId,
                $name,
                $keyHash,
                json_encode($scopes),
                $type,
                $expiresAt,
                $this->userId
            ]);
            
            $keyId = $this->pdo->lastInsertId();
            
            $this->auditTrail->log('api_key_created', 'portfolio_id=' . $portfolioId . ';key_id=' . $keyId);
            
            return [
                'id' => $keyId,
                'key' => $key, // Only shown once at creation
                'name' => $name,
                'scopes' => $scopes,
                'type' => $type,
                'expires_at' => $expiresAt
            ];
            
        } catch (PDOException $e) {
            error_log("ApiKeyService::createApiKey - " . $e->getMessage());
            throw new Exception("Failed to create API key");
        }
    }
    
    /**
     * Get API keys for portfolio
     * 
     * @param int $portfolioId Portfolio ID
     * @return array List of keys
     */
    public function getApiKeys($portfolioId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id, 
                    name, 
                    type,
                    scopes,
                    last_used_at,
                    expires_at,
                    status,
                    created_at
                FROM api_keys
                WHERE portfolio_id = ?
                ORDER BY created_at DESC
            ");
            
            $stmt->execute([$portfolioId]);
            $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($keys as &$key) {
                $key['scopes'] = json_decode($key['scopes'], true);
                $key['is_expired'] = $key['expires_at'] && strtotime($key['expires_at']) < time();
            }
            
            return $keys;
            
        } catch (PDOException $e) {
            error_log("ApiKeyService::getApiKeys - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate API key
     * 
     * @param string $key API key
     * @param string $requiredScope Required scope
     * @return bool Valid key
     */
    public function validateApiKey($key, $requiredScope = null) {
        try {
            $keyHash = hash('sha256', $key);
            
            $stmt = $this->pdo->prepare("
                SELECT id, scopes, expires_at, status
                FROM api_keys
                WHERE key_hash = ?
            ");
            
            $stmt->execute([$keyHash]);
            $keyRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$keyRecord) {
                return false;
            }
            
            // Check expiration
            if ($keyRecord['expires_at'] && strtotime($keyRecord['expires_at']) < time()) {
                return false;
            }
            
            // Check status
            if ($keyRecord['status'] !== 'active') {
                return false;
            }
            
            // Check scope
            if ($requiredScope) {
                $scopes = json_decode($keyRecord['scopes'], true);
                if (!in_array($requiredScope, $scopes)) {
                    return false;
                }
            }
            
            // Update last used
            $this->pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?")
                ->execute([$keyRecord['id']]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("ApiKeyService::validateApiKey - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revoke API key
     * 
     * @param int $keyId Key ID
     * @return bool Success
     */
    public function revokeApiKey($keyId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE api_keys 
                SET status = 'revoked', revoked_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$keyId]);
            
            $this->auditTrail->log('api_key_revoked', 'key_id=' . $keyId);
            return true;
            
        } catch (PDOException $e) {
            error_log("ApiKeyService::revokeApiKey - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate secure key
     * 
     * @return string Secure random key
     */
    private function generateSecureKey() {
        return 'fmd_' . bin2hex(random_bytes(24));
    }
    
    /**
     * Get key usage stats
     * 
     * @param int $keyId Key ID
     * @return array Usage statistics
     */
    public function getKeyStats($keyId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN response_code < 400 THEN 1 ELSE 0 END) as successful_requests,
                    SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as failed_requests,
                    MAX(created_at) as last_request
                FROM api_key_logs
                WHERE key_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $stmt->execute([$keyId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("ApiKeyService::getKeyStats - " . $e->getMessage());
            return [];
        }
    }
}
?>
