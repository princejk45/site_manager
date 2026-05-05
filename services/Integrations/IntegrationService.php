<?php
/**
 * Integration Service
 * 
 * Manages third-party integrations (Slack, Microsoft Teams, webhooks).
 * Handles connection validation, message delivery, and event dispatching.
 */

class IntegrationService {
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Supported integration platforms
    const PLATFORM_SLACK = 'slack';
    const PLATFORM_TEAMS = 'teams';
    const PLATFORM_WEBHOOK = 'webhook';
    
    // Integration statuses
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_ERROR = 'error';
    
    // Event types
    const EVENT_WEBSITE_DOWN = 'website_down';
    const EVENT_SECURITY_ALERT = 'security_alert';
    const EVENT_RULE_EXECUTED = 'rule_executed';
    const EVENT_REPORT_GENERATED = 'report_generated';
    const EVENT_BACKUP_FAILED = 'backup_failed';
    
    public function __construct(PDO $pdo, ?AuditTrail $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Add integration
     */
    public function addIntegration($platform, $name, $config, $events = []) {
        try {
            // Validate configuration
            $this->validateIntegrationConfig($platform, $config);
            
            // Test connection
            $testResult = $this->testConnection($platform, $config);
            if (!$testResult) {
                throw new Exception("Failed to connect to {$platform}");
            }
            
            // Encrypt sensitive data
            $encryptedConfig = $this->encryptConfig($config);
            
            // Store integration
            $stmt = $this->pdo->prepare("
                INSERT INTO integrations (user_id, platform, name, config, events, status, created_at)
                VALUES (:user_id, :platform, :name, :config, :events, :status, NOW())
            ");
            
            $result = $stmt->execute([
                ':user_id' => $this->userId,
                ':platform' => $platform,
                ':name' => $name,
                ':config' => $encryptedConfig,
                ':events' => json_encode($events),
                ':status' => self::STATUS_ACTIVE
            ]);
            
            if (!$result) {
                throw new Exception("Failed to save integration");
            }
            
            $integrationId = $this->pdo->lastInsertId();
            
            // Log to audit trail
            $this->auditTrail->log(
                $this->userId,
                'integration_added',
                'integrations',
                $integrationId,
                [],
                json_encode(['platform' => $platform, 'name' => $name])
            );
            
            return $integrationId;
        } catch (Exception $e) {
            throw new Exception("Error adding integration: " . $e->getMessage());
        }
    }
    
    /**
     * Get integrations
     */
    public function getIntegrations($platform = null) {
        try {
            $query = "SELECT * FROM integrations WHERE user_id = :user_id";
            $params = [':user_id' => $this->userId];
            
            if ($platform) {
                $query .= " AND platform = :platform";
                $params[':platform'] = $platform;
            }
            
            $query .= " ORDER BY created_at DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            $integrations = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['events'] = json_decode($row['events'], true) ?: [];
                $row['config'] = $this->maskSensitiveConfig($row['config']);
                $integrations[] = $row;
            }
            
            return $integrations;
        } catch (Exception $e) {
            throw new Exception("Error retrieving integrations: " . $e->getMessage());
        }
    }
    
    /**
     * Get integration by ID
     */
    public function getIntegration(int $id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM integrations 
                WHERE id = :id AND user_id = :user_id
            ");
            
            $stmt->execute([
                ':id' => $id,
                ':user_id' => $this->userId
            ]);
            
            $integration = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$integration) {
                throw new Exception("Integration not found");
            }
            
            $integration['events'] = json_decode($integration['events'], true) ?: [];
            
            return $integration;
        } catch (Exception $e) {
            throw new Exception("Error retrieving integration: " . $e->getMessage());
        }
    }
    
    /**
     * Update integration
     */
    public function updateIntegration(int $id, $name, $config, $events) {
        try {
            $integration = $this->getIntegration($id);
            
            // Validate configuration
            $this->validateIntegrationConfig($integration['platform'], $config);
            
            // Test connection if config changed
            $testResult = $this->testConnection($integration['platform'], $config);
            if (!$testResult) {
                throw new Exception("Failed to connect to {$integration['platform']}");
            }
            
            // Encrypt sensitive data
            $encryptedConfig = $this->encryptConfig($config);
            
            // Update integration
            $stmt = $this->pdo->prepare("
                UPDATE integrations 
                SET name = :name, config = :config, events = :events, updated_at = NOW()
                WHERE id = :id AND user_id = :user_id
            ");
            
            $result = $stmt->execute([
                ':name' => $name,
                ':config' => $encryptedConfig,
                ':events' => json_encode($events),
                ':id' => $id,
                ':user_id' => $this->userId
            ]);
            
            if (!$result) {
                throw new Exception("Failed to update integration");
            }
            
            // Log to audit trail
            $this->auditTrail->log(
                $this->userId,
                'integration_updated',
                'integrations',
                $id,
                ['events' => $integration['events']],
                json_encode(['events' => $events])
            );
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Error updating integration: " . $e->getMessage());
        }
    }
    
    /**
     * Delete integration
     */
    public function deleteIntegration(int $id) {
        try {
            $integration = $this->getIntegration($id);
            
            $stmt = $this->pdo->prepare("
                DELETE FROM integrations 
                WHERE id = :id AND user_id = :user_id
            ");
            
            $result = $stmt->execute([
                ':id' => $id,
                ':user_id' => $this->userId
            ]);
            
            if (!$result) {
                throw new Exception("Failed to delete integration");
            }
            
            // Log to audit trail
            $this->auditTrail->log(
                $this->userId,
                'integration_deleted',
                'integrations',
                $id,
                ['name' => $integration['name'], 'platform' => $integration['platform']],
                json_encode([])
            );
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Error deleting integration: " . $e->getMessage());
        }
    }
    
    /**
     * Send event notification
     */
    public function sendEvent($eventType, $data) {
        try {
            $integrations = $this->getIntegrations();
            $sent = 0;
            
            foreach ($integrations as $integration) {
                if ($integration['status'] !== self::STATUS_ACTIVE) {
                    continue;
                }
                
                $events = $integration['events'];
                if (!in_array('*', $events) && !in_array($eventType, $events)) {
                    continue;
                }
                
                // Decrypt config for use
                $config = $this->decryptConfig($integration['config']);
                
                $success = false;
                $error = null;
                
                try {
                    switch ($integration['platform']) {
                        case self::PLATFORM_SLACK:
                            $success = $this->sendSlackMessage($config, $eventType, $data);
                            break;
                        case self::PLATFORM_TEAMS:
                            $success = $this->sendTeamsMessage($config, $eventType, $data);
                            break;
                        case self::PLATFORM_WEBHOOK:
                            $success = $this->sendWebhook($config, $eventType, $data);
                            break;
                    }
                } catch (Exception $e) {
                    $success = false;
                    $error = $e->getMessage();
                }
                
                if ($success) {
                    $sent++;
                } else {
                    $this->updateIntegrationStatus($integration['id'], self::STATUS_ERROR, $error);
                }
                
                // Log event delivery
                $this->logEventDelivery($integration['id'], $eventType, $success, $error);
            }
            
            return $sent;
        } catch (Exception $e) {
            throw new Exception("Error sending event: " . $e->getMessage());
        }
    }
    
    /**
     * Send Slack message
     */
    private function sendSlackMessage($config, $eventType, $data) {
        try {
            if (empty($config['webhook_url'])) {
                throw new Exception("Missing Slack webhook URL");
            }
            
            $message = $this->formatMessage($eventType, $data);
            
            $payload = [
                'text' => $message['title'],
                'attachments' => [[
                    'color' => $message['color'],
                    'title' => $message['title'],
                    'text' => $message['body'],
                    'fields' => $this->formatFields($message['fields']),
                    'ts' => time()
                ]]
            ];
            
            $response = $this->postToUrl(
                $config['webhook_url'],
                json_encode($payload),
                ['Content-Type: application/json']
            );
            
            return $response !== false && strpos($response, 'ok') !== false;
        } catch (Exception $e) {
            throw new Exception("Slack error: " . $e->getMessage());
        }
    }
    
    /**
     * Send Teams message
     */
    private function sendTeamsMessage($config, $eventType, $data) {
        try {
            if (empty($config['webhook_url'])) {
                throw new Exception("Missing Teams webhook URL");
            }
            
            $message = $this->formatMessage($eventType, $data);
            
            $payload = [
                'title' => $message['title'],
                'summary' => $message['title'],
                'themeColor' => str_replace('#', '', $message['color']),
                'sections' => [[
                    'activityTitle' => $message['title'],
                    'text' => $message['body'],
                    'facts' => array_map(function($k, $v) {
                        return ['name' => $k, 'value' => $v];
                    }, array_keys($message['fields']), $message['fields'])
                ]]
            ];
            
            $response = $this->postToUrl(
                $config['webhook_url'],
                json_encode($payload),
                ['Content-Type: application/json']
            );
            
            return $response !== false && $response === '1';
        } catch (Exception $e) {
            throw new Exception("Teams error: " . $e->getMessage());
        }
    }
    
    /**
     * Send webhook
     */
    private function sendWebhook($config, $eventType, $data) {
        try {
            if (empty($config['url'])) {
                throw new Exception("Missing webhook URL");
            }
            
            $payload = [
                'event' => $eventType,
                'timestamp' => date('c'),
                'data' => $data,
                'signature' => $this->generateWebhookSignature($config['secret'] ?? '', $data)
            ];
            
            $headers = ['Content-Type: application/json'];
            if (!empty($config['headers'])) {
                $headers = array_merge($headers, (array)$config['headers']);
            }
            
            $response = $this->postToUrl(
                $config['url'],
                json_encode($payload),
                $headers
            );
            
            return $response !== false;
        } catch (Exception $e) {
            throw new Exception("Webhook error: " . $e->getMessage());
        }
    }
    
    /**
     * Format message
     */
    private function formatMessage($eventType, $data) {
        $messages = [
            self::EVENT_WEBSITE_DOWN => [
                'title' => '🔴 Website Down',
                'color' => '#dc3545',
                'body' => 'Website ' . ($data['domain'] ?? 'unknown') . ' is offline'
            ],
            self::EVENT_SECURITY_ALERT => [
                'title' => '🔒 Security Alert',
                'color' => '#fd7e14',
                'body' => 'Security issue detected: ' . ($data['issue'] ?? 'unknown')
            ],
            self::EVENT_RULE_EXECUTED => [
                'title' => '⚡ Automation Rule Executed',
                'color' => '#0066cc',
                'body' => 'Rule "' . ($data['rule_name'] ?? 'unknown') . '" executed'
            ],
            self::EVENT_REPORT_GENERATED => [
                'title' => '📊 Report Generated',
                'color' => '#28a745',
                'body' => ucfirst(str_replace('_', ' ', $data['report_type'] ?? 'Report')) . ' report ready'
            ],
            self::EVENT_BACKUP_FAILED => [
                'title' => '💾 Backup Failed',
                'color' => '#dc3545',
                'body' => 'Backup failed for ' . ($data['domain'] ?? 'unknown')
            ]
        ];
        
        $message = $messages[$eventType] ?? [
            'title' => 'Event: ' . $eventType,
            'color' => '#17a2b8',
            'body' => 'Event triggered'
        ];
        
        $message['fields'] = [
            'Timestamp' => date('Y-m-d H:i:s'),
            'Event' => $eventType,
            'Website' => $data['domain'] ?? 'N/A',
            'Details' => $data['details'] ?? 'N/A'
        ];
        
        return $message;
    }
    
    /**
     * Format fields for Slack
     */
    private function formatFields($fields) {
        $result = [];
        foreach ($fields as $key => $value) {
            $result[] = [
                'title' => $key,
                'value' => $value,
                'short' => strlen($value) < 50
            ];
        }
        return $result;
    }
    
    /**
     * Test connection
     */
    private function testConnection($platform, $config) {
        try {
            switch ($platform) {
                case self::PLATFORM_SLACK:
                    $response = $this->postToUrl(
                        $config['webhook_url'],
                        json_encode(['text' => 'Test message from Fullmidia']),
                        ['Content-Type: application/json']
                    );
                    return $response !== false && strpos($response, 'ok') !== false;
                
                case self::PLATFORM_TEAMS:
                    $response = $this->postToUrl(
                        $config['webhook_url'],
                        json_encode(['title' => 'Test message from Fullmidia']),
                        ['Content-Type: application/json']
                    );
                    return $response !== false && $response === '1';
                
                case self::PLATFORM_WEBHOOK:
                    $response = $this->postToUrl(
                        $config['url'],
                        json_encode(['test' => true]),
                        ['Content-Type: application/json']
                    );
                    return $response !== false;
                
                default:
                    return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Validate integration config
     */
    private function validateIntegrationConfig($platform, $config) {
        switch ($platform) {
            case self::PLATFORM_SLACK:
                if (empty($config['webhook_url'])) {
                    throw new Exception("Slack webhook URL is required");
                }
                if (!filter_var($config['webhook_url'], FILTER_VALIDATE_URL)) {
                    throw new Exception("Invalid Slack webhook URL");
                }
                break;
            
            case self::PLATFORM_TEAMS:
                if (empty($config['webhook_url'])) {
                    throw new Exception("Teams webhook URL is required");
                }
                if (!filter_var($config['webhook_url'], FILTER_VALIDATE_URL)) {
                    throw new Exception("Invalid Teams webhook URL");
                }
                break;
            
            case self::PLATFORM_WEBHOOK:
                if (empty($config['url'])) {
                    throw new Exception("Webhook URL is required");
                }
                if (!filter_var($config['url'], FILTER_VALIDATE_URL)) {
                    throw new Exception("Invalid webhook URL");
                }
                break;
            
            default:
                throw new Exception("Unsupported platform: $platform");
        }
    }
    
    /**
     * Update integration status
     */
    private function updateIntegrationStatus(int $id, $status, $error = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE integrations 
                SET status = :status, last_error = :error, last_checked = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':status' => $status,
                ':error' => $error,
                ':id' => $id
            ]);
        } catch (Exception $e) {
            // Silently fail
        }
    }
    
    /**
     * Log event delivery
     */
    private function logEventDelivery($integrationId, $eventType, $success, $error = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO integration_logs (integration_id, event_type, success, error, created_at)
                VALUES (:integration_id, :event_type, :success, :error, NOW())
            ");
            
            $stmt->execute([
                ':integration_id' => $integrationId,
                ':event_type' => $eventType,
                ':success' => $success ? 1 : 0,
                ':error' => $error
            ]);
        } catch (Exception $e) {
            // Silently fail
        }
    }
    
    /**
     * Post to URL
     */
    private function postToUrl($url, $data, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        return $response;
    }
    
    /**
     * Encrypt config
     */
    private function encryptConfig($config) {
        // Simple base64 encoding for now - use proper encryption in production
        return base64_encode(json_encode($config));
    }
    
    /**
     * Decrypt config
     */
    private function decryptConfig($encrypted) {
        return json_decode(base64_decode($encrypted), true);
    }
    
    /**
     * Mask sensitive config
     */
    private function maskSensitiveConfig($encrypted) {
        $config = $this->decryptConfig($encrypted);
        
        // Mask sensitive fields
        foreach (['webhook_url', 'url', 'secret', 'api_key'] as $field) {
            if (isset($config[$field])) {
                $config[$field] = substr($config[$field], 0, 10) . '...';
            }
        }
        
        return $config;
    }
    
    /**
     * Generate webhook signature
     */
    private function generateWebhookSignature($secret, $data) {
        if (empty($secret)) {
            return '';
        }
        
        return hash_hmac('sha256', json_encode($data), $secret);
    }
}
?>
