<?php
/**
 * RuleEngine Service
 * 
 * Core automation rules engine that evaluates IF conditions and executes THEN actions.
 * Supports complex conditions, multiple triggers, and various action types.
 * 
 * @author Fullmidia
 * @version 1.0.0
 * @package Services\Automation
 */

class RuleEngine {
    
    private PDO $pdo;
    private $auditTrail;
    private $userId;
    
    // Condition operators
    const OPERATORS = ['equals', 'gt', 'lt', 'gte', 'lte', 'contains', 'starts_with', 'ends_with'];
    
    // Logical operators for multiple conditions
    const LOGIC = ['AND', 'OR'];
    
    // Action types
    const ACTIONS = ['send_email', 'create_ticket', 'update_status', 'webhook', 'log_event'];
    
    /**
     * Initialize RuleEngine
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
     * Create a new automation rule
     * 
     * @param int $websiteId Website ID
     * @param array $ruleData Rule configuration
     * @return int Rule ID
     */
    public function createRule($websiteId, $ruleData) {
        // Validate rule structure
        $this->validateRuleStructure($ruleData);
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO automation_rules (
                    website_id, name, description, trigger_type, trigger_config,
                    conditions_logic, conditions_json, actions_json, is_active,
                    execution_limit_per_day, cooldown_minutes, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $websiteId,
                $ruleData['name'],
                $ruleData['description'] ?? null,
                $ruleData['trigger_type'] ?? 'HEALTH_CHECK',
                json_encode($ruleData['trigger_config'] ?? []),
                $ruleData['conditions_logic'] ?? 'AND',
                json_encode($ruleData['conditions'] ?? []),
                json_encode($ruleData['actions'] ?? []),
                $ruleData['is_active'] ?? 1,
                $ruleData['execution_limit_per_day'] ?? 10,
                $ruleData['cooldown_minutes'] ?? 60,
                $this->userId
            ]);
            
            $ruleId = $this->pdo->lastInsertId();
            
            // Log creation
            $this->auditTrail->log(
                $this->userId,
                'automation_rule_created',
                'automation_rule',
                $ruleId,
                ['name' => $ruleData['name']]
            );
            
