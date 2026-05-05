<?php
/**
 * AnalyticsController
 * 
 * Provides analytics and reporting endpoints.
 * Generates dashboards, reports, and data exports.
 * 
 * @author Fullmidia
 * @version 1.0.0
 * @package Controllers
 */

class AnalyticsController {
    
    protected $analyticsService;
    protected $reportGenerator;
    protected $websiteModel;
    protected $pdo;
    protected $userId;
    protected $auditTrail;
    
    /**
     * Initialize controller
     */
    public function __construct(PDO $pdo, $userId = null, $auditTrail = null) {
        $this->pdo = $pdo;
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $this->auditTrail = $auditTrail;
        
        // Load required services
        require_once 'services/Analytics/AnalyticsService.php';
        require_once 'services/Analytics/ReportGenerator.php';
        require_once 'models/Website.php';
        
        // Initialize services
        $this->analyticsService = new AnalyticsService($this->pdo, $this->auditTrail, $this->userId);
        $this->reportGenerator = new ReportGenerator($this->pdo, $this->analyticsService, $this->auditTrail, $this->userId);
        $this->websiteModel = new Website($this->pdo);
    }
    
    /**
     * Get portfolio overview
     * AJAX endpoint
     */
    public function getPortfolioOverview() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $period = $this->request['period'] ?? 'month';
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        $overview = $this->analyticsService->getPortfolioOverview($this->userId, $period);
        
