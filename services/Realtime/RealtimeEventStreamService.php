<?php
/**
 * Realtime Event Stream Service
 * 
 * WebSocket-based real-time event streaming with subscriptions,
 * filtering, and broadcast capabilities.
 */

class RealtimeEventStreamService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Stream types
    const STREAM_ALERTS = 'alerts';
    const STREAM_METRICS = 'metrics';
    const STREAM_LOGS = 'logs';
    const STREAM_NOTIFICATIONS = 'notifications';
    const STREAM_ALL = 'all';
    
    // Message types
    const MSG_SUBSCRIBE = 'subscribe';
    const MSG_UNSUBSCRIBE = 'unsubscribe';
    const MSG_EVENT = 'event';
    const MSG_PING = 'ping';
    const MSG_PONG = 'pong';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Register client connection for stream
     * 
     * @param string $connectionId Unique connection ID
     * @param int $portfolioId Portfolio ID
     * @param array $streams Streams to subscribe to
     * @return bool Success
     */
    public function registerConnection($connectionId, $portfolioId, $streams) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO event_stream_connections (
                    connection_id,
                    portfolio_id,
                    streams,
                    connected_at,
                    last_heartbeat
                ) VALUES (?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $connectionId,
                $portfolioId,
                json_encode($streams)
            ]);
            
            $this->auditTrail->log('stream_connected', 'connection_id=' . $connectionId);
            return true;
            
        } catch (Exception $e) {
            error_log("RealtimeEventStreamService::registerConnection - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Broadcast event to connected clients
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $streamType Stream type
     * @param array $eventData Event payload
     * @return int Number of connections notified
     */
    public function broadcastEvent($portfolioId, $streamType, $eventData) {
        try {
            // Get all connected clients subscribed to this stream
            $stmt = $this->pdo->prepare("
                SELECT connection_id, streams
                FROM event_stream_connections
                WHERE portfolio_id = ? AND active = 1
            ");
            
            $stmt->execute([$portfolioId]);
            $connections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $notified = 0;
            
            foreach ($connections as $connection) {
                $streams = json_decode($connection['streams'], true);
                
                // Check if connection is subscribed to this stream type
                if (in_array($streamType, $streams) || in_array(self::STREAM_ALL, $streams)) {
                    // Queue message for this connection
                    $this->queueMessage($connection['connection_id'], self::MSG_EVENT, $streamType, $eventData);
                    $notified++;
                }
            }
            
            // Log event
            $stmt = $this->pdo->prepare("
                INSERT INTO event_stream_logs (
                    portfolio_id,
                    stream_type,
                    event_data,
                    connections_notified,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$portfolioId, $streamType, json_encode($eventData), $notified]);
            
            return $notified;
            
        } catch (Exception $e) {
            error_log("RealtimeEventStreamService::broadcastEvent - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Subscribe connection to additional streams
     */
    public function subscribe($connectionId, $streamTypes) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT streams FROM event_stream_connections WHERE connection_id = ?
            ");
            
            $stmt->execute([$connectionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return false;
            }
            
            $streams = json_decode($result['streams'], true);
            $streams = array_unique(array_merge($streams, $streamTypes));
            
            $stmt = $this->pdo->prepare("
                UPDATE event_stream_connections
                SET streams = ?, last_activity = NOW()
                WHERE connection_id = ?
            ");
            
            $stmt->execute([json_encode($streams), $connectionId]);
            return true;
            
        } catch (Exception $e) {
            error_log("RealtimeEventStreamService::subscribe - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unsubscribe connection from streams
     */
    public function unsubscribe($connectionId, $streamTypes) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT streams FROM event_stream_connections WHERE connection_id = ?
            ");
            
            $stmt->execute([$connectionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return false;
            }
            
            $streams = json_decode($result['streams'], true);
            $streams = array_diff($streams, $streamTypes);
            
            $stmt = $this->pdo->prepare("
                UPDATE event_stream_connections
                SET streams = ?, last_activity = NOW()
                WHERE connection_id = ?
            ");
            
            $stmt->execute([json_encode($streams), $connectionId]);
            return true;
            
        } catch (Exception $e) {
            error_log("RealtimeEventStreamService::unsubscribe - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Terminate client connection
     */
    public function closeConnection($connectionId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE event_stream_connections
                SET active = 0, disconnected_at = NOW()
                WHERE connection_id = ?
            ");
            
            $stmt->execute([$connectionId]);
            $this->auditTrail->log('stream_disconnected', 'connection_id=' . $connectionId);
            return true;
            
        } catch (Exception $e) {
            error_log("RealtimeEventStreamService::closeConnection - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send heartbeat ping to connection
     */
    public function sendHeartbeat($connectionId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE event_stream_connections
                SET last_heartbeat = NOW()
                WHERE connection_id = ? AND active = 1
            ");
            
            $stmt->execute([$connectionId]);
            
            // Queue ping message
            $this->queueMessage($connectionId, self::MSG_PING, null, ['timestamp' => time()]);
            return true;
            
        } catch (Exception $e) {
            error_log("RealtimeEventStreamService::sendHeartbeat - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get pending messages for connection
     */
    public function getPendingMessages($connectionId, $limit = 100) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM event_stream_messages
                WHERE connection_id = ? AND delivered = 0
                ORDER BY created_at ASC
                LIMIT ?
            ");
            
            $stmt->execute([$connectionId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("RealtimeEventStreamService::getPendingMessages - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark message as delivered
     */
    public function markMessageDelivered($messageId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE event_stream_messages
                SET delivered = 1, delivered_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$messageId]);
            return true;
            
        } catch (Exception $e) {
            error_log("RealtimeEventStreamService::markMessageDelivered - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get connection stats
     */
    public function getConnectionStats($portfolioId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_connections,
                    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_connections,
                    COUNT(DISTINCT streams) as subscribed_streams
                FROM event_stream_connections
                WHERE portfolio_id = ?
            ");
            
            $stmt->execute([$portfolioId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get message stats
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN delivered = 0 THEN 1 ELSE 0 END) as pending_messages,
                    AVG(DATEDIFF(delivered_at, created_at)) as avg_delivery_time_seconds
                FROM event_stream_messages esm
                JOIN event_stream_connections esc ON esm.connection_id = esc.connection_id
                WHERE esc.portfolio_id = ?
            ");
            
            $stmt->execute([$portfolioId]);
            $msgStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return array_merge($stats, $msgStats);
            
        } catch (PDOException $e) {
            error_log("RealtimeEventStreamService::getConnectionStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Cleanup stale connections
     */
    public function cleanupStaleConnections($timeoutSeconds = 3600) {
        try {
            $cutoffTime = date('Y-m-d H:i:s', time() - $timeoutSeconds);
            
            $stmt = $this->pdo->prepare("
                UPDATE event_stream_connections
                SET active = 0, disconnected_at = NOW()
                WHERE last_heartbeat < ? AND active = 1
            ");
            
            $stmt->execute([$cutoffTime]);
            $affectedRows = $stmt->rowCount();
            
            // Clean up old messages
            $stmt = $this->pdo->prepare("
                DELETE FROM event_stream_messages
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            $stmt->execute();
            
            return $affectedRows;
            
        } catch (Exception $e) {
            error_log("RealtimeEventStreamService::cleanupStaleConnections - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Private: Queue message for connection
     */
    private function queueMessage($connectionId, $messageType, $streamType, $data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO event_stream_messages (
                    connection_id,
                    message_type,
                    stream_type,
                    data,
                    delivered,
                    created_at
                ) VALUES (?, ?, ?, ?, 0, NOW())
            ");
            
            $stmt->execute([
                $connectionId,
                $messageType,
                $streamType,
                json_encode($data)
            ]);
            
        } catch (Exception $e) {
            error_log("RealtimeEventStreamService::queueMessage - " . $e->getMessage());
        }
    }
    
    /**
     * Get stream activity history
     */
    public function getStreamActivity($portfolioId, $hours = 24) {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    stream_type,
                    COUNT(*) as event_count,
                    SUM(connections_notified) as total_notified,
                    MAX(created_at) as last_event
                FROM event_stream_logs
                WHERE portfolio_id = ? AND created_at > ?
                GROUP BY stream_type
                ORDER BY event_count DESC
            ");
            
            $stmt->execute([$portfolioId, $startTime]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("RealtimeEventStreamService::getStreamActivity - " . $e->getMessage());
            return [];
        }
    }
}
?>
