<?php
/**
 * Event Log Service
 * 
 * Centralized event logging, searching, filtering, archiving,
 * and compliance audit trail management.
 */

class EventLogService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Event severity levels
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';
    
    // Event categories
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_SYSTEM = 'system';
    const CATEGORY_PERFORMANCE = 'performance';
    const CATEGORY_INTEGRATION = 'integration';
    const CATEGORY_USER = 'user';
    const CATEGORY_DATA = 'data';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Log event
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $category Event category
     * @param string $eventType Event type
     * @param string $severity Severity level
     * @param array $details Event details
     * @param int $userId User ID (optional)
     * @return int Event ID
     */
    public function logEvent($portfolioId, $category, $eventType, $severity, array $details = [], $userId = null) {
        try {
            if (!$userId) {
                $userId = $this->userId;
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO event_logs (
                    portfolio_id,
                    user_id,
                    category,
                    event_type,
                    severity,
                    details,
                    ip_address,
                    user_agent,
                    timestamp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $portfolioId,
                $userId,
                $category,
                $eventType,
                $severity,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ]);
            
            $eventId = $this->pdo->lastInsertId();
            
            // Alert on critical events
            if ($severity === self::SEVERITY_CRITICAL) {
                $this->alertOnCriticalEvent($portfolioId, $eventType, $details);
            }
            
            return $eventId;
            
        } catch (Exception $e) {
            error_log("EventLogService::logEvent - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Search events with filtering
     * 
     * @param int $portfolioId Portfolio ID
     * @param array $filters Filters array
     * @param int $limit Results per page
     * @param int $offset Pagination offset
     * @return array Events and total count
     */
    public function searchEvents($portfolioId, $filters = [], $limit = 50, $offset = 0) {
        try {
            $where = ['portfolio_id = ?'];
            $params = [$portfolioId];
            
            // Add filters
            if (!empty($filters['category'])) {
                $where[] = 'category = ?';
                $params[] = $filters['category'];
            }
            
            if (!empty($filters['severity'])) {
                $where[] = 'severity = ?';
                $params[] = $filters['severity'];
            }
            
            if (!empty($filters['event_type'])) {
                $where[] = 'event_type LIKE ?';
                $params[] = '%' . $filters['event_type'] . '%';
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = 'user_id = ?';
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['start_date'])) {
                $where[] = 'timestamp >= ?';
                $params[] = $filters['start_date'];
            }
            
            if (!empty($filters['end_date'])) {
                $where[] = 'timestamp <= ?';
                $params[] = $filters['end_date'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Get total count
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM event_logs WHERE $whereClause");
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get events
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM event_logs
                WHERE $whereClause
                ORDER BY timestamp DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt->execute($params);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'total' => $total,
                'events' => $events,
                'offset' => $offset,
                'limit' => $limit
            ];
            
        } catch (Exception $e) {
            error_log("EventLogService::searchEvents - " . $e->getMessage());
            return ['total' => 0, 'events' => []];
        }
    }
    
    /**
     * Get event statistics
     */
    public function getEventStats($portfolioId, $days = 7) {
        try {
            $startDate = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            // Count by severity
            $stmt = $this->pdo->prepare("
                SELECT 
                    severity,
                    COUNT(*) as count
                FROM event_logs
                WHERE portfolio_id = ? AND timestamp > ?
                GROUP BY severity
            ");
            
            $stmt->execute([$portfolioId, $startDate]);
            $severityStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count by category
            $stmt = $this->pdo->prepare("
                SELECT 
                    category,
                    COUNT(*) as count
                FROM event_logs
                WHERE portfolio_id = ? AND timestamp > ?
                GROUP BY category
            ");
            
            $stmt->execute([$portfolioId, $startDate]);
            $categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get top event types
            $stmt = $this->pdo->prepare("
                SELECT 
                    event_type,
                    COUNT(*) as count
                FROM event_logs
                WHERE portfolio_id = ? AND timestamp > ?
                GROUP BY event_type
                ORDER BY count DESC
                LIMIT 10
            ");
            
            $stmt->execute([$portfolioId, $startDate]);
            $topEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'period_days' => $days,
                'by_severity' => $severityStats,
                'by_category' => $categoryStats,
                'top_events' => $topEvents
            ];
            
        } catch (PDOException $e) {
            error_log("EventLogService::getEventStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Archive old events
     */
    public function archiveOldEvents($portfolioId, $daysOld = 90) {
        try {
            $archiveDate = date('Y-m-d H:i:s', strtotime("-$daysOld days"));
            
            // Move events to archive table
            $stmt = $this->pdo->prepare("
                INSERT INTO event_logs_archive
                SELECT * FROM event_logs
                WHERE portfolio_id = ? AND timestamp < ?
            ");
            
            $stmt->execute([$portfolioId, $archiveDate]);
            $archivedCount = $stmt->rowCount();
            
            // Delete from main table
            $stmt = $this->pdo->prepare("
                DELETE FROM event_logs
                WHERE portfolio_id = ? AND timestamp < ?
            ");
            
            $stmt->execute([$portfolioId, $archiveDate]);
            
            return $archivedCount;
            
        } catch (Exception $e) {
            error_log("EventLogService::archiveOldEvents - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Export events to CSV
     */
    public function exportEventsCsv($portfolioId, $filters = []) {
        try {
            $result = $this->searchEvents($portfolioId, $filters, 10000, 0);
            $events = $result['events'];
            
            $csv = "Timestamp,Category,Event Type,Severity,User ID,IP Address,Details\n";
            
            foreach ($events as $event) {
                $details = json_decode($event['details'], true);
                $detailsStr = is_array($details) ? json_encode($details) : '';
                
                $csv .= sprintf(
                    "%s,%s,%s,%s,%s,%s,\"%s\"\n",
                    $event['timestamp'],
                    $event['category'],
                    $event['event_type'],
                    $event['severity'],
                    $event['user_id'],
                    $event['ip_address'],
                    addslashes($detailsStr)
                );
            }
            
            return $csv;
            
        } catch (Exception $e) {
            error_log("EventLogService::exportEventsCsv - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get compliance audit trail
     * 
     * Shows all user actions for compliance purposes
     */
    public function getComplianceAuditTrail($portfolioId, $userId = null, $days = 30) {
        try {
            $startDate = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            $where = ['portfolio_id = ?', 'category = ?', 'timestamp > ?'];
            $params = [$portfolioId, self::CATEGORY_USER, $startDate];
            
            if ($userId) {
                $where[] = 'user_id = ?';
                $params[] = $userId;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM event_logs
                WHERE $whereClause
                ORDER BY timestamp DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("EventLogService::getComplianceAuditTrail - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check for security events
     */
    public function getSecurityEvents($portfolioId, $hours = 24) {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM event_logs
                WHERE portfolio_id = ? 
                  AND category = ?
                  AND timestamp > ?
                ORDER BY timestamp DESC
            ");
            
            $stmt->execute([$portfolioId, self::CATEGORY_SECURITY, $startTime]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("EventLogService::getSecurityEvents - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get events for dashboard
     */
    public function getDashboardEvents($portfolioId, $limit = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM event_logs
                WHERE portfolio_id = ?
                ORDER BY timestamp DESC
                LIMIT ?
            ");
            
            $stmt->execute([$portfolioId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("EventLogService::getDashboardEvents - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Private: Alert on critical events
     */
    private function alertOnCriticalEvent($portfolioId, $eventType, $details) {
        // Trigger alerts based on critical event
        // This could integrate with AlertingService
    }
    
    /**
     * Purge very old archived events
     */
    public function purgeOldArchives($daysOld = 365) {
        try {
            $purgeDate = date('Y-m-d H:i:s', strtotime("-$daysOld days"));
            
            $stmt = $this->pdo->prepare("
                DELETE FROM event_logs_archive
                WHERE timestamp < ?
            ");
            
            $stmt->execute([$purgeDate]);
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("EventLogService::purgeOldArchives - " . $e->getMessage());
            return 0;
        }
    }
}
?>
