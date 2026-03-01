-- Migration: Create Cron Settings Table
-- This table manages the cron job execution status and timing

CREATE TABLE IF NOT EXISTS cron_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    is_active TINYINT(1) DEFAULT 0 COMMENT 'Whether the cron job is enabled (1) or disabled (0)',
    last_run TIMESTAMP NULL COMMENT 'Timestamp of last successful cron execution',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When this record was created',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When this record was last updated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize with disabled state
INSERT IGNORE INTO cron_settings (id, is_active, last_run) 
VALUES (1, 0, NULL);

-- Create website_notifications table for tracking sent notifications
CREATE TABLE IF NOT EXISTS website_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    website_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL COMMENT 'Type: scaduto, 30-day, 15-day, 1-day',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the notification was sent',
    UNIQUE KEY unique_notification (website_id, notification_type),
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
