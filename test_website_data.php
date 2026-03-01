<?php
/**
 * Test script to check if website data is being prepared for Google Sheets
 */

require_once __DIR__ . '/config/bootstrap.php';

$websiteModel = new Website($pdo);
$preparedData = $websiteModel->prepareForGoogleSheets();

echo "Website Data Count: " . count($preparedData['data']) . "\n";
echo "Client Row Groups: " . count($preparedData['clientRows']) . "\n\n";

if (count($preparedData['data']) > 0) {
    echo "First row data:\n";
    print_r($preparedData['data'][0]);
} else {
    echo "NO DATA FOUND!\n";
    
    // Check if websites table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM websites");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total websites in database: " . $result['count'] . "\n";
}
?>
