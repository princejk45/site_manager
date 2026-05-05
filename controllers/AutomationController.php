<?php
/**
 * AutomationController
 * 
 * Manages automation rules and executes them via AJAX endpoints.
 * Provides CRUD operations, testing, and status monitoring.
 * 
 * @author Fullmidia
 * @version 1.0.0
 * @package Controllers
 */

class AutomationController {
    
    protected RuleEngine $ruleEngine;
    protected RuleExecutor $ruleExecutor;
    protected NotificationService $notificationService;
    protected Website $websiteModel;
    protected BugReportGenerator $bugGenerator;
    protected HealthScoreCalculator $healthCalculator;
    protected PDO $pdo;
    protected ?int $userId;
    protected ?AuditTrail $auditTrail;
    
    /**
     * Initialize controller
     */
    public function __construct(PDO $pdo, ?int $userId = null, ?AuditTrail $auditTrail = null) {
        $this->pdo = $pdo;
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $this->auditTrail = $auditTrail;
        
        // Load required services
        require_once 'services/Automation/RuleEngine.php';
        require_once 'services/Automation/RuleExecutor.php';
        require_once 'services/Email/NotificationService.php';
        require_once 'services/Diagnostics/BugReportGenerator.php';
        require_once 'services/Health/HealthScoreCalculator.php';
        require_once 'models/Website.php';
        
        // Initialize services
        $this->ruleEngine = new RuleEngine($this->pdo, $this->auditTrail, $this->userId);
        $this->notificationService = new NotificationService($this->pdo);
        $this->ruleExecutor = new RuleExecutor(
            $this->pdo,
            $this->ruleEngine,
            $this->notificationService,
            $this->auditTrail,
            $this->userId
        );
        $this->websiteModel = new Website($this->pdo);
        $this->bugGenerator = new BugReportGenerator($this->pdo, $this->auditTrail, $this->userId);
        $this->healthCalculator = new HealthScoreCalculator($this->pdo, $this->auditTrail, $this->userId);
    }
    
    /**
     * List automation rules for a website
     * AJAX endpoint
     */
    public function list() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $websiteId = $this->request['website_id'] ?? null;
        
        if (!$websiteId || !$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        // Check license feature
        if (!FEATURE_AVAILABLE('automation_rules')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        $rules = $this->ruleEngine->getAllRules($websiteId);
        
        // Enhance rules with execution statistics
        foreach ($rules as &$rule) {
            $rule['stats'] = $this->ruleEngine->getStatistics($rule['id']);
        }
        
        return $this->jsonSuccess([
            'rules' => $rules,
            'total' => count($rules)
        ]);
    }
    
    /**
     * Get single rule details
     * AJAX endpoint
     */
    public function getRule() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $ruleId = $this->request['rule_id'] ?? null;
        
        if (!$ruleId) {
            return $this->jsonError('Rule ID required', 400);
        }
        
        $rule = $this->ruleEngine->getRule($ruleId);
        
        if (!$rule) {
            return $this->jsonError('Rule not found', 404);
        }
        
        // Verify ownership
        $website = $this->websiteModel->getById($rule['website_id']);
        if (!$website || !$this->websiteModel->ownsWebsite($this->userId, $website['id'])) {
            return $this->jsonError('Unauthorized', 403);
        }
        
        // Get execution history
        $rule['execution_history'] = $this->ruleEngine->getExecutionHistory($ruleId, 20);
        $rule['statistics'] = $this->ruleEngine->getStatistics($ruleId);
        
        // Decode JSON fields
        $rule['trigger_config'] = json_decode($rule['trigger_config'], true);
        $rule['conditions'] = json_decode($rule['conditions_json'], true);
        $rule['actions'] = json_decode($rule['actions_json'], true);
        
        return $this->jsonSuccess(['rule' => $rule]);
    }
    
    /**
     * Create new automation rule
     * AJAX endpoint
     */
    public function create() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $websiteId = $this->request['website_id'] ?? null;
        $ruleData = $this->request['rule_data'] ?? null;
        
        if (!$websiteId || !$ruleData) {
            return $this->jsonError('Website ID and rule data required', 400);
        }
        
        if (!$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        // Check license feature
        if (!FEATURE_AVAILABLE('automation_rules')) {
            return $this->jsonError('Feature not available', 403);
        }
        
        try {
            $ruleData['website_id'] = $websiteId;
            $ruleId = $this->ruleEngine->createRule($websiteId, $ruleData);
            
            return $this->jsonSuccess([
                'rule_id' => $ruleId,
                'message' => 'Rule created successfully'
            ]);
        } catch (Exception $e) {
            return $this->jsonError('Failed to create rule: ' . $e->getMessage(), 400);
        }
    }
    
    /**
     * Update automation rule
     * AJAX endpoint
     */
    public function update() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $ruleId = $this->request['rule_id'] ?? null;
        $updates = $this->request['updates'] ?? null;
        
        if (!$ruleId || !$updates) {
            return $this->jsonError('Rule ID and updates required', 400);
        }
        
        $rule = $this->ruleEngine->getRule($ruleId);
        if (!$rule || !$this->websiteModel->ownsWebsite($this->userId, $rule['website_id'])) {
            return $this->jsonError('Unauthorized', 403);
        }
        
