-- Webhooks System - Outbound integrations (Slack, Discord, custom)
CREATE TABLE IF NOT EXISTS webhooks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Webhook Configuration
    name VARCHAR(255) NOT NULL,
    description TEXT,
    webhook_url VARCHAR(500) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    
    -- Events to Subscribe
    events JSON NOT NULL,  -- ["website_created", "health_alert", "bug_report_generated"]
    
    -- Metadata
    event_filter JSON,  -- { "severity": "CRITICAL", "website_type": "domain" }
    
    -- Retry Policy
    max_retries INT DEFAULT 3,
    retry_delay_seconds INT DEFAULT 60,
    timeout_seconds INT DEFAULT 30,
    
    -- Headers
    headers JSON,  -- Custom headers to send
    
    -- Status
    status ENUM('ACTIVE', 'DISABLED', 'ERRORING') DEFAULT 'ACTIVE',
    last_triggered_at TIMESTAMP NULL,
    last_error_at TIMESTAMP NULL,
    last_error_message TEXT,
    failure_count INT DEFAULT 0,
    
    -- Statistics
    total_triggers INT DEFAULT 0,
    total_successes INT DEFAULT 0,
    total_failures INT DEFAULT 0,
    
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_is_active (is_active),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by)
);

-- Webhook Delivery Log
CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    webhook_id INT NOT NULL,
    
    -- Event Details
    event_type VARCHAR(100) NOT NULL,
    event_data JSON NOT NULL,
    
    -- Delivery Attempt
    attempt_number INT DEFAULT 1,
    http_status_code INT,
    http_method VARCHAR(10) DEFAULT 'POST',
    
    -- Response
    response_body LONGTEXT,
    response_time_ms INT,
    
    -- Status
    status ENUM('SUCCESS', 'FAILED', 'RETRY', 'TIMEOUT', 'INVALID_URL') DEFAULT 'SUCCESS',
    error_message TEXT,
    
    # Timing
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP NULL,
    
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE,
    INDEX idx_webhook_id (webhook_id),
    INDEX idx_status (status),
    INDEX idx_triggered_at (triggered_at)
);

-- Activity Feed - Real-time user activity log
CREATE TABLE IF NOT EXISTS activity_feed (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Actor
    user_id INT,
    user_name VARCHAR(255),
    
    -- Activity
    activity_type ENUM(
        'site_added',
        'site_updated',
        'site_deleted',
        'rule_created',
        'rule_triggered',
        'bug_resolved',
        'export_completed',
        'import_completed',
        'health_alert',
        'security_issue_found',
        'user_login',
        'settings_changed',
        'api_key_rotated'
    ) NOT NULL,
    
    -- Context
    entity_type VARCHAR(50),  -- 'website', 'automation_rule', 'bug_report'
    entity_id INT,
    entity_name VARCHAR(255),
    
    -- Details
    title VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50),  -- For UI display
    color VARCHAR(20),  -- For UI display (danger, warning, success, info)
    
    -- Metadata
    metadata JSON,
    
    -- Visibility
    is_public BOOLEAN DEFAULT false,  -- Show to other users?
    related_users JSON,  -- Array of user IDs who should see this
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_entity_id (entity_id),
    INDEX idx_created_at (created_at),
    INDEX idx_is_public (is_public)
);
