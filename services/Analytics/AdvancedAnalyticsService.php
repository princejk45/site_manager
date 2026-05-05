<?php
/**
 * Advanced Analytics Service
 * 
 * Real-time analytics with custom metrics, performance trending,
 * anomaly detection, and comprehensive reporting capabilities.
 */

class AdvancedAnalyticsService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    private $cacheExpiry = 3600; // 1 hour cache
    
    // Metric types
    const METRIC_UPTIME = 'uptime';
    const METRIC_RESPONSE_TIME = 'response_time';
    const METRIC_CPU = 'cpu_usage';
    const METRIC_MEMORY = 'memory_usage';
    const METRIC_BANDWIDTH = 'bandwidth';
    const METRIC_API_CALLS = 'api_calls';
    const METRIC_ERROR_RATE = 'error_rate';
    const METRIC_CUSTOM = 'custom';
    
    // Aggregation intervals
    const INTERVAL_MINUTE = 'minute';
    const INTERVAL_HOUR = 'hour';
    const INTERVAL_DAY = 'day';
    const INTERVAL_WEEK = 'week';
    const INTERVAL_MONTH = 'month';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Record metric data
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $metric Metric name
     * @param float $value Metric value
     * @param array $tags Optional tags for segmentation
     * @return bool Success
     */
    public function recordMetric($portfolioId, $metric, $value, $tags = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO analytics_metrics (
                    portfolio_id,
                    metric_name,
                    metric_value,
                    tags,
                    recorded_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $portfolioId,
                $metric,
                $value,
                json_encode($tags)
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("AdvancedAnalyticsService::recordMetric - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get real-time metrics
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $metric Metric name
     * @param int $minutes Time range in minutes
     * @return array Metric data
     */
    public function getRealtimeMetrics($portfolioId, $metric, $minutes = 60) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    metric_value,
                    tags,
                    recorded_at
                FROM analytics_metrics
                WHERE portfolio_id = ? 
                    AND metric_name = ?
                    AND recorded_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ORDER BY recorded_at ASC
            ");
            
            $stmt->execute([$portfolioId, $metric, $minutes]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($data as &$row) {
                $row['tags'] = json_decode($row['tags'], true);
            }
            
            return [
                'metric' => $metric,
                'data' => $data,
                'count' => count($data),
                'time_range_minutes' => $minutes
            ];
            
        } catch (PDOException $e) {
            error_log("AdvancedAnalyticsService::getRealtimeMetrics - " . $e->getMessage());
            return ['error' => 'Failed to retrieve metrics'];
        }
    }
    
    /**
     * Calculate trend data
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $metric Metric name
     * @param string $interval Aggregation interval
     * @param int $periods Number of periods
     * @return array Trend data with statistics
     */
    public function calculateTrend($portfolioId, $metric, $interval = self::INTERVAL_DAY, $periods = 30) {
        try {
            // Map interval to SQL date format
            $dateFormat = $this->getDateFormat($interval);
            $timeRange = $this->getTimeRange($interval, $periods);
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE_FORMAT(recorded_at, ?) as period,
                    COUNT(*) as count,
                    AVG(metric_value) as average,
                    MIN(metric_value) as minimum,
                    MAX(metric_value) as maximum,
                    STDDEV(metric_value) as stddev
                FROM analytics_metrics
                WHERE portfolio_id = ? 
                    AND metric_name = ?
                    AND recorded_at > ?
                GROUP BY DATE_FORMAT(recorded_at, ?)
                ORDER BY period ASC
            ");
            
            $stmt->execute([$dateFormat, $portfolioId, $metric, $timeRange, $dateFormat]);
            $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate trend slope (linear regression)
            $slope = $this->calculateTrendSlope($trends);
            
            return [
                'metric' => $metric,
                'interval' => $interval,
                'periods' => $periods,
                'trends' => $trends,
                'trend_slope' => $slope,
                'direction' => $slope > 0 ? 'up' : 'down'
            ];
            
        } catch (PDOException $e) {
            error_log("AdvancedAnalyticsService::calculateTrend - " . $e->getMessage());
            return ['error' => 'Failed to calculate trend'];
        }
    }
    
    /**
     * Get performance summary
     * 
     * @param int $portfolioId Portfolio ID
     * @param int $days Number of days to analyze
     * @return array Performance summary
     */
    public function getPerformanceSummary($portfolioId, $days = 7) {
        try {
            $startDate = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            // Get key metrics
            $stmt = $this->pdo->prepare("
                SELECT 
                    metric_name,
                    COUNT(*) as count,
                    AVG(metric_value) as average,
                    MIN(metric_value) as minimum,
                    MAX(metric_value) as maximum
                FROM analytics_metrics
                WHERE portfolio_id = ? AND recorded_at > ?
                GROUP BY metric_name
            ");
            
            $stmt->execute([$portfolioId, $startDate]);
            $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate aggregates
            $summary = [];
            foreach ($metrics as $metric) {
                $summary[$metric['metric_name']] = [
                    'average' => round($metric['average'], 2),
                    'min' => round($metric['minimum'], 2),
                    'max' => round($metric['maximum'], 2),
                    'data_points' => $metric['count']
                ];
            }
            
            return [
                'portfolio_id' => $portfolioId,
                'period_days' => $days,
                'metrics' => $summary,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (PDOException $e) {
            error_log("AdvancedAnalyticsService::getPerformanceSummary - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Detect anomalies using statistical methods
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $metric Metric name
     * @param float $threshold Standard deviations (default: 3)
     * @return array Anomalies detected
     */
    public function detectAnomalies($portfolioId, $metric, $threshold = 3.0) {
        try {
            // Get recent data
            $stmt = $this->pdo->prepare("
                SELECT 
                    metric_value,
                    recorded_at
                FROM analytics_metrics
                WHERE portfolio_id = ? 
                    AND metric_name = ?
                    AND recorded_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY recorded_at DESC
                LIMIT 100
            ");
            
            $stmt->execute([$portfolioId, $metric]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($data) < 10) {
                return ['anomalies' => [], 'reason' => 'Insufficient data for analysis'];
            }
            
            // Calculate statistics
            $values = array_column($data, 'metric_value');
            $mean = array_sum($values) / count($values);
            $variance = array_sum(array_map(function($x) use ($mean) {
                return pow($x - $mean, 2);
            }, $values)) / count($values);
            $stddev = sqrt($variance);
            
            // Find anomalies
            $anomalies = [];
            foreach ($data as $row) {
                $zscore = ($row['metric_value'] - $mean) / ($stddev ?: 1);
                if (abs($zscore) > $threshold) {
                    $anomalies[] = [
                        'value' => $row['metric_value'],
                        'timestamp' => $row['recorded_at'],
                        'zscore' => round($zscore, 2),
                        'severity' => abs($zscore) > ($threshold * 2) ? 'critical' : 'warning'
                    ];
                }
            }
            
            return [
                'metric' => $metric,
                'anomalies' => $anomalies,
                'mean' => round($mean, 2),
                'stddev' => round($stddev, 2),
                'threshold_zscore' => $threshold
            ];
            
        } catch (Exception $e) {
            error_log("AdvancedAnalyticsService::detectAnomalies - " . $e->getMessage());
            return ['error' => 'Anomaly detection failed'];
        }
    }
    
    /**
     * Create custom dashboard
     * 
     * @param int $portfolioId Portfolio ID
     * @param array $dashboard Dashboard configuration
     * @return int Dashboard ID
     */
    public function createDashboard($portfolioId, $dashboard) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO analytics_dashboards (
                    portfolio_id,
                    name,
                    description,
                    layout,
                    widgets,
                    is_default,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $portfolioId,
                $dashboard['name'],
                $dashboard['description'] ?? null,
                $dashboard['layout'] ?? 'grid',
                json_encode($dashboard['widgets'] ?? []),
                $dashboard['is_default'] ? 1 : 0,
                $this->userId
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("AdvancedAnalyticsService::createDashboard - " . $e->getMessage());
            throw new Exception("Failed to create dashboard");
        }
    }
    
    /**
     * Get dashboard
     */
    public function getDashboard($dashboardId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM analytics_dashboards WHERE id = ?");
            $stmt->execute([$dashboardId]);
            $dashboard = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dashboard) {
                $dashboard['widgets'] = json_decode($dashboard['widgets'], true);
            }
            
            return $dashboard;
            
        } catch (PDOException $e) {
            error_log("AdvancedAnalyticsService::getDashboard - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all dashboards for portfolio
     */
    public function getDashboards($portfolioId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM analytics_dashboards 
                WHERE portfolio_id = ?
                ORDER BY is_default DESC, created_at DESC
            ");
            
            $stmt->execute([$portfolioId]);
            $dashboards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($dashboards as &$dashboard) {
                $dashboard['widgets'] = json_decode($dashboard['widgets'], true);
            }
            
            return $dashboards;
            
        } catch (PDOException $e) {
            error_log("AdvancedAnalyticsService::getDashboards - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Export analytics to CSV
     */
    public function exportToCSV($portfolioId, $metric, $startDate, $endDate) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    metric_name,
                    metric_value,
                    tags,
                    recorded_at
                FROM analytics_metrics
                WHERE portfolio_id = ? 
                    AND metric_name = ?
                    AND recorded_at BETWEEN ? AND ?
                ORDER BY recorded_at ASC
            ");
            
            $stmt->execute([$portfolioId, $metric, $startDate, $endDate]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Generate CSV
            $csv = "Metric,Value,Tags,Timestamp\n";
            foreach ($data as $row) {
                $tags = json_encode(json_decode($row['tags'], true));
                $csv .= "{$row['metric_name']},{$row['metric_value']},\"{$tags}\",{$row['recorded_at']}\n";
            }
            
            return [
                'csv' => $csv,
                'rows' => count($data),
                'filename' => "analytics_{$metric}_" . date('Ymd_His') . ".csv"
            ];
            
        } catch (PDOException $e) {
            error_log("AdvancedAnalyticsService::exportToCSV - " . $e->getMessage());
            return ['error' => 'Export failed'];
        }
    }
    
    /**
     * Helper: Get date format for interval
     */
    private function getDateFormat($interval) {
        switch ($interval) {
            case self::INTERVAL_MINUTE:
                return '%Y-%m-%d %H:%i';
            case self::INTERVAL_HOUR:
                return '%Y-%m-%d %H:00';
            case self::INTERVAL_DAY:
                return '%Y-%m-%d';
            case self::INTERVAL_WEEK:
                return '%x-%v'; // Year-week
            case self::INTERVAL_MONTH:
                return '%Y-%m';
            default:
                return '%Y-%m-%d';
        }
    }
    
    /**
     * Helper: Get time range
     */
    private function getTimeRange($interval, $periods) {
        switch ($interval) {
            case self::INTERVAL_MINUTE:
                return date('Y-m-d H:i:s', strtotime("-$periods minutes"));
            case self::INTERVAL_HOUR:
                return date('Y-m-d H:i:s', strtotime("-$periods hours"));
            case self::INTERVAL_DAY:
                return date('Y-m-d H:i:s', strtotime("-$periods days"));
            case self::INTERVAL_WEEK:
                return date('Y-m-d H:i:s', strtotime("-$periods weeks"));
            case self::INTERVAL_MONTH:
                return date('Y-m-d H:i:s', strtotime("-$periods months"));
            default:
                return date('Y-m-d H:i:s', strtotime("-$periods days"));
        }
    }
    
    /**
     * Calculate trend slope (simple linear regression)
     */
    private function calculateTrendSlope($trends) {
        if (count($trends) < 2) {
            return 0;
        }
        
        $n = count($trends);
        $x = array_keys($trends);
        $y = array_column($trends, 'average');
        
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        return round($slope, 4);
    }
    
    /**
     * Generate performance report
     */
    public function generateReport($portfolioId, $reportType = 'summary', $days = 30) {
        try {
            $report = [
                'portfolio_id' => $portfolioId,
                'type' => $reportType,
                'period_days' => $days,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            switch ($reportType) {
                case 'summary':
                    $report['data'] = $this->getPerformanceSummary($portfolioId, $days);
                    break;
                case 'trend':
                    $report['data'] = $this->calculateTrend($portfolioId, self::METRIC_RESPONSE_TIME, self::INTERVAL_DAY, $days);
                    break;
                default:
                    $report['data'] = [];
            }
            
            $this->auditTrail->log('report_generated', 'portfolio_id=' . $portfolioId . ';type=' . $reportType);
            
            return $report;
            
        } catch (Exception $e) {
            error_log("AdvancedAnalyticsService::generateReport - " . $e->getMessage());
            return ['error' => 'Report generation failed'];
        }
    }
}
?>
