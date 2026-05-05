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
        $ok = $stmt->execute([$websiteId, $notificationType]);
    } catch (PDOException $e) {
        cronLog("DB error recording '$notificationType': " . $e->getMessage(), "ERROR");
        return false;
    }

    // Also write to notification_events (best-effort)
    try {
        $tableCheck = $pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_events'"
        )->fetchColumn();

        if ($tableCheck) {
            $site = $pdo->prepare(
                "SELECT hosting_id, COALESCE(service_type, 'hosting_web') AS service_type FROM websites WHERE id = ?"
            );
            $site->execute([$websiteId]);
            $row = $site->fetch();
            $clientId   = $row['hosting_id'] ?? null;
            $serviceType = $row['service_type'] ?? 'hosting_web';

            $ne = $pdo->prepare(
                "INSERT INTO notification_events
                 (client_id, website_id, service_type, event_type, severity, channel, payload_json, sent_at, status)
                 VALUES (?, ?, ?, ?, 'info', 'email', ?, NOW(), 'sent')"
            );
            $ne->execute([
                $clientId,
                $websiteId,
                $serviceType,
                'expiry_' . $notificationType,
                json_encode(['notification_type' => $notificationType, 'triggered_by' => 'cron']),
            ]);
        }
    } catch (Exception $e) {
        cronLog("notification_events insert skipped: " . $e->getMessage(), "WARN");
    }

    return $ok ?? false;
}

// Main processing — driven by automation rules
$results = [
    'websites_checked' => 0,
    'notifications_sent' => 0,
    'notifications_skipped' => 0,
    'errors' => [],
    'start_time' => microtime(true)
];

try {
    // Load active automation rules for expiry notifications (replaces hardcoded thresholds)
    $rulesStmt = $pdo->query(
        "SELECT * FROM automation_rules
          WHERE trigger_type = 'expiry_approaching' AND is_active = 1
          ORDER BY trigger_threshold ASC"
    );
    $rules = $rulesStmt ? $rulesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    if (empty($rules)) {
        cronLog("No active automation rules found for trigger_type='expiry_approaching' — no notifications will be sent");
        cronLog("Create an Automation Rule with trigger 'Expiry Approaching' to enable notifications");
    } else {
        cronLog("Found " . count($rules) . " active expiry rule(s)");

        foreach ($rules as $rule) {
            $ruleId   = (int)$rule['id'];
            $days     = (int)$rule['trigger_threshold'];
            $notificationType = 'rule_' . $ruleId; // unique dedup key per rule

            cronLog("Processing rule #{$ruleId} \"{$rule['name']}\" (threshold: {$days} days)");

            // Find websites expiring within the rule's threshold window
            $sitesStmt = $pdo->prepare(
                "SELECT w.id, w.domain, w.expiry_date, w.client_email
                   FROM websites w
                  WHERE w.expiry_date IS NOT NULL
                    AND DATEDIFF(w.expiry_date, CURDATE()) BETWEEN 0 AND ?
                  ORDER BY w.expiry_date"
            );
            $sitesStmt->execute([$days]);
            $matchingSites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC);

            $results['websites_checked'] += count($matchingSites);
            $ruleTriggered = 0;

            foreach ($matchingSites as $website) {
                try {
                    $daysLeft    = (int)floor((strtotime($website['expiry_date']) - time()) / 86400);
                    $alreadySent = hasNotificationBeenSent($pdo, $website['id'], $notificationType);
                    $shouldSend  = $forceSend || !$alreadySent;

                    cronLog(
                        "Rule #{$ruleId} | Domain: {$website['domain']} | Days left: {$daysLeft} | " .
                        "AlreadySent: " . ($alreadySent ? 'yes' : 'no')
                    );

                    if ($shouldSend) {
                        if ($dryRun) {
                            cronLog("[DRY RUN] Would send rule #{$ruleId} notification to {$website['domain']}", "WARN");
                            $results['notifications_sent']++;
                            $ruleTriggered++;
                            continue;
                        }

                        try {
                            $sent = $emailModel->sendExpiryNotification($website['id'], $days);

                            if ($sent) {
                                recordNotificationSent($pdo, $website['id'], $notificationType, false);

                                // Log execution to automation_rule_executions
                                $logStmt = $pdo->prepare(
                                    "INSERT INTO automation_rule_executions
                                     (rule_id, website_id, trigger_value, action_result, executed_at)
                                     VALUES (?, ?, ?, 'email_sent', NOW())"
                                );
                                $logStmt->execute([$ruleId, $website['id'], "{$daysLeft} days left"]);

                                cronLog("✓ Sent rule #{$ruleId} notification for {$website['domain']}");
                                $results['notifications_sent']++;
                                $ruleTriggered++;
                            } else {
                                // Log failure in executions table
                                $logStmt = $pdo->prepare(
                                    "INSERT INTO automation_rule_executions
                                     (rule_id, website_id, trigger_value, action_result, executed_at)
                                     VALUES (?, ?, ?, 'email_failed', NOW())"
                                );
                                $logStmt->execute([$ruleId, $website['id'], "{$daysLeft} days left"]);

                                $error = "Failed to send rule #{$ruleId} email for {$website['domain']}";
                                cronLog($error, "ERROR");
                                $results['errors'][] = $error;
                            }
                        } catch (Exception $e) {
                            $error = "Exception sending rule #{$ruleId} for {$website['domain']}: " . $e->getMessage();
                            cronLog($error, "ERROR");
                            $results['errors'][] = $error;
                        }
                    } else {
                        cronLog("⊘ Skipping rule #{$ruleId} for {$website['domain']} — already sent");
                        $results['notifications_skipped']++;
                    }
                } catch (Exception $e) {
                    $error = "Error processing website {$website['domain']}: " . $e->getMessage();
                    cronLog($error, "ERROR");
                    $results['errors'][] = $error;
                }
            }

            // Update rule execution metadata
            if (!$dryRun && $ruleTriggered > 0) {
                $updStmt = $pdo->prepare(
                    "UPDATE automation_rules
                        SET execution_count = execution_count + ?,
                            last_executed_at = NOW()
                      WHERE id = ?"
                );
                $updStmt->execute([$ruleTriggered, $ruleId]);
            }
        }
    }
} catch (Exception $e) {
    $error = "Fatal error in automation-driven cron: " . $e->getMessage();
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
