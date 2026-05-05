<?php
/**
 * DiagnosticsController
 * 
 * Handles all diagnostics and health center requests.
 * Manages bug report generation, health score calculation, and data retrieval.
 * 
 * @author Fullmidia
 * @version 1.0.0
 * @package Controllers
 */

class DiagnosticsController {
    
    protected BugReportGenerator $bugGenerator;
    protected HealthScoreCalculator $healthCalculator;
    protected Website $websiteModel;
    protected PDO $pdo;
    protected ?int $userId;
    protected ?AuditTrail $auditTrail;
    protected array $request;
    
    /**
     * Initialize controller with services
     */
    public function __construct(PDO $pdo, ?int $userId = null, ?AuditTrail $auditTrail = null) {
        $this->pdo = $pdo;
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $this->auditTrail = $auditTrail;
        $resolvedUserId = (int)($this->userId ?? 1);

        // Merge GET, POST, and any JSON body into a single request map
        $jsonBody = [];
        $rawInput = file_get_contents('php://input');
        if ($rawInput) {
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                $jsonBody = $decoded;
            }
        }
        $this->request = array_merge($_GET, $_POST, $jsonBody);
        
        // Load required services
        require_once 'services/Diagnostics/BugReportGenerator.php';
        require_once 'services/Health/HealthScoreCalculator.php';
        require_once 'models/Website.php';
        
