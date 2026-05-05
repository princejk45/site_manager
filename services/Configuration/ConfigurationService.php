<?php
/**
 * ConfigurationService
 * Environment-specific settings and secrets management
 */

namespace Services\Configuration;

use PDO;
use Exception;

class ConfigurationService
{
    private $db;
    private $environment;
    private $config_cache = [];

    public function __construct(PDO $db, $environment = 'production')
    {
        $this->db = $db;
        $this->environment = $environment;
    }

    /**
     * Get configuration value
     */
    public function get($key, $default = null)
    {
        // Check cache first
        if (isset($this->config_cache[$key])) {
            return $this->config_cache[$key];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT value, is_encrypted FROM configuration
                WHERE `key` = :key AND environment = :environment AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([
                ':key' => $key,
                ':environment' => $this->environment
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $value = $result['is_encrypted'] ? $this->decryptValue($result['value']) : $result['value'];
                $this->config_cache[$key] = $value;
                return $value;
            }

            return $default;
        } catch (\PDOException $e) {
            return $default;
        }
    }

    /**
     * Set configuration value
     */
    public function set($key, $value, $encrypt = false)
    {
        try {
            $encrypted_value = $encrypt ? $this->encryptValue($value) : $value;

            $stmt = $this->db->prepare("
                INSERT INTO configuration (`key`, value, environment, is_encrypted, is_active, created_at)
                VALUES (:key, :value, :environment, :is_encrypted, 1, NOW())
                ON DUPLICATE KEY UPDATE
                value = :value,
                is_encrypted = :is_encrypted,
                updated_at = NOW()
            ");

            $stmt->execute([
                ':key' => $key,
                ':value' => $encrypted_value,
                ':environment' => $this->environment,
                ':is_encrypted' => $encrypt ? 1 : 0
            ]);

            // Invalidate cache
            unset($this->config_cache[$key]);

            // Log change
            $this->logConfigurationChange($key, 'set', $encrypt);

            return ['status' => 'success', 'message' => "Configuration {$key} updated"];
        } catch (\PDOException $e) {
            throw new Exception("Failed to set configuration: " . $e->getMessage());
        }
    }

    /**
     * Get secret
     */
    public function getSecret($secret_name)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT secret_value FROM secrets
                WHERE secret_name = :name AND environment = :environment AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([
                ':name' => $secret_name,
                ':environment' => $this->environment
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Log secret access
                $this->logSecretAccess($secret_name, 'read');
                return $this->decryptValue($result['secret_value']);
            }

            return null;
        } catch (\PDOException $e) {
            throw new Exception("Failed to get secret: " . $e->getMessage());
        }
    }

    /**
     * Set secret
     */
    public function setSecret($secret_name, $secret_value, $metadata = [])
    {
        try {
            $encrypted_value = $this->encryptValue($secret_value);

            $stmt = $this->db->prepare("
                INSERT INTO secrets (secret_name, secret_value, environment, metadata, is_active, created_at)
                VALUES (:name, :value, :environment, :metadata, 1, NOW())
                ON DUPLICATE KEY UPDATE
                secret_value = :value,
                metadata = :metadata,
                updated_at = NOW()
            ");

            $stmt->execute([
                ':name' => $secret_name,
                ':value' => $encrypted_value,
                ':environment' => $this->environment,
                ':metadata' => json_encode($metadata)
            ]);

            $this->logSecretAccess($secret_name, 'write');

            return ['status' => 'success', 'message' => "Secret {$secret_name} stored"];
        } catch (\PDOException $e) {
            throw new Exception("Failed to set secret: " . $e->getMessage());
        }
    }

    /**
     * Rotate secret
     */
    public function rotateSecret($secret_name)
    {
        try {
            // Get current secret
            $current = $this->getSecret($secret_name);

            if (!$current) {
                throw new Exception("Secret not found");
            }

            // Archive old secret
            $stmt = $this->db->prepare("
                UPDATE secrets
                SET is_active = 0, archived_at = NOW()
                WHERE secret_name = :name AND environment = :environment AND is_active = 1
            ");
            $stmt->execute([
                ':name' => $secret_name,
                ':environment' => $this->environment
            ]);

            // Generate new secret
            $new_secret = bin2hex(random_bytes(32));

            // Store new secret
            $this->setSecret($secret_name, $new_secret, ['rotated_from_previous' => true]);

            // Log rotation
            $stmt = $this->db->prepare("
                INSERT INTO secret_rotations (secret_name, environment, rotated_at)
                VALUES (:name, :environment, NOW())
            ");
            $stmt->execute([
                ':name' => $secret_name,
                ':environment' => $this->environment
            ]);

            return ['status' => 'success', 'new_secret' => $new_secret];
        } catch (Exception $e) {
            throw new Exception("Failed to rotate secret: " . $e->getMessage());
        }
    }

    /**
     * Get all configuration for environment
     */
    public function getEnvironmentConfig()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT `key`, value, is_encrypted FROM configuration
                WHERE environment = :environment AND is_active = 1
                ORDER BY `key` ASC
            ");
            $stmt->execute([':environment' => $this->environment]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $config = [];
            foreach ($results as $row) {
                $value = $row['is_encrypted'] ? $this->decryptValue($row['value']) : $row['value'];
                $config[$row['key']] = $value;
            }

            return [
                'status' => 'success',
                'environment' => $this->environment,
                'configuration' => $config,
                'count' => count($config)
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get environment config: " . $e->getMessage());
        }
    }

    /**
     * Export configuration (for backup)
     */
    public function exportConfig($include_secrets = false)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT `key`, value, is_encrypted FROM configuration
                WHERE environment = :environment AND is_active = 1
                ORDER BY `key` ASC
            ");
            $stmt->execute([':environment' => $this->environment]);
            $config = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $export = [
                'environment' => $this->environment,
                'exported_at' => date('Y-m-d H:i:s'),
                'configuration' => $config
            ];

            if ($include_secrets) {
                $stmt = $this->db->prepare("
                    SELECT secret_name, secret_value FROM secrets
                    WHERE environment = :environment AND is_active = 1
                ");
                $stmt->execute([':environment' => $this->environment]);
                $secrets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $export['secrets'] = $secrets;
            }

            return [
                'status' => 'success',
                'export' => json_encode($export, JSON_PRETTY_PRINT)
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to export configuration: " . $e->getMessage());
        }
    }

    /**
     * Import configuration
     */
    public function importConfig($config_json, $overwrite = false)
    {
        try {
            $config = json_decode($config_json, true);

            if (!$config || !isset($config['configuration'])) {
                throw new Exception("Invalid configuration format");
            }

            $imported_count = 0;

            foreach ($config['configuration'] as $item) {
                if (!$overwrite) {
                    $stmt = $this->db->prepare("
                        SELECT id FROM configuration
                        WHERE key = :key AND environment = :environment
                    ");
                    $stmt->execute([
                        ':key' => $item['key'],
                        ':environment' => $this->environment
                    ]);

                    if ($stmt->fetch()) {
                        continue; // Skip if exists
                    }
                }

                $stmt = $this->db->prepare("
                    INSERT INTO configuration (`key`, value, environment, is_encrypted, is_active, created_at)
                    VALUES (:key, :value, :environment, :is_encrypted, 1, NOW())
                    ON DUPLICATE KEY UPDATE value = :value
                ");

                $stmt->execute([
                    ':key' => $item['key'],
                    ':value' => $item['value'],
                    ':environment' => $this->environment,
                    ':is_encrypted' => $item['is_encrypted'] ?? 0
                ]);

                $imported_count++;
            }

            // Clear cache
            $this->config_cache = [];

            return [
                'status' => 'success',
                'imported_count' => $imported_count,
                'message' => "Imported {$imported_count} configuration items"
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to import configuration: " . $e->getMessage());
        }
    }

    /**
     * Get configuration audit log
     */
    public function getAuditLog($limit = 100)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM configuration_audit_log
                WHERE environment = :environment
                ORDER BY created_at DESC
                LIMIT :limit
            ");
            $stmt->execute([
                ':environment' => $this->environment,
                ':limit' => $limit
            ]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'logs' => $logs,
                'count' => count($logs)
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get audit log: " . $e->getMessage());
        }
    }

    /**
     * Clear configuration cache
     */
    public function clearCache()
    {
        $this->config_cache = [];
        return ['status' => 'success', 'message' => 'Cache cleared'];
    }

    /**
     * Validate configuration
     */
    public function validateConfig($required_keys = [])
    {
        try {
            $missing = [];

            foreach ($required_keys as $key) {
                $value = $this->get($key);
                if ($value === null) {
                    $missing[] = $key;
                }
            }

            return [
                'status' => 'success',
                'valid' => empty($missing),
                'missing_keys' => $missing
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Log configuration change
     */
    private function logConfigurationChange($key, $action, $is_encrypted = false)
    {
        $stmt = $this->db->prepare("
            INSERT INTO configuration_audit_log (environment, `key`, action, is_encrypted, ip_address, user_agent, created_at)
            VALUES (:environment, :key, :action, :is_encrypted, :ip, :ua, NOW())
        ");
        $stmt->execute([
            ':environment' => $this->environment,
            ':key' => $key,
            ':action' => $action,
            ':is_encrypted' => $is_encrypted ? 1 : 0,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * Log secret access
     */
    private function logSecretAccess($secret_name, $action)
    {
        $stmt = $this->db->prepare("
            INSERT INTO secret_access_logs (secret_name, environment, action, ip_address, user_agent, accessed_at)
            VALUES (:name, :environment, :action, :ip, :ua, NOW())
        ");
        $stmt->execute([
            ':name' => $secret_name,
            ':environment' => $this->environment,
            ':action' => $action,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * Encrypt value
     */
    private function encryptValue($value)
    {
        // Simple encryption - in production use proper key management
        return base64_encode($value);
    }

    /**
     * Decrypt value
     */
    private function decryptValue($value)
    {
        return base64_decode($value);
    }
}
