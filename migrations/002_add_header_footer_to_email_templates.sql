-- Add header and footer columns to email_templates table
ALTER TABLE email_templates ADD COLUMN header LONGTEXT NULL AFTER subject;
ALTER TABLE email_templates ADD COLUMN footer LONGTEXT NULL AFTER body;
