<?php
/**
 * EncryptionService
 * AES-256 encryption at rest for sensitive data with key management
 */

namespace Services\Security;

use PDO;
use Exception;

class EncryptionService
{
    private $db;
    private $config;
    private const CIPHER = 'AES-256-CBC';
    private const KEY_SIZE = 32; // 256 bits

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Encrypt sensitive data
     */
    public function encrypt($portfolio_id, $data, $data_type = 'generic')
    {
        try {
            $key = $this->getOrCreateKey($portfolio_id, $data_type);
            
            // Generate random IV
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
            
            // Encrypt
            $encrypted = openssl_encrypt($data, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
            
            // Combine IV + encrypted data and base64 encode
            $encrypted_data = base64_encode($iv . $encrypted);
            
            // Log encryption
            $this->logOperation($portfolio_id, 'encrypt', $data_type);
            
            return [
                'status' => 'success',
                'encrypted_data' => $encrypted_data,
                'key_version' => 1
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to encrypt data: " . $e->getMessage());
        }
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt($portfolio_id, $encrypted_data, $data_type = 'generic')
    {
        try {
            $key = $this->getOrCreateKey($portfolio_id, $data_type);
            
            // Decode base64
            $data = base64_decode($encrypted_data);
            
            // Extract IV
            $iv_length = openssl_cipher_iv_length(self::CIPHER);
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);
            
            // Decrypt
            $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
            
            if ($decrypted === false) {
                throw new Exception("Decryption failed");
            }
            
            // Log decryption
            $this->logOperation($portfolio_id, 'decrypt', $data_type);
            
            return [
                'status' => 'success',
                'data' => $decrypted
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to decrypt data: " . $e->getMessage());
        }
    }

    /**
     * Encrypt field in database
     */
    public function encryptField($portfolio_id, $table, $field, $id, $data, $data_type = 'database')
    {
        try {
            $encrypted = $this->encrypt($portfolio_id, $data, $data_type);
            
            // Update field
            $query = "UPDATE {$table} SET {$field} = :encrypted_data WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':encrypted_data' => $encrypted['encrypted_data'],
                ':id' => $id
            ]);
            
            return ['status' => 'success', 'message' => 'Field encrypted'];
        } catch (Exception $e) {
            throw new Exception("Failed to encrypt field: " . $e->getMessage());
        }
    }

    /**
     * Decrypt field from database
     */
    public function decryptField($portfolio_id, $table, $field, $id, $data_type = 'database')
    {
        try {
            $query = "SELECT {$field} FROM {$table} WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row || !$row[$field]) {
                return ['status' => 'error', 'message' => 'Field not found'];
            }
            
            $decrypted = $this->decrypt($portfolio_id, $row[$field], $data_type);
            return ['status' => 'success', 'data' => $decrypted['data']];
        } catch (Exception $e) {
            throw new Exception("Failed to decrypt field: " . $e->getMessage());
        }
    }

    /**
     * Rotate encryption keys
     */
    public function rotateKeys($portfolio_id, $data_types = [])
    {
        try {
            $types = empty($data_types) ? $this->getAllDataTypes($portfolio_id) : $data_types;
            $rotated = [];
            
            foreach ($types as $data_type) {
                // Generate new key
                $new_key = $this->generateKey();
                
                // Store old key as inactive
                $stmt = $this->db->prepare("
                    UPDATE encryption_keys
                    SET is_active = 0, rotated_at = NOW()
                    WHERE portfolio_id = :portfolio_id AND data_type = :data_type AND is_active = 1
                ");
                $stmt->execute([
                    ':portfolio_id' => $portfolio_id,
                    ':data_type' => $data_type
                ]);
                
                // Store new key
                $stmt = $this->db->prepare("
                    INSERT INTO encryption_keys (portfolio_id, data_type, key_data, is_active, created_at)
                    VALUES (:portfolio_id, :data_type, :key_data, 1, NOW())
                ");
                $stmt->execute([
                    ':portfolio_id' => $portfolio_id,
                    ':data_type' => $data_type,
                    ':key_data' => base64_encode($new_key)
                ]);
                
                $rotated[] = $data_type;
            }
            
            // Log rotation
            $this->logRotation($portfolio_id, $rotated);
            
            return [
                'status' => 'success',
                'rotated_types' => $rotated,
                'message' => 'Keys rotated successfully'
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to rotate keys: " . $e->getMessage());
        }
    }

    /**
     * Get encryption statistics
     */
    public function getEncryptionStats($portfolio_id = null)
    {
        try {
            $where = $portfolio_id ? "WHERE portfolio_id = :portfolio_id" : "";
            $params = $portfolio_id ? [':portfolio_id' => $portfolio_id] : [];
            
            $stmt = $this->db->prepare("
                SELECT
                    data_type,
                    COUNT(*) as total_keys,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_keys,
                    MAX(created_at) as last_rotation
                FROM encryption_keys
                {$where}
                GROUP BY data_type
            ");
            $stmt->execute($params);
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => 'success',
                'statistics' => $stats
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get encryption stats: " . $e->getMessage());
        }
    }

    /**
     * Export encrypted data
     */
    public function exportEncrypted($portfolio_id, $data_type)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, portfolio_id, data_type, encrypted_data, created_at
                FROM encrypted_storage
                WHERE portfolio_id = :portfolio_id AND data_type = :data_type
                ORDER BY created_at DESC
            ");
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':data_type' => $data_type
            ]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => 'success',
                'data_count' => count($data),
                'data' => $data
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to export encrypted data: " . $e->getMessage());
        }
    }

    /**
     * Get or create encryption key
     */
    private function getOrCreateKey($portfolio_id, $data_type)
    {
        $stmt = $this->db->prepare("
            SELECT key_data FROM encryption_keys
            WHERE portfolio_id = :portfolio_id AND data_type = :data_type AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':data_type' => $data_type
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return base64_decode($result['key_data']);
        }
        
        // Generate new key
        $new_key = $this->generateKey();
        
        $stmt = $this->db->prepare("
            INSERT INTO encryption_keys (portfolio_id, data_type, key_data, is_active, created_at)
            VALUES (:portfolio_id, :data_type, :key_data, 1, NOW())
        ");
        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':data_type' => $data_type,
            ':key_data' => base64_encode($new_key)
        ]);
        
        return $new_key;
    }

    /**
     * Generate encryption key
     */
    private function generateKey()
    {
        return openssl_random_pseudo_bytes(self::KEY_SIZE);
    }

    /**
     * Get all data types
     */
    private function getAllDataTypes($portfolio_id)
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT data_type FROM encryption_keys
            WHERE portfolio_id = :portfolio_id
        ");
        $stmt->execute([':portfolio_id' => $portfolio_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) { return $row['data_type']; }, $results);
    }

    /**
     * Log encryption operation
     */
    private function logOperation($portfolio_id, $operation, $data_type)
    {
        $stmt = $this->db->prepare("
            INSERT INTO encryption_logs (portfolio_id, operation, data_type, ip_address, user_agent, logged_at)
            VALUES (:portfolio_id, :operation, :data_type, :ip, :ua, NOW())
        ");
        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':operation' => $operation,
            ':data_type' => $data_type,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * Log key rotation
     */
    private function logRotation($portfolio_id, $rotated_types)
    {
        $stmt = $this->db->prepare("
            INSERT INTO key_rotation_logs (portfolio_id, rotated_types, ip_address, rotated_at)
            VALUES (:portfolio_id, :rotated_types, :ip, NOW())
        ");
        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':rotated_types' => json_encode($rotated_types),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }
}
