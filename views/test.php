<?php
echo "TEST OUTPUT - HEADER SHOULD APPEAR ABOVE THIS";
include APP_PATH . '/includes/header.php';
echo "TEST OUTPUT AFTER HEADER";
include APP_PATH . '/includes/sidebar.php';
echo "TEST OUTPUT AFTER SIDEBAR";
?>
<h1>Test Page</h1>
<?php include APP_PATH . '/includes/footer.php'; ?>