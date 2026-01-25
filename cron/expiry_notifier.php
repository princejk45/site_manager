<?php
require __DIR__ . '/../config/bootstrap.php';
// Check if cron is enabled
$cronModel = new CronModel($pdo);
if (!$cronModel->getCronStatus()) {
    error_log("Cron job is disabled in settings");
    exit(0);
}

// Config
$dryRun = false;
$forceSend = false;

$websiteModel = new Website($pdo);
$emailModel = new Email($pdo);

function hasNotificationBeenSent($pdo, $websiteId, $notificationType): bool
{
    $stmt = $pdo->prepare("
        SELECT 1 FROM website_notifications 
        WHERE website_id = ? AND notification_type = ?
    ");
    $stmt->execute([$websiteId, $notificationType]);
    return (bool) $stmt->fetch();
}

function recordNotificationSent($pdo, $websiteId, $notificationType, $dryRun): bool
{
    if ($dryRun) {
        error_log("[DRY RUN] Would record '$notificationType' for website $websiteId");
        return true;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO website_notifications (website_id, notification_type, sent_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE sent_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$websiteId, $notificationType]);
    } catch (PDOException $e) {
        error_log("DB error recording '$notificationType': " . $e->getMessage());
        return false;
    }
}

$websites = $websiteModel->getWebsites('', 'expiry_date', 'asc', 1, PHP_INT_MAX);
$notificationsSent = 0;
$potentialNotifications = 0;

foreach ($websites as $website) {
    $today = new DateTime('today');
    $expiryDate = new DateTime($website['expiry_date']);
    $expiryDate->setTime(0, 0);

    $interval = $today->diff($expiryDate);
    $daysUntilExpiry = $interval->invert ? -$interval->days : $interval->days;
    $status = $websiteModel->calculateDynamicStatus($website['expiry_date']);

    $notificationType = null;
    $emailDays = null;

    // Determine notification type
    if ($status === 'scaduto') {
        $notificationType = 'scaduto';
        $emailDays = 0;
    } elseif ($daysUntilExpiry <= 30 && $daysUntilExpiry > 15) {
        $notificationType = '30-day';
        $emailDays = 30;
    } elseif ($daysUntilExpiry <= 15 && $daysUntilExpiry > 1) {
        $notificationType = '15-day';
        $emailDays = 15;
    } elseif ($daysUntilExpiry <= 1 && $daysUntilExpiry >= 0) {
        $notificationType = '1-day';
        $emailDays = 1;
    }

    if (!$notificationType) {
        continue;
    }

    $alreadySent = hasNotificationBeenSent($pdo, $website['id'], $notificationType);
    $shouldSend = $forceSend || !$alreadySent;

    error_log("Check for {$website['domain']} - status: $status, type: $notificationType, sent: " . ($alreadySent ? 'yes' : 'no'));

    if ($shouldSend) {
        if ($dryRun) {
            error_log("[DRY RUN] Would send '$notificationType' to {$website['domain']}");
            $potentialNotifications++;
            continue;
        }

        try {
            $sent = $emailModel->sendExpiryNotification($website['id'], $emailDays);

            if ($sent) {
                if (recordNotificationSent($pdo, $website['id'], $notificationType, $dryRun)) {
                    error_log("Sent and recorded '$notificationType' for {$website['domain']}");
                    $notificationsSent++;
                } else {
                    error_log("Failed to record '$notificationType' for {$website['domain']}");
                }
            } else {
                error_log("Failed to send '$notificationType' for {$website['domain']}");
            }
        } catch (Exception $e) {
            error_log("Exception for {$website['domain']}: " . $e->getMessage());
        }
    } else {
        error_log("Skipping '{$notificationType}' for {$website['domain']} - already sent");
    }
}

// Final report
$scadutoCount = $pdo->query("
    SELECT COUNT(*) FROM website_notifications WHERE notification_type = 'scaduto'
")->fetchColumn();

error_log("Database contains $scadutoCount 'scaduto' notifications");

$summary = sprintf(
    "Cron completed at %s. %s%d notification%s (%d websites processed)",
    date('Y-m-d H:i:s'),
    $dryRun ? "[DRY RUN] " : "",
    $dryRun ? $potentialNotifications : $notificationsSent,
    $dryRun ? "s would have been sent" : "s sent",
    count($websites)
);
error_log($summary);
$cronModel->updateLastRunTime();
