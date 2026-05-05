-- Migration 035: Add generated name_key column to providers for case/space-insensitive deduplication.
-- "Serverplan", "Server Plan", "server plan" all normalize to "serverplan" via LOWER(REPLACE(name,' ','')).
-- The unique constraint is moved to (name_key, type) so the lookup is both indexed and correct.

-- Step 1: Remove duplicate providers that would collide after normalization (keep the lowest id).
DELETE p1 FROM providers p1
INNER JOIN providers p2
    ON p1.type = p2.type
   AND LOWER(REPLACE(p1.name, ' ', '')) = LOWER(REPLACE(p2.name, ' ', ''))
   AND p1.id > p2.id;

-- Step 2: Drop old exact-match unique key.
ALTER TABLE providers DROP INDEX uk_providers_name_type;

-- Step 3: Add stored generated column for the normalized name.
ALTER TABLE providers
    ADD COLUMN name_key VARCHAR(100) GENERATED ALWAYS AS (LOWER(REPLACE(name, ' ', ''))) STORED
        COMMENT 'Normalized lookup key: lowercase with spaces stripped';

-- Step 4: Add new unique constraint on (name_key, type).
ALTER TABLE providers
    ADD UNIQUE KEY uk_providers_name_key_type (name_key, type);
