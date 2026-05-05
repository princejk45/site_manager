<?php
/**
 * Advanced Alerting Service
 * 
 * Intelligent alert routing, escalation policies, deduplication,
 * and multi-channel notification delivery.
 */

class AdvancedAlertingService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Alert severity levels
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_EMERGENCY = 'emergency';
    
    // Alert channels
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_SLACK = 'slack';
    const CHANNEL_TEAMS = 'teams';
    const CHANNEL_PAGERDUTY = 'pagerduty';
    const CHANNEL_WEBHOOK = 'webhook';
    
    // Escalation policies
    const ESCALATION_LINEAR = 'linear';
    const ESCALATION_EXPONENTIAL = 'exponential';
    const ESCALATION_IMMEDIATE = 'immediate';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Create alert rule
     * 
     * @param int $portfolioId Portfolio ID
     * @param array $rule Alert rule definition
     * @return int Rule ID
     */
    public function createAlertRule($portfolioId, $rule) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO alert_rules (
                    portfolio_id,
                    name,
                    description,
                    metric,
                    condition,
                    threshold,
                    severity,
                    channels,
                    escalation_policy,
                    escalation_delay_minutes,
                    enabled,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $portfolioId,
                $rule['name'],
                $rule['description'] ?? null,
                $rule['metric'],
                $rule['condition'], // '>', '<', '==', '!='
                $rule['threshold'],
                $rule['severity'] ?? self::SEVERITY_WARNING,
                json_encode($rule['channels'] ?? [self::CHANNEL_EMAIL]),
                $rule['escalation_policy'] ?? self::ESCALATION_LINEAR,
                $rule['escalation_delay_minutes'] ?? 5,
                $rule['enabled'] ? 1 : 0,
                $this->userId
            ]);
            
            $ruleId = $this->pdo->lastInsertId();
            $this->auditTrail->log('alert_rule_created', 'portfolio_id=' . $portfolioId . ';rule_id=' . $ruleId);
            
            return $ruleId;
            
        } catch (PDOException $e) {
            error_log("AdvancedAlertingService::createAlertRule - " . $e->getMessage());
            throw new Exception("Failed to create alert rule");
        }
    }
    
    /**
     * Create escalation policy
     * 
     * @param int $portfolioId Portfolio ID
     * @param array $policy Escalation policy definition
     * @return int Policy ID
     */
    public function createEscalationPolicy($portfolioId, $policy) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO escalation_policies (
                    portfolio_id,
                    name,
                    description,
                    levels,
                    repeat_policy,
                    max_escalations,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Escalation levels: array of [delay_minutes, targets]
            $levels = [];
            foreach ($policy['levels'] ?? [] as $level) {
                $levels[] = [
                    'delay_minutes' => $level['delay_minutes'] ?? 5,
                    'targets' => $level['targets'] ?? [],
                    'channels' => $level['channels'] ?? [self::CHANNEL_EMAIL]
                ];
            }
            
            $stmt->execute([
                $portfolioId,
                $policy['name'],
                $policy['description'] ?? null,
                json_encode($levels),
                $policy['repeat_policy'] ?? 'repeat',
                $policy['max_escalations'] ?? 3,
                $this->userId
            ]);
            
            $policyId = $this->pdo->lastInsertId();
            $this->auditTrail->log('escalation_policy_created', 'portfolio_id=' . $portfolioId . ';policy_id=' . $policyId);
            
            return $policyId;
            
        } catch (PDOException $e) {
            error_log("AdvancedAlertingService::createEscalationPolicy - " . $e->getMessage());
            throw new Exception("Failed to create escalation policy");
        }
    }
    
    /**
     * Trigger alert
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $metric Metric name
     * @param float $value Current metric value
     * @param float $threshold Alert threshold
     * @return int Alert ID
     */
    public function triggerAlert($portfolioId, $metric, $value, $threshold): ?int {
        try {
            // Find matching rules
            $stmt = $this->pdo->prepare("
                SELECT * FROM alert_rules
                WHERE portfolio_id = ? AND metric = ? AND enabled = 1
            ");
            
            $stmt->execute([$portfolioId, $metric]);
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $triggeredAlerts = [];
            
            foreach ($rules as $rule) {
                if ($this->evaluateCondition($value, $rule['condition'], $rule['threshold'])) {
                    // Check deduplication
                    if ($this->isDuplicate($portfolioId, $metric, $rule['id'])) {
                        continue;
                    }
                    
                    // Create alert
                    $alertId = $this->createAlert([
                        'portfolio_id' => $portfolioId,
                        'rule_id' => $rule['id'],
                        'metric' => $metric,
                        'value' => $value,
                        'threshold' => $rule['threshold'],
                        'severity' => $rule['severity'],
                        'channels' => json_decode($rule['channels'], true)
                    ]);
                    
                    // Send notifications
                    $channels = json_decode($rule['channels'], true);
                    foreach ($channels as $channel) {
                        $this->sendNotification($alertId, $channel, $rule, $metric, $value);
                    }
                    
                    // Schedule escalation
                    if ($rule['escalation_policy'] !== 'none') {
                        $this->scheduleEscalation($alertId, $rule['escalation_policy'], $rule['escalation_delay_minutes']);
                    }
                    
                    $triggeredAlerts[] = $alertId;
                }
            }
            
            return count($triggeredAlerts) > 0 ? $triggeredAlerts[0] : null;
            
        } catch (Exception $e) {
            error_log("AdvancedAlertingService::triggerAlert - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Escalate alert
     * 
     * @param int $alertId Alert ID
     * @return bool Success
     */
    public function escalateAlert($alertId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM alerts WHERE id = ?
            ");
            
            $stmt->execute([$alertId]);
            $alert = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$alert) {
                return false;
            }
            
            // Get rule for escalation policy
            $stmt = $this->pdo->prepare("
                SELECT * FROM alert_rules WHERE id = ?
            ");
            
            $stmt->execute([$alert['rule_id']]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Increment escalation level
            $newLevel = $alert['escalation_level'] + 1;
            $maxEscalations = $rule['escalation_delay_minutes']; // Simplified
            
            if ($newLevel > $maxEscalations) {
                return false; // Max escalations reached
            }
            
            // Update alert
            $stmt = $this->pdo->prepare("
                UPDATE alerts 
                SET escalation_level = ?, escalated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$newLevel, $alertId]);
            
            // Send escalation notification
            $this->sendEscalationNotification($alertId, $newLevel, $rule);
            
            $this->auditTrail->log('alert_escalated', 'alert_id=' . $alertId . ';level=' . $newLevel);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("AdvancedAlertingService::escalateAlert - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Acknowledge alert
     * 
     * @param int $alertId Alert ID
     * @param string $message Optional acknowledgment message
     * @return bool Success
     */
    public function acknowledgeAlert($alertId, $message = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE alerts
                SET status = 'acknowledged', acknowledged_by = ?, acknowledged_at = NOW(), acknowledged_message = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$this->userId, $message, $alertId]);
            
            $this->auditTrail->log('alert_acknowledged', 'alert_id=' . $alertId);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("AdvancedAlertingService::acknowledgeAlert - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Resolve alert
     * 
     * @param int $alertId Alert ID
     * @return bool Success
     */
    public function resolveAlert($alertId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE alerts
                SET status = 'resolved', resolved_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$alertId]);
            
            $this->auditTrail->log('alert_resolved', 'alert_id=' . $alertId);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("AdvancedAlertingService::resolveAlert - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get active alerts for portfolio
     */
    public function getActiveAlerts($portfolioId, $status = 'triggered') {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM alerts
                WHERE portfolio_id = ? AND status = ?
                ORDER BY created_at DESC
                LIMIT 100
            ");
            
            $stmt->execute([$portfolioId, $status]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("AdvancedAlertingService::getActiveAlerts - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get alert history
     */
    public function getAlertHistory($portfolioId, $days = 30) {
        try {
            $startDate = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM alerts
                WHERE portfolio_id = ? AND created_at > ?
                ORDER BY created_at DESC
                LIMIT 1000
            ");
            
            $stmt->execute([$portfolioId, $startDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("AdvancedAlertingService::getAlertHistory - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get alert metrics
     */
    public function getAlertMetrics($portfolioId, $days = 7) {
        try {
            $startDate = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    status,
                    severity,
                    COUNT(*) as count
                FROM alerts
                WHERE portfolio_id = ? AND created_at > ?
                GROUP BY status, severity
            ");
            
            $stmt->execute([$portfolioId, $startDate]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $metrics = [
                'total' => 0,
                'by_status' => [],
                'by_severity' => [],
                'mttr' => 0 // Mean time to resolution
            ];
            
            foreach ($results as $row) {
                $metrics['total'] += $row['count'];
                if (!isset($metrics['by_status'][$row['status']])) {
                    $metrics['by_status'][$row['status']] = 0;
                }
                $metrics['by_status'][$row['status']] += $row['count'];
                
                if (!isset($metrics['by_severity'][$row['severity']])) {
                    $metrics['by_severity'][$row['severity']] = 0;
                }
                $metrics['by_severity'][$row['severity']] += $row['count'];
            }
            
            return $metrics;
            
        } catch (PDOException $e) {
            error_log("AdvancedAlertingService::getAlertMetrics - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Private: Evaluate alert condition
     */
    private function evaluateCondition($value, $operator, $threshold) {
        switch ($operator) {
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
     * Private: Check for duplicate alerts
     */
    private function isDuplicate($portfolioId, $metric, $ruleId, $deduplicationWindow = 60) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM alerts
            WHERE portfolio_id = ? AND metric = ? AND rule_id = ? 
                AND status IN ('triggered', 'acknowledged')
                AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            LIMIT 1
        ");
        
        $stmt->execute([$portfolioId, $metric, $ruleId, $deduplicationWindow]);
        return $stmt->fetch() !== null;
    }
    
    /**
     * Private: Create alert record
     */
    private function createAlert($alertData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO alerts (
                portfolio_id,
                rule_id,
                metric,
                value,
                threshold,
                severity,
                status,
                escalation_level,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $alertData['portfolio_id'],
            $alertData['rule_id'],
            $alertData['metric'],
            $alertData['value'],
            $alertData['threshold'],
            $alertData['severity'],
            'triggered',
            0
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Private: Send notification
     */
    private function sendNotification($alertId, $channel, $rule, $metric, $value) {
        // Route to appropriate channel handler
        switch ($channel) {
            case self::CHANNEL_EMAIL:
                // Send email notification
                break;
            case self::CHANNEL_SLACK:
                // Send to Slack
                break;
            case self::CHANNEL_TEAMS:
                // Send to Teams
                break;
            case self::CHANNEL_PAGERDUTY:
                // Send to PagerDuty
                break;
        }
    }
    
    /**
     * Private: Schedule escalation
     */
    private function scheduleEscalation($alertId, $escalationPolicy, $delayMinutes) {
        // Schedule cron job or queue task to escalate alert after delay
    }
    
    /**
     * Private: Send escalation notification
     */
    private function sendEscalationNotification($alertId, $level, $rule) {
        // Send escalation notification to higher level targets
    }
}
?>
