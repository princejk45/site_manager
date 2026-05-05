<?php
/**
 * AnalyticsService
 * 
 * Collects, aggregates, and analyzes portfolio data.
 * Tracks trends, KPIs, and performance metrics over time.
 * 
 * @author Fullmidia
 * @version 1.0.0
 * @package Services\Analytics
 */

class AnalyticsService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    /**
     * Initialize AnalyticsService
     * 
     * @param PDO $pdo Database connection
     * @param ?AuditTrail $auditTrail Audit trail service (null when using static initialization)
     * @param int $userId User ID
     */
    public function __construct(PDO $pdo, ?AuditTrail $auditTrail, int $userId = 1) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Get portfolio overview statistics
     * 
     * @param int $userId User ID to get their websites
     * @param string $period Time period (today, week, month, year)
     * @return array Portfolio statistics
     */
    public function getPortfolioOverview(int $userId, $period = 'month') {
        try {
            // Get user's websites
            $stmt = $this->pdo->prepare("
                SELECT id, domain FROM websites 
                WHERE user_id = ? AND is_active = 1
                ORDER BY domain
            ");
            $stmt->execute([$userId]);
            $websites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($websites)) {
                return $this->getEmptyPortfolioStats();
            }
            
            $websiteIds = array_column($websites, 'id');
            $dateRange = $this->getDateRange($period);
            
            // Get health metrics summary
            $placeholders = str_repeat('?,', count($websiteIds) - 1) . '?';
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_metrics,
                    AVG(health_score) as avg_health,
                    MIN(health_score) as min_health,
                    MAX(health_score) as max_health,
                    SUM(CASE WHEN health_score >= 90 THEN 1 ELSE 0 END) as grade_a,
                    SUM(CASE WHEN health_score >= 80 AND health_score < 90 THEN 1 ELSE 0 END) as grade_b,
                    SUM(CASE WHEN health_score >= 70 AND health_score < 80 THEN 1 ELSE 0 END) as grade_c,
                    SUM(CASE WHEN health_score < 70 THEN 1 ELSE 0 END) as grade_below_c
                FROM health_metrics
                WHERE website_id IN ($placeholders)
                AND created_at >= ?
            ");
            
            $params = array_merge($websiteIds, [$dateRange['start']]);
            $stmt->execute($params);
            $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get bug summary
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_bugs,
                    SUM(CASE WHEN severity = 'CRITICAL' THEN 1 ELSE 0 END) as critical,
                    SUM(CASE WHEN severity = 'HIGH' THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN severity = 'MEDIUM' THEN 1 ELSE 0 END) as medium,
                    SUM(CASE WHEN severity = 'LOW' THEN 1 ELSE 0 END) as low,
                    SUM(CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END) as open_bugs
                FROM bug_reports_auto
                WHERE website_id IN ($placeholders)
                AND created_at >= ?
            ");
            
            $stmt->execute($params);
            $bugs = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get uptime data
            $stmt = $this->pdo->prepare("
                SELECT 
                    AVG(CAST(JSON_EXTRACT(components_data, '$.uptime.score') AS DECIMAL(5,2))) as avg_uptime,
                    COUNT(CASE WHEN CAST(JSON_EXTRACT(components_data, '$.uptime.score') AS DECIMAL(5,2)) >= 95 THEN 1 END) as excellent_uptime
                FROM health_metrics
                WHERE website_id IN ($placeholders)
                AND created_at >= ?
            ");
            
            $stmt->execute($params);
            $uptime = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get automation data
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_rules,
                    COUNT(*) as total_rules
                FROM automation_rules
                WHERE website_id IN ($placeholders)
            ");
            
            $websiteParams = $websiteIds;
            $stmt->execute($websiteParams);
            $automation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'websites_total' => count($websites),
                'websites' => $websites,
                'health_metrics' => $metrics,
                'bugs' => $bugs,
                'uptime' => $uptime,
                'automation' => $automation,
                'period' => $period,
                'date_range' => $dateRange
            ];
        } catch (PDOException $e) {
            error_log("Get portfolio overview error: " . $e->getMessage());
            return $this->getEmptyPortfolioStats();
        }
    }
    
    /**
     * Get website-specific analytics
     * 
     * @param int $websiteId Website ID
     * @param string $period Time period
     * @return array Website analytics
     */
    public function getWebsiteAnalytics($websiteId, $period = 'month') {
        try {
            $dateRange = $this->getDateRange($period);
            
            // Get health trend
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    AVG(health_score) as avg_score,
                    MIN(health_score) as min_score,
                    MAX(health_score) as max_score,
                    COUNT(*) as data_points
                FROM health_metrics
                WHERE website_id = ? AND created_at >= ?
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            
            $stmt->execute([$websiteId, $dateRange['start']]);
            $healthTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get component breakdown (latest)
            $stmt = $this->pdo->prepare("
                SELECT 
                    JSON_EXTRACT(components_data, '$.uptime.score') as uptime_score,
                    JSON_EXTRACT(components_data, '$.security.score') as security_score,
                    JSON_EXTRACT(components_data, '$.performance.score') as performance_score,
                    JSON_EXTRACT(components_data, '$.plugins.score') as plugins_score,
                    JSON_EXTRACT(components_data, '$.backup.score') as backup_score
                FROM health_metrics
                WHERE website_id = ? AND created_at >= ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            
            $stmt->execute([$websiteId, $dateRange['start']]);
            $components = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get bug timeline
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_bugs,
                    SUM(CASE WHEN severity = 'CRITICAL' THEN 1 ELSE 0 END) as critical_bugs
                FROM bug_reports_auto
                WHERE website_id = ? AND created_at >= ?
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            
            $stmt->execute([$websiteId, $dateRange['start']]);
            $bugTimeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get automation execution stats
            $stmt = $this->pdo->prepare("
                SELECT 
                    ar.name as rule_name,
                    COUNT(are.id) as total_executions,
                    SUM(CASE WHEN are.status = 'SUCCESS' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN are.status = 'FAILED' THEN 1 ELSE 0 END) as failed,
                    AVG(are.execution_time_ms) as avg_execution_time
                FROM automation_rules ar
                LEFT JOIN automation_rule_executions are ON ar.id = are.rule_id 
                    AND are.executed_at >= ?
                WHERE ar.website_id = ?
                GROUP BY ar.id, ar.name
                ORDER BY total_executions DESC
            ");
            
            $stmt->execute([$dateRange['start'], $websiteId]);
            $automationStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get performance metrics
            $stmt = $this->pdo->prepare("
                SELECT 
                    AVG(execution_time_ms) as avg_time,
                    MAX(execution_time_ms) as max_time,
                    MIN(execution_time_ms) as min_time
                FROM health_metrics
                WHERE website_id = ? AND created_at >= ?
            ");
            
            $stmt->execute([$websiteId, $dateRange['start']]);
            $performance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'health_trend' => $healthTrend,
                'components' => $components,
                'bug_timeline' => $bugTimeline,
                'automation_stats' => $automationStats,
                'performance' => $performance,
                'period' => $period,
                'date_range' => $dateRange
            ];
        } catch (PDOException $e) {
            error_log("Get website analytics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get KPI summary (Key Performance Indicators)
     * 
     * @param int $userId User ID
     * @return array KPI data
     */
    public function getKPISummary(int $userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM websites WHERE user_id = ? AND is_active = 1
            ");
            $stmt->execute([$userId]);
            $websites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($websites)) {
                return [];
            }
            
            $websiteIds = array_column($websites, 'id');
            $placeholders = str_repeat('?,', count($websiteIds) - 1) . '?';
            
            // Portfolio KPIs
            $stmt = $this->pdo->query("
                SELECT 
                    'Portfolio Health' as kpi,
                    ROUND(AVG(hm.health_score), 1) as value,
                    'score' as unit
                FROM health_metrics hm
                WHERE website_id IN ($placeholders)
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $kpis = [];
            if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $kpis[] = $result;
            }
            
            // Critical bugs
            $stmt = $this->pdo->prepare("
                SELECT 
                    'Critical Issues' as kpi,
                    COUNT(*) as value,
                    'count' as unit
                FROM bug_reports_auto
                WHERE website_id IN ($placeholders)
                AND severity = 'CRITICAL'
                AND resolved_at IS NULL
            ");
            $stmt->execute($websiteIds);
            $kpis[] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Uptime average
            $stmt = $this->pdo->prepare("
                SELECT 
                    'Average Uptime' as kpi,
                    ROUND(AVG(CAST(JSON_EXTRACT(components_data, '$.uptime.score') AS DECIMAL(5,2))), 1) as value,
                    '%' as unit
                FROM health_metrics
                WHERE website_id IN ($placeholders)
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute($websiteIds);
            $kpis[] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Active automation rules
            $stmt = $this->pdo->prepare("
                SELECT 
                    'Active Rules' as kpi,
                    COUNT(*) as value,
                    'count' as unit
                FROM automation_rules
                WHERE website_id IN ($placeholders)
                AND is_active = 1
            ");
            $stmt->execute($websiteIds);
            $kpis[] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return array_filter($kpis);
        } catch (PDOException $e) {
            error_log("Get KPI summary error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get comparison data between websites
     * 
     * @param int $userId User ID
     * @return array Comparison data
     */
    public function getComparison(int $userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    w.id,
                    w.domain,
                    hm.health_score,
                    (SELECT COUNT(*) FROM bug_reports_auto WHERE website_id = w.id AND resolved_at IS NULL) as open_bugs,
                    (SELECT COUNT(*) FROM automation_rules WHERE website_id = w.id AND is_active = 1) as active_rules
                FROM websites w
                LEFT JOIN (
                    SELECT website_id, health_score
                    FROM health_metrics
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                    ORDER BY created_at DESC
                    LIMIT 1
                ) hm ON w.id = hm.website_id
                WHERE w.user_id = ? AND w.is_active = 1
                ORDER BY hm.health_score DESC
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get comparison error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Record custom analytics event
     * 
     * @param int $websiteId Website ID
     * @param string $eventType Event type
     * @param array $metadata Event metadata
     * @return bool Success
     */
    public function recordEvent($websiteId, $eventType, $metadata = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO analytics_events (
                    website_id, event_type, metadata, created_at
                ) VALUES (?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $websiteId,
                $eventType,
                json_encode($metadata)
            ]);
        } catch (PDOException $e) {
            error_log("Record event error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get event timeline for website
     * 
     * @param int $websiteId Website ID
     * @param int $limit Records to return
     * @return array Events
     */
    public function getEventTimeline($websiteId, $limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM analytics_events
                WHERE website_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            
            $stmt->bindValue(1, $websiteId);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get event timeline error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Helper: Get date range for period
     * 
     * @param string $period Period (today, week, month, year)
     * @return array Start and end dates
     */
    private function getDateRange($period) {
        $end = date('Y-m-d 23:59:59');
        
        switch ($period) {
            case 'today':
                $start = date('Y-m-d 00:00:00');
                break;
            case 'week':
                $start = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case 'month':
                $start = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
            case 'year':
                $start = date('Y-m-d 00:00:00', strtotime('-365 days'));
                break;
            default:
                $start = date('Y-m-d 00:00:00', strtotime('-30 days'));
        }
        
        return ['start' => $start, 'end' => $end];
    }
    
    /**
     * Helper: Empty portfolio stats
     * 
     * @return array Empty stats structure
     */
    private function getEmptyPortfolioStats() {
        return [
            'websites_total' => 0,
            'websites' => [],
            'health_metrics' => [
                'total_metrics' => 0,
                'avg_health' => 0,
                'grade_a' => 0,
                'grade_b' => 0,
                'grade_c' => 0,
                'grade_below_c' => 0
            ],
            'bugs' => [
                'total_bugs' => 0,
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
                'open_bugs' => 0
            ],
            'uptime' => [
                'avg_uptime' => 0,
                'excellent_uptime' => 0
            ],
            'automation' => [
                'active_rules' => 0,
                'total_rules' => 0
            ]
        ];
    }
}
?>
