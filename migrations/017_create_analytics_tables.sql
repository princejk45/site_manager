-- Phase 3: Analytics Engine
-- Creates tables for analytics data, events, and reports

-- ============================================================
-- analytics_events
-- ============================================================
-- Stores custom analytics events for tracking
CREATE TABLE IF NOT EXISTS `analytics_events` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `website_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `metadata` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_website_id` (`website_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY `fk_website` (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
);

-- ============================================================
-- generated_reports
-- ============================================================
-- Stores generated reports for history and re-access
CREATE TABLE IF NOT EXISTS `generated_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `report_type` VARCHAR(50) NOT NULL,
    `report_format` VARCHAR(20) NOT NULL,
    `report_data` LONGTEXT NOT NULL,
    `file_size` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_report_type` (`report_type`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY `fk_user` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);

-- ============================================================
-- Verify table creation
-- ============================================================
SELECT 'Phase 3 migration complete' AS status,
       (SELECT COUNT(*) FROM information_schema.tables WHERE table_name='analytics_events' AND table_schema=DATABASE()) as analytics_events_created,
       (SELECT COUNT(*) FROM information_schema.tables WHERE table_name='generated_reports' AND table_schema=DATABASE()) as generated_reports_created;
