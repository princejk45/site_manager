<?php
$dbConfig = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'website_manager',
    'charset' => 'utf8mb4',
    'socket' => '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'
];

try {
    // Try with socket first (for CLI), then fallback to host (for web)
    $dsn = "mysql:unix_socket={$dbConfig['socket']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    
    try {
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    } catch (PDOException $e) {
        // Fallback to host-based connection if socket fails
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    }
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
