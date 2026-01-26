-- Add global email header and footer settings
INSERT INTO site_settings (setting_key, setting_value, created_at, updated_at) 
VALUES 
('email_global_header', '<div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-bottom: 1px solid #dee2e6;"><h1 style="color: #333; margin: 0; font-size: 20px;">Notification</h1></div>', NOW(), NOW()),
('email_global_footer', '<div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6; font-size: 12px; color: #666;"><p>This is an automated message. Please do not reply directly to this email.</p><p>&copy; 2024 Site Manager. All rights reserved.</p></div>', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Remove individual header/footer from email_templates and add back to settings
ALTER TABLE email_templates DROP COLUMN IF EXISTS header;
ALTER TABLE email_templates DROP COLUMN IF EXISTS footer;
