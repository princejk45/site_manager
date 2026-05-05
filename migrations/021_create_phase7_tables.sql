-- Phase 7 Database Migration: Advanced Analytics & Real-time Dashboards
-- Tables: analytics metrics, dashboards, reports, performance data

-- Create analytics_metrics table (high-volume time-series data)
CREATE TABLE IF NOT EXISTS `analytics_metrics` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `metric_name` VARCHAR(100) NOT NULL,
    `metric_value` DECIMAL(15, 4),
    `tags` JSON,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    INDEX idx_portfolio_metric_time (portfolio_id, metric_name, recorded_at),
    INDEX idx_metric_name (metric_name),
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_portfolio_id (portfolio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (YEAR(recorded_at)) (
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p2026 VALUES LESS THAN (2027),
    PARTITION pmax VALUES LESS THAN MAXVALUE
);

-- Create analytics_dashboards table
CREATE TABLE IF NOT EXISTS `analytics_dashboards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `layout` VARCHAR(50) DEFAULT 'grid',
    `widgets` JSON NOT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY fk_user (created_by) REFERENCES users(id),
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_is_default (is_default),
    UNIQUE KEY unique_portfolio_dashboard (portfolio_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create analytics_reports table
CREATE TABLE IF NOT EXISTS `analytics_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `report_type` VARCHAR(50) NOT NULL,
    `schedule` VARCHAR(50),
    `recipients` JSON,
    `data_format` ENUM('html', 'pdf', 'csv') DEFAULT 'html',
    `last_generated_at` TIMESTAMP NULL,
    `last_sent_at` TIMESTAMP NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY fk_user (created_by) REFERENCES users(id),
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_report_type (report_type),
    INDEX idx_is_active (is_active),
    INDEX idx_schedule (schedule)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create analytics_report_history table
CREATE TABLE IF NOT EXISTS `analytics_report_history` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `report_id` INT NOT NULL,
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `sent_at` TIMESTAMP NULL,
    `recipient_count` INT,
    `status` ENUM('generated', 'sent', 'failed') DEFAULT 'generated',
    `file_path` VARCHAR(500),
    `error_message` TEXT,
    FOREIGN KEY fk_report (report_id) REFERENCES analytics_reports(id) ON DELETE CASCADE,
    INDEX idx_report_id (report_id),
    INDEX idx_generated_at (generated_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create analytics_anomalies table
CREATE TABLE IF NOT EXISTS `analytics_anomalies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `metric_name` VARCHAR(100) NOT NULL,
    `anomaly_value` DECIMAL(15, 4),
    `expected_value` DECIMAL(15, 4),
    `zscore` DECIMAL(10, 4),
    `severity` ENUM('warning', 'critical') DEFAULT 'warning',
    `status` ENUM('new', 'acknowledged', 'resolved') DEFAULT 'new',
    `detected_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `acknowledged_by` INT,
    `acknowledged_at` TIMESTAMP NULL,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY fk_user (acknowledged_by) REFERENCES users(id),
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_metric_name (metric_name),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_detected_at (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create analytics_thresholds table
CREATE TABLE IF NOT EXISTS `analytics_thresholds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `metric_name` VARCHAR(100) NOT NULL,
    `warning_threshold` DECIMAL(15, 4),
    `critical_threshold` DECIMAL(15, 4),
    `comparison_operator` VARCHAR(10) DEFAULT '>=',
    `enabled` TINYINT(1) DEFAULT 1,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY fk_user (created_by) REFERENCES users(id),
    UNIQUE KEY unique_portfolio_metric (portfolio_id, metric_name),
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create analytics_exports table
CREATE TABLE IF NOT EXISTS `analytics_exports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `export_type` VARCHAR(50),
    `filters` JSON,
    `format` ENUM('csv', 'json', 'xlsx') DEFAULT 'csv',
    `file_path` VARCHAR(500),
    `row_count` INT,
    `file_size_bytes` BIGINT,
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY fk_user (created_by) REFERENCES users(id),
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create real_time_performance table for current metrics
CREATE TABLE IF NOT EXISTS `real_time_performance` (
    `portfolio_id` INT PRIMARY KEY,
    `uptime_percentage` DECIMAL(5, 2),
    `avg_response_time_ms` DECIMAL(10, 2),
    `cpu_usage_percent` DECIMAL(5, 2),
    `memory_usage_percent` DECIMAL(5, 2),
    `api_calls_per_minute` INT,
    `error_rate_percent` DECIMAL(5, 2),
    `last_updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    INDEX idx_last_updated_at (last_updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add analytics fields to licenses table
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `advanced_analytics` TINYINT(1) DEFAULT 0 AFTER `multi_tenant`;
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `real_time_dashboards` TINYINT(1) DEFAULT 0 AFTER `advanced_analytics`;
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `predictive_alerts` TINYINT(1) DEFAULT 0 AFTER `real_time_dashboards`;
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `custom_reports` TINYINT(1) DEFAULT 0 AFTER `predictive_alerts`;
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `data_export` TINYINT(1) DEFAULT 0 AFTER `custom_reports`;

-- Create views for common analytics queries
CREATE OR REPLACE VIEW daily_uptime_summary AS
SELECT 
    portfolio_id,
    DATE(recorded_at) as date,
    AVG(metric_value) as avg_uptime,
    MIN(metric_value) as min_uptime,
    MAX(metric_value) as max_uptime,
    COUNT(*) as data_points
FROM analytics_metrics
WHERE metric_name = 'uptime'
GROUP BY portfolio_id, DATE(recorded_at);

CREATE OR REPLACE VIEW response_time_percentiles AS
SELECT 
    portfolio_id,
    DATE(recorded_at) as date,
    COUNT(*) as sample_count,
    PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY metric_value) as p50,
    PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY metric_value) as p95,
    PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY metric_value) as p99
FROM analytics_metrics
WHERE metric_name = 'response_time'
GROUP BY portfolio_id, DATE(recorded_at);

-- Verify all tables created
SELECT 
    TABLE_NAME, 
    TABLE_ROWS as row_count,
    ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME IN (
        'analytics_metrics', 'analytics_dashboards', 'analytics_reports',
        'analytics_report_history', 'analytics_anomalies', 'analytics_thresholds',
        'analytics_exports', 'real_time_performance'
    )
ORDER BY TABLE_NAME;
