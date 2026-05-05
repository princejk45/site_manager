<?php
/**
 * Webhook Event Service
 * 
 * Real-time event streaming, webhook delivery with retry logic,
 * event filtering, and delivery tracking.
 */

class WebhookEventService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Event types
    const EVENT_ALERT_TRIGGERED = 'alert.triggered';
    const EVENT_ALERT_ESCALATED = 'alert.escalated';
    const EVENT_ALERT_RESOLVED = 'alert.resolved';
    const EVENT_METRIC_RECORDED = 'metric.recorded';
    const EVENT_ANOMALY_DETECTED = 'anomaly.detected';
    const EVENT_WORKFLOW_EXECUTED = 'workflow.executed';
    const EVENT_WEBSITE_DOWN = 'website.down';
    const EVENT_WEBSITE_UP = 'website.up';
    
    // Delivery status
    const STATUS_PENDING = 'pending';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_RETRYING = 'retrying';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Register webhook endpoint
     * 
     * @param int $portfolioId Portfolio ID
     * @param array $webhook Webhook configuration
     * @return int Webhook ID
     */
    public function registerWebhook($portfolioId, $webhook) {
        try {
            // Verify webhook URL is reachable
            $this->verifyWebhookUrl($webhook['url']);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO webhooks (
                    portfolio_id,
                    url,
                    events,
                    secret,
                    active,
                    headers,
                    retry_attempts,
                    retry_delay_seconds,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $secret = bin2hex(random_bytes(32));
            
            $stmt->execute([
                $portfolioId,
                $webhook['url'],
                json_encode($webhook['events'] ?? []),
                hash('sha256', $secret),
                $webhook['active'] ? 1 : 0,
                json_encode($webhook['headers'] ?? []),
                $webhook['retry_attempts'] ?? 3,
                $webhook['retry_delay_seconds'] ?? 300,
                $this->userId
            ]);
            
            $webhookId = $this->pdo->lastInsertId();
            $this->auditTrail->log('webhook_registered', 'portfolio_id=' . $portfolioId . ';webhook_id=' . $webhookId);
            
            return $webhookId;
            
        } catch (Exception $e) {
            error_log("WebhookEventService::registerWebhook - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Dispatch event to webhooks
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $eventType Event type
     * @param array $eventData Event payload
     * @return array Webhook delivery results
     */
    public function dispatchEvent($portfolioId, $eventType, $eventData) {
        try {
            // Get webhooks subscribed to this event
            $stmt = $this->pdo->prepare("
                SELECT * FROM webhooks
                WHERE portfolio_id = ? AND active = 1
            ");
            
            $stmt->execute([$portfolioId]);
            $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $deliveries = [];
            
            foreach ($webhooks as $webhook) {
                $events = json_decode($webhook['events'], true);
                
                // Check if webhook is subscribed to this event type
                if (!in_array($eventType, $events) && !in_array('*', $events)) {
                    continue;
                }
                
                // Create event record
                $eventId = $this->createEvent($portfolioId, $eventType, $eventData);
                
                // Create delivery record
                $deliveryId = $this->createDelivery($eventId, $webhook['id']);
                
                // Deliver webhook asynchronously
                $deliveries[] = [
                    'webhook_id' => $webhook['id'],
                    'delivery_id' => $deliveryId,
                    'status' => 'queued'
                ];
                
                // Queue for async delivery
                $this->queueWebhookDelivery($deliveryId, $webhook, $eventData);
            }
            
            return $deliveries;
            
        } catch (Exception $e) {
            error_log("WebhookEventService::dispatchEvent - " . $e->getMessage());
            return ['error' => 'Event dispatch failed'];
        }
    }
    
    /**
     * Deliver webhook with retry logic
     * 
     * @param int $deliveryId Delivery ID
     * @return bool Success
     */
    public function deliverWebhook($deliveryId) {
        try {
            // Get delivery details
            $stmt = $this->pdo->prepare("
                SELECT d.*, w.url, w.secret, w.headers, w.retry_attempts, e.event_data
                FROM webhook_deliveries d
                JOIN webhooks w ON d.webhook_id = w.id
                JOIN webhook_events e ON d.event_id = e.id
                WHERE d.id = ?
            ");
            
            $stmt->execute([$deliveryId]);
            $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$delivery) {
                return false;
            }
            
            $eventData = json_decode($delivery['event_data'], true);
            $headers = json_decode($delivery['headers'], true) ?? [];
            
            // Create signature
            $payload = json_encode($eventData);
            $signature = hash_hmac('sha256', $payload, $delivery['secret']);
            
            // Add webhook headers
            $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
            $headers['X-Webhook-Delivery'] = $deliveryId;
            $headers['Content-Type'] = 'application/json';
            
            // Send request
            $response = $this->sendWebhookRequest($delivery['url'], $payload, $headers);
            
            if ($response['success']) {
                // Mark as delivered
                $stmt = $this->pdo->prepare("
                    UPDATE webhook_deliveries
                    SET status = ?, response_code = ?, delivered_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([self::STATUS_DELIVERED, $response['status_code'], $deliveryId]);
                return true;
                
            } else {
                // Handle retry
                return $this->handleDeliveryFailure($deliveryId, $delivery, $response);
            }
            
        } catch (Exception $e) {
            error_log("WebhookEventService::deliverWebhook - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get webhook delivery history
     */
    public function getDeliveryHistory($webhookId, $limit = 100) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT d.*, e.event_type, e.created_at as event_time
                FROM webhook_deliveries d
                JOIN webhook_events e ON d.event_id = e.id
                WHERE d.webhook_id = ?
                ORDER BY d.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$webhookId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("WebhookEventService::getDeliveryHistory - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get webhook statistics
     */
    public function getWebhookStats($webhookId, $days = 7) {
        try {
            $startDate = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM webhook_deliveries
                WHERE webhook_id = ? AND created_at > ?
                GROUP BY status
            ");
            
            $stmt->execute([$webhookId, $startDate]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats = [
                'webhook_id' => $webhookId,
                'period_days' => $days,
                'delivered' => 0,
                'failed' => 0,
                'pending' => 0,
                'success_rate' => 0
            ];
            
            foreach ($results as $row) {
                $stats[$row['status']] = (int)$row['count'];
            }
            
            $total = $stats['delivered'] + $stats['failed'];
            $stats['success_rate'] = $total > 0 ? round(($stats['delivered'] / $total) * 100, 2) : 0;
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("WebhookEventService::getWebhookStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Disable webhook
     */
    public function disableWebhook($webhookId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE webhooks SET active = 0 WHERE id = ?");
            $stmt->execute([$webhookId]);
            return true;
        } catch (PDOException $e) {
            error_log("WebhookEventService::disableWebhook - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete webhook
     */
    public function deleteWebhook($webhookId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM webhooks WHERE id = ?");
            $stmt->execute([$webhookId]);
            return true;
        } catch (PDOException $e) {
            error_log("WebhookEventService::deleteWebhook - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Private: Verify webhook URL is valid
     */
    private function verifyWebhookUrl($url) {
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid webhook URL");
        }
        
        // Attempt connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
        
        @curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // URL should be accessible (allow redirects)
        if (!in_array($httpCode, [200, 301, 302, 404, 405])) {
            throw new Exception("Webhook URL not accessible (HTTP $httpCode)");
        }
    }
    
    /**
     * Private: Create event record
     */
    private function createEvent($portfolioId, $eventType, $eventData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO webhook_events (
                portfolio_id,
                event_type,
                event_data,
                created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([$portfolioId, $eventType, json_encode($eventData)]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Private: Create delivery record
     */
    private function createDelivery($eventId, $webhookId) {
        $stmt = $this->pdo->prepare("
            INSERT INTO webhook_deliveries (
                event_id,
                webhook_id,
                status,
                attempts,
                created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$eventId, $webhookId, self::STATUS_PENDING, 0]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Private: Queue webhook for async delivery
     */
    private function queueWebhookDelivery($deliveryId, $webhook, $eventData) {
        // Queue to job processor (e.g., Redis queue, database queue, etc.)
        // For now, return for later async processing
    }
    
    /**
     * Private: Send webhook HTTP request
     */
    private function sendWebhookRequest($url, $payload, $headers) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Set headers
        $headerArray = [];
        foreach ($headers as $key => $value) {
            $headerArray[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'success' => $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'response' => $response,
            'error' => $error
        ];
    }
    
    /**
     * Private: Handle delivery failure with retry
     */
    private function handleDeliveryFailure($deliveryId, $delivery, $response) {
        $maxAttempts = (int)$delivery['retry_attempts'];
        $currentAttempts = (int)$delivery['attempts'] + 1;
        
        if ($currentAttempts >= $maxAttempts) {
            // Mark as failed
            $stmt = $this->pdo->prepare("
                UPDATE webhook_deliveries
                SET status = ?, attempts = ?, response_code = ?
                WHERE id = ?
            ");
            
            $stmt->execute([self::STATUS_FAILED, $currentAttempts, $response['status_code'], $deliveryId]);
            return false;
        }
        
        // Mark for retry
        $nextRetry = date('Y-m-d H:i:s', time() + ((int)$delivery['retry_delay_seconds'] * $currentAttempts));
        
        $stmt = $this->pdo->prepare("
            UPDATE webhook_deliveries
            SET status = ?, attempts = ?, next_retry_at = ?, response_code = ?
            WHERE id = ?
        ");
        
        $stmt->execute([self::STATUS_RETRYING, $currentAttempts, $nextRetry, $response['status_code'], $deliveryId]);
        return false;
    }
}
?>
