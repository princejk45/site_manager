-- Audit Trail - Track all user actions for compliance and debugging
CREATE TABLE IF NOT EXISTS audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Actor
    user_id INT,
    user_name VARCHAR(255),  -- Cached for deleted users
    
    -- Action
    action VARCHAR(100) NOT NULL,  -- 'website_created', 'rule_updated', etc.
    entity_type VARCHAR(50) NOT NULL,  -- 'website', 'automation_rule', 'user', etc.
    entity_id INT,
    entity_name VARCHAR(255),  -- Cached name of entity
    
    -- Changes
    changes JSON,  -- { "domain": { "old": "old.com", "new": "new.com" }, ... }
    old_values JSON,  -- Previous state of entity
    new_values JSON,  -- Current state of entity
    
    -- Request Info
    ip_address VARCHAR(45),  -- IPv4 or IPv6
    user_agent TEXT,
    http_method VARCHAR(10),  -- GET, POST, PUT, DELETE
    request_url VARCHAR(500),
    
    -- Status
    status ENUM('SUCCESS', 'FAILED', 'PARTIAL') DEFAULT 'SUCCESS',
    error_message TEXT,
    
    -- Performance
    execution_time_ms INT,  -- How long the action took
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_entity_type (entity_type),
    INDEX idx_entity_id (entity_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address)
);

-- Audit Trail Search Index (for faster queries)
CREATE TABLE IF NOT EXISTS audit_trail_search (
    id INT PRIMARY KEY AUTO_INCREMENT,
    audit_trail_id INT NOT NULL,
    search_text MEDIUMTEXT,  -- Full text searchable version
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (audit_trail_id) REFERENCES audit_trail(id) ON DELETE CASCADE,
    FULLTEXT INDEX ft_search_text (search_text),
    INDEX idx_created_at (created_at)
);
