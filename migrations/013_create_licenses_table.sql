-- Licenses Table - Secure licensing system for world-class product
CREATE TABLE IF NOT EXISTS licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(50) UNIQUE NOT NULL,
    license_hash VARCHAR(64) NOT NULL,
    
    -- License Type & Tier
    license_type ENUM(
        'TRIAL',
        'MONTHLY',
        'QUARTERLY',
        'YEARLY',
        'LIFETIME'
    ) NOT NULL,
    
    product_tier ENUM(
        'LIGHT',
        'PROFESSIONAL',
        'ENTERPRISE'
    ) DEFAULT 'PROFESSIONAL',
    
    status ENUM(
        'ACTIVE',
        'INACTIVE',
        'EXPIRED',
        'REVOKED',
        'SUSPENDED'
    ) DEFAULT 'ACTIVE',
    
    -- License Limits
    max_websites INT NOT NULL,
    max_users INT NOT NULL,
    max_api_calls_per_day INT,
    
    -- Feature Flags
    google_sheets_sync BOOLEAN DEFAULT true,
    wordpress_integration BOOLEAN DEFAULT true,
    automation_rules BOOLEAN DEFAULT true,
    advanced_reporting BOOLEAN DEFAULT true,
    webhooks_enabled BOOLEAN DEFAULT true,
    slack_integration BOOLEAN DEFAULT false,
    priority_support BOOLEAN DEFAULT false,
    custom_branding BOOLEAN DEFAULT false,
    
    -- Issued By
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activated_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    
    -- Customer Information
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    company_name VARCHAR(255),
    customer_website VARCHAR(255),
    
    -- Activation
    activation_code VARCHAR(50) UNIQUE,
    activation_attempts INT DEFAULT 0,
    max_activation_attempts INT DEFAULT 5,
    last_activation_attempt TIMESTAMP NULL,
    
    -- Usage Tracking
    current_websites_count INT DEFAULT 0,
    current_users_count INT DEFAULT 0,
    api_calls_today INT DEFAULT 0,
    api_calls_total INT DEFAULT 0,
    last_validation TIMESTAMP NULL,
    
    -- Hardware Fingerprint (optional, for offline validation)
    hardware_fingerprint VARCHAR(255),  -- SHA-256 of hardware ID
    allowed_hosts JSON,  -- ["192.168.1.100", "123.45.67.89"]
    
    -- Notes
    notes TEXT,
    internal_notes TEXT,
    
    -- Tracking
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_license_key (license_key),
    INDEX idx_status (status),
    INDEX idx_customer_email (customer_email),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
);

-- License Validation Log
CREATE TABLE IF NOT EXISTS license_validation_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_id INT NOT NULL,
    
    validation_type ENUM(
        'STARTUP',
        'PERIODIC',
        'FEATURE_ACCESS',
        'API_CALL',
        'MANUAL'
    ) NOT NULL,
    
    validation_status ENUM(
        'SUCCESS',
        'FAILED',
        'EXPIRED',
        'INVALID',
        'REVOKED',
        'SUSPENDED',
        'LIMIT_EXCEEDED',
        'HARDWARE_MISMATCH'
    ) NOT NULL,
    
    validation_result JSON,  -- Detailed reason and metrics
    ip_address VARCHAR(45),
    
    validated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    INDEX idx_license_id (license_id),
    INDEX idx_validation_status (validation_status),
    INDEX idx_validated_at (validated_at)
);

-- License Feature Usage (for analytics)
CREATE TABLE IF NOT EXISTS license_usage_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_id INT NOT NULL,
    
    feature_name VARCHAR(100) NOT NULL,  -- 'google_sheets_sync', 'automation_rule_create', etc.
    action_type VARCHAR(50),  -- 'access', 'create', 'update', 'delete'
    resource_count INT DEFAULT 1,  -- How many items (websites, rules, etc.)
    
    success BOOLEAN DEFAULT true,
    error_message TEXT,
    
    api_call_duration_ms INT,
    response_size_bytes INT,
    
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    INDEX idx_license_id (license_id),
    INDEX idx_feature_name (feature_name),
    INDEX idx_logged_at (logged_at)
);
