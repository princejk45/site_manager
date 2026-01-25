<?php
class EmailController
{
    private $emailModel;
    private $websiteModel;

    public function __construct($pdo)
    {
        $this->emailModel = new Email($pdo);
        $this->websiteModel = new Website($pdo);
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