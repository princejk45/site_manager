<?php
/**
 * Website Expiry Notifier Cron Job
 * Sends email notifications when websites are expiring soon
 * 
 * Exit Codes:
 * 0 = Success or disabled
 * 1 = Database/initialization error
 * 2 = Email configuration error
 * 3 = Unexpected exception
 */

require __DIR__ . '/../config/bootstrap.php';

// Initialize logging
$logFile = dirname(__DIR__) . '/logs/cron-expiry.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

function cronLog($message, $level = 'INFO')
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message";
    error_log($logMessage);
    @file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
}

function sendErrorNotification($subject, $details, $emailModel)
{
    try {
        $smtpSettings = $emailModel->getSmtpSettings();
        if (!$smtpSettings) {
            cronLog("SMTP settings not configured for error notification", "WARN");
            return false;
        }

        // Send to admin/support email
        $adminEmail = $smtpSettings['from_email'] ?? 'admin@fullmidia.it';
        $body = "Cron Job Error Report\n\n";
        $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Server: " . gethostname() . "\n\n";
        $body .= $details;

        require_once dirname(__DIR__) . '/vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpSettings['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpSettings['username'];
        $mail->Password = $smtpSettings['password'];
        $mail->Port = (int)$smtpSettings['port'];
        
        if ($smtpSettings['encryption'] === 'starttls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtpSettings['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name'] ?? 'Cron System');
        $mail->addAddress($adminEmail);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        return $mail->send();
    } catch (Exception $e) {
        cronLog("Failed to send error notification: " . $e->getMessage(), "ERROR");
        return false;
    }
}

// Check if cron is enabled
try {
    $cronModel = new CronModel($pdo);
    if (!$cronModel->getCronStatus()) {
        cronLog("Cron job is disabled in settings - exiting");
        exit(0);
    }
} catch (Exception $e) {
    cronLog("Failed to check cron status: " . $e->getMessage(), "ERROR");
    exit(1);
}

// Config
$dryRun = isset($argv[1]) && $argv[1] === '--dry-run';
$forceSend = isset($argv[2]) && $argv[2] === '--force';
$isTestMode = gethostname() === '127.0.0.1' || strpos(gethostname(), 'local') !== false;

if ($dryRun) {
    cronLog("Running in DRY RUN mode - no emails will be sent", "WARN");
}
if ($forceSend) {
    cronLog("Running with FORCE mode - will resend already-sent notifications", "WARN");
}

try {
    $websiteModel = new Website($pdo);
    $emailModel = new Email($pdo);
} catch (Exception $e) {
    cronLog("Failed to initialize models: " . $e->getMessage(), "ERROR");
    sendErrorNotification(
        "Cron Job - Initialization Error",
        "Failed to initialize models:\n" . $e->getMessage(),
        null
    );
    exit(1);
}

function hasNotificationBeenSent($pdo, $websiteId, $notificationType): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM website_notifications 
            WHERE website_id = ? AND notification_type = ?
        ");
        $stmt->execute([$websiteId, $notificationType]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        cronLog("DB query error checking notification: " . $e->getMessage(), "ERROR");
        return true; // Assume sent to prevent duplicate sends on error
    }
}

function recordNotificationSent($pdo, $websiteId, $notificationType, $dryRun): bool
{
    if ($dryRun) {
        cronLog("[DRY RUN] Would record '$notificationType' for website $websiteId");
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
        cronLog("DB error recording '$notificationType': " . $e->getMessage(), "ERROR");
        return false;
    }
}

// Main processing
$results = [
    'websites_checked' => 0,
    'notifications_sent' => 0,
    'notifications_skipped' => 0,
    'errors' => [],
    'start_time' => microtime(true)
];

