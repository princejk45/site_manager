-- Migration 033: Add extended fields to websites table
-- These columns store service-level data that is displayed in and synced with Google Sheets.
-- Each ALTER is guarded so the migration is safe to re-run on environments that already
-- have some of the columns.

-- assigned_email --------------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'websites'
               AND COLUMN_NAME = 'assigned_email');
SET @sql := IF(@has = 0,
    'ALTER TABLE websites ADD COLUMN assigned_email VARCHAR(255) NULL AFTER service_type',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- proprietario ---------------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'websites'
               AND COLUMN_NAME = 'proprietario');
SET @sql := IF(@has = 0,
    'ALTER TABLE websites ADD COLUMN proprietario VARCHAR(255) NULL AFTER assigned_email',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- vendita --------------------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'websites'
               AND COLUMN_NAME = 'vendita');
SET @sql := IF(@has = 0,
    'ALTER TABLE websites ADD COLUMN vendita VARCHAR(100) NULL AFTER proprietario',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cpanel ---------------------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'websites'
               AND COLUMN_NAME = 'cpanel');
SET @sql := IF(@has = 0,
    'ALTER TABLE websites ADD COLUMN cpanel VARCHAR(255) NULL AFTER vendita',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- epanel ---------------------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'websites'
               AND COLUMN_NAME = 'epanel');
SET @sql := IF(@has = 0,
    'ALTER TABLE websites ADD COLUMN epanel VARCHAR(255) NULL AFTER cpanel',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- dns ------------------------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'websites'
               AND COLUMN_NAME = 'dns');
SET @sql := IF(@has = 0,
    'ALTER TABLE websites ADD COLUMN dns VARCHAR(255) NULL AFTER epanel',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- remark ---------------------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'websites'
               AND COLUMN_NAME = 'remark');
SET @sql := IF(@has = 0,
    'ALTER TABLE websites ADD COLUMN remark TEXT NULL AFTER dns',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- registrante_import ---------------------------------------------------------
-- Stores the registrar/provider name imported from Google Sheets (denormalised
-- text field; FK linkage is via provider_id).
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'websites'
               AND COLUMN_NAME = 'registrante_import');
SET @sql := IF(@has = 0,
    'ALTER TABLE websites ADD COLUMN registrante_import VARCHAR(255) NULL AFTER remark',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
