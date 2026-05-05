<?php
/**
 * Google Sheets Safe Sync Cron
 * Runs baseline-aware bidirectional merge with schedule control.
 *
 * Exit Codes:
 * 0 = Success/partial/skipped
 * 1 = Initialization or DB error
 * 2 = Sync failed
 */

require __DIR__ . '/../config/bootstrap.php';

$logFile = dirname(__DIR__) . '/logs/cron-google-sync.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

function googleSyncCronLog(string $message, string $level = 'INFO'): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] $message";
    error_log($line);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

function ensureGoogleSyncTables(PDO $pdo): void
{
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS google_sheets_sync (\n            id INT PRIMARY KEY AUTO_INCREMENT,\n            sheet_id VARCHAR(255) NOT NULL,\n            sheet_name VARCHAR(255) NOT NULL,\n            sync_direction ENUM('EXPORT', 'IMPORT', 'BIDIRECTIONAL') NOT NULL DEFAULT 'BIDIRECTIONAL',\n            sync_interval_minutes INT DEFAULT 60,\n            last_sync_at TIMESTAMP NULL,\n            next_sync_at TIMESTAMP NULL,\n            status ENUM('ACTIVE', 'PAUSED', 'ERROR', 'NOT_STARTED') DEFAULT 'ACTIVE',\n            last_error_message TEXT,\n            last_export_count INT DEFAULT 0,\n            last_import_count INT DEFAULT 0,\n            total_exports INT DEFAULT 0,\n            total_imports INT DEFAULT 0,\n            created_by INT NOT NULL DEFAULT 1,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n            INDEX idx_status (status),\n            INDEX idx_next_sync_at (next_sync_at)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");

    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS google_sheets_sync_log (\n            id INT PRIMARY KEY AUTO_INCREMENT,\n            sync_config_id INT NOT NULL,\n            sync_direction ENUM('EXPORT', 'IMPORT', 'BIDIRECTIONAL') NOT NULL,\n            status ENUM('SUCCESS', 'FAILED', 'PARTIAL', 'SKIPPED') NOT NULL,\n            records_processed INT DEFAULT 0,\n            records_created INT DEFAULT 0,\n            records_updated INT DEFAULT 0,\n            records_deleted INT DEFAULT 0,\n            records_failed INT DEFAULT 0,\n            error_message TEXT,\n            error_details JSON,\n            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            completed_at TIMESTAMP NULL,\n            duration_seconds INT,\n            conflicts_detected INT DEFAULT 0,\n            conflicts_resolved INT DEFAULT 0,\n            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            INDEX idx_sync_config_id (sync_config_id),\n            INDEX idx_status (status),\n            INDEX idx_synced_at (synced_at)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
}

function getOrCreateSyncConfig(PDO $pdo, array $settings): ?array
{
    $sheetId = trim((string)($settings['sheet_id'] ?? ''));
    $sheetName = trim((string)($settings['sheet_name'] ?? 'Sheet1'));

    $stmt = $pdo->prepare("SELECT * FROM google_sheets_sync WHERE sheet_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$sheetId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($config) {
        return $config;
    }

    $userId = (int)($pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);

    $insert = $pdo->prepare("\n        INSERT INTO google_sheets_sync\n            (sheet_id, sheet_name, sync_direction, sync_interval_minutes, next_sync_at, status, created_by)\n        VALUES\n            (?, ?, 'BIDIRECTIONAL', 60, NOW(), 'ACTIVE', ?)\n    ");
    $insert->execute([$sheetId, $sheetName, $userId]);

    $id = (int)$pdo->lastInsertId();
    $read = $pdo->prepare("SELECT * FROM google_sheets_sync WHERE id = ?");
    $read->execute([$id]);
    return $read->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    $forceRun = in_array('--force-run', $argv ?? [], true);

    ensureGoogleSyncTables($pdo);

    $settingsModel = new SettingsModel($pdo);
    $settings = $settingsModel->getGoogleSheetsSettings();

    if ((int)($settings['enabled'] ?? 0) !== 1) {
        googleSyncCronLog('Google Sheets sync disabled in settings; skipping.');
        exit(0);
    }

    if (empty($settings['sheet_id']) || empty($settings['credentials'])) {
        googleSyncCronLog('Google Sheets settings incomplete (sheet_id or credentials missing); skipping.', 'WARN');
        exit(0);
    }

    $config = getOrCreateSyncConfig($pdo, $settings);
    if (!$config) {
        googleSyncCronLog('Failed to load or create sync configuration.', 'ERROR');
        exit(1);
    }

    if (($config['status'] ?? 'ACTIVE') === 'PAUSED' && !$forceRun) {
        googleSyncCronLog('Sync status is PAUSED; skipping.');
        exit(0);
    }

    if (!empty($config['next_sync_at']) && strtotime($config['next_sync_at']) > time() && !$forceRun) {
        googleSyncCronLog('Next sync not due yet; skipping.');
        exit(0);
    }

    if ($forceRun) {
        googleSyncCronLog('Force-run requested; bypassing schedule gate.', 'WARN');
    }

    $startedAt = microtime(true);
    $controller = new SettingsController($pdo);
    $syncResult = $controller->runGoogleSheetsSafeSyncForCron();

    $recordsCreated = (int)($syncResult['added_to_db'] ?? 0);
    $recordsUpdated = (int)($syncResult['updated_in_db'] ?? 0) + (int)($syncResult['updated_in_google'] ?? 0);
    $conflicts = (int)($syncResult['conflicts_detected'] ?? 0);
    $errors = $syncResult['errors'] ?? [];
    $duration = (int)round(microtime(true) - $startedAt);

    $status = 'SUCCESS';
    if (!empty($errors) && ($recordsCreated + $recordsUpdated) === 0) {
        $status = 'FAILED';
    } elseif (!empty($errors) || $conflicts > 0) {
        $status = 'PARTIAL';
    }

    $recordsProcessed = $recordsCreated + $recordsUpdated + (int)($syncResult['skipped_in_google'] ?? 0);
    $errorMessage = !empty($errors) ? implode(' | ', array_slice($errors, 0, 3)) : null;

    $logStmt = $pdo->prepare("\n        INSERT INTO google_sheets_sync_log\n            (sync_config_id, sync_direction, status, records_processed, records_created, records_updated, records_failed,\n             error_message, error_details, started_at, completed_at, duration_seconds, conflicts_detected, conflicts_resolved)\n        VALUES\n            (?, 'BIDIRECTIONAL', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)\n    ");
    $logStmt->execute([
        (int)$config['id'],
        $status,
        $recordsProcessed,
        $recordsCreated,
        $recordsUpdated,
        !empty($errors) ? count($errors) : 0,
        $errorMessage,
        json_encode($syncResult),
        $duration,
        $conflicts,
        (int)($syncResult['conflicts_resolved'] ?? 0),
    ]);

    $interval = max(1, (int)($config['sync_interval_minutes'] ?? 60));
    $updateStmt = $pdo->prepare("\n        UPDATE google_sheets_sync\n        SET\n            status = ?,\n            last_sync_at = NOW(),\n            next_sync_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),\n            last_error_message = ?,\n            last_import_count = ?,\n            last_export_count = ?,\n            total_imports = total_imports + ?,\n            total_exports = total_exports + ?\n        WHERE id = ?\n    ");

    $updateStmt->execute([
        $status === 'FAILED' ? 'ERROR' : 'ACTIVE',
        $interval,
        $errorMessage,
        (int)($syncResult['updated_in_db'] ?? 0) + $recordsCreated,
        (int)($syncResult['updated_in_google'] ?? 0),
        (int)($syncResult['updated_in_db'] ?? 0) + $recordsCreated,
        (int)($syncResult['updated_in_google'] ?? 0),
        (int)$config['id'],
    ]);

    googleSyncCronLog("Sync finished with status {$status}. Created={$recordsCreated}, Updated={$recordsUpdated}, Conflicts={$conflicts}");

    if ($status === 'FAILED') {
        exit(2);
    }

    exit(0);
} catch (Throwable $e) {
    googleSyncCronLog('Fatal sync cron error: ' . $e->getMessage(), 'ERROR');
    exit(1);
}
