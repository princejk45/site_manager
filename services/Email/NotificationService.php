<?php
/**
 * NotificationService
 * 
 * Sends email notifications and manages notification preferences.
 * Integrates with PHPMailer for reliable email delivery.
 * 
 * @author Fullmidia
 * @version 1.0.0
 * @package Services\Email
 */

class NotificationService {
    
    private $pdo;
    private $mailer;
    private $fromEmail;
    private $fromName;
    
    /**
     * Initialize NotificationService
     * 
     * @param PDO $pdo Database connection
     * @param object $mailer PHPMailer instance
     * @param string $fromEmail From email address
     * @param string $fromName From display name
     */
    public function __construct(PDO $pdo, $mailer = null, $fromEmail = null, $fromName = null) {
        $this->pdo = $pdo;
        $this->mailer = $mailer;
        $this->fromEmail = $fromEmail ?? 'noreply@fullmidia.com';
        $this->fromName = $fromName ?? 'Fullmidia';
    }
    
    /**
     * Send email notification
     * 
     * @param string $toEmail Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $options Additional options
     * @return bool Success
     */
    public function sendEmail($toEmail, $subject, $body, $options = []) {
        try {
            // Check recipient preference
            if (!$this->canSendTo($toEmail, $options['type'] ?? 'ALERT')) {
                error_log("Email blocked by preference for $toEmail");
                return false;
            }
            
            // Use PHPMailer if available
            if ($this->mailer) {
                return $this->sendViaPHPMailer($toEmail, $subject, $body, $options);
            }
            
            // Fallback to PHP mail()
            return $this->sendViaPhpMail($toEmail, $subject, $body, $options);
            
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email via PHPMailer
     * 
     * @param string $toEmail Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $options Options
     * @return bool Success
     */
    private function sendViaPHPMailer($toEmail, $subject, $body, $options) {
        try {
            // Reset recipients for fresh send
            $this->mailer->clearAllRecipients();
            
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($toEmail);
            $this->mailer->Subject = $subject;
            $this->mailer->msgHTML($body);
            $this->mailer->isHTML(true);
            
            // Add reply-to if specified
            if (isset($options['reply_to'])) {
                $this->mailer->addReplyTo($options['reply_to']);
            }
            
            // Set priority if specified
            if (isset($options['priority'])) {
                $priorityMap = ['LOW' => 5, 'MEDIUM' => 3, 'HIGH' => 1];
                $this->mailer->Priority = $priorityMap[$options['priority']] ?? 3;
            }
            
            $success = $this->mailer->send();
            
            if ($success) {
                $this->logEmailSent($toEmail, $subject, 'SUCCESS');
            } else {
                error_log("PHPMailer send failed: " . $this->mailer->ErrorInfo);
                $this->logEmailSent($toEmail, $subject, 'FAILED', $this->mailer->ErrorInfo);
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("PHPMailer error: " . $e->getMessage());
            $this->logEmailSent($toEmail, $subject, 'FAILED', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email via PHP mail()
     * 
     * @param string $toEmail Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $options Options
     * @return bool Success
     */
    private function sendViaPhpMail($toEmail, $subject, $body, $options) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>'
        ];
        
        if (isset($options['reply_to'])) {
            $headers[] = 'Reply-To: ' . $options['reply_to'];
        }
        
        $headers[] = 'X-Mailer: Fullmidia/1.0';
        
        $success = mail($toEmail, $subject, $body, implode("\r\n", $headers));
        
        $this->logEmailSent(
            $toEmail,
            $subject,
            $success ? 'SUCCESS' : 'FAILED'
        );
        
        return $success;
    }
    
    /**
     * Send bulk emails
     * 
     * @param array $recipients Array of email addresses
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $options Options
     * @return array Send results
     */
    public function sendBulk($recipients, $subject, $body, $options = []) {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'blocked' => 0,
            'details' => []
        ];
        
        foreach ($recipients as $email) {
            // Skip invalid emails
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['failed']++;
                $results['details'][] = ['email' => $email, 'status' => 'INVALID'];
                continue;
            }
            
            // Check if can send
            if (!$this->canSendTo($email, $options['type'] ?? 'ALERT')) {
                $results['blocked']++;
                $results['details'][] = ['email' => $email, 'status' => 'BLOCKED'];
                continue;
            }
            
            // Attempt send
            if ($this->sendEmail($email, $subject, $body, $options)) {
                $results['sent']++;
                $results['details'][] = ['email' => $email, 'status' => 'SENT'];
            } else {
                $results['failed']++;
                $results['details'][] = ['email' => $email, 'status' => 'FAILED'];
            }
        }
        
        return $results;
    }
    
    /**
     * Check if recipient accepts this notification type
     * 
     * @param string $email Email address
     * @param string $type Notification type
     * @return bool Can send
     */
    private function canSendTo($email, $type) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notification_preferences
                WHERE email = ? AND notification_type = ?
                LIMIT 1
            ");
            $stmt->execute([$email, $type]);
            $pref = $stmt->fetch();
            
            if ($pref) {
                return (bool)$pref['enabled'];
            }
            
            // Default to enabled if no preference exists
            return true;
        } catch (PDOException $e) {
            error_log("Check notification preference error: " . $e->getMessage());
            return true; // Default to enabled on error
        }
    }
    