        // Initialize services
        $this->bugGenerator = new BugReportGenerator($this->pdo, $this->auditTrail, $resolvedUserId);
        $this->healthCalculator = new HealthScoreCalculator($this->pdo, $this->auditTrail, $resolvedUserId);
        $this->websiteModel = new Website($this->pdo);
    }
    
    /**
     * Main diagnostics center view
     * Display overview of all websites health
     */
    public function center() {
        // Check feature access (license gating)
        if (!FEATURE_AVAILABLE('diagnostics_center')) {
            return $this->jsonError('Diagnostics Center requires Professional or higher license', 403);
        }
        
        // Get all websites for current user
        $websites = $this->websiteModel->getUserWebsites($this->userId);
        
        $websitesData = [];
        $totalScore = 0;
        $criticalCount = 0;
        
        foreach ($websites as $website) {
            $latestMetric = $this->healthCalculator->getLatestMetric($website['id']);
            $activeBugs = $this->bugGenerator->getActiveBugs($website['id']);

            // getLatestMetric() returns a DB row: column is health_score, not overall_score.
            // grade and status are not stored in DB — derive them from health_score.
            $healthScore = $latestMetric ? ($latestMetric['health_score'] ?? null) : null;
            $grade       = $healthScore !== null ? $this->scoreToGrade($healthScore) : null;
            $status      = $healthScore !== null ? ($healthScore >= 80 ? 'GOOD' : ($healthScore >= 60 ? 'WARNING' : 'CRITICAL')) : 'Unknown';

            $websiteData = [
                'id' => $website['id'],
                'domain' => $website['domain'],
                'health_score' => $healthScore,
                'grade' => $grade,
                'status' => $status,
                'active_bugs' => count($activeBugs),
                'critical_bugs' => count(array_filter($activeBugs, function($b) { return $b['severity'] === 'CRITICAL'; })),
                'last_scan' => $latestMetric ? ($latestMetric['recorded_at'] ?? null) : null
            ];
            
            $websitesData[] = $websiteData;
            
            if ($healthScore !== null) {
                $totalScore += $healthScore;
            }
            
            if ($websiteData['critical_bugs'] > 0) {
                $criticalCount += $websiteData['critical_bugs'];
            }
        }
        
        // Calculate portfolio average
        $averageScore = count($websites) > 0 ? round($totalScore / count($websites), 1) : 0;
        
        // Get recommendations for worst performing sites
        $recommendations = $this->getPortfolioRecommendations($websites);
        
        // Load view
        $viewData = [
            'websites' => $websitesData,
            'portfolio_score' => $averageScore,
            'portfolio_grade' => $this->scoreToGrade($averageScore),
            'total_websites' => count($websites),
            'critical_issues' => $criticalCount,
            'recommendations' => $recommendations,
            'stats' => [
                'excellent' => count(array_filter($websitesData, function($w) { return $w['grade'] === 'A'; })),
                'good' => count(array_filter($websitesData, function($w) { return $w['grade'] === 'B'; })),
                'fair' => count(array_filter($websitesData, function($w) { return $w['grade'] === 'C'; })),
                'poor' => count(array_filter($websitesData, function($w) { return $w['grade'] === 'D'; })),
                'critical' => count(array_filter($websitesData, function($w) { return $w['grade'] === 'F'; }))
            ]
        ];
        
        $this->view('dashboard/diagnostics_center', $viewData);
    }
    
    /**
     * Get diagnostics for a specific website
     * AJAX endpoint
     */
    public function getSiteMetrics() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $websiteId = $this->request['website_id'] ?? null;
        
        if (!$websiteId || !$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        // Check license feature
        if (!FEATURE_AVAILABLE('diagnostics_center')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        $metric = $this->healthCalculator->getLatestMetric($websiteId);
        $trend = $this->healthCalculator->calculateTrend($websiteId);
        $recommendations = $this->healthCalculator->getRecommendations($websiteId);
        
        return $this->jsonSuccess([
            'metric' => $metric,
            'trend' => $trend,
            'recommendations' => $recommendations
        ]);
    }
    
    /**
     * Analyze a website and generate bug reports
     * AJAX endpoint
     */
    public function analyze() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $websiteId = $this->request['website_id'] ?? null;
        
        if (!$websiteId || !$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        // Check license feature
        if (!FEATURE_AVAILABLE('diagnostics_center')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        // Get WordPress diagnostics from API
        $diagnosticsData = $this->fetchWordPressDiagnostics($websiteId);
        
        if (!$diagnosticsData) {
            return $this->jsonError('Could not fetch diagnostics from WordPress', 500);
        }
        
        // Generate bug reports
        $generatedBugs = $this->bugGenerator->generateReports($websiteId, $diagnosticsData);
        
        // Calculate health score
        $metrics = $this->healthCalculator->calculateScore($websiteId, $diagnosticsData);
        
        // Get updated bug list
        $activeBugs = $this->bugGenerator->getActiveBugs($websiteId);
        $severitySummary = $this->bugGenerator->getSeveritySummary($websiteId);
        
        return $this->jsonSuccess([
            'generated_bugs' => count($generatedBugs),
            'health_score' => $metrics['overall_score'],
            'grade' => $metrics['grade'],
            'status' => $metrics['status'],
            'active_bugs' => $activeBugs,
            'severity_summary' => $severitySummary,
            'components' => $metrics['components'],
            'message' => sprintf('Analysis complete. Found %d active issues.', count($activeBugs))
        ]);
    }
    
    /**
     * Get bug reports for a website
     * AJAX endpoint
     */
    public function getBugs() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $websiteId = $this->request['website_id'] ?? null;
        $severity = $this->request['severity'] ?? null;
        
        if (!$websiteId || !$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        $bugs = $this->bugGenerator->getActiveBugs($websiteId, $severity);
        $summary = $this->bugGenerator->getSeveritySummary($websiteId);
        
        return $this->jsonSuccess([
            'bugs' => $bugs,
            'summary' => $summary,
            'total' => count($bugs)
        ]);
    }
    
    /**
     * Resolve a bug report
     * AJAX endpoint
     */
    public function resolveBug() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $bugId = $this->request['bug_id'] ?? null;
        $reason = $this->request['reason'] ?? 'MANUAL_RESOLUTION';
        
        if (!$bugId) {
            return $this->jsonError('Bug ID required', 400);
        }
        
        $success = $this->bugGenerator->resolveBug($bugId, $reason);
        
        if ($success) {
            return $this->jsonSuccess(['message' => 'Bug resolved successfully']);
        } else {
            return $this->jsonError('Failed to resolve bug', 500);
        }
    }
    
    /**
     * Get health metrics history for a website
     * AJAX endpoint
     */
    public function getMetricsHistory() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $websiteId = $this->request['website_id'] ?? null;
        $limit = $this->request['limit'] ?? 30;
        
        if (!$websiteId || !$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        $metrics = $this->healthCalculator->getMetrics($websiteId, $limit);
        
        return $this->jsonSuccess([
            'metrics' => $metrics,
            'count' => count($metrics)
        ]);
    }
    
    /**
     * Get bug timeline for a website
     * AJAX endpoint
     */
    public function getBugTimeline() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $websiteId = $this->request['website_id'] ?? null;
        
        if (!$websiteId || !$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT br.id, br.title, br.severity, br.detected_count,
                       br.last_detected, brh.action, brh.created_at
                FROM bug_reports_auto br
                LEFT JOIN bug_report_history brh ON br.id = brh.bug_report_id
                WHERE br.website_id = ?
                ORDER BY brh.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$websiteId]);
            $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->jsonSuccess(['timeline' => $timeline]);
        } catch (PDOException $e) {
            return $this->jsonError('Database error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Fetch and normalize WordPress diagnostics using the shared service layer.
     * Uses the same wordpress_sites credential store (X-Fullmidia-Key header)
     * and DiagnosticsNormalizer as WebsiteController::fetch_diagnostics().
     *
     * Returns normalized data ready for HealthScoreCalculator / BugReportGenerator,
     * or false on any configuration / network error.
     *
     * @param int $websiteId Website ID
     * @return array|false Normalized diagnostics data or false on error
     */
    private function fetchWordPressDiagnostics($websiteId) {
        try {
            require_once APP_PATH . '/services/WordPress/Exceptions.php';
            require_once APP_PATH . '/services/WordPress/WordPressApiClient.php';
            require_once APP_PATH . '/services/WordPress/DiagnosticsNormalizer.php';
            require_once APP_PATH . '/models/WordPressSite.php';

            $wpSiteModel = new WordPressSite($this->pdo);
            $wpSiteConfig = $wpSiteModel->getByWebsiteId($websiteId);

            if (!$wpSiteConfig) {
                error_log("DiagnosticsController: no wordpress_sites config for website $websiteId");
                return false;
            }

            if (!$wpSiteConfig['is_active']) {
                error_log("DiagnosticsController: wordpress_sites config is inactive for website $websiteId");
                return false;
            }

            $apiClient   = new WordPressApiClient();
            $apiResponse = $apiClient->fetchDiagnostics(
                $wpSiteConfig['wordpress_url'],
                $wpSiteConfig['api_key']
            );

            DiagnosticsNormalizer::validate($apiResponse['data']);
            $normalized = DiagnosticsNormalizer::normalize($apiResponse['data']);

            // Store the diagnostics snapshot via the model so history is preserved
            $storageData = DiagnosticsNormalizer::extractForStorage($normalized, $apiResponse['data']);
            $storageData['http_status_code']  = $apiResponse['http_code'];
            $storageData['fetch_duration_ms'] = $apiResponse['duration_ms'];
            $storageData['fetch_method']      = 'on_demand';
            $wpSiteModel->storeDiagnostics($wpSiteConfig['id'], $storageData);
            $wpSiteModel->updateFetchStatus($wpSiteConfig['id'], 'healthy');
            // Note: health score is NOT written here — analyze() calls
            // $this->healthCalculator->calculateScore() with audit-trail context.

            return $normalized;

        } catch (Exception $e) {
            error_log("DiagnosticsController::fetchWordPressDiagnostics - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get portfolio-wide recommendations
     * 
     * @param array $websites Websites list
     * @return array Top recommendations
     */
    private function getPortfolioRecommendations($websites) {
        $recommendations = [];
        
        foreach ($websites as $website) {
            $siteRecommendations = $this->healthCalculator->getRecommendations($website['id']);
            
            // Take top recommendation from each site
            if (!empty($siteRecommendations)) {
                $recommendations[] = array_merge(
                    array_slice($siteRecommendations, 0, 1)[0],
                    ['website_id' => $website['id'], 'domain' => $website['domain']]
                );
            }
        }
        
        // Sort by priority and return top 5
        usort($recommendations, function($a, $b) {
            $priorityOrder = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2];
            return ($priorityOrder[$a['priority']] ?? 3) <=> ($priorityOrder[$b['priority']] ?? 3);
        });
        
        return array_slice($recommendations, 0, 5);
    }
    
    /**
     * Convert numeric score to letter grade
     * 
     * @param float $score Score (0-100)
     * @return string Grade
     */
    private function scoreToGrade($score) {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
    
    /**
     * Verify request is AJAX
     * 
     * @return bool
     */
    private function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Return JSON success response
     * 
     * @param mixed $data Response data
     * @return void
     */
    private function jsonSuccess(array $data) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    
    /**
     * Render a view file with optional data
     * 
     * @param string $view View path (e.g., 'dashboard/diagnostics_center')
     * @param array $data Data to pass to view
     * @return void
     */
    protected function view(string $view, array $data = []): void
    {
        // Extract data into individual variables
        extract($data, EXTR_SKIP);
        
        // Construct path to view file
        $viewPath = APP_PATH . '/views/' . str_replace('.', '/', $view) . '.php';
        
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            throw new Exception("View file not found: {$viewPath}");
        }
    }

    /**
     * Return JSON error response
     * 
     * @param string $message Error message
     * @param int $code HTTP code
     * @return void
     */
    private function jsonError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message, 'code' => $code]);
        exit;
    }
}
?>
