<?php
/**
 * WordPress Diagnostics Refresh Cron Job
 *
 * Sweeps every active wordpress_sites record, fetches fresh diagnostics from
 * the WordPress REST API, normalises the response, stores results in
 * wordpress_diagnostics, writes composite scores to health_metrics, and
 * updates bug_reports_auto — all via DiagnosticsService.
 *
 * Usage (cPanel / server cron):
 *   php /path/to/cron/wp_diagnostics_refresh.php
 *   php /path/to/cron/wp_diagnostics_refresh.php --site=42    (single site)
 *   php /path/to/cron/wp_diagnostics_refresh.php --dry-run    (log only, no DB writes)
 *
 * Recommended schedule: every 6 hours, or nightly at minimum.
 *
 * Exit Codes:
 *   0 = Success (full or partial)
 *   1 = Initialization error
 *   3 = Unexpected fatal exception
 */

require __DIR__ . '/../config/bootstrap.php';

// ── Service includes ──────────────────────────────────────────────────────────
require_once APP_PATH . '/models/WordPressSite.php';
require_once APP_PATH . '/services/WordPress/Exceptions.php';
require_once APP_PATH . '/services/WordPress/WordPressApiClient.php';
require_once APP_PATH . '/services/WordPress/DiagnosticsNormalizer.php';
require_once APP_PATH . '/services/WordPress/DiagnosticsService.php';

// ── Logging ───────────────────────────────────────────────────────────────────
$logFile = dirname(__DIR__) . '/logs/cron-wp-diagnostics.log';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

function wpDiagCronLog(string $message, string $level = 'INFO'): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] $message";
    error_log($line);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

// ── CLI flags ─────────────────────────────────────────────────────────────────
$dryRun    = in_array('--dry-run', $argv ?? [], true);
$singleSite = null;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--site=(\d+)$/', $arg, $m)) {
        $singleSite = (int)$m[1];
    }
}

if ($dryRun)    { wpDiagCronLog('Running in DRY RUN mode — no DB writes.', 'WARN'); }
if ($singleSite){ wpDiagCronLog("Single-site mode — website_id=$singleSite"); }

// ── Initialization ────────────────────────────────────────────────────────────
try {
    $cronModel = new CronModel($pdo);
    if (!$cronModel->getCronStatus()) {
        wpDiagCronLog('Cron disabled in settings — exiting.');
        exit(0);
    }
} catch (Exception $e) {
    wpDiagCronLog('Failed to check cron status: ' . $e->getMessage(), 'ERROR');
    exit(1);
}

// ── Load active WordPress sites ───────────────────────────────────────────────
try {
    if ($singleSite !== null) {
        $stmt = $pdo->prepare("
            SELECT ws.website_id
            FROM wordpress_sites ws
            WHERE ws.is_active = 1 AND ws.website_id = ?
        ");
        $stmt->execute([$singleSite]);
    } else {
        $stmt = $pdo->query("
            SELECT ws.website_id
            FROM wordpress_sites ws
            WHERE ws.is_active = 1
            ORDER BY ws.last_fetch_timestamp ASC
        ");
    }
    $sites = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    wpDiagCronLog('Failed to load WordPress sites: ' . $e->getMessage(), 'ERROR');
    exit(1);
}

if (empty($sites)) {
    wpDiagCronLog('No active WordPress sites found — nothing to do.');
    exit(0);
}

wpDiagCronLog('Starting diagnostics refresh for ' . count($sites) . ' site(s).');

// ── Per-site refresh ──────────────────────────────────────────────────────────
$diagnosticsService = new DiagnosticsService($pdo);

$counts = ['success' => 0, 'failed' => 0, 'skipped' => 0];

foreach ($sites as $websiteId) {
    $websiteId = (int)$websiteId;

    try {
        if ($dryRun) {
            wpDiagCronLog("  [DRY RUN] Would fetch website_id=$websiteId");
            $counts['skipped']++;
            continue;
        }

        $result = $diagnosticsService->fetchDiagnostics($websiteId);

        if ($result['success']) {
            $score = $result['data']['health']['score'] ?? '?';
            wpDiagCronLog("  OK  website_id=$websiteId  health={$score}");
            $counts['success']++;
        } else {
            wpDiagCronLog(
                "  ERR website_id=$websiteId  status={$result['status']}  {$result['error']}",
                'WARN'
            );
            $counts['failed']++;
        }
    } catch (Exception $e) {
        wpDiagCronLog("  FATAL website_id=$websiteId — " . $e->getMessage(), 'ERROR');
        $counts['failed']++;
    }

    // Brief pause between sites to avoid hammering the server when there are many
    if (count($sites) > 5) {
        usleep(250000); // 250 ms
    }
}

// ── Summary ───────────────────────────────────────────────────────────────────
wpDiagCronLog(sprintf(
    'Diagnostics refresh complete. Success=%d  Failed=%d  Skipped=%d',
    $counts['success'],
    $counts['failed'],
    $counts['skipped']
));

exit(0);
