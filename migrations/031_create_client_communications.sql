-- Migration 031: Client Communications CRM Log
-- Creates a unified log of all client communications (system + manual)

CREATE TABLE IF NOT EXISTS client_communications (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    hosting_id      INT NOT NULL COMMENT 'The client (hosting record)',
    website_id      INT NULL     COMMENT 'Optional: which site/service this relates to',
    comm_type       ENUM(
                        'invoice',
                        'domain_renewal',
                        'hosting_renewal',
                        'email_hosting',
                        'email_space',
                        'website_changes',
                        'health_report',
                        'maintenance',
                        'general',
                        'other'
                    ) NOT NULL DEFAULT 'general',
    channel         ENUM('email','phone','whatsapp','in_person','portal','other') NOT NULL DEFAULT 'email',
    subject         VARCHAR(255) NOT NULL,
    notes           TEXT NULL    COMMENT 'Summary / body of the communication',
    sent_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When it was actually sent/done',
    sent_by         INT NULL     COMMENT 'user_id of who logged this',
    source          ENUM('system','manual') NOT NULL DEFAULT 'manual' COMMENT 'system = auto-generated, manual = hand-logged',
    email_log_id    INT NULL     COMMENT 'FK to email_logs if source=system',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_hosting_id  (hosting_id),
    INDEX idx_website_id  (website_id),
    INDEX idx_sent_at     (sent_at),
    INDEX idx_comm_type   (comm_type),

    FOREIGN KEY (hosting_id)   REFERENCES hosting(id)   ON DELETE CASCADE,
    FOREIGN KEY (website_id)   REFERENCES websites(id)  ON DELETE SET NULL,
    FOREIGN KEY (sent_by)      REFERENCES users(id)      ON DELETE SET NULL,
    FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
