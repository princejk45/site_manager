-- Migration for Site Settings and Email Templates

CREATE TABLE IF NOT EXISTS site_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body LONGTEXT NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default site settings
INSERT IGNORE INTO site_settings (setting_key, setting_value, description) VALUES
('site_name', 'Fullmidia Web', 'Website name'),
('site_slogan', 'Gestione Siti Web e Hosting', 'Site slogan'),
('logo_path', 'assets/images/logo.png', 'Path to site logo'),
('favicon_path', 'assets/images/favicon.png', 'Path to favicon'),
('company_name', 'Fullmidia', 'Company name'),
('company_address', '', 'Company address'),
('company_phone', '', 'Company phone number'),
('company_email', 'info@fullmidia.it', 'Company email'),
('header_bg_color', '#1f2732', 'Email header background color'),
('footer_bg_color', '#1f2732', 'Email footer background color'),
('highlight_color', '#f39200', 'Brand highlight color');

-- Insert default email templates
INSERT IGNORE INTO email_templates (name, slug, subject, body, description, status) VALUES
(
    'Website Expiry Notification',
    'website_expiry',
    'Avviso di Scadenza - {domain}',
    '<h2>Notifica di Scadenza</h2><p>Il tuo dominio <strong>{domain}</strong> scadrà tra <strong>{days}</strong> giorni.</p><p>Ti consigliamo di rinnovarlo al più presto.</p>',
    'Sent when a website is expiring soon',
    'active'
),
(
    'Website Status Notification',
    'website_status',
    'Rapporto di Stato - {domain}',
    '<h2>Rapporto di Stato Servizio</h2><p>Stato servizio <strong>{domain}</strong>:</p><p>{status_content}</p>',
    'Sent to notify about website status',
    'active'
),
(
    'Website Renewal Notification',
    'website_renewal',
    'Rinnovo Confermato - {domain}',
    '<h2>Rinnovo Confermato</h2><p>Il tuo dominio <strong>{domain}</strong> è stato rinnovato con successo.</p><p>Nuova data di scadenza: <strong>{new_expiry}</strong></p>',
    'Sent when a website is renewed',
    'active'
),
(
    'Message Notification',
    'message_notification',
    'Nuovo Messaggio: {subject}',
    '<h2>Nuovo Messaggio</h2><p>Hai ricevuto un nuovo messaggio:</p><p>{content}</p>',
    'Sent when a user receives a message',
    'active'
);
