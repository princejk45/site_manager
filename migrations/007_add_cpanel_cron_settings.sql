-- Add cPanel cron settings table
CREATE TABLE IF NOT EXISTS cpanel_cron_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cpanel_host VARCHAR(255),
    cpanel_username VARCHAR(255),
    cpanel_api_token VARCHAR(1000),
    cpanel_command VARCHAR(500),
    command_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
