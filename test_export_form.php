<?php
/**
 * Test form submission to see what data is being sent
 */

require_once __DIR__ . '/config/bootstrap.php';

// Get current settings
$settingsModel = new SettingsModel($pdo);
$googleSheetSettings = $settingsModel->getGoogleSheetsSettings();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Export Form</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h1>Test Google Sheets Export Form</h1>
    
    <div class="alert alert-info">
        <h4>Current Stored Settings:</h4>
        <p><strong>Sheet ID:</strong> <?= htmlspecialchars($googleSheetSettings['sheet_id'] ?? 'NOT SET') ?></p>
        <p><strong>Sheet Name:</strong> <?= htmlspecialchars($googleSheetSettings['sheet_name'] ?? 'NOT SET') ?></p>
        <p><strong>Enabled:</strong> <?= $googleSheetSettings['enabled'] ?? 0 ?></p>
        <p><strong>Credentials Length:</strong> <?= strlen($googleSheetSettings['credentials'] ?? '') ?> bytes</p>
    </div>
    
    <form method="post" action="index.php?action=settings&do=google_sheets">
        <div class="form-group">
            <label>Sheet ID:</label>
            <input type="text" name="google_sheet_id" class="form-control" value="<?= htmlspecialchars($googleSheetSettings['sheet_id'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>Sheet Name:</label>
            <input type="text" name="google_sheet_name" class="form-control" value="<?= htmlspecialchars($googleSheetSettings['sheet_name'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>Credentials (JSON):</label>
            <textarea name="google_credentials" class="form-control" rows="5"><?= htmlspecialchars($googleSheetSettings['credentials'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="google_sync_enabled" value="1" <?= ($googleSheetSettings['enabled'] ?? false) ? 'checked' : '' ?>>
                Enable Synchronization
            </label>
        </div>
        
        <div class="form-group">
            <button type="submit" name="export_to_google" class="btn btn-success">Export to Google Sheets</button>
            <a href="index.php?action=settings&do=advanced" class="btn btn-secondary">Back to Settings</a>
        </div>
    </form>
</div>
</body>
</html>
