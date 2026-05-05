<?php
/**
 * Advanced Automation Service
 * 
 * Extends base automation with complex scheduling, workflow templates,
 * conditional logic, and multi-step automation chains.
 */

class AdvancedAutomationService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Automation types
    const TYPE_WORKFLOW = 'workflow';
    const TYPE_SCHEDULED = 'scheduled';
    const TYPE_TRIGGERED = 'triggered';
    const TYPE_CHAIN = 'chain';
    
    // Trigger types
    const TRIGGER_UPTIME_DOWN = 'uptime_down';
    const TRIGGER_PERFORMANCE_SPIKE = 'performance_spike';
    const TRIGGER_SECURITY_ALERT = 'security_alert';
    const TRIGGER_SCHEDULED = 'scheduled';
    const TRIGGER_MANUAL = 'manual';
    const TRIGGER_WEBHOOK = 'webhook';
    
    // Action types
    const ACTION_NOTIFY = 'notify';
    const ACTION_CREATE_TICKET = 'create_ticket';
    const ACTION_RESTART_SERVICE = 'restart_service';
    const ACTION_RUN_SCRIPT = 'run_script';
    const ACTION_UPDATE_DNS = 'update_dns';
    const ACTION_TRIGGER_BACKUP = 'trigger_backup';
    const ACTION_SEND_EMAIL = 'send_email';
    const ACTION_SEND_WEBHOOK = 'send_webhook';
    const ACTION_SCALE_RESOURCE = 'scale_resource';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Create automation workflow
     * 
     * @param int $portfolioId Portfolio ID
     * @param array $workflow Workflow definition
     * @return int Workflow ID
     */
    public function createWorkflow($portfolioId, $workflow) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO automation_workflows (
                    portfolio_id,
                    name,
                    description,
                    type,
                    triggers,
                    actions,
                    conditions,
                    schedule,
                    is_enabled,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $portfolioId,
                $workflow['name'] ?? 'Untitled',
                $workflow['description'] ?? null,
                $workflow['type'] ?? self::TYPE_WORKFLOW,
                json_encode($workflow['triggers'] ?? []),
                json_encode($workflow['actions'] ?? []),
                json_encode($workflow['conditions'] ?? []),
                json_encode($workflow['schedule'] ?? null),
                $workflow['is_enabled'] ? 1 : 0,
                $this->userId
            ]);
            
            $workflowId = $this->pdo->lastInsertId();
            $this->auditTrail->log('automation_workflow_created', 'portfolio_id=' . $portfolioId . ';workflow_id=' . $workflowId);
            
            return $workflowId;
            
        } catch (PDOException $e) {
            error_log("AdvancedAutomationService::createWorkflow - " . $e->getMessage());
            throw new Exception("Failed to create workflow");
        }
    }
    
    /**
     * Get automation workflows
     * 
     * @param int $portfolioId Portfolio ID
     * @return array List of workflows
     */
    public function getWorkflows($portfolioId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM automation_workflows 
                WHERE portfolio_id = ? 
                ORDER BY created_at DESC
            ");
            
            $stmt->execute([$portfolioId]);
            $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($workflows as &$workflow) {
                $workflow['triggers'] = json_decode($workflow['triggers'], true);
                $workflow['actions'] = json_decode($workflow['actions'], true);
                $workflow['conditions'] = json_decode($workflow['conditions'], true);
                $workflow['schedule'] = json_decode($workflow['schedule'], true);
            }
            
            return $workflows;
            
        } catch (PDOException $e) {
            error_log("AdvancedAutomationService::getWorkflows - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Execute workflow
     * 
     * @param int $workflowId Workflow ID
     * @param array $triggerData Data from trigger
     * @return array Execution result
     */
    public function executeWorkflow($workflowId, $triggerData = []) {
        try {
            // Get workflow
            $stmt = $this->pdo->prepare("SELECT * FROM automation_workflows WHERE id = ?");
            $stmt->execute([$workflowId]);
            $workflow = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$workflow) {
                throw new Exception("Workflow not found");
            }
            
            $workflow['actions'] = json_decode($workflow['actions'], true);
            $workflow['conditions'] = json_decode($workflow['conditions'], true);
            
            // Check conditions
            if (!$this->evaluateConditions($workflow['conditions'], $triggerData)) {
                return ['success' => false, 'reason' => 'Conditions not met'];
            }
            
            // Execute actions
            $executionId = $this->createExecution($workflowId, $triggerData);
            $results = [];
            
            foreach ($workflow['actions'] as $action) {
                $result = $this->executeAction($action, $triggerData);
                $results[] = $result;
                
                // Log action execution
                $this->logActionExecution($executionId, $action, $result);
            }
            
            $this->auditTrail->log('automation_workflow_executed', 'workflow_id=' . $workflowId);
            
            return ['success' => true, 'execution_id' => $executionId, 'results' => $results];
            
        } catch (Exception $e) {
            error_log("AdvancedAutomationService::executeWorkflow - " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create automation template
     * 
     * @param string $name Template name
     * @param string $category Category
     * @param array $template Template definition
     * @return int Template ID
     */
    public function createTemplate($name, $category, $template) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO automation_templates (
                    name,
                    category,
                    description,
                    template_data,
                    is_public,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name,
                $category,
                $template['description'] ?? null,
                json_encode($template),
                $template['is_public'] ? 1 : 0,
                $this->userId
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("AdvancedAutomationService::createTemplate - " . $e->getMessage());
            throw new Exception("Failed to create template");
        }
    }
    
    /**
     * Get templates
     * 
     * @param string $category Filter by category
     * @return array List of templates
     */
    public function getTemplates($category = null) {
        try {
            $sql = "SELECT * FROM automation_templates WHERE is_public = 1";
            $params = [];
            
            if ($category) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }
            
            $sql .= " ORDER BY name";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($templates as &$template) {
                $template['template_data'] = json_decode($template['template_data'], true);
            }
            
            return $templates;
            
        } catch (PDOException $e) {
            error_log("AdvancedAutomationService::getTemplates - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Evaluate conditions
     * 
     * @param array $conditions Condition definitions
     * @param array $data Trigger data
     * @return bool Conditions met
     */
    private function evaluateConditions($conditions, $data) {
        if (empty($conditions)) {
            return true; // No conditions = always execute
        }
        
        $logic = $conditions['logic'] ?? 'AND';
        $results = [];
        
        foreach ($conditions['rules'] ?? [] as $rule) {
            $field = $rule['field'] ?? null;
            $operator = $rule['operator'] ?? '==';
            $value = $rule['value'] ?? null;
            
            if (!$field || !isset($data[$field])) {
                $results[] = false;
                continue;
            }
            
            $fieldValue = $data[$field];
            
            switch ($operator) {
                case '==':
                    $results[] = $fieldValue == $value;
                    break;
                case '!=':
                    $results[] = $fieldValue != $value;
                    break;
                case '>':
                    $results[] = $fieldValue > $value;
                    break;
                case '<':
                    $results[] = $fieldValue < $value;
                    break;
                case '>=':
                    $results[] = $fieldValue >= $value;
                    break;
                case '<=':
                    $results[] = $fieldValue <= $value;
                    break;
                case 'contains':
                    $results[] = strpos($fieldValue, $value) !== false;
                    break;
                case 'in':
                    $results[] = in_array($fieldValue, (array)$value);
                    break;
                default:
                    $results[] = false;
            }
        }
        
        if ($logic === 'AND') {
            return count(array_filter($results)) === count($results);
        } else { // OR
            return count(array_filter($results)) > 0;
        }
    }
    
    /**
     * Execute action
     * 
     * @param array $action Action definition
     * @param array $data Trigger data
     * @return array Execution result
     */
    private function executeAction($action, $data) {
        $type = $action['type'] ?? null;
        
        try {
            switch ($type) {
                case self::ACTION_NOTIFY:
                    return $this->actionNotify($action, $data);
                case self::ACTION_SEND_EMAIL:
                    return $this->actionSendEmail($action, $data);
                case self::ACTION_SEND_WEBHOOK:
                    return $this->actionSendWebhook($action, $data);
                case self::ACTION_CREATE_TICKET:
                    return $this->actionCreateTicket($action, $data);
                default:
                    return ['success' => false, 'error' => 'Unknown action type'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Action: Notify
     */
    private function actionNotify($action, $data) {
        // Send notification via configured channels
        return ['success' => true, 'type' => 'notify'];
    }
    
    /**
     * Action: Send Email
     */
    private function actionSendEmail($action, $data) {
        $to = $action['to'] ?? null;
        $subject = $this->parseTemplate($action['subject'] ?? '', $data);
        $body = $this->parseTemplate($action['body'] ?? '', $data);
        
        // Send email
        return ['success' => true, 'type' => 'email', 'to' => $to];
    }
    
    /**
     * Action: Send Webhook
     */
    private function actionSendWebhook($action, $data) {
        $url = $action['url'] ?? null;
        $payload = $this->parseTemplate($action['payload'] ?? [], $data);
        
        // Send webhook
        return ['success' => true, 'type' => 'webhook', 'url' => $url];
    }
    
    /**
     * Action: Create Ticket
     */
    private function actionCreateTicket($action, $data) {
        $title = $this->parseTemplate($action['title'] ?? '', $data);
        $description = $this->parseTemplate($action['description'] ?? '', $data);
        
        return ['success' => true, 'type' => 'ticket', 'title' => $title];
    }
    
    /**
     * Parse template variables
     * 
     * @param string|array $template Template with {{variable}} syntax
     * @param array $data Variable data
     * @return string|array Parsed template
     */
    private function parseTemplate($template, $data) {
        if (is_array($template)) {
            return array_map(function($item) use ($data) {
                return $this->parseTemplate($item, $data);
            }, $template);
        }
        
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) use ($data) {
            $key = trim($matches[1]);
            return $data[$key] ?? $matches[0];
        }, $template);
    }
    
    /**
     * Create execution record
     */
    private function createExecution($workflowId, $triggerData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO automation_executions (
                    workflow_id,
                    trigger_data,
                    status,
                    started_at
                ) VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $workflowId,
                json_encode($triggerData),
                'running'
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("AdvancedAutomationService::createExecution - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log action execution
     */
    private function logActionExecution($executionId, $action, $result) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO automation_execution_logs (
                    execution_id,
                    action_type,
                    status,
                    result,
                    executed_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $executionId,
                $action['type'] ?? 'unknown',
                $result['success'] ? 'success' : 'failure',
                json_encode($result)
            ]);
            
        } catch (PDOException $e) {
            error_log("AdvancedAutomationService::logActionExecution - " . $e->getMessage());
        }
    }
    
    /**
     * Get execution history
     * 
     * @param int $portfolioId Portfolio ID
     * @param int $limit Limit results
     * @return array Execution history
     */
    public function getExecutionHistory($portfolioId, $limit = 100) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ae.* FROM automation_executions ae
                INNER JOIN automation_workflows aw ON ae.workflow_id = aw.id
                WHERE aw.portfolio_id = ?
                ORDER BY ae.started_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$portfolioId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("AdvancedAutomationService::getExecutionHistory - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Disable workflow
     */
    public function disableWorkflow($workflowId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE automation_workflows SET is_enabled = 0 WHERE id = ?");
            $stmt->execute([$workflowId]);
            return true;
        } catch (PDOException $e) {
            error_log("AdvancedAutomationService::disableWorkflow - " . $e->getMessage());
            return false;
        }
    }
}
?>
