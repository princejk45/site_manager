-- Auto-Generated Bug Reports Table
-- Stores issues detected from WordPress diagnostics, health checks, etc.
CREATE TABLE IF NOT EXISTS bug_reports_auto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    website_id INT NOT NULL,
    
    -- Report Details
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL DEFAULT 'MEDIUM',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    impact_description TEXT,  -- "May cause 20% slowdown"
    
    -- Source Info
    source ENUM(
        'wordpress_api',
        'health_check',
        'cron_job',
        'security_scan',
        'user_report'
    ) NOT NULL DEFAULT 'health_check',
    source_data JSON,  -- Additional context from source
    
    -- Status & Resolution
    status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'DISMISSED', 'BLOCKED') DEFAULT 'OPEN',
    assigned_to INT,
    internal_notes TEXT,
    
    -- Timing
    first_detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    
    -- Tracking
    auto_generated BOOLEAN DEFAULT true,
    recurrence_count INT DEFAULT 1,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_website_id (website_id),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Bug Report History (audit trail for changes)
CREATE TABLE IF NOT EXISTS bug_report_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bug_report_id INT NOT NULL,
    changed_by INT,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    change_notes TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (bug_report_id) REFERENCES bug_reports_auto(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_bug_report_id (bug_report_id),
    INDEX idx_changed_at (changed_at)
);
