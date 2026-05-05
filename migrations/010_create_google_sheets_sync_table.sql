-- Google Sheets Synchronization Tracking
-- Manages bidirectional sync between DB and Google Sheets
CREATE TABLE IF NOT EXISTS google_sheets_sync (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Sheets Configuration
    sheet_id VARCHAR(255) NOT NULL,  -- Google Sheets ID
    sheet_name VARCHAR(255) NOT NULL,  -- Tab name
    sheet_url TEXT,
    
    -- Sync Settings
    sync_direction ENUM('EXPORT', 'IMPORT', 'BIDIRECTIONAL') NOT NULL DEFAULT 'BIDIRECTIONAL',
    sync_interval_minutes INT DEFAULT 60,  -- Run sync every N minutes
    last_sync_at TIMESTAMP NULL,
    next_sync_at TIMESTAMP NULL,
    
    -- Sync Status
    status ENUM('ACTIVE', 'PAUSED', 'ERROR', 'NOT_STARTED') DEFAULT 'ACTIVE',
    last_error_message TEXT,
    
    -- Record Counts
    last_export_count INT DEFAULT 0,
    last_import_count INT DEFAULT 0,
    total_exports INT DEFAULT 0,
    total_imports INT DEFAULT 0,
    
    -- Column Mapping
    column_mapping JSON,  -- { "domain": "A", "status": "B", ... }
    
    -- Metadata
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sheet_id (sheet_id),
    INDEX idx_status (status),
    INDEX idx_next_sync_at (next_sync_at)
);

-- Google Sheets Sync Log
CREATE TABLE IF NOT EXISTS google_sheets_sync_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    sync_config_id INT NOT NULL,
    sync_direction ENUM('EXPORT', 'IMPORT', 'BIDIRECTIONAL') NOT NULL,
    status ENUM('SUCCESS', 'FAILED', 'PARTIAL', 'SKIPPED') NOT NULL,
    
    -- Record Tracking
    records_processed INT DEFAULT 0,
    records_created INT DEFAULT 0,
    records_updated INT DEFAULT 0,
    records_deleted INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    
    -- Details
    error_message TEXT,
    error_details JSON,
    
    -- Timing
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    duration_seconds INT,
    
    -- Conflict Resolution
    conflicts_detected INT DEFAULT 0,
    conflicts_resolved INT DEFAULT 0,
    
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sync_config_id) REFERENCES google_sheets_sync(id) ON DELETE CASCADE,
    INDEX idx_sync_config_id (sync_config_id),
    INDEX idx_status (status),
    INDEX idx_synced_at (synced_at)
);

-- Google Sheets Credentials (encrypted)
CREATE TABLE IF NOT EXISTS google_sheets_credentials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    account_email VARCHAR(255) NOT NULL,
    service_account_json LONGTEXT NOT NULL,  -- Encrypted JSON key
    
    oauth_refresh_token TEXT,  -- For user auth
    oauth_access_token TEXT,
    oauth_token_expiry TIMESTAMP,
    
    is_active BOOLEAN DEFAULT true,
    last_used_at TIMESTAMP NULL,
    
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_is_active (is_active)
);
