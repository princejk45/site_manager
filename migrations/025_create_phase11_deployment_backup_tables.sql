-- Migration 025: Create Phase 11 Deployment, Backup, Configuration, and Performance Tables
-- Phase 11: Production Deployment & Hardening

-- Deployment Tables
CREATE TABLE IF NOT EXISTS deployments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deployment_id VARCHAR(255) UNIQUE NOT NULL,
    portfolio_id INT NOT NULL,
    deployment_type ENUM('blue_green', 'canary', 'rolling') DEFAULT 'blue_green',
    status ENUM('pending', 'started', 'migrations_complete', 'tests_passed', 'health_check_passed', 'traffic_switched', 'completed', 'failed', 'rolled_back') DEFAULT 'pending',
    git_commit_hash VARCHAR(255),
    previous_deployment_id VARCHAR(255),
    environment VARCHAR(50) DEFAULT 'production',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deployment_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deployment_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    status VARCHAR(50),
    message TEXT,
    details JSON,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deployment_id) REFERENCES deployments(deployment_id),
    INDEX idx_deployment_id (deployment_id),
    INDEX idx_event_type (event_type),
    INDEX idx_occurred_at (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deployment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deployment_id VARCHAR(255) NOT NULL,
    log_type VARCHAR(50), -- migrations, tests, health_checks, traffic_switch, rollback
    log_level VARCHAR(20), -- INFO, WARNING, ERROR
    log_message TEXT,
    log_details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deployment_id) REFERENCES deployments(deployment_id),
    INDEX idx_deployment_id (deployment_id),
    INDEX idx_log_type (log_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup Tables
CREATE TABLE IF NOT EXISTS backup_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_id VARCHAR(255) UNIQUE NOT NULL,
    backup_type ENUM('full', 'incremental', 'file', 'snapshot') DEFAULT 'full',
    filename VARCHAR(255),
    filepath TEXT,
    size_bytes BIGINT,
    status ENUM('pending', 'in_progress', 'completed', 'failed', 'deleted') DEFAULT 'pending',
    integrity_verified BOOLEAN DEFAULT 0,
    integrity_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_backup_type (backup_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS backup_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('full', 'incremental', 'file') DEFAULT 'full',
    schedule_cron VARCHAR(100), -- cron expression
    portfolio_id INT,
    is_active BOOLEAN DEFAULT 1,
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_backup_type (backup_type),
    INDEX idx_next_run_at (next_run_at),
    INDEX idx_portfolio_id (portfolio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS restore_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_id VARCHAR(255) NOT NULL,
    snapshot_id VARCHAR(255),
    point_in_time TIMESTAMP NULL,
    status ENUM('started', 'in_progress', 'completed', 'failed') DEFAULT 'started',
    restored_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (backup_id) REFERENCES backup_metadata(backup_id),
    INDEX idx_backup_id (backup_id),
    INDEX idx_restored_at (restored_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_id VARCHAR(255) UNIQUE NOT NULL,
    snapshot_type VARCHAR(100), -- pre_restore, post_restore, pre_deployment
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_snapshot_type (snapshot_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration Tables
CREATE TABLE IF NOT EXISTS configuration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(255) NOT NULL,
    value LONGTEXT,
    environment VARCHAR(50) DEFAULT 'production', -- development, staging, production
    is_encrypted BOOLEAN DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_key_env (`key`, environment),
    INDEX idx_environment (environment),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS secrets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    secret_name VARCHAR(255) NOT NULL,
    secret_value LONGTEXT NOT NULL,
    environment VARCHAR(50) DEFAULT 'production',
    metadata JSON,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL,
    UNIQUE KEY unique_secret_env (secret_name, environment),
    INDEX idx_environment (environment),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuration_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    environment VARCHAR(50),
    `key` VARCHAR(255),
    action VARCHAR(50), -- set, delete, update
    is_encrypted BOOLEAN DEFAULT 0,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_environment (environment),
    INDEX idx_key (`key`),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS secret_access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    secret_name VARCHAR(255),
    environment VARCHAR(50),
    action VARCHAR(50), -- read, write, rotate
    ip_address VARCHAR(45),
    user_agent TEXT,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_secret_name (secret_name),
    INDEX idx_environment (environment),
    INDEX idx_accessed_at (accessed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS secret_rotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    secret_name VARCHAR(255),
    environment VARCHAR(50),
    rotated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    previous_hash VARCHAR(255),
    new_hash VARCHAR(255),
    INDEX idx_secret_name (secret_name),
    INDEX idx_rotated_at (rotated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance & Optimization Tables
CREATE TABLE IF NOT EXISTS cdn_configuration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    provider VARCHAR(100), -- cloudflare, aws_cloudfront, akamai
    api_key VARCHAR(255),
    configuration JSON,
    is_enabled BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_portfolio_provider (portfolio_id, provider),
    INDEX idx_is_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compression_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    compression_types JSON, -- ["gzip", "brotli"]
    is_enabled BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_portfolio (portfolio_id),
    INDEX idx_is_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS browser_cache_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    cache_rules JSON,
    is_enabled BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_portfolio (portfolio_id),
    INDEX idx_is_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS http2_push_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    push_resources JSON,
    is_enabled BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_portfolio (portfolio_id),
    INDEX idx_is_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limit_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    limits JSON,
    is_enabled BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_portfolio (portfolio_id),
    INDEX idx_is_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    response_time_ms INT,
    bytes_transferred BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS image_optimization_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    filepath TEXT,
    original_size BIGINT,
    optimized_size BIGINT,
    savings_percent DECIMAL(5,2),
    optimized_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_optimized_at (optimized_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Views for Dashboards

CREATE OR REPLACE VIEW deployment_dashboard AS
SELECT
    d.deployment_id,
    d.portfolio_id,
    d.deployment_type,
    d.status,
    d.environment,
    COUNT(de.id) as event_count,
    MAX(de.occurred_at) as last_event_time,
    d.created_at,
    d.updated_at
FROM deployments d
LEFT JOIN deployment_events de ON d.deployment_id = de.deployment_id
GROUP BY d.deployment_id, d.portfolio_id, d.deployment_type, d.status, d.environment, d.created_at, d.updated_at;

CREATE OR REPLACE VIEW backup_status AS
SELECT
    backup_id,
    backup_type,
    status,
    ROUND(size_bytes / 1048576, 2) as size_mb,
    integrity_verified,
    created_at
FROM backup_metadata
WHERE status = 'completed'
ORDER BY created_at DESC;

CREATE OR REPLACE VIEW configuration_audit_trail AS
SELECT
    environment,
    `key`,
    action,
    is_encrypted,
    ip_address,
    created_at
FROM configuration_audit_log
ORDER BY created_at DESC
LIMIT 10000;

CREATE OR REPLACE VIEW performance_overview AS
SELECT
    portfolio_id,
    ROUND(AVG(response_time_ms), 2) as avg_response_time,
    MAX(response_time_ms) as max_response_time,
    COUNT(*) as total_requests,
    SUM(bytes_transferred) as total_bytes,
    DATE(created_at) as metric_date
FROM performance_metrics
GROUP BY portfolio_id, DATE(created_at);
