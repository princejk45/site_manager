-- Migration 016: Add Phase 1 feature flags to licenses table
-- Date: 2026-05-02
-- Purpose: Add diagnostics_center feature flag for Phase 1

ALTER TABLE licenses ADD COLUMN diagnostics_center tinyint(1) DEFAULT 0 AFTER advanced_reporting;

-- Update existing licenses based on tier
UPDATE licenses SET diagnostics_center = 1 WHERE product_tier IN ('PROFESSIONAL', 'ENTERPRISE');

-- Verify the changes
SELECT COUNT(*) as total_with_diagnostics FROM licenses WHERE diagnostics_center = 1;
