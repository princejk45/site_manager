-- Phase 4: Integrations & Team Management
-- Creates tables for third-party integrations and team collaboration features

-- ============================================================
-- integrations
-- ============================================================
-- Stores third-party integration configurations
CREATE TABLE IF NOT EXISTS `integrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `platform` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `config` LONGTEXT NOT NULL,
    `events` JSON,
    `status` VARCHAR(20) DEFAULT 'active',
    `last_error` TEXT,
    `last_checked` TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_platform` (`platform`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY `fk_user` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);

-- ============================================================
-- integration_logs
-- ============================================================
-- Logs integration delivery attempts and results
CREATE TABLE IF NOT EXISTS `integration_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `integration_id` INT NOT NULL,
    `event_type` VARCHAR(100),
    `success` TINYINT DEFAULT 0,
    `error` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_integration_id` (`integration_id`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY `fk_integration` (`integration_id`) REFERENCES `integrations` (`id`) ON DELETE CASCADE
);

-- ============================================================
-- team_members
-- ============================================================
-- Stores team member access and roles
CREATE TABLE IF NOT EXISTS `team_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` VARCHAR(50) NOT NULL,
    `permissions` JSON,
    `accepted_at` TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_member` (`portfolio_id`, `user_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_portfolio_id` (`portfolio_id`),
    INDEX `idx_role` (`role`)
);

-- ============================================================
-- team_invitations
-- ============================================================
-- Stores pending team member invitations
CREATE TABLE IF NOT EXISTS `team_invitations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) NOT NULL,
    `permissions` JSON,
    `token` VARCHAR(255) UNIQUE NOT NULL,
    `status` VARCHAR(50) DEFAULT 'pending',
    `accepted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP,
    INDEX `idx_portfolio_id` (`portfolio_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_token` (`token`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expires_at` (`expires_at`)
);

-- ============================================================
-- team_activity_log
-- ============================================================
-- Tracks team member activities
CREATE TABLE IF NOT EXISTS `team_activity_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `team_member_id` INT,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_team_member_id` (`team_member_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
);

-- ============================================================
-- Verify table creation
-- ============================================================
SELECT 'Phase 4 migration complete' AS status,
       (SELECT COUNT(*) FROM information_schema.tables WHERE table_name='integrations' AND table_schema=DATABASE()) as integrations_created,
       (SELECT COUNT(*) FROM information_schema.tables WHERE table_name='team_members' AND table_schema=DATABASE()) as team_members_created,
       (SELECT COUNT(*) FROM information_schema.tables WHERE table_name='team_invitations' AND table_schema=DATABASE()) as team_invitations_created;