        return $this->jsonSuccess([
            'overview' => $overview
        ]);
    }
    
    /**
     * Get website analytics
     * AJAX endpoint
     */
    public function getWebsiteAnalytics() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $websiteId = $this->request['website_id'] ?? null;
        $period = $this->request['period'] ?? 'month';
        
        if (!$websiteId || !$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        $analytics = $this->analyticsService->getWebsiteAnalytics($websiteId, $period);
        
        return $this->jsonSuccess([
            'analytics' => $analytics
        ]);
    }
    
    /**
     * Get KPI summary
     * AJAX endpoint
     */
    public function getKPISummary() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        $kpis = $this->analyticsService->getKPISummary($this->userId);
        
        return $this->jsonSuccess([
            'kpis' => $kpis
        ]);
    }
    
    /**
     * Get website comparison
     * AJAX endpoint
     */
    public function getComparison() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        $comparison = $this->analyticsService->getComparison($this->userId);
        
        return $this->jsonSuccess([
            'comparison' => $comparison
        ]);
    }
    
    /**
     * Generate portfolio health report
     * AJAX endpoint
     */
    public function generatePortfolioHealthReport() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $period = $this->request['period'] ?? 'month';
        $format = $this->request['format'] ?? 'html';
        
        if (!in_array($format, ['html', 'csv', 'json'])) {
            return $this->jsonError('Invalid format', 400);
        }
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        try {
            $report = $this->reportGenerator->generatePortfolioHealthReport($this->userId, $period, $format);
            
            // Save report for record
            $reportId = $this->reportGenerator->saveReport('portfolio_health', $this->userId, $format, $report);
            
            $this->auditTrail->log(
                $this->userId,
                'analytics_report_generated',
                'report',
                $reportId,
                ['type' => 'portfolio_health', 'period' => $period, 'format' => $format]
            );
            
            if ($format === 'html') {
                return $this->jsonSuccess([
                    'report_id' => $reportId,
                    'html' => $report
                ]);
            } else {
                return $this->jsonSuccess([
                    'report_id' => $reportId,
                    'data' => $report
                ]);
            }
        } catch (Exception $e) {
            return $this->jsonError('Report generation failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate website performance report
     * AJAX endpoint
     */
    public function generateWebsitePerformanceReport() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $websiteId = $this->request['website_id'] ?? null;
        $period = $this->request['period'] ?? 'month';
        $format = $this->request['format'] ?? 'html';
        
        if (!$websiteId || !$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        if (!in_array($format, ['html', 'csv', 'json'])) {
            return $this->jsonError('Invalid format', 400);
        }
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        try {
            $report = $this->reportGenerator->generateWebsitePerformanceReport($websiteId, $period, $format);
            
            $reportId = $this->reportGenerator->saveReport('website_performance', $this->userId, $format, $report);
            
            $this->auditTrail->log(
                $this->userId,
                'analytics_report_generated',
                'report',
                $reportId,
                ['type' => 'website_performance', 'website_id' => $websiteId, 'period' => $period]
            );
            
            if ($format === 'html') {
                return $this->jsonSuccess(['html' => $report, 'report_id' => $reportId]);
            } else {
                return $this->jsonSuccess(['data' => $report, 'report_id' => $reportId]);
            }
        } catch (Exception $e) {
            return $this->jsonError('Report generation failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate uptime report
     * AJAX endpoint
     */
    public function generateUptimeReport() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $period = $this->request['period'] ?? 'month';
        $format = $this->request['format'] ?? 'html';
        
        if (!in_array($format, ['html', 'csv', 'json'])) {
            return $this->jsonError('Invalid format', 400);
        }
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        try {
            $report = $this->reportGenerator->generateUptimeReport($this->userId, $period, $format);
            
            $reportId = $this->reportGenerator->saveReport('uptime', $this->userId, $format, $report);
            
            $this->auditTrail->log(
                $this->userId,
                'analytics_report_generated',
                'report',
                $reportId,
                ['type' => 'uptime', 'period' => $period]
            );
            
            if ($format === 'html') {
                return $this->jsonSuccess(['html' => $report, 'report_id' => $reportId]);
            } else {
                return $this->jsonSuccess(['data' => $report, 'report_id' => $reportId]);
            }
        } catch (Exception $e) {
            return $this->jsonError('Report generation failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate security report
     * AJAX endpoint
     */
    public function generateSecurityReport() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $period = $this->request['period'] ?? 'month';
        $format = $this->request['format'] ?? 'html';
        
        if (!in_array($format, ['html', 'csv', 'json'])) {
            return $this->jsonError('Invalid format', 400);
        }
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        try {
            $report = $this->reportGenerator->generateSecurityReport($this->userId, $period, $format);
            
            $reportId = $this->reportGenerator->saveReport('security', $this->userId, $format, $report);
            
            $this->auditTrail->log(
                $this->userId,
                'analytics_report_generated',
                'report',
                $reportId,
                ['type' => 'security', 'period' => $period]
            );
            
            if ($format === 'html') {
                return $this->jsonSuccess(['html' => $report, 'report_id' => $reportId]);
            } else {
                return $this->jsonSuccess(['data' => $report, 'report_id' => $reportId]);
            }
        } catch (Exception $e) {
            return $this->jsonError('Report generation failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate automation report
     * AJAX endpoint
     */
    public function generateAutomationReport() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $period = $this->request['period'] ?? 'month';
        $format = $this->request['format'] ?? 'html';
        
        if (!in_array($format, ['html', 'csv', 'json'])) {
            return $this->jsonError('Invalid format', 400);
        }
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        try {
            $report = $this->reportGenerator->generateAutomationReport($this->userId, $period, $format);
            
            $reportId = $this->reportGenerator->saveReport('automation', $this->userId, $format, $report);
            
            $this->auditTrail->log(
                $this->userId,
                'analytics_report_generated',
                'report',
                $reportId,
                ['type' => 'automation', 'period' => $period]
            );
            
            if ($format === 'html') {
                return $this->jsonSuccess(['html' => $report, 'report_id' => $reportId]);
            } else {
                return $this->jsonSuccess(['data' => $report, 'report_id' => $reportId]);
            }
        } catch (Exception $e) {
            return $this->jsonError('Report generation failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get saved reports
     * AJAX endpoint
     */
    public function getSavedReports() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        $reports = $this->reportGenerator->getSavedReports($this->userId, 50);
        
        return $this->jsonSuccess([
            'reports' => $reports,
            'total' => count($reports)
        ]);
    }
    
    /**
     * Export data as CSV
     * AJAX endpoint
     */
    public function exportCSV() {
        $dataType = $this->request['data_type'] ?? null;
        $websiteId = $this->request['website_id'] ?? null;
        
        if (!$dataType) {
            return $this->jsonError('Data type required', 400);
        }
        
        if (!FEATURE_AVAILABLE('analytics')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        try {
            $csv = "Data Export: $dataType\n";
            $csv .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
            
            if ($dataType === 'health_metrics' && $websiteId) {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM health_metrics WHERE website_id = ? ORDER BY created_at DESC
                ");
                $stmt->execute([$websiteId]);
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM health_metrics WHERE website_id IN 
                    (SELECT id FROM websites WHERE user_id = ?)
                    ORDER BY created_at DESC LIMIT 1000
                ");
                $stmt->execute([$this->userId]);
            }
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($data)) {
                $headers = array_keys($data[0]);
                $csv .= implode(',', $headers) . "\n";
                
                foreach ($data as $row) {
                    $values = array_map(function($val) {
                        return '"' . str_replace('"', '""', $val) . '"';
                    }, $row);
                    $csv .= implode(',', $values) . "\n";
                }
            }
            
            // Return as download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d_H-i-s') . '.csv"');
            echo $csv;
            exit;
        } catch (Exception $e) {
            return $this->jsonError('Export failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Helper: Check if AJAX request
     */
    private function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Helper: Return JSON success
     */
    private function jsonSuccess(array $data) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    
    /**
     * Helper: Return JSON error
     */
    private function jsonError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message, 'code' => $code]);
        exit;
    }
}
?>
