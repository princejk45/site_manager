-- Add test_email column to smtp_settings table
ALTER TABLE smtp_settings ADD COLUMN test_email VARCHAR(255);
