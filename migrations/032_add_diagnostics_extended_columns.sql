-- Extend wordpress_diagnostics with columns produced by DiagnosticsNormalizer::extractForStorage().
-- Each ALTER TABLE is guarded with an INFORMATION_SCHEMA check so this migration
-- is safe to run on environments where the table already has some of these columns.

-- ssl_valid ---------------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordpress_diagnostics'
               AND COLUMN_NAME = 'ssl_valid');
SET @sql := IF(@has = 0,
    'ALTER TABLE wordpress_diagnostics ADD COLUMN ssl_valid TINYINT(1) NULL AFTER health_status',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- wp_version_outdated -----------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordpress_diagnostics'
               AND COLUMN_NAME = 'wp_version_outdated');
SET @sql := IF(@has = 0,
    'ALTER TABLE wordpress_diagnostics ADD COLUMN wp_version_outdated TINYINT(1) NULL AFTER ssl_valid',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- security_issues_count ---------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordpress_diagnostics'
               AND COLUMN_NAME = 'security_issues_count');
SET @sql := IF(@has = 0,
    'ALTER TABLE wordpress_diagnostics ADD COLUMN security_issues_count INT NULL AFTER wp_version_outdated',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- uptime_percent ----------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordpress_diagnostics'
               AND COLUMN_NAME = 'uptime_percent');
SET @sql := IF(@has = 0,
    'ALTER TABLE wordpress_diagnostics ADD COLUMN uptime_percent DECIMAL(5,2) NULL AFTER security_issues_count',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- average_response_time_ms ------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordpress_diagnostics'
               AND COLUMN_NAME = 'average_response_time_ms');
SET @sql := IF(@has = 0,
    'ALTER TABLE wordpress_diagnostics ADD COLUMN average_response_time_ms INT NULL AFTER uptime_percent',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- page_load_time_ms -------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordpress_diagnostics'
               AND COLUMN_NAME = 'page_load_time_ms');
SET @sql := IF(@has = 0,
    'ALTER TABLE wordpress_diagnostics ADD COLUMN page_load_time_ms INT NULL AFTER average_response_time_ms',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- backup_enabled ----------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordpress_diagnostics'
               AND COLUMN_NAME = 'backup_enabled');
SET @sql := IF(@has = 0,
    'ALTER TABLE wordpress_diagnostics ADD COLUMN backup_enabled TINYINT(1) NULL AFTER page_load_time_ms',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
