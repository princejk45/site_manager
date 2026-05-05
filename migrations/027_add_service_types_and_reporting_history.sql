-- Migration 027: Service taxonomy + reporting/notification history
-- Safe to run multiple times via INFORMATION_SCHEMA checks.

-- 1) Add websites.service_type if missing
SET @has_service_type := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'websites'
      AND COLUMN_NAME = 'service_type'
);

SET @sql_add_service_type := IF(
    @has_service_type = 0,
    "ALTER TABLE websites ADD COLUMN service_type ENUM('domain','hosting_web','hosting_mail') NOT NULL DEFAULT 'hosting_web' AFTER domain",
    'SELECT 1'
);
PREPARE stmt_add_service_type FROM @sql_add_service_type;
EXECUTE stmt_add_service_type;
DEALLOCATE PREPARE stmt_add_service_type;

-- 2) Add index on websites.service_type if missing
SET @has_service_type_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'websites'
      AND INDEX_NAME = 'idx_websites_service_type'
);

SET @sql_add_service_type_idx := IF(
    @has_service_type_idx = 0,
    'ALTER TABLE websites ADD INDEX idx_websites_service_type (service_type)',
    'SELECT 1'
);
PREPARE stmt_add_service_type_idx FROM @sql_add_service_type_idx;
EXECUTE stmt_add_service_type_idx;
DEALLOCATE PREPARE stmt_add_service_type_idx;

-- 3) Report run history (immutable metadata per generated report)
CREATE TABLE IF NOT EXISTS report_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    generated_report_id INT NULL,
    client_id INT NULL,
    report_type VARCHAR(100) NOT NULL,
    report_format VARCHAR(20) NOT NULL,
    service_type_filter VARCHAR(20) NULL,
    filters_json JSON NULL,
    generated_by INT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(255) NULL,
    file_size_bytes BIGINT NULL,
    status VARCHAR(50) DEFAULT 'GENERATED',
    INDEX idx_report_runs_type (report_type),
    INDEX idx_report_runs_client (client_id),
    INDEX idx_report_runs_generated_at (generated_at),
    CONSTRAINT fk_report_runs_generated_report_id
        FOREIGN KEY (generated_report_id) REFERENCES generated_reports(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_report_runs_client_id
        FOREIGN KEY (client_id) REFERENCES hosting(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_report_runs_generated_by
        FOREIGN KEY (generated_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Notification/event history by client + service
CREATE TABLE IF NOT EXISTS notification_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NULL,
    website_id INT NULL,
    service_type VARCHAR(20) NULL,
    event_type VARCHAR(100) NOT NULL,
    severity VARCHAR(20) DEFAULT 'info',
    channel VARCHAR(30) NULL,
    payload_json JSON NULL,
    sent_at TIMESTAMP NULL,
    status VARCHAR(50) DEFAULT 'queued',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notification_events_client (client_id),
    INDEX idx_notification_events_website (website_id),
    INDEX idx_notification_events_type (event_type),
    INDEX idx_notification_events_created_at (created_at),
    CONSTRAINT fk_notification_events_client_id
        FOREIGN KEY (client_id) REFERENCES hosting(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_notification_events_website_id
        FOREIGN KEY (website_id) REFERENCES websites(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
