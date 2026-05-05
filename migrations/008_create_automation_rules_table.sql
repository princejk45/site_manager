-- Automation Rules Table
-- Enables IF/THEN rules for proactive site management
CREATE TABLE IF NOT EXISTS automation_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    
    -- Rule Definition
    trigger_type ENUM(
        'health_drop',
        'expiry_near',
        'error_rate_high',
        'uptime_drop',
        'security_issue',
        'backup_old',
        'plugin_update_available'
    ) NOT NULL,
    trigger_threshold INT DEFAULT 0,
    trigger_threshold_unit VARCHAR(50),  -- 'percent', 'days', 'hours'
    
    -- Action Definition
    action_type ENUM(
        'alert_admin',
        'notify_owner',
        'disable_api',
        'create_ticket',
        'send_email',
        'webhook_trigger',
        'export_to_sheets'
    ) NOT NULL,
    action_params JSON,
    
    -- Conditions (JSON for flexibility)
    conditions JSON,  -- { "site_type": "domain", "status": "active" }
    
    -- Metadata
    execution_count INT DEFAULT 0,
    last_executed_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_is_active (is_active),
    INDEX idx_trigger_type (trigger_type),
    INDEX idx_created_by (created_by)
);

-- Automation Rule Execution Log
CREATE TABLE IF NOT EXISTS automation_rule_executions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rule_id INT NOT NULL,
    website_id INT,
    trigger_value DECIMAL(10, 2),
    action_result ENUM('success', 'failed', 'skipped') NOT NULL,
    error_message TEXT,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL,
    INDEX idx_rule_id (rule_id),
    INDEX idx_executed_at (executed_at)
);
