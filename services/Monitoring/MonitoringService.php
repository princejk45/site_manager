<?php
/**
 * Monitoring Service
 * 
 * System metrics collection, performance tracking, alerting integration,
 * monitoring dashboards, and SLA monitoring.
 */

class MonitoringService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Metric types
    const METRIC_CPU = 'cpu';
    const METRIC_MEMORY = 'memory';
    const METRIC_DISK = 'disk';
    const METRIC_NETWORK = 'network';
    const METRIC_DATABASE = 'database';
    const METRIC_HTTP = 'http';
    
    // Alert levels
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_CRITICAL = 'critical';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Record system metric
     */
    public function recordMetric($portfolioId, $metricType, $value, $metadata = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO monitoring_metrics (
                    portfolio_id,
                    metric_type,
                    `value`,
                    metadata,
                    recorded_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $portfolioId,
                $metricType,
                $value,
                json_encode($metadata)
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            error_log("MonitoringService::recordMetric - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get current system metrics
     */
    public function getSystemMetrics() {
        try {
            $metrics = [];
            
            // CPU usage
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $metrics['cpu'] = round($load[0] * 100, 2);
            }
            
            // Memory usage
            if (function_exists('memory_get_usage')) {
                $memUsage = memory_get_usage();
                $memLimit = ini_get('memory_limit');
                $memLimitBytes = $this->parseBytes($memLimit);
                
                $metrics['memory'] = [
                    'used' => round($memUsage / (1024 * 1024), 2),
                    'limit' => round($memLimitBytes / (1024 * 1024), 2),
                    'percent' => round(($memUsage / $memLimitBytes) * 100, 2)
                ];
            }
            
            // Disk usage
            if (function_exists('disk_free_space')) {
                $diskTotal = disk_total_space('/');
                $diskFree = disk_free_space('/');
                $diskUsed = $diskTotal - $diskFree;
                
                $metrics['disk'] = [
                    'used' => round($diskUsed / (1024 * 1024 * 1024), 2),
                    'free' => round($diskFree / (1024 * 1024 * 1024), 2),
                    'total' => round($diskTotal / (1024 * 1024 * 1024), 2),
                    'percent' => round(($diskUsed / $diskTotal) * 100, 2)
                ];
            }
            
            return $metrics;
            
        } catch (Exception $e) {
            error_log("MonitoringService::getSystemMetrics - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get metrics for portfolio
     */
    public function getMetrics($portfolioId, $metricType = null, $hours = 24) {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            
            $where = ['portfolio_id = ?', 'recorded_at > ?'];
            $params = [$portfolioId, $startTime];
            
            if ($metricType) {
                $where[] = 'metric_type = ?';
                $params[] = $metricType;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM monitoring_metrics
                WHERE $whereClause
                ORDER BY recorded_at DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("MonitoringService::getMetrics - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get metric statistics
     */
    public function getMetricStats($portfolioId, $metricType, $hours = 24) {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    AVG(`value`) as average,
                    MIN(`value`) as minimum,
                    MAX(`value`) as maximum,
                    STDDEV(`value`) as stddev,
                    COUNT(*) as data_points
                FROM monitoring_metrics
                WHERE portfolio_id = ? AND metric_type = ? AND recorded_at > ?
            ");
            
            $stmt->execute([$portfolioId, $metricType, $startTime]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("MonitoringService::getMetricStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create monitoring alert
     */
    public function createAlert($portfolioId, $alert) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO monitoring_alerts (
                    portfolio_id,
                    metric_type,
                    `condition`,
                    threshold,
                    `level`,
                    message,
                    enabled,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $portfolioId,
                $alert['metric_type'],
                $alert['condition'] ?? '>',  // >, <, >=, <=, ==, !=
                $alert['threshold'],
                $alert['level'] ?? self::LEVEL_WARNING,
                $alert['message'],
                1,
                $this->userId
            ]);
            
            $alertId = $this->pdo->lastInsertId();
            $this->auditTrail->log('monitoring_alert_created', 'alert_id=' . $alertId);
            
            return $alertId;
            
        } catch (Exception $e) {
            error_log("MonitoringService::createAlert - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check alerts against metrics
     */
    public function checkAlerts($portfolioId) {
        try {
            // Get active alerts
            $stmt = $this->pdo->prepare("
                SELECT * FROM monitoring_alerts
                WHERE portfolio_id = ? AND enabled = 1
            ");
            
            $stmt->execute([$portfolioId]);
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $triggered = [];
            
            foreach ($alerts as $alert) {
                // Get latest metric
                $stmt = $this->pdo->prepare("
                    SELECT `value` FROM monitoring_metrics
                    WHERE portfolio_id = ? AND metric_type = ?
                    ORDER BY recorded_at DESC
                    LIMIT 1
                ");
                
                $stmt->execute([$portfolioId, $alert['metric_type']]);
                $metric = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($metric && $this->evaluateCondition($metric['value'], $alert['condition'], $alert['threshold'])) {
                    $triggered[] = $alert;
                    $this->recordAlertTriggered($alert['id'], $metric['value']);
                }
            }
            
            return $triggered;
            
        } catch (Exception $e) {
            error_log("MonitoringService::checkAlerts - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get monitoring dashboard
     */
    public function getDashboard($portfolioId) {
        try {
            $dashboard = [
                'timestamp' => time(),
                'portfolio_id' => $portfolioId
            ];
            
            // Current metrics
            $dashboard['current_metrics'] = [];
            
            foreach ([self::METRIC_CPU, self::METRIC_MEMORY, self::METRIC_DISK] as $type) {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM monitoring_metrics
                    WHERE portfolio_id = ? AND metric_type = ?
                    ORDER BY recorded_at DESC
                    LIMIT 1
                ");
                
                $stmt->execute([$portfolioId, $type]);
                $metric = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($metric) {
                    $dashboard['current_metrics'][$type] = $metric;
                }
            }
            
            // Recent alerts
            $stmt = $this->pdo->prepare("
                SELECT * FROM monitoring_alerts_triggered
                WHERE portfolio_id = ?
                ORDER BY triggered_at DESC
                LIMIT 10
            ");
            
            $stmt->execute([$portfolioId]);
            $dashboard['recent_alerts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // SLA stats
            $dashboard['sla'] = $this->calculateSLA($portfolioId);
            
            return $dashboard;
            
        } catch (Exception $e) {
            error_log("MonitoringService::getDashboard - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get SLA statistics
     */
    public function calculateSLA($portfolioId, $days = 30) {
        try {
            $startDate = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            // Get uptime
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_measurements,
                    SUM(CASE WHEN `value` >= 95 THEN 1 ELSE 0 END) as healthy_measurements
                FROM monitoring_metrics
                WHERE portfolio_id = ? AND metric_type = 'uptime' AND recorded_at > ?
            ");
            
            $stmt->execute([$portfolioId, $startDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $uptime = $result['total_measurements'] > 0 ?
                round(($result['healthy_measurements'] / $result['total_measurements']) * 100, 2) : 0;
            
            return [
                'uptime_percent' => $uptime,
                'target_sla' => 99.9,
                'compliant' => $uptime >= 99.9,
                'period_days' => $days
            ];
            
        } catch (Exception $e) {
            error_log("MonitoringService::calculateSLA - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get monitoring report
     */
    public function generateReport($portfolioId, $days = 30) {
        try {
            $startDate = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            $report = [
                'portfolio_id' => $portfolioId,
                'period_days' => $days,
                'generated_at' => date('Y-m-d H:i:s'),
                'metrics' => [],
                'alerts' => [],
                'sla' => $this->calculateSLA($portfolioId, $days)
            ];
            
            // Metric summaries
            foreach ([self::METRIC_CPU, self::METRIC_MEMORY, self::METRIC_DISK, self::METRIC_HTTP] as $type) {
                $report['metrics'][$type] = $this->getMetricStats($portfolioId, $type, $days * 24);
            }
            
            // Alert summary
            $stmt = $this->pdo->prepare("
                SELECT 
                    `level`,
                    COUNT(*) as count
                FROM monitoring_alerts_triggered
                WHERE portfolio_id = ? AND triggered_at > ?
                GROUP BY `level`
            ");
            
            $stmt->execute([$portfolioId, $startDate]);
            $report['alerts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $report;
            
        } catch (Exception $e) {
            error_log("MonitoringService::generateReport - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Private: Evaluate condition
     */
    private function evaluateCondition($value, $condition, $threshold) {
        switch ($condition) {
            case '>':
                return $value > $threshold;
            case '<':
                return $value < $threshold;
            case '>=':
                return $value >= $threshold;
            case '<=':
                return $value <= $threshold;
            case '==':
                return $value == $threshold;
            case '!=':
                return $value != $threshold;
            default:
                return false;
        }
    }
    
    /**
     * Private: Record alert triggered
     */
    private function recordAlertTriggered($alertId, $value) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO monitoring_alerts_triggered (
                    alert_id,
                    `value`,
                    triggered_at
                ) VALUES (?, ?, NOW())
            ");
            
            $stmt->execute([$alertId, $value]);
            
        } catch (Exception $e) {
            error_log("MonitoringService::recordAlertTriggered - " . $e->getMessage());
        }
    }
    
    /**
     * Private: Parse byte strings
     */
    private function parseBytes($value) {
        $unit = strtoupper(substr($value, -1));
        $value = (int)$value;
        
        switch ($unit) {
            case 'K':
                return $value * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'G':
                return $value * 1024 * 1024 * 1024;
            default:
                return $value;
        }
    }
}
?>
