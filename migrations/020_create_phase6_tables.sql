-- Phase 6 Database Migration: Advanced Automation, SSO & Email Templates
-- Tables: automation workflows, templates, executions, SSO accounts, email templates

-- Create automation_workflows table
CREATE TABLE IF NOT EXISTS `automation_workflows` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `type` ENUM('workflow', 'scheduled', 'triggered', 'chain') DEFAULT 'workflow',
    `triggers` JSON,
    `actions` JSON NOT NULL,
    `conditions` JSON,
    `schedule` JSON,
    `execution_count` INT DEFAULT 0,
    `last_executed_at` TIMESTAMP NULL,
    `is_enabled` TINYINT(1) DEFAULT 1,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY fk_user (created_by) REFERENCES users(id),
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_type (type),
    INDEX idx_is_enabled (is_enabled),
    INDEX idx_last_executed_at (last_executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create automation_templates table
CREATE TABLE IF NOT EXISTS `automation_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `category` VARCHAR(100),
    `description` TEXT,
    `template_data` JSON NOT NULL,
    `is_public` TINYINT(1) DEFAULT 1,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_user (created_by) REFERENCES users(id),
    INDEX idx_category (category),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create automation_executions table
CREATE TABLE IF NOT EXISTS `automation_executions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workflow_id` INT NOT NULL,
    `trigger_data` JSON,
    `status` ENUM('running', 'completed', 'failed') DEFAULT 'running',
    `result` JSON,
    `duration_ms` INT,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    FOREIGN KEY fk_workflow (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE,
    INDEX idx_workflow_id (workflow_id),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),
    INDEX idx_completed_at (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create automation_execution_logs table
CREATE TABLE IF NOT EXISTS `automation_execution_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `execution_id` INT NOT NULL,
    `action_type` VARCHAR(100),
    `status` ENUM('success', 'failure', 'skipped') DEFAULT 'success',
    `result` JSON,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_execution (execution_id) REFERENCES automation_executions(id) ON DELETE CASCADE,
    INDEX idx_execution_id (execution_id),
    INDEX idx_action_type (action_type),
    INDEX idx_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_sso_accounts table
CREATE TABLE IF NOT EXISTS `user_sso_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `provider` VARCHAR(50) NOT NULL,
    `provider_user_id` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255),
    `name` VARCHAR(255),
    `picture_url` TEXT,
    `access_token` TEXT,
    `refresh_token` TEXT,
    `expires_at` TIMESTAMP NULL,
    `last_login_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sso (user_id, provider),
    INDEX idx_user_id (user_id),
    INDEX idx_provider (provider),
    INDEX idx_provider_user_id (provider_user_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create email_templates table
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) UNIQUE,
    `type` ENUM('notification', 'alert', 'report', 'welcome', 'custom') DEFAULT 'custom',
    `subject` VARCHAR(255) NOT NULL,
    `body_html` LONGTEXT NOT NULL,
    `body_text` LONGTEXT,
    `variables` JSON,
    `header_template` LONGTEXT,
    `footer_template` LONGTEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY fk_user (created_by) REFERENCES users(id),
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_slug (slug),
    INDEX idx_type (type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create email_template_versions table for audit trail
CREATE TABLE IF NOT EXISTS `email_template_versions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT NOT NULL,
    `version` INT DEFAULT 1,
    `subject` VARCHAR(255),
    `body_html` LONGTEXT,
    `body_text` LONGTEXT,
    `changed_by` INT,
    `change_reason` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_template (template_id) REFERENCES email_templates(id) ON DELETE CASCADE,
    FOREIGN KEY fk_user (changed_by) REFERENCES users(id),
    INDEX idx_template_id (template_id),
    INDEX idx_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create tenant_settings table for multi-tenant support
CREATE TABLE IF NOT EXISTS `tenant_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL UNIQUE,
    `instance_id` VARCHAR(255) UNIQUE,
    `isolation_level` ENUM('shared', 'dedicated', 'isolated') DEFAULT 'shared',
    `data_residency` VARCHAR(100) DEFAULT 'us',
    `retention_days` INT DEFAULT 90,
    `backup_frequency` VARCHAR(50) DEFAULT 'daily',
    `custom_subdomain` VARCHAR(100) UNIQUE,
    `max_users` INT,
    `max_websites` INT,
    `max_api_calls_per_day` INT,
    `features` JSON,
    `settings` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_instance_id (instance_id),
    INDEX idx_custom_subdomain (custom_subdomain),
    INDEX idx_isolation_level (isolation_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create tenant_usage table for quota tracking
CREATE TABLE IF NOT EXISTS `tenant_usage` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `month` VARCHAR(7),
    `api_calls` INT DEFAULT 0,
    `users_created` INT DEFAULT 0,
    `websites_added` INT DEFAULT 0,
    `storage_gb` DECIMAL(10, 2) DEFAULT 0,
    `bandwidth_gb` DECIMAL(10, 2) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_portfolio (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_month (portfolio_id, month),
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_month (month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add Phase 6 features to licenses table
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `advanced_automation` TINYINT(1) DEFAULT 0 AFTER `whitelabel_enabled`;
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `sso_enabled` TINYINT(1) DEFAULT 0 AFTER `advanced_automation`;
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `email_templates` TINYINT(1) DEFAULT 0 AFTER `sso_enabled`;
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `multi_tenant` TINYINT(1) DEFAULT 0 AFTER `email_templates`;

-- Verify all tables created
SELECT 
    TABLE_NAME, 
    TABLE_ROWS as row_count,
    ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME IN (
        'automation_workflows', 'automation_templates', 'automation_executions',
        'automation_execution_logs', 'user_sso_accounts', 'email_templates',
        'email_template_versions', 'tenant_settings', 'tenant_usage'
    )
ORDER BY TABLE_NAME;
