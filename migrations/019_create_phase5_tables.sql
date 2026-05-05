-- Phase 5 Database Migration: Whitelabeling & Advanced Features
-- Tables: whitelabel_settings, api_keys, api_key_logs

-- Create whitelabel_settings table
CREATE TABLE IF NOT EXISTS `whitelabel_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL UNIQUE,
    `company_name` VARCHAR(255) DEFAULT 'Fullmidia',
    `logo_url` TEXT,
    `favicon_url` TEXT,
    `primary_color` VARCHAR(7) DEFAULT '#0066cc',
    `secondary_color` VARCHAR(7) DEFAULT '#f0f0f0',
    `colors` JSON,
    `custom_domain` VARCHAR(255) UNIQUE,
    `email_from_name` VARCHAR(255) DEFAULT 'Fullmidia',
    `email_footer_text` TEXT,
    `custom_css` LONGTEXT,
    `social_links` JSON,
    `enabled` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_whitelabel_settings_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_custom_domain (custom_domain),
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create api_keys table
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `key_hash` VARCHAR(64) NOT NULL UNIQUE,
    `scopes` JSON NOT NULL,
    `type` ENUM('personal', 'application', 'webhook') DEFAULT 'personal',
    `status` ENUM('active', 'inactive', 'revoked') DEFAULT 'active',
    `last_used_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NULL,
    `revoked_at` TIMESTAMP NULL,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_keys_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    CONSTRAINT fk_api_keys_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_key_hash (key_hash),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create api_key_logs table for tracking API usage
CREATE TABLE IF NOT EXISTS `api_key_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `key_id` INT NOT NULL,
    `endpoint` VARCHAR(255),
    `method` VARCHAR(10),
    `response_code` INT,
    `response_time_ms` INT,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_key_logs_key FOREIGN KEY (key_id) REFERENCES api_keys(id) ON DELETE CASCADE,
    INDEX idx_key_id (key_id),
    INDEX idx_created_at (created_at),
    INDEX idx_endpoint (endpoint),
    INDEX idx_response_code (response_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add whitelabel feature to licenses table (if not exists)
ALTER TABLE `licenses` ADD COLUMN `whitelabel_enabled` TINYINT(1) DEFAULT 0 AFTER `custom_branding`;

-- Create notification_templates table for custom email templates
CREATE TABLE IF NOT EXISTS `notification_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) UNIQUE NOT NULL,
    `subject` VARCHAR(255),
    `body_html` LONGTEXT,
    `body_text` LONGTEXT,
    `variables` JSON,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_templates_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_preferences table for advanced user settings
CREATE TABLE IF NOT EXISTS `user_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `theme` ENUM('light', 'dark', 'auto') DEFAULT 'auto',
    `language` VARCHAR(5) DEFAULT 'en',
    `timezone` VARCHAR(50) DEFAULT 'UTC',
    `notifications_email` TINYINT(1) DEFAULT 1,
    `notifications_slack` TINYINT(1) DEFAULT 0,
    `notifications_teams` TINYINT(1) DEFAULT 0,
    `weekly_digest` TINYINT(1) DEFAULT 1,
    `date_format` VARCHAR(20) DEFAULT 'Y-m-d',
    `time_format` VARCHAR(20) DEFAULT 'H:i:s',
    `sidebar_collapsed` TINYINT(1) DEFAULT 0,
    `dashboard_layout` VARCHAR(50) DEFAULT 'grid',
    `csv_delimiter` VARCHAR(1) DEFAULT ',',
    `preferences` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_theme (theme),
    INDEX idx_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create portfolio_branding table for portfolio-specific branding
CREATE TABLE IF NOT EXISTS `portfolio_branding` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL UNIQUE,
    `display_name` VARCHAR(255),
    `description` TEXT,
    `logo_url` TEXT,
    `banner_url` TEXT,
    `color_scheme` JSON,
    `custom_fonts` JSON,
    `is_public` TINYINT(1) DEFAULT 0,
    `public_url_slug` VARCHAR(100) UNIQUE,
    `metadata` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_portfolio_branding_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_is_public (is_public),
    INDEX idx_public_url_slug (public_url_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create sso_providers table for SSO integrations
CREATE TABLE IF NOT EXISTS `sso_providers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT NOT NULL,
    `provider` VARCHAR(50) NOT NULL,
    `provider_id` VARCHAR(255) NOT NULL UNIQUE,
    `client_id` VARCHAR(255),
    `client_secret` VARCHAR(255),
    `redirect_uri` VARCHAR(255),
    `config` JSON,
    `is_enabled` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sso_providers_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_provider (provider),
    UNIQUE KEY unique_provider (portfolio_id, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify all tables created
SELECT 
    TABLE_NAME, 
    TABLE_ROWS as row_count,
    ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME IN (
        'whitelabel_settings', 'api_keys', 'api_key_logs',
        'notification_templates', 'user_preferences', 
        'portfolio_branding', 'sso_providers'
    )
ORDER BY TABLE_NAME;
