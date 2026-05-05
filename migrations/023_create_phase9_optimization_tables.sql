-- Phase 9: System Integration & Performance Optimization
-- Tables for ApiGatewayService, CachingService, DatabaseOptimizationService, LoadBalancingService, MonitoringService

-- API endpoints table
CREATE TABLE IF NOT EXISTS api_endpoints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    methods JSON,
    handler_class VARCHAR(255),
    handler_method VARCHAR(255),
    authentication_required TINYINT DEFAULT 1,
    rate_limit_enabled TINYINT DEFAULT 1,
    rate_limit_requests INT DEFAULT 1000,
    rate_limit_window_seconds INT DEFAULT 3600,
    cache_enabled TINYINT DEFAULT 1,
    cache_ttl_seconds INT DEFAULT 300,
    documentation LONGTEXT,
    active TINYINT DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_endpoint (portfolio_id, endpoint),
    INDEX idx_active (active),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API tokens table
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    expires_at TIMESTAMP,
    active TINYINT DEFAULT 1,
    last_used_at TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_active (portfolio_id, active),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API gateway logs table
CREATE TABLE IF NOT EXISTS api_gateway_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    user_id INT,
    method VARCHAR(10),
    path VARCHAR(500),
    endpoint VARCHAR(255),
    response_time_ms INT,
    status_code INT,
    request_size INT,
    response_size INT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    error_message VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_created (portfolio_id, created_at),
    INDEX idx_endpoint (endpoint),
    INDEX idx_status_code (status_code),
    INDEX idx_method_path (method, path),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache storage table
CREATE TABLE IF NOT EXISTS cache_storage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key VARCHAR(255) UNIQUE NOT NULL,
    data LONGBLOB,
    ttl INT,
    expires_at TIMESTAMP NULL,
    portfolio_id INT,
    access_count INT DEFAULT 0,
    last_accessed TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_expires (portfolio_id, expires_at),
    INDEX idx_access_count (access_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache statistics table
CREATE TABLE IF NOT EXISTS cache_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key VARCHAR(255),
    tier VARCHAR(50),
    hits INT DEFAULT 0,
    misses INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_key (key),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Slow queries table
CREATE TABLE IF NOT EXISTS slow_queries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    query LONGTEXT,
    duration_ms INT,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_duration (duration_ms),
    INDEX idx_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Load balancer servers table
CREATE TABLE IF NOT EXISTS load_balancer_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT DEFAULT 80,
    weight INT DEFAULT 1,
    strategy VARCHAR(50),
    health_check_type VARCHAR(50),
    health_check_path VARCHAR(255),
    health_check_interval_seconds INT DEFAULT 30,
    max_connections INT DEFAULT 1000,
    active_connections INT DEFAULT 0,
    total_requests BIGINT DEFAULT 0,
    failed_requests BIGINT DEFAULT 0,
    avg_response_time INT DEFAULT 0,
    response_time_ms INT,
    status VARCHAR(50) DEFAULT 'healthy',
    last_check_time TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_status (portfolio_id, status),
    INDEX idx_host_port (host, port),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Load balancer configuration table
CREATE TABLE IF NOT EXISTS load_balancer_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT UNIQUE NOT NULL,
    strategy VARCHAR(50) DEFAULT 'round_robin',
    last_index INT DEFAULT 0,
    healthcheck_interval_seconds INT DEFAULT 30,
    failover_enabled TINYINT DEFAULT 1,
    session_persistence_enabled TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monitoring metrics table
CREATE TABLE IF NOT EXISTS monitoring_metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    metric_type VARCHAR(50),
    value DECIMAL(10, 2),
    metadata JSON,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_type_recorded (portfolio_id, metric_type, recorded_at),
    INDEX idx_recorded_at (recorded_at),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monitoring alerts table
CREATE TABLE IF NOT EXISTS monitoring_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    metric_type VARCHAR(50),
    condition VARCHAR(10),
    threshold DECIMAL(10, 2),
    level VARCHAR(50),
    message VARCHAR(500),
    enabled TINYINT DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_portfolio_enabled (portfolio_id, enabled),
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monitoring alerts triggered table
CREATE TABLE IF NOT EXISTS monitoring_alerts_triggered (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    alert_id INT NOT NULL,
    portfolio_id INT NOT NULL,
    value DECIMAL(10, 2),
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged TINYINT DEFAULT 0,
    acknowledged_by INT,
    acknowledged_at TIMESTAMP NULL,
    
    INDEX idx_alert_triggered (alert_id, triggered_at),
    INDEX idx_portfolio_triggered (portfolio_id, triggered_at),
    INDEX idx_acknowledged (acknowledged),
    FOREIGN KEY (alert_id) REFERENCES monitoring_alerts(id) ON DELETE CASCADE,
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add feature flags for Phase 9 to licenses table
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS api_gateway_enabled TINYINT DEFAULT 0;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS caching_enabled TINYINT DEFAULT 0;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS load_balancing_enabled TINYINT DEFAULT 0;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS monitoring_enabled TINYINT DEFAULT 0;

-- Create view for API usage by endpoint
CREATE OR REPLACE VIEW api_usage_by_endpoint AS
SELECT 
    endpoint,
    method,
    COUNT(*) as request_count,
    AVG(response_time_ms) as avg_response_time,
    MAX(response_time_ms) as max_response_time,
    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count,
    ROUND(SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as error_rate
FROM api_gateway_logs
GROUP BY endpoint, method
ORDER BY request_count DESC;

-- Create view for server health status
CREATE OR REPLACE VIEW server_health_status AS
SELECT 
    portfolio_id,
    host,
    port,
    status,
    COUNT(*) as check_count,
    AVG(response_time_ms) as avg_response_time,
    MAX(last_check_time) as last_check
FROM load_balancer_servers
GROUP BY portfolio_id, host, port, status;

-- Create view for cache performance
CREATE OR REPLACE VIEW cache_performance AS
SELECT 
    key,
    hits,
    misses,
    (hits + misses) as total_accesses,
    ROUND(hits / (hits + misses) * 100, 2) as hit_rate
FROM cache_stats
WHERE (hits + misses) > 0
ORDER BY hit_rate DESC;

-- Create view for monitoring dashboard
CREATE OR REPLACE VIEW monitoring_dashboard AS
SELECT 
    portfolio_id,
    'cpu' as metric_type,
    AVG(value) as current_value,
    MAX(value) as peak_value,
    MIN(value) as min_value
FROM monitoring_metrics
WHERE metric_type = 'cpu' AND recorded_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY portfolio_id
UNION ALL
SELECT 
    portfolio_id,
    'memory' as metric_type,
    AVG(value) as current_value,
    MAX(value) as peak_value,
    MIN(value) as min_value
FROM monitoring_metrics
WHERE metric_type = 'memory' AND recorded_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY portfolio_id;
