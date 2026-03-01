<?php
// Debug script to test Google Sheets export issue

// Include bootstrap to set up the application
require_once __DIR__ . '/config/bootstrap.php';

// Check if we have settings
$settingsModel = new SettingsModel($pdo);
$settings = $settingsModel->getGoogleSheetsSettings();

echo "=== GOOGLE SHEETS SETTINGS ===\n";
echo "Sheet ID: '" . $settings['sheet_id'] . "'\n";
echo "Sheet Name: '" . $settings['sheet_name'] . "'\n";
echo "Sheet Name Length: " . strlen($settings['sheet_name']) . "\n";
echo "Sheet Name Bytes: " . bin2hex($settings['sheet_name']) . "\n";
echo "Enabled: " . $settings['enabled'] . "\n";
echo "Credentials Length: " . strlen($settings['credentials']) . "\n\n";

// Try to get Google Client
try {
    require_once APP_PATH . '/vendor/autoload.php';
    
    $client = new \Google\Client();
    $client->setApplicationName('Site Manager Debug');
    $client->setScopes([\Google\Service\Sheets::SPREADSHEETS]);
    
    $credentials = json_decode($settings['credentials'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "ERROR: Invalid JSON credentials\n";
        exit(1);
    }
    
    $client->setAuthConfig($credentials);
    $service = new \Google\Service\Sheets($client);
    
    echo "=== FETCHING SPREADSHEET ===\n";
    $spreadsheet = $service->spreadsheets->get($settings['sheet_id'], ['includeGridData' => true]);
    
    echo "Spreadsheet Title: " . $spreadsheet->getProperties()->getTitle() . "\n";
    echo "Spreadsheet ID: " . $spreadsheet->getSpreadsheetId() . "\n\n";
    
    echo "=== AVAILABLE SHEETS ===\n";
    $sheetCount = 0;
    foreach ($spreadsheet->getSheets() as $s) {
        $sheetCount++;
        $sheetTitle = $s->getProperties()->getTitle();
        $sheetId = $s->getProperties()->getSheetId();
        
        echo "Sheet $sheetCount:\n";
        echo "  Title: '" . $sheetTitle . "'\n";
        echo "  Title Length: " . strlen($sheetTitle) . "\n";
        echo "  Title Bytes: " . bin2hex($sheetTitle) . "\n";
        echo "  Sheet ID: $sheetId\n";
        
        // Check for match
        $matches = ($sheetTitle === $settings['sheet_name']);
        echo "  Matches Configured Sheet Name: " . ($matches ? "YES" : "NO") . "\n";
        
        // Try case-insensitive match
        $iMatches = (strtolower($sheetTitle) === strtolower($settings['sheet_name']));
        echo "  Case-Insensitive Match: " . ($iMatches ? "YES" : "NO") . "\n\n";
    }
    
    echo "=== RESULT ===\n";
    echo "Total sheets found: $sheetCount\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
