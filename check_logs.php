<?php
// Check PHP error log location
$errorLogPath = ini_get('error_log');
$stderrPath = '/var/log/php-errors.log';
$xamppPath = '/Applications/XAMPP/logs/php_error.log';

echo "=== PHP Error Log Locations ===\n\n";

$paths = [
    'Configured error_log' => $errorLogPath,
    'Standard stderr' => $stderrPath,
    'XAMPP default' => $xamppPath,
    'System log' => '/var/log/system.log',
];

foreach ($paths as $name => $path) {
    echo "$name: $path\n";
    if (file_exists($path)) {
        echo "  ✓ EXISTS\n";
        $size = filesize($path);
        echo "  Size: " . $size . " bytes\n";
        if ($size > 0) {
            echo "  Last 20 lines:\n";
            $lines = array_slice(file($path), -20);
            foreach ($lines as $line) {
                echo "    " . trim($line) . "\n";
            }
        }
    } else {
        echo "  ✗ Not found\n";
    }
    echo "\n";
}

// Also check if we can find recent PHP files that might have written logs
echo "=== Checking common locations ===\n\n";
$commonPaths = [
    '/var/log/',
    '/tmp/',
    '/Applications/XAMPP/logs/',
    getenv('HOME') . '/Library/Logs/',
];

foreach ($commonPaths as $dir) {
    if (is_dir($dir)) {
        echo "Contents of $dir:\n";
        $files = array_slice(scandir($dir), 2);
        $phpFiles = array_filter($files, function($f) { 
            return strpos(strtolower($f), 'php') !== false || strpos(strtolower($f), 'error') !== false;
        });
        if (!empty($phpFiles)) {
            foreach ($phpFiles as $file) {
                echo "  - $file\n";
            }
        }
        echo "\n";
    }
}
?>
