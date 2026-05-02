<?php
class CronModel
{
    private $pdo;
    private $cronScheduler;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->cronScheduler = new CronScheduler($pdo);
    }

    public function getCronStatus()
    {
        $stmt = $this->pdo->query("SELECT is_active FROM cron_settings LIMIT 1");
        return (bool) $stmt->fetchColumn();
    }

    public function updateCronStatus($isActive)
    {
        $stmt = $this->pdo->prepare("UPDATE cron_settings SET is_active = ?");
        return $stmt->execute([$isActive ? 1 : 0]);
    }

    public function updateLastRunTime()
    {
        $stmt = $this->pdo->prepare("UPDATE cron_settings SET last_run = NOW()");
        return $stmt->execute();
    }

    public function getLastRunTime()
    {
        $stmt = $this->pdo->query("SELECT last_run FROM cron_settings LIMIT 1");
        return $stmt->fetchColumn();
    }

    /**
     * Get comprehensive cron diagnostics
     */
    public function getDiagnostics()
    {
        try {
            $websiteModel = new Website($this->pdo);
            $emailModel = new Email($this->pdo);
            
            // Get execution timing
            $nextExecution = $this->cronScheduler->getNextExecutionTime();
            
            // Count websites by expiry status
            $allWebsites = $websiteModel->getWebsites('', 'expiry_date', 'asc', 1, PHP_INT_MAX);
            $today = new DateTime('today');
            
            $counts = [
                'total' => count($allWebsites),
                'expired' => 0,
                'expires_1_day' => 0,
                'expires_15_days' => 0,
                'expires_30_days' => 0
            ];
            
            foreach ($allWebsites as $website) {
                $expiryDate = new DateTime($website['expiry_date']);
                $expiryDate->setTime(0, 0);
                $interval = $today->diff($expiryDate);
                $daysUntilExpiry = $interval->invert ? -$interval->days : $interval->days;
                
                if ($daysUntilExpiry < 0) {
                    $counts['expired']++;
                } elseif ($daysUntilExpiry <= 1) {
                    $counts['expires_1_day']++;
                } elseif ($daysUntilExpiry <= 15) {
                    $counts['expires_15_days']++;
                } elseif ($daysUntilExpiry <= 30) {
                    $counts['expires_30_days']++;
                }
            }
            
            // Total emails to be sent
            $totalEmails = $counts['expired'] + $counts['expires_1_day'] + 
                          $counts['expires_15_days'] + $counts['expires_30_days'];
            
            // Check SMTP configuration
            $smtpSettings = $emailModel->getSmtpSettings();
            $smtpConfigured = !empty($smtpSettings) && !empty($smtpSettings['host']);
            
            // Get cron status
            $cronEnabled = $this->getCronStatus();
            $lastRun = $this->getLastRunTime();
            
            return [
                'success' => true,
                'cron_enabled' => $cronEnabled,
                'is_localhost' => $nextExecution['is_localhost'] ?? false,
                'next_execution' => $nextExecution['next_run'] ?? null,
                'next_execution_message' => $nextExecution['message'] ?? null,
                'cron_expression' => $nextExecution['cron_expression'] ?? null,
                'last_run' => $lastRun,
                'total_websites' => $counts['total'],
                'expired_count' => $counts['expired'],
                'expires_1_day_count' => $counts['expires_1_day'],
                'expires_15_days_count' => $counts['expires_15_days'],
                'expires_30_days_count' => $counts['expires_30_days'],
                'total_emails' => $totalEmails,
                'smtp_configured' => $smtpConfigured,
                'smtp_from_email' => $smtpSettings['from_email'] ?? null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
