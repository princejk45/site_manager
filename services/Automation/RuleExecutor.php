<?php
/**
 * RuleExecutor Service
 * 
 * Executes automation rules - runs actions, handles notifications, tracks results.
 * Integrates with RuleEngine to evaluate conditions and determine actions.
 * 
 * @author Fullmidia
 * @version 1.0.0
 * @package Services\Automation
 */

class RuleExecutor {
    
    private PDO $pdo;
    private RuleEngine $ruleEngine;
    private NotificationService $notificationService;
    private ?AuditTrail $auditTrail;
    private int $userId;
    
    /**
     * Initialize RuleExecutor
     * 
     * @param PDO $pdo Database connection
     * @param RuleEngine $ruleEngine Rule engine service
     * @param NotificationService $notificationService Notification service
     * @param ?AuditTrail $auditTrail Audit trail service (null when using static initialization)
     * @param int $userId User ID
     */
    public function __construct(PDO $pdo, RuleEngine $ruleEngine, NotificationService $notificationService, ?AuditTrail $auditTrail, int $userId = 1) {
        $this->pdo = $pdo;
        $this->ruleEngine = $ruleEngine;
        $this->notificationService = $notificationService;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Execute a rule if conditions are met
     * 
     * @param int $ruleId Rule ID
     * @param int $websiteId Website ID
     * @param array $diagnosticData Diagnostic data to evaluate against
     * @return array Execution result
     */
    public function executeRule($ruleId, $websiteId, $diagnosticData) {
        $startTime = microtime(true);
        
        try {
            // Get rule
            $rule = $this->ruleEngine->getRule($ruleId);
            if (!$rule) {
                return $this->recordExecution($ruleId, 'FAILED', $startTime, ['error' => 'Rule not found']);
            }
            
            // Check if can execute
            $canExecute = $this->ruleEngine->canExecute($ruleId);
            if (!$canExecute['can_execute']) {
                return $this->recordExecution($ruleId, 'SKIPPED', $startTime, ['reason' => $canExecute['reason']]);
            }
            
            // Evaluate conditions
            if (!$this->ruleEngine->evaluateConditions($rule, $diagnosticData)) {
                return $this->recordExecution($ruleId, 'SKIPPED', $startTime, ['reason' => 'Conditions not met']);
            }
            
            // Execute actions
            $actions = json_decode($rule['actions_json'], true) ?? [];
            $results = [];
            $partialFailure = false;
            
            foreach ($actions as $action) {
                try {
                    $actionResult = $this->executeAction($action, $websiteId, $rule, $diagnosticData);
                    $results[] = $actionResult;
                    
                    if (!$actionResult['success']) {
                        $partialFailure = true;
                    }
                } catch (Exception $e) {
                    $partialFailure = true;
                    $results[] = [
                        'type' => $action['type'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $status = $partialFailure ? 'PARTIAL' : 'SUCCESS';
            $result = $this->recordExecution($ruleId, $status, $startTime, ['actions' => $results]);
            
            // Log in audit trail
            $this->auditTrail->log(
                $this->userId,
                'automation_rule_executed',
                'automation_rule',
                $ruleId,
                ['website_id' => $websiteId, 'status' => $status, 'actions_count' => count($results)]
            );
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Rule execution error: " . $e->getMessage());
            return $this->recordExecution($ruleId, 'FAILED', $startTime, ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Execute a single action
     * 
     * @param array $action Action configuration
     * @param int $websiteId Website ID
     * @param array $rule Rule data
     * @param array $diagnosticData Diagnostic data
     * @return array Action result
     */
    private function executeAction($action, $websiteId, $rule, $diagnosticData) {
        $type = $action['type'];
        
        switch ($type) {
            case 'send_email':
                return $this->executeEmailAction($action, $websiteId, $rule, $diagnosticData);
            
            case 'create_ticket':
                return $this->executeTicketAction($action, $websiteId, $rule, $diagnosticData);
            
            case 'update_status':
                return $this->executeStatusAction($action, $websiteId, $rule, $diagnosticData);
            
            case 'webhook':
                return $this->executeWebhookAction($action, $websiteId, $rule, $diagnosticData);
            
            case 'log_event':
                return $this->executeLogAction($action, $websiteId, $rule, $diagnosticData);
            
            default:
                throw new Exception("Unknown action type: $type");
        }
    }
    
    /**
     * Execute send_email action
     * 
     * @param array $action Action config
     * @param int $websiteId Website ID
     * @param array $rule Rule data
     * @param array $diagnosticData Diagnostic data
     * @return array Result
     */
    private function executeEmailAction($action, $websiteId, $rule, $diagnosticData) {
        $recipients = $action['recipients'] ?? [];
        $subject = $this->interpolateString($action['subject'] ?? 'Automation Alert', $diagnosticData);
        $body = $this->interpolateString($action['body'] ?? '', $diagnosticData);
        
        if (empty($recipients)) {
            return ['type' => 'send_email', 'success' => false, 'error' => 'No recipients specified'];
        }
        
        // Send email via notification service
        $sent = 0;
        $failed = 0;
        
        foreach ($recipients as $recipient) {
            if ($this->notificationService->sendEmail($recipient, $subject, $body)) {
                $sent++;
            } else {
                $failed++;
            }
        }
        
        return [
            'type' => 'send_email',
            'success' => $failed === 0,
            'sent' => $sent,
            'failed' => $failed,
            'recipients' => count($recipients)
        ];
    }
    
    /**
     * Execute create_ticket action
     * 
     * @param array $action Action config
     * @param int $websiteId Website ID
     * @param array $rule Rule data
     * @param array $diagnosticData Diagnostic data
     * @return array Result
     */
    private function executeTicketAction($action, $websiteId, $rule, $diagnosticData) {
        // This would integrate with your ticketing system
        // For now, create a notification log entry
        
        $title = $this->interpolateString($action['title'] ?? 'Automation Ticket', $diagnosticData);
        $description = $this->interpolateString($action['description'] ?? '', $diagnosticData);
        $priority = $action['priority'] ?? 'MEDIUM';
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_queue (
                    website_id, type, priority, title, message, status, created_at
                ) VALUES (?, 'TICKET', ?, ?, ?, 'PENDING', NOW())
            ");
            
            $stmt->execute([
                $websiteId,
                $priority,
                $title,
                $description
            ]);
            
            return [
                'type' => 'create_ticket',
                'success' => true,
                'ticket_id' => $this->pdo->lastInsertId(),
                'priority' => $priority
            ];
        } catch (PDOException $e) {
            return ['type' => 'create_ticket', 'success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Execute update_status action
     * 
     * @param array $action Action config
     * @param int $websiteId Website ID
     * @param array $rule Rule data
     * @param array $diagnosticData Diagnostic data
     * @return array Result
     */
    private function executeStatusAction($action, $websiteId, $rule, $diagnosticData) {
        $newStatus = $action['new_status'] ?? 'ALERT';
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE websites SET status = ?, status_updated_at = NOW() WHERE id = ?
            ");
            
            $stmt->execute([$newStatus, $websiteId]);
            
            return [
                'type' => 'update_status',
                'success' => true,
                'new_status' => $newStatus
            ];
        } catch (PDOException $e) {
            return ['type' => 'update_status', 'success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Execute webhook action
     * 
     * @param array $action Action config
     * @param int $websiteId Website ID
     * @param array $rule Rule data
     * @param array $diagnosticData Diagnostic data
     * @return array Result
     */
    private function executeWebhookAction($action, $websiteId, $rule, $diagnosticData) {
        $url = $action['url'] ?? null;
        $method = $action['method'] ?? 'POST';
        $headers = $action['headers'] ?? [];
        
        if (!$url) {
            return ['type' => 'webhook', 'success' => false, 'error' => 'No webhook URL specified'];
        }
        
        try {
            $payload = [
                'rule_id' => $rule['id'],
                'rule_name' => $rule['name'],
                'website_id' => $websiteId,
                'timestamp' => date('Y-m-d H:i:s'),
                'diagnostic_data' => $diagnosticData
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $defaultHeaders = ['Content-Type: application/json'];
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $success = $httpCode >= 200 && $httpCode < 300;
            
            return [
                'type' => 'webhook',
                'success' => $success,
                'http_code' => $httpCode,
                'url' => $url
            ];
        } catch (Exception $e) {
            return ['type' => 'webhook', 'success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Execute log_event action
     * 
     * @param array $action Action config
     * @param int $websiteId Website ID
     * @param array $rule Rule data
     * @param array $diagnosticData Diagnostic data
     * @return array Result
     */
    private function executeLogAction($action, $websiteId, $rule, $diagnosticData) {
        $message = $this->interpolateString($action['message'] ?? '', $diagnosticData);
        $level = $action['level'] ?? 'INFO';
        
        $logMessage = sprintf(
            "[%s] Rule '%s' triggered for website %d: %s",
            $level,
            $rule['name'],
            $websiteId,
            $message
        );
        
        error_log($logMessage);
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_feed (
                    website_id, action, description, details, created_at
                ) VALUES (?, 'AUTOMATION', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $websiteId,
                'Rule execution: ' . $rule['name'],
                json_encode(['level' => $level, 'message' => $message])
            ]);
            
            return [
                'type' => 'log_event',
                'success' => true,
                'level' => $level,
                'message' => $message
            ];
        } catch (PDOException $e) {
            return ['type' => 'log_event', 'success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Interpolate variables in a string
     * 
     * Supports dot notation for nested values:
     * {diagnostic.health_score}, {website.domain}, {rule.name}
     * 
     * @param string $template Template string
     * @param array $data Data for interpolation
     * @return string Interpolated string
     */
    private function interpolateString($template, $data) {
        return preg_replace_callback('/\{([a-z_\.]+)\}/i', function($matches) use ($data) {
            $key = $matches[1];
            $keys = explode('.', $key);
            $value = $data;
            
            foreach ($keys as $k) {
                if (is_array($value) && isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    return '';
                }
            }
            
            return (string)$value;
        }, $template);
    }
    
    /**
     * Record execution in database
     * 
     * @param int $ruleId Rule ID
     * @param string $status Execution status
     * @param float $startTime Start time (microtime)
     * @param array $result Result data
     * @return array Formatted result
     */
    private function recordExecution($ruleId, $status, $startTime, $result) {
        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $this->ruleEngine->logExecution($ruleId, $status, $executionTime, $result);
        
        return [
            'rule_id' => $ruleId,
            'status' => $status,
            'execution_time_ms' => $executionTime,
            'result' => $result
        ];
    }
    
    /**
     * Execute all active rules for a website
     * 
     * @param int $websiteId Website ID
     * @param array $diagnosticData Diagnostic data
     * @return array Execution results
     */
    public function executeAllRules($websiteId, $diagnosticData) {
        $rules = $this->ruleEngine->getActiveRules($websiteId);
        $results = [];
        
        foreach ($rules as $rule) {
            $result = $this->executeRule($rule['id'], $websiteId, $diagnosticData);
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * Get execution summary for a website
     * 
     * @param int $websiteId Website ID
     * @param string $period Period (today, week, month)
     * @return array Summary data
     */
    public function getExecutionSummary($websiteId, $period = 'today') {
        try {
            // Determine date range
            $dateCondition = match($period) {
                'week' => 'DATE(are.executed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
                'month' => 'DATE(are.executed_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
                default => 'DATE(are.executed_at) = CURDATE()'
            };
            
            $query = "
                SELECT 
                    COUNT(*) as total_executions,
                    SUM(CASE WHEN are.status = 'SUCCESS' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN are.status = 'FAILED' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN are.status = 'PARTIAL' THEN 1 ELSE 0 END) as partial,
                    SUM(CASE WHEN are.status = 'SKIPPED' THEN 1 ELSE 0 END) as skipped,
                    AVG(are.execution_time_ms) as avg_execution_time
                FROM automation_rules ar
                LEFT JOIN automation_rule_executions are ON ar.id = are.rule_id
                WHERE ar.website_id = ? AND $dateCondition
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$websiteId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get execution summary error: " . $e->getMessage());
            return [];
        }
    }
}
?>
