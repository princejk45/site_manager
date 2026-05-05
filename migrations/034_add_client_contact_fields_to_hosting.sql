-- Migration 034: Add client contact fields to the hosting (clients) table.
-- These correspond to sheet columns B (address/ip), C (email), D (VAT/P.IVA).
-- All guards use INFORMATION_SCHEMA so re-running is safe.

-- email_address -----------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosting'
               AND COLUMN_NAME = 'email_address');
SET @sql := IF(@has = 0,
    'ALTER TABLE hosting ADD COLUMN email_address VARCHAR(255) NULL AFTER name',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- address -----------------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosting'
               AND COLUMN_NAME = 'address');
SET @sql := IF(@has = 0,
    'ALTER TABLE hosting ADD COLUMN address VARCHAR(255) NULL AFTER email_address',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- vat_number (P.IVA) ------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosting'
               AND COLUMN_NAME = 'vat_number');
SET @sql := IF(@has = 0,
    'ALTER TABLE hosting ADD COLUMN vat_number VARCHAR(100) NULL AFTER address',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
