<?php
require __DIR__ . '/config/bootstrap.php';

// Update both last activity and creation time
$_SESSION['LAST_ACTIVITY'] = time();
$_SESSION['CREATED'] = time(); // Reset creation time on activity

header('Content-Type: application/json');
echo json_encode(['success' => true]);