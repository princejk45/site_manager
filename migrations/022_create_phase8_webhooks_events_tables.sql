-- Phase 8: Advanced Webhooks, Real-time Events, Event Logging
-- Tables for WebhookEventService, RealtimeEventStreamService, EventLogService

-- Webhooks table
CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    events JSON,
    secret VARCHAR(255) NOT NULL,
    active TINYINT DEFAULT 1,
    headers JSON,
    retry_attempts INT DEFAULT 3,
    retry_delay_seconds INT DEFAULT 300,
    last_delivery_at TIMESTAMP NULL,
    failure_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_active (portfolio_id, active),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE webhooks ADD COLUMN IF NOT EXISTS portfolio_id INT NULL AFTER id;
ALTER TABLE webhooks ADD COLUMN IF NOT EXISTS url VARCHAR(500) NULL AFTER portfolio_id;
ALTER TABLE webhooks ADD COLUMN IF NOT EXISTS secret VARCHAR(255) NULL AFTER events;
ALTER TABLE webhooks ADD COLUMN IF NOT EXISTS active TINYINT DEFAULT 1 AFTER secret;
ALTER TABLE webhooks ADD COLUMN IF NOT EXISTS retry_attempts INT DEFAULT 3 AFTER headers;
ALTER TABLE webhooks ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE webhooks ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE webhooks ADD COLUMN IF NOT EXISTS last_delivery_at TIMESTAMP NULL AFTER retry_delay_seconds;

UPDATE webhooks
SET url = COALESCE(url, webhook_url),
    active = COALESCE(active, is_active, 1),
    retry_attempts = COALESCE(retry_attempts, max_retries, 3),
    last_delivery_at = COALESCE(last_delivery_at, last_triggered_at)
WHERE url IS NULL
   OR last_delivery_at IS NULL;

-- Webhook events table
CREATE TABLE IF NOT EXISTS webhook_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_type (portfolio_id, event_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook deliveries table
CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    webhook_id INT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    response_code INT,
    response_body LONGTEXT,
    attempts INT DEFAULT 0,
    next_retry_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_webhook_status (webhook_id, status),
    INDEX idx_event_id (event_id),
    INDEX idx_created_at (created_at),
    INDEX idx_next_retry (next_retry_at),
    FOREIGN KEY (event_id) REFERENCES webhook_events(id) ON DELETE CASCADE,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE webhook_deliveries ADD COLUMN IF NOT EXISTS event_id BIGINT NULL AFTER id;
ALTER TABLE webhook_deliveries ADD COLUMN IF NOT EXISTS response_code INT NULL AFTER status;
ALTER TABLE webhook_deliveries ADD COLUMN IF NOT EXISTS attempts INT DEFAULT 0 AFTER response_body;
ALTER TABLE webhook_deliveries ADD COLUMN IF NOT EXISTS next_retry_at TIMESTAMP NULL AFTER attempts;
ALTER TABLE webhook_deliveries ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER delivered_at;

UPDATE webhook_deliveries
SET response_code = COALESCE(response_code, http_status_code),
    attempts = COALESCE(attempts, attempt_number, 0),
    created_at = COALESCE(created_at, triggered_at)
WHERE response_code IS NULL
   OR attempts = 0
   OR created_at IS NULL;

-- Event stream connections table
CREATE TABLE IF NOT EXISTS event_stream_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    connection_id VARCHAR(255) UNIQUE NOT NULL,
    portfolio_id INT NOT NULL,
    streams JSON,
    active TINYINT DEFAULT 1,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_activity TIMESTAMP NULL,
    disconnected_at TIMESTAMP NULL,
    
    INDEX idx_portfolio_active (portfolio_id, active),
    INDEX idx_last_heartbeat (last_heartbeat),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event stream messages table
CREATE TABLE IF NOT EXISTS event_stream_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    connection_id VARCHAR(255) NOT NULL,
    message_type VARCHAR(50),
    stream_type VARCHAR(50),
    data JSON,
    delivered TINYINT DEFAULT 0,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_connection_delivered (connection_id, delivered),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (connection_id) REFERENCES event_stream_connections(connection_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event stream logs table
CREATE TABLE IF NOT EXISTS event_stream_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    stream_type VARCHAR(50),
    event_data JSON,
    connections_notified INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_stream (portfolio_id, stream_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event logs table
CREATE TABLE IF NOT EXISTS event_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    user_id INT,
    category VARCHAR(50),
    event_type VARCHAR(100),
    severity VARCHAR(20),
    details JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_timestamp (portfolio_id, timestamp),
    INDEX idx_severity (severity),
    INDEX idx_category (category),
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event logs archive table
CREATE TABLE IF NOT EXISTS event_logs_archive (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    user_id INT,
    category VARCHAR(50),
    event_type VARCHAR(100),
    severity VARCHAR(20),
    details JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_timestamp (portfolio_id, timestamp),
    INDEX idx_archived_at (archived_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add feature flags for Phase 8 to licenses table
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS webhooks_enabled TINYINT DEFAULT 0;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS realtime_events_enabled TINYINT DEFAULT 0;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS event_logging_enabled TINYINT DEFAULT 0;

-- Create view for recent critical events
CREATE OR REPLACE VIEW critical_events AS
SELECT 
    el.id,
    el.portfolio_id,
    el.event_type,
    el.severity,
    el.timestamp,
    u.email as user_email
FROM event_logs el
LEFT JOIN users u ON el.user_id = u.id
WHERE el.severity = 'critical'
ORDER BY el.timestamp DESC;

-- Create view for webhook delivery performance
CREATE OR REPLACE VIEW webhook_performance AS
SELECT 
    w.id,
    w.portfolio_id,
    COALESCE(w.url, w.webhook_url) as url,
    COUNT(wd.id) as total_deliveries,
    SUM(CASE WHEN wd.status = 'delivered' THEN 1 ELSE 0 END) as successful_deliveries,
    SUM(CASE WHEN wd.status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries,
    ROUND(SUM(CASE WHEN wd.status = 'delivered' THEN 1 ELSE 0 END) / COUNT(wd.id) * 100, 2) as success_rate,
    MAX(wd.delivered_at) as last_delivery
FROM webhooks w
LEFT JOIN webhook_deliveries wd ON w.id = wd.webhook_id
GROUP BY w.id, w.portfolio_id, COALESCE(w.url, w.webhook_url);

-- Create view for stream statistics
CREATE OR REPLACE VIEW stream_statistics AS
SELECT 
    esc.portfolio_id,
    COUNT(DISTINCT esc.connection_id) as active_connections,
    COUNT(DISTINCT esm.connection_id) as connections_with_messages,
    SUM(CASE WHEN esm.delivered = 0 THEN 1 ELSE 0 END) as pending_messages,
    COUNT(DISTINCT esl.stream_type) as active_streams
FROM event_stream_connections esc
LEFT JOIN event_stream_messages esm ON esc.connection_id = esm.connection_id
LEFT JOIN event_stream_logs esl ON esc.portfolio_id = esl.portfolio_id
WHERE esc.active = 1
GROUP BY esc.portfolio_id;
