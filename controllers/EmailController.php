<?php
class EmailController
{
    private Email $emailModel;
    private Website $websiteModel;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->emailModel = new Email($pdo);
        $this->websiteModel = new Website($pdo);
    }

    /**
     * Write a row to notification_events (best-effort, never fatal).
     */
    private function logNotificationEvent(
        int $websiteId,
        string $eventType,
        string $status,
        string $channel = 'email',
        string $severity = 'info',
        ?array $payload = null
    ): void {
        try {
            $exists = $this->pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_events'"
            )->fetchColumn();
            if (!$exists) return;

            $website    = $this->websiteModel->getWebsiteById($websiteId);
            $clientId   = $website['hosting_id'] ?? null;
            $serviceType = $website['service_type'] ?? 'hosting_web';
            $sentAt     = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
            $payloadJson = $payload ? json_encode($payload) : null;

            $stmt = $this->pdo->prepare(
                "INSERT INTO notification_events
                 (client_id, website_id, service_type, event_type, severity, channel, payload_json, sent_at, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $clientId, $websiteId, $serviceType, $eventType,
                $severity, $channel, $payloadJson, $sentAt, $status,
            ]);
        } catch (Exception $e) {
            error_log("logNotificationEvent error: " . $e->getMessage());
        }
    }

    public function sendExpiryNotification($websiteId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $website = $this->websiteModel->getWebsiteById($websiteId);
        if (!$website) {
            $_SESSION['error'] = "Sito web non trovato";
            header('Location: index.php?action=websites');
            exit;
        }

        // Calculate days until expiry
        $expiryDate = new DateTime($website['expiry_date']);
        $today = new DateTime();
        $interval = $today->diff($expiryDate);
        $daysUntilExpiry = $interval->invert ? -$interval->days : $interval->days;

        $success = $this->emailModel->sendExpiryNotification($websiteId, $daysUntilExpiry);

        $this->logNotificationEvent(
            (int)$websiteId,
            'expiry_notification',
            $success ? 'sent' : 'failed',
            'email',
            $success ? 'info' : 'error',
            ['days_until_expiry' => $daysUntilExpiry, 'triggered_by' => 'manual']
        );

        if ($success) {
            $_SESSION['message'] = "Notifica di scadenza inviata con successo per '{$website['domain']}'";
        } else {
            $_SESSION['error'] = "Impossibile inviare la notifica di scadenza per '{$website['domain']}'";
        }

        header('Location: index.php?action=websites');
        exit;
    }

    public function sendStatusNotification($websiteId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $website = $this->websiteModel->getWebsiteById($websiteId);
        if (!$website) {
            $_SESSION['error'] = "Sito web non trovato";
            header('Location: index.php?action=websites');
            exit;
        }

        $success = $this->emailModel->sendStatusNotification($websiteId);

        $this->logNotificationEvent(
            (int)$websiteId,
            'status_notification',
            $success ? 'sent' : 'failed',
            'email',
            $success ? 'info' : 'error',
            ['triggered_by' => 'manual']
        );

        if ($success) {
            $_SESSION['message'] = "Notifica di stato inviata con successo per '{$website['domain']}'";
        } else {
            $_SESSION['error'] = "No bugs?!!! Impossibile inviare la notifica di stato per '{$website['domain']}'";
        }

        header('Location: index.php?action=websites');
        exit;
    }

    public function sendRenewalNotification($websiteId, $newExpiryDate, $renewalCost)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $website = $this->websiteModel->getWebsiteById($websiteId);
        if (!$website) {
            $_SESSION['error'] = "Sito web non trovato";
            header('Location: index.php?action=websites');
            exit;
        }

        $success = $this->emailModel->sendRenewalNotification(
            $websiteId,
            $website['assigned_email'],
            $website['domain'],
            $newExpiryDate,
            $renewalCost
        );

        $this->logNotificationEvent(
            (int)$websiteId,
            'renewal_notification',
            $success ? 'sent' : 'failed',
            'email',
            $success ? 'info' : 'warning',
            ['new_expiry_date' => $newExpiryDate, 'renewal_cost' => $renewalCost, 'triggered_by' => 'manual']
        );

        if ($success) {
            $_SESSION['message'] = "Notifica di rinnovo inviata con successo per '{$website['domain']}' ";
            return true;
        } else {
            $_SESSION['error'] = "Impossibile inviare la notifica di rinnovo per '{$website['domain']}'";
            return false;
        }
    }

    public function showEmailLogs()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $logs = $this->emailModel->getEmailLogs();
        require APP_PATH . '/views/settings/email_logs.php';
    }
}