            return $ruleId;
        } catch (PDOException $e) {
            error_log("Rule creation error: " . $e->getMessage());
            throw new Exception("Failed to create rule: " . $e->getMessage());
        }
    }
    
    /**
     * Validate rule structure
     * 
     * @param array $ruleData Rule data to validate
     * @throws Exception
     */
    private function validateRuleStructure($ruleData) {
        if (empty($ruleData['name'])) {
            throw new Exception('Rule name is required');
        }
        
        if (empty($ruleData['conditions']) || !is_array($ruleData['conditions'])) {
            throw new Exception('Conditions array required');
        }
        
        if (empty($ruleData['actions']) || !is_array($ruleData['actions'])) {
            throw new Exception('Actions array required');
        }
        
        // Validate conditions
        foreach ($ruleData['conditions'] as $condition) {
            if (empty($condition['field']) || empty($condition['operator']) || !isset($condition['value'])) {
                throw new Exception('Invalid condition structure');
            }
            
            if (!in_array($condition['operator'], self::OPERATORS)) {
                throw new Exception('Invalid operator: ' . $condition['operator']);
            }
        }
        
        // Validate actions
        foreach ($ruleData['actions'] as $action) {
            if (empty($action['type']) || !in_array($action['type'], self::ACTIONS)) {
                throw new Exception('Invalid action type: ' . ($action['type'] ?? 'unknown'));
            }
        }
    }
    
    /**
     * Evaluate if a rule should trigger
     * 
     * @param array $rule Rule data
     * @param array $diagnosticData Current diagnostic data
     * @return bool Whether rule condition is met
     */
    public function evaluateConditions($rule, $diagnosticData) {
        $conditions = json_decode($rule['conditions_json'], true) ?? [];
        $logic = $rule['conditions_logic'] ?? 'AND';
        
        if (empty($conditions)) {
            return false;
        }
        
        $results = [];
        
        foreach ($conditions as $condition) {
            $results[] = $this->evaluateCondition($condition, $diagnosticData);
        }
        
        // Apply logic
        if ($logic === 'AND') {
            return array_reduce($results, function($carry, $item) {
                return $carry && $item;
            }, true);
        } elseif ($logic === 'OR') {
            return array_reduce($results, function($carry, $item) {
                return $carry || $item;
            }, false);
        }
        
        return false;
    }
    
    /**
     * Evaluate a single condition
     * 
     * @param array $condition Condition configuration
     * @param array $data Data to test against
     * @return bool Condition result
     */
    private function evaluateCondition($condition, $data) {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        
        // Get nested field value using dot notation (e.g., "components.security.score")
        $dataValue = $this->getNestedValue($data, $field);
        
        if ($dataValue === null) {
            return false;
        }
        
        switch ($operator) {
            case 'equals':
                return $dataValue == $value;
            case 'gt':
                return (float)$dataValue > (float)$value;
            case 'lt':
                return (float)$dataValue < (float)$value;
            case 'gte':
                return (float)$dataValue >= (float)$value;
            case 'lte':
                return (float)$dataValue <= (float)$value;
            case 'contains':
                return strpos((string)$dataValue, (string)$value) !== false;
            case 'starts_with':
                return strpos((string)$dataValue, (string)$value) === 0;
            case 'ends_with':
                return substr((string)$dataValue, -strlen((string)$value)) === (string)$value;
            default:
                return false;
        }
    }
    
    /**
     * Get nested value from array using dot notation
     * 
     * @param array $array Data array
     * @param string $key Dot-notation key (e.g., "components.security.score")
     * @return mixed Value or null
     */
    private function getNestedValue($array, $key) {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    /**
     * Get active rules for a website
     * 
     * @param int $websiteId Website ID
     * @return array Active rules
     */
    public function getActiveRules($websiteId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM automation_rules
                WHERE website_id = ? AND is_active = 1
                ORDER BY created_at DESC
            ");
            $stmt->execute([$websiteId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get active rules error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all rules for a website (active and inactive)
     * 
     * @param int $websiteId Website ID
     * @return array All rules
     */
    public function getAllRules($websiteId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM automation_rules
                WHERE website_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$websiteId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get all rules error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get rule by ID
     * 
     * @param int $ruleId Rule ID
     * @return array|null Rule data
     */
    public function getRule($ruleId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM automation_rules WHERE id = ?");
            $stmt->execute([$ruleId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get rule error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update a rule
     * 
     * @param int $ruleId Rule ID
     * @param array $updates Updates to apply
     * @return bool Success
     */
    public function updateRule($ruleId, $updates) {
        try {
            $rule = $this->getRule($ruleId);
            if (!$rule) {
                throw new Exception('Rule not found');
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE automation_rules SET
                    name = ?,
                    description = ?,
                    trigger_config = ?,
                    conditions_logic = ?,
                    conditions_json = ?,
                    actions_json = ?,
                    is_active = ?,
                    execution_limit_per_day = ?,
                    cooldown_minutes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $updates['name'] ?? $rule['name'],
                $updates['description'] ?? $rule['description'],
                json_encode($updates['trigger_config'] ?? json_decode($rule['trigger_config'], true)),
                $updates['conditions_logic'] ?? $rule['conditions_logic'],
                json_encode($updates['conditions'] ?? json_decode($rule['conditions_json'], true)),
                json_encode($updates['actions'] ?? json_decode($rule['actions_json'], true)),
                $updates['is_active'] ?? $rule['is_active'],
                $updates['execution_limit_per_day'] ?? $rule['execution_limit_per_day'],
                $updates['cooldown_minutes'] ?? $rule['cooldown_minutes'],
                $ruleId
            ]);
            
            $this->auditTrail->log(
                $this->userId,
                'automation_rule_updated',
                'automation_rule',
                $ruleId,
                ['changes' => array_keys($updates)]
            );
            
            return true;
        } catch (PDOException $e) {
            error_log("Update rule error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a rule
     * 
     * @param int $ruleId Rule ID
     * @return bool Success
     */
    public function deleteRule($ruleId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM automation_rules WHERE id = ?");
            $stmt->execute([$ruleId]);
            
            $this->auditTrail->log(
                $this->userId,
                'automation_rule_deleted',
                'automation_rule',
                $ruleId,
                []
            );
            
            return true;
        } catch (PDOException $e) {
            error_log("Delete rule error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enable/disable a rule
     * 
     * @param int $ruleId Rule ID
     * @param bool $active Active status
     * @return bool Success
     */
    public function setActive($ruleId, $active) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE automation_rules SET is_active = ?, updated_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$active ? 1 : 0, $ruleId]);
            
            $this->auditTrail->log(
                $this->userId,
                'automation_rule_' . ($active ? 'enabled' : 'disabled'),
                'automation_rule',
                $ruleId,
                []
            );
            
            return true;
        } catch (PDOException $e) {
            error_log("Set active error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if rule can execute (respects limits and cooldown)
     * 
     * @param int $ruleId Rule ID
     * @return array Status with reasons if cannot execute
     */
    public function canExecute($ruleId) {
        try {
            $rule = $this->getRule($ruleId);
            if (!$rule) {
                return ['can_execute' => false, 'reason' => 'Rule not found'];
            }
            
            if (!$rule['is_active']) {
                return ['can_execute' => false, 'reason' => 'Rule is disabled'];
            }
            
            // Check daily limit
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count FROM automation_rule_executions
                WHERE rule_id = ? AND DATE(executed_at) = CURDATE()
            ");
            $stmt->execute([$ruleId]);
            $result = $stmt->fetch();
            
            if ($result['count'] >= $rule['execution_limit_per_day']) {
                return ['can_execute' => false, 'reason' => 'Daily execution limit reached'];
            }
            
            // Check cooldown
            $stmt = $this->pdo->prepare("
                SELECT MAX(executed_at) as last_execution FROM automation_rule_executions
                WHERE rule_id = ? AND status = 'SUCCESS'
            ");
            $stmt->execute([$ruleId]);
            $result = $stmt->fetch();
            
            if ($result['last_execution']) {
                $lastExecution = strtotime($result['last_execution']);
                $cooldownExpiry = $lastExecution + ($rule['cooldown_minutes'] * 60);
                
                if (time() < $cooldownExpiry) {
                    $minutesRemaining = ceil(($cooldownExpiry - time()) / 60);
                    return ['can_execute' => false, 'reason' => "Cooldown active ($minutesRemaining minutes remaining)"];
                }
            }
            
            return ['can_execute' => true, 'reason' => 'Rule ready to execute'];
        } catch (PDOException $e) {
            error_log("Can execute error: " . $e->getMessage());
            return ['can_execute' => false, 'reason' => 'Error checking execution status'];
        }
    }
    
    /**
     * Get rule statistics
     * 
     * @param int $ruleId Rule ID
     * @return array Statistics
     */
    public function getStatistics($ruleId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_executions,
                    SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed,
                    AVG(execution_time_ms) as avg_execution_time,
                    MAX(executed_at) as last_execution
                FROM automation_rule_executions
                WHERE rule_id = ?
            ");
            $stmt->execute([$ruleId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get statistics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get execution history
     * 
     * @param int $ruleId Rule ID
     * @param int $limit Number of records
     * @return array Execution history
     */
    public function getExecutionHistory($ruleId, $limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM automation_rule_executions
                WHERE rule_id = ?
                ORDER BY executed_at DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $ruleId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get execution history error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log rule execution
     * 
     * @param int $ruleId Rule ID
     * @param string $status Execution status (SUCCESS, FAILED, PARTIAL)
     * @param int $executionTime Time in milliseconds
     * @param array $result Result data
     * @return int Execution log ID
     */
    public function logExecution($ruleId, $status, $executionTime, $result = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO automation_rule_executions (
                    rule_id, status, execution_time_ms, result_json, executed_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $ruleId,
                $status,
                $executionTime,
                json_encode($result)
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Log execution error: " . $e->getMessage());
            return 0;
        }
    }
}
?>
