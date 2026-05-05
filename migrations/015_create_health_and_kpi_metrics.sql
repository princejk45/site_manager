-- Health Metrics - Store historical health data for trending
CREATE TABLE IF NOT EXISTS health_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    website_id INT NOT NULL,
    
    -- Health Score Components
    health_score INT DEFAULT 100,  -- 0-100
    uptime_percent DECIMAL(5, 2) DEFAULT 100.00,  -- 99.99%
    security_score INT DEFAULT 100,
    performance_score INT DEFAULT 100,
    backup_freshness_score INT DEFAULT 100,
    plugin_status_score INT DEFAULT 100,
    
    -- Detailed Metrics
    uptime_status ENUM('EXCELLENT', 'GOOD', 'WARNING', 'CRITICAL') DEFAULT 'EXCELLENT',
    security_issues_count INT DEFAULT 0,
    outdated_plugins INT DEFAULT 0,
    outdated_php_version BOOLEAN DEFAULT false,
    backup_age_days INT,
    
    -- Traffic & Performance
    average_response_time_ms INT,
    page_load_time_ms INT,
    error_rate_percent DECIMAL(5, 2),
    
    -- SSL Certificate
    ssl_valid BOOLEAN DEFAULT true,
    ssl_expiry_days INT,
    
    -- Database
    database_size_mb DECIMAL(10, 2),
    database_tables_count INT,
    database_last_optimized TIMESTAMP NULL,
    
    -- Recorded At
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    INDEX idx_website_id (website_id),
    INDEX idx_health_score (health_score),
    INDEX idx_recorded_at (recorded_at)
);

-- KPI Dashboard Metrics
CREATE TABLE IF NOT EXISTS kpi_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Organization Level KPIs
    metric_type ENUM(
        'total_revenue',
        'renewal_rate',
        'average_health_score',
        'critical_issues_count',
        'average_uptime',
        'websites_growth_rate',
        'churn_risk_count',
        'security_incidents',
        'backup_compliance',
        'license_compliance'
    ) NOT NULL,
    
    -- Values
    metric_value DECIMAL(12, 2),
    previous_value DECIMAL(12, 2),
    target_value DECIMAL(12, 2),
    unit VARCHAR(50),  -- 'USD', '%', 'count'
    
    -- Trending
    trend ENUM('UP', 'DOWN', 'STABLE', 'UNKNOWN') DEFAULT 'STABLE',
    trend_percent DECIMAL(5, 2),  -- % change
    
    -- Breakdown
    breakdown_data JSON,  -- { "by_type": {...}, "by_status": {...} }
    
    -- Date Range
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_metric_type (metric_type),
    INDEX idx_recorded_at (recorded_at),
    UNIQUE KEY unique_metric_period (metric_type, date_from, date_to)
);

-- Forecasting Data (for predictions)
CREATE TABLE IF NOT EXISTS forecasts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    forecast_type ENUM(
        'revenue',
        'renewal_rate',
        'health_trend',
        'churn_risk',
        'security_incidents',
        'growth_trajectory'
    ) NOT NULL,
    
    entity_type VARCHAR(50),  -- 'website', 'portfolio', 'organization'
    entity_id INT,
    
    -- Forecast Data
    forecast_period_start DATE NOT NULL,
    forecast_period_end DATE NOT NULL,
    
    forecast_value DECIMAL(12, 2),
    confidence_percent INT,  -- 0-100, how confident is this prediction
    
    methodology VARCHAR(100),  -- 'linear_regression', 'trend_analysis', 'ml_model'
    
    -- Actual vs Forecast (after period ends)
    actual_value DECIMAL(12, 2),
    variance_percent DECIMAL(5, 2),
    accuracy_rating VARCHAR(20),  -- 'excellent', 'good', 'fair', 'poor'
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_forecast_type (forecast_type),
    INDEX idx_entity_id (entity_id),
    INDEX idx_forecast_period_start (forecast_period_start)
);

-- Reports Generated (for analytics of what reports were used)
CREATE TABLE IF NOT EXISTS generated_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    report_type VARCHAR(100) NOT NULL,  -- 'health_summary', 'security_audit', 'performance'
    report_format VARCHAR(20),  -- 'pdf', 'csv', 'json', 'html'
    
    title VARCHAR(255) NOT NULL,
    description TEXT,
    
    -- Generation
    generated_by INT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Data Range
    date_from DATE,
    date_to DATE,
    
    -- File Storage
    file_path VARCHAR(500),
    file_size_bytes INT,
    
    -- Distribution
    distributed_to JSON,  -- User IDs or email addresses
    
    -- Status
    status ENUM('GENERATED', 'SCHEDULED', 'FAILED', 'ARCHIVED') DEFAULT 'GENERATED',
    
    -- Analytics
    download_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    last_accessed_at TIMESTAMP NULL,
    
    INDEX idx_report_type (report_type),
    INDEX idx_generated_at (generated_at),
    INDEX idx_generated_by (generated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
