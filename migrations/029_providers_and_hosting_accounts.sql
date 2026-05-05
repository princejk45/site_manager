-- Migration 029: Providers, Hosting Accounts, Domains, Domain Assignments, Email Services
-- Safe to run multiple times (CREATE TABLE IF NOT EXISTS + INFORMATION_SCHEMA guards).
-- Does NOT drop or alter the existing `websites` table — migration happens in phases.

-- ============================================================
-- 1) PROVIDERS — dynamic list of WHM servers, registrars, mail
-- ============================================================
CREATE TABLE IF NOT EXISTS providers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    type        ENUM('whm','registrar','email','other') NOT NULL DEFAULT 'whm',
    base_url    VARCHAR(255) NULL COMMENT 'WHM/cPanel API base URL or registrar portal URL',
    username    VARCHAR(150) NULL COMMENT 'WHM reseller username or registrar account',
    api_token   TEXT         NULL COMMENT 'Encrypted API token (store hashed/encrypted at app level)',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    notes       TEXT         NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_providers_name_type (name, type),
    INDEX idx_providers_type (type),
    INDEX idx_providers_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2) HOSTING ACCOUNTS — a WHM/cPanel account per client per server
--    Replaces the conceptual role of websites.service_type = 'hosting_web'
-- ============================================================
CREATE TABLE IF NOT EXISTS hosting_accounts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT          NOT NULL COMMENT '→ hosting.id (the client)',
    provider_id     INT          NOT NULL COMMENT '→ providers.id (the WHM server)',
    cpanel_username VARCHAR(100) NULL,
    package_name    VARCHAR(100) NULL COMMENT 'WHM package/plan name',
    disk_quota_mb   INT UNSIGNED NULL,
    bandwidth_mb    INT UNSIGNED NULL,
    ip_address      VARCHAR(45)  NULL COMMENT 'Shared or dedicated IP on this server',
    expiry_date     DATE         NULL,
    auto_renew      TINYINT(1)   NOT NULL DEFAULT 0,
    status          ENUM('active','suspended','expired','cancelled') NOT NULL DEFAULT 'active',
    notes           TEXT         NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ha_client_id (client_id),
    INDEX idx_ha_provider_id (provider_id),
    INDEX idx_ha_status (status),
    INDEX idx_ha_expiry (expiry_date),
    CONSTRAINT fk_ha_client
        FOREIGN KEY (client_id) REFERENCES hosting(id) ON DELETE CASCADE,
    CONSTRAINT fk_ha_provider
        FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3) DOMAINS — domain registrations (bought from a registrar)
-- ============================================================
CREATE TABLE IF NOT EXISTS domains (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT          NOT NULL COMMENT '→ hosting.id',
    registrar_id    INT          NOT NULL COMMENT '→ providers.id (type=registrar)',
    domain_name     VARCHAR(255) NOT NULL,
    expiry_date     DATE         NULL,
    auto_renew      TINYINT(1)   NOT NULL DEFAULT 0,
    status          ENUM('active','expired','transferred','cancelled') NOT NULL DEFAULT 'active',
    notes           TEXT         NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_domains_name (domain_name),
    INDEX idx_domains_client_id (client_id),
    INDEX idx_domains_registrar_id (registrar_id),
    INDEX idx_domains_status (status),
    INDEX idx_domains_expiry (expiry_date),
    CONSTRAINT fk_domains_client
        FOREIGN KEY (client_id) REFERENCES hosting(id) ON DELETE CASCADE,
    CONSTRAINT fk_domains_registrar
        FOREIGN KEY (registrar_id) REFERENCES providers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4) DOMAIN ASSIGNMENTS — which hosting account a domain points to
--    A domain bought from aruba can point to a vhosting WHM account.
--    is_primary=1: main domain of the cPanel account
--    is_primary=0: addon domain
-- ============================================================
CREATE TABLE IF NOT EXISTS domain_assignments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    domain_id           INT         NOT NULL COMMENT '→ domains.id',
    hosting_account_id  INT         NOT NULL COMMENT '→ hosting_accounts.id',
    is_primary          TINYINT(1)  NOT NULL DEFAULT 1,
    assigned_at         TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unassigned_at       TIMESTAMP   NULL COMMENT 'Set when removed; NULL = currently active',
    INDEX idx_da_domain_id (domain_id),
    INDEX idx_da_hosting_account_id (hosting_account_id),
    INDEX idx_da_active (unassigned_at),
    CONSTRAINT fk_da_domain
        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    CONSTRAINT fk_da_hosting_account
        FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5) EMAIL SERVICES — mail per client/domain
--    Two modes:
--      a) cPanel mail: hosting_account_id IS NOT NULL, expiry_date IS NULL
--         → expiry inherited from the hosting account
--      b) Dedicated provider (Google, M365, Aruba mail):
--         provider_id IS NOT NULL, expiry_date IS NOT NULL (own billing cycle)
-- ============================================================
CREATE TABLE IF NOT EXISTS email_services (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    client_id           INT          NOT NULL COMMENT '→ hosting.id',
    domain_id           INT          NULL     COMMENT '→ domains.id (which domain this mail is for)',
    hosting_account_id  INT          NULL     COMMENT '→ hosting_accounts.id (if cPanel/shared mail)',
    provider_id         INT          NULL     COMMENT '→ providers.id (if dedicated mail provider)',
    service_type        ENUM('cpanel','google_workspace','microsoft365','aruba_mail','other') NOT NULL DEFAULT 'cpanel',
    mailboxes           SMALLINT UNSIGNED NULL COMMENT 'Number of mailboxes (if known)',
    expiry_date         DATE         NULL     COMMENT 'NULL = inherit from hosting_account',
    auto_renew          TINYINT(1)   NOT NULL DEFAULT 0,
    status              ENUM('active','suspended','expired','cancelled') NOT NULL DEFAULT 'active',
    notes               TEXT         NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_es_client_id (client_id),
    INDEX idx_es_domain_id (domain_id),
    INDEX idx_es_hosting_account_id (hosting_account_id),
    INDEX idx_es_provider_id (provider_id),
    INDEX idx_es_status (status),
    CONSTRAINT fk_es_client
        FOREIGN KEY (client_id) REFERENCES hosting(id) ON DELETE CASCADE,
    CONSTRAINT fk_es_domain
        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL,
    CONSTRAINT fk_es_hosting_account
        FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_es_provider
        FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6) Seed default providers (common in Italian market)
--    INSERT IGNORE so re-running is safe.
-- ============================================================
INSERT IGNORE INTO providers (name, type, base_url, notes) VALUES
    ('vhosting',   'whm',       'https://vhosting.it',       'Primary WHM reseller'),
    ('serverplan', 'whm',       'https://serverplan.it',     'Secondary WHM reseller'),
    ('aruba',      'whm',       'https://hosting.aruba.it',  'Aruba shared hosting'),
    ('aruba',      'registrar', 'https://www.aruba.it',      'Aruba domain registrar'),
    ('register.it','registrar', 'https://www.register.it',   'Register.it domain registrar'),
    ('Google Workspace', 'email', 'https://workspace.google.com', 'Google Workspace mail'),
    ('Microsoft 365',    'email', 'https://microsoft.com/m365',   'Microsoft 365 mail');
