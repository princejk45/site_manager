-- Add optional hosting/client relation on websites table.
-- Uses INFORMATION_SCHEMA checks so it can run safely on existing environments.

-- Add websites.hosting_id if missing
SET @has_hosting_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'websites'
      AND COLUMN_NAME = 'hosting_id'
);

SET @sql_add_col := IF(
    @has_hosting_id = 0,
    'ALTER TABLE websites ADD COLUMN hosting_id INT NULL AFTER id',
    'SELECT 1'
);
PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

-- Add index on websites.hosting_id if missing
SET @has_hosting_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'websites'
      AND INDEX_NAME = 'idx_websites_hosting_id'
);

SET @sql_add_idx := IF(
    @has_hosting_idx = 0,
    'ALTER TABLE websites ADD INDEX idx_websites_hosting_id (hosting_id)',
    'SELECT 1'
);
PREPARE stmt_add_idx FROM @sql_add_idx;
EXECUTE stmt_add_idx;
DEALLOCATE PREPARE stmt_add_idx;

-- Add foreign key if missing
SET @has_fk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'websites'
      AND CONSTRAINT_NAME = 'fk_websites_hosting_id'
);

SET @sql_add_fk := IF(
    @has_fk = 0,
    'ALTER TABLE websites ADD CONSTRAINT fk_websites_hosting_id FOREIGN KEY (hosting_id) REFERENCES hosting(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt_add_fk FROM @sql_add_fk;
EXECUTE stmt_add_fk;
DEALLOCATE PREPARE stmt_add_fk;
