-- Migration 028: Task Queue + API Keys
-- Safe to run multiple times via CREATE TABLE IF NOT EXISTS / INFORMATION_SCHEMA guards.

-- 1) Task Queue — logs background/batch operations
CREATE TABLE IF NOT EXISTS task_queue (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    type          VARCHAR(80)  NOT NULL,
    label         VARCHAR(255) NOT NULL,
    status        ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
    created_by    INT          NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    started_at    TIMESTAMP    NULL,
    completed_at  TIMESTAMP    NULL,
    progress      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100',
    result_json   JSON         NULL,
    error_message TEXT         NULL,
    INDEX idx_task_queue_status (status),
    INDEX idx_task_queue_created_at (created_at),
    INDEX idx_task_queue_type (type),
    CONSTRAINT fk_task_queue_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) API Keys — named tokens for external integrations (super_admin managed)
CREATE TABLE IF NOT EXISTS api_keys (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    key_prefix   CHAR(8)      NOT NULL,
    key_hash     VARCHAR(64)  NOT NULL COMMENT 'SHA-256 hex of the full key',
    scopes_json  JSON         NULL     COMMENT 'e.g. ["read_websites","export_data"]',
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    expires_at   DATE         NULL,
    last_used_at TIMESTAMP    NULL,
    created_by   INT          NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_api_keys_key_hash (key_hash),
    INDEX idx_api_keys_prefix (key_prefix),
    INDEX idx_api_keys_is_active (is_active),
    CONSTRAINT fk_api_keys_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