    /**
     * Log email sent
     * 
     * @param string $email Recipient email
     * @param string $subject Subject
     * @param string $status Status (SUCCESS, FAILED, BOUNCED)
     * @param string $notes Additional notes
     */
    private function logEmailSent($email, $subject, $status = 'SUCCESS', $notes = '') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_log (
                    recipient_email, subject, status, error_message, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$email, $subject, $status, $notes]);
        } catch (PDOException $e) {
            error_log("Email log error: " . $e->getMessage());
        }
    }
    
    /**
     * Queue email for later sending
     * 
     * @param string $toEmail Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $metadata Metadata (rule_id, website_id, etc.)
     * @return int Queue ID
     */
    public function queueEmail($toEmail, $subject, $body, $metadata = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_queue (
                    type, recipient, subject, message, metadata, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'PENDING', NOW())
            ");
            
            $stmt->execute([
                'EMAIL',
                $toEmail,
                $subject,
                $body,
                json_encode($metadata)
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Queue email error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Send queued emails (called from cron)
     * 
     * @param int $limit Number to send per execution
     * @return array Results
     */
    public function processQueue($limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notification_queue
                WHERE type = 'EMAIL' AND status = 'PENDING'
                ORDER BY created_at ASC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [
                'processed' => 0,
                'sent' => 0,
                'failed' => 0
            ];
            
            foreach ($queue as $item) {
                $sent = $this->sendEmail(
                    $item['recipient'],
                    $item['subject'],
                    $item['message']
                );
                
                $status = $sent ? 'SENT' : 'FAILED';
                
                // Update queue status
                $updateStmt = $this->pdo->prepare("
                    UPDATE notification_queue SET status = ?, sent_at = NOW() WHERE id = ?
                ");
                $updateStmt->execute([$status, $item['id']]);
                
                $results['processed']++;
                if ($sent) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Process queue error: " . $e->getMessage());
            return ['processed' => 0, 'sent' => 0, 'failed' => 0];
        }
    }
    
    /**
     * Set notification preference for email
     * 
     * @param string $email Email address
     * @param string $type Notification type
     * @param bool $enabled Enable/disable
     * @return bool Success
     */
    public function setPreference($email, $type, $enabled) {
        try {
            // Check if preference exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM notification_preferences
                WHERE email = ? AND notification_type = ?
            ");
            $stmt->execute([$email, $type]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Update existing
                $stmt = $this->pdo->prepare("
                    UPDATE notification_preferences SET enabled = ? WHERE id = ?
                ");
                $stmt->execute([$enabled ? 1 : 0, $exists['id']]);
            } else {
                // Insert new
                $stmt = $this->pdo->prepare("
                    INSERT INTO notification_preferences (
                        email, notification_type, enabled, created_at
                    ) VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$email, $type, $enabled ? 1 : 0]);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Set preference error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notification preferences for email
     * 
     * @param string $email Email address
     * @return array Preferences
     */
    public function getPreferences($email) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT notification_type, enabled FROM notification_preferences
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            
            $prefs = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prefs[$row['notification_type']] = (bool)$row['enabled'];
            }
            
            return $prefs;
        } catch (PDOException $e) {
            error_log("Get preferences error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get email log/history
     * 
     * @param string $email Email address (optional filter)
     * @param int $limit Number of records
     * @return array Email log
     */
    public function getEmailLog($email = null, $limit = 100) {
        try {
            $query = "SELECT * FROM email_log";
            $params = [];
            
            if ($email) {
                $query .= " WHERE recipient_email = ?";
                $params[] = $email;
            }
            
            $query .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(count($params) - 1, $limit, PDO::PARAM_INT);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get email log error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create HTML email template
     * 
     * @param string $title Email title
     * @param string $content Email content
     * @param array $cta Call-to-action button config
     * @return string HTML email
     */
    public function createEmailTemplate($title, $content, $cta = null) {
        $ctaHtml = '';
        
        if ($cta) {
            $ctaHtml = sprintf(
                '<p style="text-align: center; margin-top: 30px;"><a href="%s" style="background-color: #0066cc; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block;">%s</a></p>',
                htmlspecialchars($cta['url']),
                htmlspecialchars($cta['text'])
            );
        }
        
        return sprintf('
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #0066cc 0%%, #0052a3 100%%); color: white; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .footer { font-size: 0.9em; color: #666; margin-top: 30px; text-align: center; border-top: 1px solid #ddd; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>%s</h1>
        </div>
        <div class="content">
            %s
            %s
        </div>
        <div class="footer">
            <p>© 2026 Fullmidia. All rights reserved.</p>
            <p><a href="https://fullmidia.com/preferences" style="color: #0066cc; text-decoration: none;">Manage preferences</a></p>
        </div>
    </div>
</body>
</html>
        ', htmlspecialchars($title), $content, $ctaHtml);
    }
}
?>