try {
    $websites = $websiteModel->getWebsites('', 'expiry_date', 'asc', 1, PHP_INT_MAX);
    $results['websites_checked'] = count($websites);
    cronLog("Processing " . count($websites) . " websites");

    foreach ($websites as $website) {
        try {
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

            cronLog(
                "Domain: {$website['domain']} | Status: $status | Days: $daysUntilExpiry | " .
                "Type: $notificationType | AlreadySent: " . ($alreadySent ? 'yes' : 'no')
            );

            if ($shouldSend) {
                if ($dryRun) {
                    cronLog("[DRY RUN] Would send '$notificationType' to {$website['domain']}", "WARN");
                    $results['notifications_sent']++;
                    continue;
                }

                try {
                    $sent = $emailModel->sendExpiryNotification($website['id'], $emailDays);

                    if ($sent) {
                        if (recordNotificationSent($pdo, $website['id'], $notificationType, false)) {
                            cronLog(
                                "✓ Sent and recorded '$notificationType' for {$website['domain']}"
                            );
                            $results['notifications_sent']++;
                        } else {
                            $error = "Failed to record '$notificationType' for {$website['domain']}";
                            cronLog($error, "ERROR");
                            $results['errors'][] = $error;
                        }
                    } else {
                        $error = "Failed to send '$notificationType' for {$website['domain']}";
                        cronLog($error, "ERROR");
                        $results['errors'][] = $error;
                    }
                } catch (Exception $e) {
                    $error = "Exception for {$website['domain']}: " . $e->getMessage();
                    cronLog($error, "ERROR");
                    $results['errors'][] = $error;
                }
            } else {
                cronLog("⊘ Skipping '{$notificationType}' for {$website['domain']} - already sent");
                $results['notifications_skipped']++;
            }
        } catch (Exception $e) {
            $error = "Error processing website {$website['domain']}: " . $e->getMessage();
            cronLog($error, "ERROR");
            $results['errors'][] = $error;
        }
    }
} catch (Exception $e) {
    $error = "Fatal error fetching websites: " . $e->getMessage();
    cronLog($error, "ERROR");
    $results['errors'][] = $error;
}

// Final report
try {
    $scadutoCount = $pdo->query(
        "SELECT COUNT(*) FROM website_notifications WHERE notification_type = 'scaduto'"
    )->fetchColumn();
    $results['total_expired_tracked'] = $scadutoCount;
} catch (Exception $e) {
    cronLog("Failed to get scaduto count: " . $e->getMessage(), "WARN");
}

// Calculate execution time
$results['execution_time'] = round(microtime(true) - $results['start_time'], 2);

// Build summary
$summary = sprintf(
    "Cron Job Summary [%s] - Sent: %d | Skipped: %d | Checked: %d | Errors: %d | Time: %.2fs",
    $dryRun ? "DRY RUN" : "PRODUCTION",
    $results['notifications_sent'],
    $results['notifications_skipped'],
    $results['websites_checked'],
    count($results['errors']),
    $results['execution_time']
);

cronLog($summary);
cronLog("===== CRON JOB END =====\n");

// Update last run time (only if not dry run)
try {
    if (!$dryRun) {
        $cronModel->updateLastRunTime();
    }
} catch (Exception $e) {
    cronLog("Failed to update last run time: " . $e->getMessage(), "ERROR");
}

// Send error notification if there were errors
if (!empty($results['errors']) && !$dryRun) {
    $errorDetails = "CRON JOB ERRORS:\n\n";
    foreach ($results['errors'] as $i => $error) {
        $errorDetails .= ($i + 1) . ". $error\n";
    }
    $errorDetails .= "\n\nSUMMARY:\n";
    $errorDetails .= "- Websites Checked: " . $results['websites_checked'] . "\n";
    $errorDetails .= "- Notifications Sent: " . $results['notifications_sent'] . "\n";
    $errorDetails .= "- Notifications Skipped: " . $results['notifications_skipped'] . "\n";
    $errorDetails .= "- Total Errors: " . count($results['errors']) . "\n";
    $errorDetails .= "- Execution Time: " . $results['execution_time'] . "s\n";

    if (sendErrorNotification(
        "Cron Job - Errors Detected",
        $errorDetails,
        $emailModel
    )) {
        cronLog("Error notification email sent to admin");
    } else {
        cronLog("Failed to send error notification email", "WARN");
    }
}

// Exit with appropriate code
if (!empty($results['errors'])) {
    exit(3); // Errors occurred
}

if (!$dryRun) {
    exit(0); // Success
} else {
    exit(0); // Dry run completed
}
