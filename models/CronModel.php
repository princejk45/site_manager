<?php
class CronModel
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
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
}
