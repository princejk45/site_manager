#!/bin/bash
# Cron Job Test Suite for Website Expiry Notifier
# Tests the enhanced cron script with various scenarios

SITE_PATH="/Applications/XAMPP/xamppfiles/htdocs/fullmidia/site_manager"
LOG_DIR="$SITE_PATH/logs"
TEST_DIR="/tmp/cron_tests"

mkdir -p "$TEST_DIR"

echo "=================================================="
echo "CRON JOB TESTING SUITE"
echo "=================================================="
echo ""

# Test 1: Dry-run mode
echo "[TEST 1] Dry-run Mode (no emails sent)"
echo "Command: php cron/expiry_notifier.php --dry-run"
cd "$SITE_PATH"
php cron/expiry_notifier.php --dry-run > "$TEST_DIR/test1_dry_run.log" 2>&1
TEST1_EXIT=$?
echo "Exit Code: $TEST1_EXIT"
echo "Lines processed: $(wc -l < "$TEST_DIR/test1_dry_run.log")"
SUMMARY=$(grep "Cron Job Summary" "$TEST_DIR/test1_dry_run.log" | tail -1)
echo "Result: $SUMMARY"
echo ""

# Test 2: Check database state after dry-run
echo "[TEST 2] Verify Dry-run Didn't Change Database"
php -r "
\$dsn = 'mysql:unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;dbname=website_manager';
\$pdo = new PDO(\$dsn, 'root', '');
\$count = \$pdo->query('SELECT COUNT(*) FROM website_notifications')->fetchColumn();
echo \"Notifications in DB: \$count\n\";
"
echo ""

# Test 3: Force mode (with dry-run to prevent actual sends)
echo "[TEST 3] Force Mode Test (--force --dry-run)"
echo "Command: php cron/expiry_notifier.php --force --dry-run"
php cron/expiry_notifier.php --force --dry-run > "$TEST_DIR/test3_force_mode.log" 2>&1
TEST3_EXIT=$?
echo "Exit Code: $TEST3_EXIT"
SUMMARY=$(grep "Cron Job Summary" "$TEST_DIR/test3_force_mode.log" | tail -1)
echo "Result: $SUMMARY"
echo ""

# Test 4: Check cron status
echo "[TEST 4] Cron Status Check"
php -r "
\$dsn = 'mysql:unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;dbname=website_manager';
\$pdo = new PDO(\$dsn, 'root', '');
\$row = \$pdo->query('SELECT * FROM cron_settings LIMIT 1')->fetch();
echo 'Status: ' . (\$row['is_active'] ? 'ENABLED' : 'DISABLED') . \"\n\";
echo 'Last Run: ' . (\$row['last_run'] ?? 'Never') . \"\n\";
"
echo ""

# Test 5: Log file persistence
echo "[TEST 5] Log File Persistence"
if [ -f "$LOG_DIR/cron-expiry.log" ]; then
    echo "✓ Log file exists: $LOG_DIR/cron-expiry.log"
    echo "Size: $(du -h "$LOG_DIR/cron-expiry.log" | cut -f1)"
    echo "Lines: $(wc -l < "$LOG_DIR/cron-expiry.log")"
else
    echo "✗ Log file not found"
fi
echo ""

echo "=================================================="
echo "TESTS COMPLETE"
echo "=================================================="
echo ""
echo "Test outputs saved to: $TEST_DIR"
echo "Cron logs saved to: $LOG_DIR"
echo ""
echo "Next Steps:"
echo "1. Set up actual OS-level cron job (see setup commands below)"
echo "2. Configure SMTP settings for email notifications"
echo "3. Monitor logs in: $LOG_DIR/cron-expiry.log"
echo ""
echo "=== CRONTAB SETUP COMMANDS ==="
echo "# Run every 3 hours"
echo "0 */3 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/fullmidia/site_manager/cron/expiry_notifier.php >> /tmp/cron-site-manager.log 2>&1"
echo ""
echo "# Run every day at 2 AM"
echo "0 2 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/fullmidia/site_manager/cron/expiry_notifier.php >> /tmp/cron-site-manager.log 2>&1"
