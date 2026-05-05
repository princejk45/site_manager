<?php
/**
 * ReportGenerator
 * 
 * Generates comprehensive reports from analytics data.
 * Supports multiple formats: HTML, PDF, CSV, JSON
 * 
 * @author Fullmidia
 * @version 1.0.0
 * @package Services\Analytics
 */

class ReportGenerator {
    
    private $pdo;
    private $analyticsService;
    private $auditTrail;
    private $userId;
    
    /**
     * Initialize ReportGenerator
     * 
     * @param PDO $pdo Database connection
     * @param AnalyticsService $analyticsService Analytics service
     * @param ?AuditTrail $auditTrail Audit trail service (null when using static initialization)
     * @param int $userId User ID
     */
    public function __construct(PDO $pdo, $analyticsService, ?AuditTrail $auditTrail, int $userId = 1) {
        $this->pdo = $pdo;
        $this->analyticsService = $analyticsService;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Generate portfolio health report
     * 
     * @param int $userId User ID
     * @param string $period Time period
     * @param string $format Output format (html, csv, json)
     * @return mixed Report data or string
     */
    public function generatePortfolioHealthReport(int $userId, $period = 'month', $format = 'html') {
        $data = $this->analyticsService->getPortfolioOverview($userId, $period);
        
        $report = [
            'title' => 'Portfolio Health Report',
            'generated_at' => date('Y-m-d H:i:s'),
            'period' => $period,
            'data' => $data
        ];
        
        return $this->formatReport($report, $format);
    }
    
    /**
     * Generate website performance report
     * 
     * @param int $websiteId Website ID
     * @param string $period Time period
     * @param string $format Output format
     * @return mixed Report data
     */
    public function generateWebsitePerformanceReport($websiteId, $period = 'month', $format = 'html') {
        $analytics = $this->analyticsService->getWebsiteAnalytics($websiteId, $period);
        
        // Get website info
        $stmt = $this->pdo->prepare("SELECT domain FROM websites WHERE id = ?");
        $stmt->execute([$websiteId]);
        $website = $stmt->fetch();
        
        $report = [
            'title' => 'Website Performance Report',
            'website' => $website['domain'] ?? 'Unknown',
            'generated_at' => date('Y-m-d H:i:s'),
            'period' => $period,
            'health_trend' => $analytics['health_trend'] ?? [],
            'components' => $analytics['components'] ?? [],
            'bug_timeline' => $analytics['bug_timeline'] ?? [],
            'automation_stats' => $analytics['automation_stats'] ?? [],
            'performance' => $analytics['performance'] ?? []
        ];
        
        return $this->formatReport($report, $format);
    }
    
    /**
     * Generate uptime report
     * 
     * @param int $userId User ID
     * @param string $period Time period
     * @param string $format Output format
     * @return mixed Report data
     */
    public function generateUptimeReport(int $userId, $period = 'month', $format = 'html') {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, domain FROM websites WHERE user_id = ? AND is_active = 1
            ");
            $stmt->execute([$userId]);
            $websites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $uptimeData = [];
            
            foreach ($websites as $website) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        DATE(created_at) as date,
                        AVG(CAST(JSON_EXTRACT(components_data, '$.uptime.score') AS DECIMAL(5,2))) as uptime
                    FROM health_metrics
                    WHERE website_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC
                ");
                
                $stmt->execute([$website['id']]);
                $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $uptimeData[] = [
                    'website' => $website['domain'],
                    'trend' => $trend,
                    'average' => array_sum(array_column($trend, 'uptime')) / count($trend)
                ];
            }
            
            $report = [
                'title' => 'Uptime Report',
                'generated_at' => date('Y-m-d H:i:s'),
                'period' => $period,
                'data' => $uptimeData
            ];
            
            return $this->formatReport($report, $format);
        } catch (Exception $e) {
            error_log("Generate uptime report error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate security report
     * 
     * @param int $userId User ID
     * @param string $period Time period
     * @param string $format Output format
     * @return mixed Report data
     */
    public function generateSecurityReport(int $userId, $period = 'month', $format = 'html') {
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
            
            // Security issues summary
            $stmt = $this->pdo->prepare("
                SELECT 
                    'Security' as category,
                    COUNT(*) as total,
                    SUM(CASE WHEN severity = 'CRITICAL' THEN 1 ELSE 0 END) as critical
                FROM bug_reports_auto
                WHERE website_id IN ($placeholders)
                AND category = 'Security'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $stmt->execute($websiteIds);
            $securityIssues = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Average security score
            $stmt = $this->pdo->prepare("
                SELECT AVG(CAST(JSON_EXTRACT(components_data, '$.security.score') AS DECIMAL(5,2))) as avg_score
                FROM health_metrics
                WHERE website_id IN ($placeholders)
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $stmt->execute($websiteIds);
            $securityScore = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $report = [
                'title' => 'Security Report',
                'generated_at' => date('Y-m-d H:i:s'),
                'period' => $period,
                'issues_summary' => $securityIssues,
                'average_score' => $securityScore['avg_score'] ?? 0,
                'recommendations' => $this->getSecurityRecommendations($websiteIds)
            ];
            
            return $this->formatReport($report, $format);
        } catch (Exception $e) {
            error_log("Generate security report error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate automation activity report
     * 
     * @param int $userId User ID
     * @param string $period Time period
     * @param string $format Output format
     * @return mixed Report data
     */
    public function generateAutomationReport(int $userId, $period = 'month', $format = 'html') {
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
            
            // Total automation activity
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_executions,
                    SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed,
                    AVG(execution_time_ms) as avg_time
                FROM automation_rule_executions
                WHERE website_id IN ($placeholders)
                AND executed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $stmt->execute($websiteIds);
            $automationStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Top performing rules
            $stmt = $this->pdo->prepare("
                SELECT 
                    ar.name,
                    COUNT(are.id) as executions,
                    SUM(CASE WHEN are.status = 'SUCCESS' THEN 1 ELSE 0 END) as successful
                FROM automation_rules ar
                LEFT JOIN automation_rule_executions are ON ar.id = are.rule_id
                WHERE ar.website_id IN ($placeholders)
                AND are.executed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY ar.id, ar.name
                ORDER BY executions DESC
                LIMIT 10
            ");
            
            $stmt->execute($websiteIds);
            $topRules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $report = [
                'title' => 'Automation Activity Report',
                'generated_at' => date('Y-m-d H:i:s'),
                'period' => $period,
                'statistics' => $automationStats,
                'top_rules' => $topRules
            ];
            
            return $this->formatReport($report, $format);
        } catch (Exception $e) {
            error_log("Generate automation report error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Format report for output
     * 
     * @param array $report Report data
     * @param string $format Output format
     * @return mixed Formatted report
     */
    private function formatReport($report, $format) {
        switch ($format) {
            case 'json':
                return json_encode($report, JSON_PRETTY_PRINT);
            
            case 'csv':
                return $this->generateCSV($report);
            
            case 'html':
            default:
                return $this->generateHTML($report);
        }
    }
    
    /**
     * Generate HTML report
     * 
     * @param array $report Report data
     * @return string HTML
     */
    private function generateHTML($report) {
        return sprintf('
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>%s</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0066cc; margin-bottom: 10px; }
        .meta { color: #666; font-size: 0.9em; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .section { margin-bottom: 30px; }
        .section h2 { color: #333; font-size: 1.3em; border-left: 4px solid #0066cc; padding-left: 15px; margin-bottom: 15px; }
        table { width: 100%%; border-collapse: collapse; margin-top: 10px; }
        table th { background: #f0f0f0; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #0066cc; }
        table td { padding: 12px; border-bottom: 1px solid #eee; }
        table tr:hover { background: #f9f9f9; }
        .metric { display: inline-block; margin: 15px 30px 15px 0; }
        .metric-value { font-size: 2em; font-weight: bold; color: #0066cc; }
        .metric-label { color: #666; font-size: 0.9em; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 0.85em; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>%s</h1>
        <div class="meta">
            Generated: %s<br>
            Period: %s
        </div>
        
        <div class="section">
            <h2>Overview</h2>
            %s
        </div>
        
        <div class="footer">
            <p>© 2025 Fullmidia. This is a confidential report generated from your portfolio analytics.</p>
        </div>
    </div>
</body>
</html>
        ', 
            htmlspecialchars($report['title']),
            htmlspecialchars($report['title']),
            $report['generated_at'],
            $report['period'],
            $this->renderReportContent($report)
        );
    }
    
    /**
     * Render report content
     * 
     * @param array $report Report data
     * @return string HTML content
     */
    private function renderReportContent($report) {
        $content = '';
        
        // Render metrics
        if (isset($report['data'])) {
            if (isset($report['data']['websites_total'])) {
                $content .= '<div class="metric"><div class="metric-value">' . $report['data']['websites_total'] . '</div><div class="metric-label">Websites</div></div>';
                $content .= '<div class="metric"><div class="metric-value">' . round($report['data']['health_metrics']['avg_health'] ?? 0, 1) . '</div><div class="metric-label">Avg Health Score</div></div>';
                $content .= '<div class="metric"><div class="metric-value">' . ($report['data']['bugs']['critical'] ?? 0) . '</div><div class="metric-label">Critical Issues</div></div>';
            }
        }
        
        return $content;
    }
    
    /**
     * Generate CSV report
     * 
     * @param array $report Report data
     * @return string CSV data
     */
    private function generateCSV($report) {
        $csv = "Report: " . $report['title'] . "\n";
        $csv .= "Generated: " . $report['generated_at'] . "\n";
        $csv .= "Period: " . $report['period'] . "\n\n";
        
        if (isset($report['data'])) {
            // Flatten data for CSV
            $data = $report['data'];
            foreach ($data as $key => $value) {
                if (is_scalar($value)) {
                    $csv .= "$key," . $value . "\n";
                }
            }
        }
        
        return $csv;
    }
    
    /**
     * Get security recommendations
     * 
     * @param array $websiteIds Website IDs
     * @return array Recommendations
     */
    private function getSecurityRecommendations($websiteIds) {
        $recommendations = [];
        
        if (count($websiteIds) > 0) {
            $placeholders = str_repeat('?,', count($websiteIds) - 1) . '?';
            
            // Check for critical issues
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count FROM bug_reports_auto
                WHERE website_id IN ($placeholders)
                AND category = 'Security'
                AND severity = 'CRITICAL'
                AND resolved_at IS NULL
            ");
            
            $stmt->execute($websiteIds);
            $criticalCount = $stmt->fetch()['count'];
            
            if ($criticalCount > 0) {
                $recommendations[] = "Address $criticalCount critical security issues immediately";
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Save report for later retrieval
     * 
     * @param string $type Report type
     * @param int $userId User ID
     * @param string $format Report format
     * @param string $data Report data
     * @return int Report ID
     */
    public function saveReport($type, int $userId, $format, $data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO generated_reports (
                    user_id, report_type, format, data, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $success = $stmt->execute([
                $userId,
                $type,
                $format,
                $data
            ]);
            
            if ($success) {
                return $this->pdo->lastInsertId();
            }
            
            return 0;
        } catch (PDOException $e) {
            error_log("Save report error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get saved reports
     * 
     * @param int $userId User ID
     * @param int $limit Number of records
     * @return array Saved reports
     */
    public function getSavedReports(int $userId, $limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM generated_reports
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            
            $stmt->bindValue(1, $userId);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get saved reports error: " . $e->getMessage());
            return [];
        }
    }
}
?>
