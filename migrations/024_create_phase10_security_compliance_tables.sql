-- Phase 10: Advanced Security & Compliance Migration
-- Created: 2026-05-03
-- Description: OAuth2, SAML2, 2FA, Encryption, GDPR, and Security Scanning infrastructure

-- OAuth2 Tables
CREATE TABLE IF NOT EXISTS oauth2_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    provider_name VARCHAR(50) NOT NULL,
    client_id VARCHAR(255) NOT NULL,
    client_secret VARCHAR(255) NOT NULL,
    provider_config JSON NOT NULL,
    is_enabled TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    disconnected_at TIMESTAMP NULL,
    UNIQUE KEY unique_provider (portfolio_id, provider_name),
    KEY idx_portfolio (portfolio_id),
    KEY idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth2_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    provider_name VARCHAR(50) NOT NULL,
    access_token VARCHAR(1024) NOT NULL,
    token_type VARCHAR(50) DEFAULT 'Bearer',
    expires_at TIMESTAMP NULL,
    refresh_token VARCHAR(1024) NULL,
    scope VARCHAR(255) NULL,
    provider_config JSON NULL,
    is_revoked TINYINT DEFAULT 0,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_provider (provider_name),
    KEY idx_revoked (is_revoked),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth2_states (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    state VARCHAR(255) NOT NULL UNIQUE,
    provider_name VARCHAR(50) NOT NULL,
    redirect_uri VARCHAR(512) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_state (state),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SAML2 Tables
CREATE TABLE IF NOT EXISTS saml2_configurations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL UNIQUE,
    idp_name VARCHAR(100) NOT NULL,
    sso_url VARCHAR(512) NOT NULL,
    slo_url VARCHAR(512) NULL,
    x509_certificate LONGTEXT NOT NULL,
    metadata_url VARCHAR(512) NULL,
    is_enabled TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    disabled_at TIMESTAMP NULL,
    KEY idx_portfolio (portfolio_id),
    KEY idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saml2_assertions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    attributes JSON NULL,
    validated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_email (user_email),
    KEY idx_validated (validated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saml2_sp_metadata (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL UNIQUE,
    entity_id VARCHAR(512) NOT NULL,
    metadata_xml LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saml2_auth_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    request_id VARCHAR(255) NOT NULL UNIQUE,
    request_xml LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saml2_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_id INT NOT NULL,
    session_index VARCHAR(255) NOT NULL,
    is_active TINYINT DEFAULT 1,
    invalidated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_session (session_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saml2_certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL UNIQUE,
    certificate LONGTEXT NOT NULL,
    private_key LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Two-Factor Authentication Tables
CREATE TABLE IF NOT EXISTS two_factor_auth (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_id INT NOT NULL,
    method VARCHAR(50) NOT NULL,
    secret VARCHAR(255) NULL,
    phone_number VARCHAR(20) NULL,
    email VARCHAR(255) NULL,
    is_enabled TINYINT DEFAULT 0,
    verified_at TIMESTAMP NULL,
    disabled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_method (portfolio_id, user_id, method),
    KEY idx_portfolio (portfolio_id),
    KEY idx_user (user_id),
    KEY idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS two_factor_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_id INT NOT NULL,
    method VARCHAR(50) NOT NULL,
    code VARCHAR(10) NOT NULL,
    is_used TINYINT DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_user (user_id),
    KEY idx_code (code),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS two_factor_backup_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_id INT NOT NULL,
    code VARCHAR(255) NOT NULL,
    is_used TINYINT DEFAULT 0,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_user (user_id),
    KEY idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS two_factor_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_id INT NOT NULL,
    method VARCHAR(50) NOT NULL,
    success TINYINT,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_user (user_id),
    KEY idx_success (success),
    KEY idx_verified (verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Encryption Tables
CREATE TABLE IF NOT EXISTS encryption_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    data_type VARCHAR(100) NOT NULL,
    key_data LONGTEXT NOT NULL,
    is_active TINYINT DEFAULT 1,
    rotated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_data_type (data_type),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS encryption_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    operation VARCHAR(50) NOT NULL,
    data_type VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_operation (operation),
    KEY idx_logged (logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS key_rotation_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    rotated_types JSON NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    rotated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_rotated (rotated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GDPR Compliance Tables
CREATE TABLE IF NOT EXISTS gdpr_consents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_id INT NOT NULL,
    consent_type VARCHAR(100) NOT NULL,
    version INT DEFAULT 1,
    is_revoked TINYINT DEFAULT 0,
    ip_address VARCHAR(45) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_user (user_id),
    KEY idx_type (consent_type),
    KEY idx_recorded (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gdpr_retention_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    policy_name VARCHAR(255) NOT NULL,
    data_category VARCHAR(100) NOT NULL,
    retention_days INT NOT NULL,
    deletion_method VARCHAR(50) NOT NULL,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_category (data_category),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gdpr_deletion_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_id INT NOT NULL,
    deletion_method VARCHAR(50) NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_user (user_id),
    KEY idx_requested (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gdpr_deletion_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_id INT NOT NULL,
    deletion_method VARCHAR(50) NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_user (user_id),
    KEY idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gdpr_access_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_id INT NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_user (user_id),
    KEY idx_requested (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gdpr_export_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    user_id INT NOT NULL,
    format VARCHAR(50) NOT NULL,
    exported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_user (user_id),
    KEY idx_exported (exported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Scanning Tables
CREATE TABLE IF NOT EXISTS security_scans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    scan_type VARCHAR(100) NOT NULL,
    results JSON NOT NULL,
    vulnerability_count INT DEFAULT 0,
    scan_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_scan_type (scan_type),
    KEY idx_scan_date (scan_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_penetration_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    test_type VARCHAR(100) NOT NULL,
    scheduled_at TIMESTAMP NOT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    results JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_scheduled (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portfolio_id INT NOT NULL,
    total_vulnerabilities INT DEFAULT 0,
    critical_count INT DEFAULT 0,
    high_count INT DEFAULT 0,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio (portfolio_id),
    KEY idx_generated (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update licenses table with feature flags
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS oauth2_enabled TINYINT DEFAULT 0;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS saml2_enabled TINYINT DEFAULT 0;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT DEFAULT 0;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS encryption_enabled TINYINT DEFAULT 0;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS gdpr_compliance_enabled TINYINT DEFAULT 0;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS security_scanning_enabled TINYINT DEFAULT 0;

-- Create views for security dashboards
CREATE OR REPLACE VIEW oauth2_configuration_status AS
SELECT
    p.id as portfolio_id,
    COUNT(op.id) as total_providers,
    SUM(CASE WHEN op.is_enabled = 1 THEN 1 ELSE 0 END) as enabled_providers,
    COUNT(DISTINCT ot.provider_name) as connected_providers,
    MAX(ot.created_at) as last_connection
FROM portfolios p
LEFT JOIN oauth2_providers op ON p.id = op.portfolio_id
LEFT JOIN oauth2_tokens ot ON p.id = ot.portfolio_id
GROUP BY p.id;

CREATE OR REPLACE VIEW saml2_status AS
SELECT
    sc.portfolio_id,
    sc.idp_name,
    sc.is_enabled,
    COUNT(sa.id) as total_assertions,
    MAX(sa.validated_at) as last_assertion,
    COUNT(DISTINCT DATE(sa.validated_at)) as active_days
FROM saml2_configurations sc
LEFT JOIN saml2_assertions sa ON sc.portfolio_id = sa.portfolio_id
GROUP BY sc.portfolio_id;

CREATE OR REPLACE VIEW security_vulnerability_summary AS
SELECT
    portfolio_id,
    SUM(CASE WHEN vulnerability_count > 0 THEN 1 ELSE 0 END) as scans_with_vulnerabilities,
    SUM(vulnerability_count) as total_vulnerabilities,
    MAX(scan_date) as last_scan,
    COUNT(*) as total_scans
FROM security_scans
GROUP BY portfolio_id;

CREATE OR REPLACE VIEW gdpr_compliance_dashboard AS
SELECT
    portfolio_id,
    COUNT(DISTINCT user_id) as users_with_consent,
    COUNT(DISTINCT CASE WHEN is_revoked = 0 THEN user_id END) as active_consents,
    COUNT(DISTINCT CASE WHEN is_revoked = 1 THEN user_id END) as revoked_consents,
    MAX(recorded_at) as last_consent_change
FROM gdpr_consents
GROUP BY portfolio_id;

-- Index optimization
CREATE INDEX idx_oauth_token_expiry ON oauth2_tokens(portfolio_id, expires_at);
CREATE INDEX idx_2fa_enabled ON two_factor_auth(portfolio_id, is_enabled);
CREATE INDEX idx_encryption_active ON encryption_keys(portfolio_id, is_active);
CREATE INDEX idx_gdpr_requests ON gdpr_deletion_requests(portfolio_id, requested_at);
CREATE INDEX idx_security_critical ON security_scans(portfolio_id, scan_date);
