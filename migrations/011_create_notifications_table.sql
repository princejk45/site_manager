-- Notification Queue System
-- Handles multi-channel notifications (email, dashboard, webhook, Slack)
CREATE TABLE IF NOT EXISTS notification_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Recipient
    user_id INT,
    webhook_url VARCHAR(500),  -- For webhook notifications
    slack_channel VARCHAR(100),  -- For Slack notifications
    
    -- Notification Content
    notification_type ENUM(
        'email',
        'dashboard',
        'webhook',
        'slack',
        'discord'
    ) NOT NULL,
    
    priority ENUM('LOW', 'NORMAL', 'HIGH', 'CRITICAL') DEFAULT 'NORMAL',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    html_message TEXT,
    
    -- Related Entity
    related_entity_type VARCHAR(50),  -- 'website', 'bug_report', 'automation_rule'
    related_entity_id INT,
    
    -- Actions
    action_url VARCHAR(500),  -- URL to take action
    action_button_text VARCHAR(100),
    
    -- Status & Delivery
    status ENUM('PENDING', 'SENT', 'FAILED', 'BOUNCED', 'OPENED', 'CLICKED') DEFAULT 'PENDING',
    send_attempts INT DEFAULT 0,
    last_attempt_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    
    -- Retry
    retry_until TIMESTAMP,
    next_retry_at TIMESTAMP NULL,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scheduled_for TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_scheduled_for (scheduled_for)
);

-- User Notification Preferences
CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    
    -- Channel Preferences
    email_enabled BOOLEAN DEFAULT true,
    dashboard_enabled BOOLEAN DEFAULT true,
    slack_enabled BOOLEAN DEFAULT false,
    discord_enabled BOOLEAN DEFAULT false,
    webhook_enabled BOOLEAN DEFAULT false,
    
    -- Slack Settings
    slack_channel VARCHAR(100),
    slack_webhook_url VARCHAR(500),
    
    -- Discord Settings
    discord_webhook_url VARCHAR(500),
    
    -- Custom Webhook
    custom_webhook_url VARCHAR(500),
    custom_webhook_events JSON,  -- ['bug_created', 'health_alert', ...]
    
    -- Notification Types
    notify_health_alerts BOOLEAN DEFAULT true,
    notify_bug_reports BOOLEAN DEFAULT true,
    notify_expiry_reminders BOOLEAN DEFAULT true,
    notify_security_issues BOOLEAN DEFAULT true,
    notify_automation_triggered BOOLEAN DEFAULT true,
    notify_user_actions BOOLEAN DEFAULT false,
    
    -- Frequency
    digest_frequency ENUM('IMMEDIATE', 'HOURLY', 'DAILY', 'WEEKLY', 'NEVER') DEFAULT 'IMMEDIATE',
    digest_time TIME,  -- For daily/weekly digests
    digest_day_of_week INT,  -- 0=Sunday for weekly
    
    -- Quiet Hours
    quiet_hours_enabled BOOLEAN DEFAULT false,
    quiet_hours_start TIME,
    quiet_hours_end TIME,
    
    -- Threshold
    min_severity_to_notify ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id),
    INDEX idx_user_id (user_id)
);

-- Notification History (for analytics)
CREATE TABLE IF NOT EXISTS notification_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    notification_id INT,
    
    status ENUM('SENT', 'FAILED', 'OPENED', 'CLICKED', 'BOUNCED') NOT NULL,
    status_message TEXT,
    
    event_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (notification_id) REFERENCES notification_queue(id) ON DELETE CASCADE,
    INDEX idx_notification_id (notification_id),
    INDEX idx_event_timestamp (event_timestamp)
);