        if ($this->ruleEngine->updateRule($ruleId, $updates)) {
            return $this->jsonSuccess(['message' => 'Rule updated successfully']);
        } else {
            return $this->jsonError('Failed to update rule', 500);
        }
    }
    
    /**
     * Delete automation rule
     * AJAX endpoint
     */
    public function delete() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $ruleId = $this->request['rule_id'] ?? null;
        
        if (!$ruleId) {
            return $this->jsonError('Rule ID required', 400);
        }
        
        $rule = $this->ruleEngine->getRule($ruleId);
        if (!$rule || !$this->websiteModel->ownsWebsite($this->userId, $rule['website_id'])) {
            return $this->jsonError('Unauthorized', 403);
        }
        
        if ($this->ruleEngine->deleteRule($ruleId)) {
            return $this->jsonSuccess(['message' => 'Rule deleted successfully']);
        } else {
            return $this->jsonError('Failed to delete rule', 500);
        }
    }
    
    /**
     * Toggle rule active status
     * AJAX endpoint
     */
    public function toggle() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $ruleId = $this->request['rule_id'] ?? null;
        $active = $this->request['active'] ?? null;
        
        if (!$ruleId || $active === null) {
            return $this->jsonError('Rule ID and active status required', 400);
        }
        
        $rule = $this->ruleEngine->getRule($ruleId);
        if (!$rule || !$this->websiteModel->ownsWebsite($this->userId, $rule['website_id'])) {
            return $this->jsonError('Unauthorized', 403);
        }
        
        if ($this->ruleEngine->setActive($ruleId, $active)) {
            return $this->jsonSuccess([
                'message' => 'Rule ' . ($active ? 'enabled' : 'disabled'),
                'active' => $active
            ]);
        } else {
            return $this->jsonError('Failed to toggle rule', 500);
        }
    }
    
    /**
     * Test a rule (execute it manually)
     * AJAX endpoint
     */
    public function test() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $ruleId = $this->request['rule_id'] ?? null;
        $websiteId = $this->request['website_id'] ?? null;
        
        if (!$ruleId || !$websiteId) {
            return $this->jsonError('Rule ID and website ID required', 400);
        }
        
        if (!$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        $rule = $this->ruleEngine->getRule($ruleId);
        if (!$rule || $rule['website_id'] != $websiteId) {
            return $this->jsonError('Rule not found', 404);
        }
        
        try {
            // Get current diagnostics
            $metric = $this->healthCalculator->getLatestMetric($websiteId);
            $bugs = $this->bugGenerator->getActiveBugs($websiteId);
            
            if (!$metric) {
                return $this->jsonError('No diagnostics available for this website', 400);
            }
            
            $diagnosticData = array_merge($metric, [
                'active_bugs' => $bugs,
                'bugs_count' => count($bugs)
            ]);
            
            // Execute rule
            $result = $this->ruleExecutor->executeRule($ruleId, $websiteId, $diagnosticData);
            
            return $this->jsonSuccess([
                'execution_result' => $result,
                'message' => 'Rule test executed. Check result below.'
            ]);
        } catch (Exception $e) {
            return $this->jsonError('Test execution failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get execution history
     * AJAX endpoint
     */
    public function getHistory() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $ruleId = $this->request['rule_id'] ?? null;
        $limit = $this->request['limit'] ?? 50;
        
        if (!$ruleId) {
            return $this->jsonError('Rule ID required', 400);
        }
        
        $rule = $this->ruleEngine->getRule($ruleId);
        if (!$rule || !$this->websiteModel->ownsWebsite($this->userId, $rule['website_id'])) {
            return $this->jsonError('Unauthorized', 403);
        }
        
        $history = $this->ruleEngine->getExecutionHistory($ruleId, $limit);
        
        return $this->jsonSuccess([
            'history' => $history,
            'count' => count($history)
        ]);
    }
    
    /**
     * Get rule statistics
     * AJAX endpoint
     */
    public function getStats() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $ruleId = $this->request['rule_id'] ?? null;
        
        if (!$ruleId) {
            return $this->jsonError('Rule ID required', 400);
        }
        
        $rule = $this->ruleEngine->getRule($ruleId);
        if (!$rule || !$this->websiteModel->ownsWebsite($this->userId, $rule['website_id'])) {
            return $this->jsonError('Unauthorized', 403);
        }
        
        $stats = $this->ruleEngine->getStatistics($ruleId);
        $canExecute = $this->ruleEngine->canExecute($ruleId);
        
        return $this->jsonSuccess([
            'statistics' => $stats,
            'can_execute' => $canExecute
        ]);
    }
    
    /**
     * Get execution summary for website
     * AJAX endpoint
     */
    public function getSummary() {
        if (!$this->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $websiteId = $this->request['website_id'] ?? null;
        $period = $this->request['period'] ?? 'today';
        
        if (!$websiteId || !$this->websiteModel->ownsWebsite($this->userId, $websiteId)) {
            return $this->jsonError('Website not found', 404);
        }
        
        $summary = $this->ruleExecutor->getExecutionSummary($websiteId, $period);
        $activeRules = $this->ruleEngine->getActiveRules($websiteId);
        
        return $this->jsonSuccess([
            'summary' => $summary,
            'active_rules' => count($activeRules),
            'total_rules' => count($this->ruleEngine->getAllRules($websiteId))
        ]);
    }
    
    /**
     * Helper: Check if AJAX request
     */
    private function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Helper: Return JSON success
     */
    private function jsonSuccess(array $data): void {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    
    /**
     * Helper: Return JSON error
     */
    private function jsonError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message, 'code' => $code]);
        exit;
    }
}
?>
