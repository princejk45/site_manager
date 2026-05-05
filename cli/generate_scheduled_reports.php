#!/usr/bin/env php
<?php
/**
 * Scheduled Report Generation Runner
 * 
 * Generate and email scheduled reports to users.
 * Run daily via cron.
 * 
 * Usage: php cli/generate_scheduled_reports.php [--user-id=ID] [--report-type=TYPE] [--dry-run]
 * 
 * Cron: 0 2 * * * /usr/bin/php /path/to/cli/generate_scheduled_reports.php >> /var/log/fullmidia-reports.log 2>&1
 */

ob_end_clean();

// Parse arguments
$options = getopt('h', ['user-id:', 'report-type:', 'dry-run', 'verbose', 'help']);

if (isset($options['h']) || isset($options['help'])) {
    printUsage();
    exit(0);
}

$userId = $options['user-id'] ?? null;
$reportType = $options['report-type'] ?? null;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

// Bootstrap
require_once __DIR__ . '/../config/bootstrap.php';

// Load required services
require_once __DIR__ . '/../services/Analytics/AnalyticsService.php';
require_once __DIR__ . '/../services/Analytics/ReportGenerator.php';
require_once __DIR__ . '/../services/Email/NotificationService.php';
require_once __DIR__ . '/../models/Website.php';
require_once __DIR__ . '/../models/User.php';

// Get database connection
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    logError("Database connection failed: " . $e->getMessage());
    exit(1);
}

// Initialize audit trail
AuditTrail::initialize($pdo, $userId ?? 0, 'scheduled-reports-cli');

// Initialize services with static AuditTrail
$analyticsService = new AnalyticsService($pdo, null, $userId ?? 1);
$reportGenerator = new ReportGenerator($pdo, $analyticsService, null, $userId ?? 1);
$notificationService = new NotificationService($pdo);
$websiteModel = new Website($pdo);
$userModel = new User($pdo);

// Run report generation
generateScheduledReports($pdo, $userId, $reportType, $dryRun, $verbose);

/**
 * Generate scheduled reports
 */
function generateScheduledReports(PDO $pdo, $userId = null, $reportType = null, $dryRun = false, $verbose = false) {
    global $reportGenerator, $notificationService, $userModel;
    
    logInfo('=== Scheduled Report Generation Started ===');
    
    if ($dryRun) {
        logInfo('[DRY-RUN MODE] No reports will be sent');
    }
    
    // Get users to generate reports for
    if ($userId) {
        $users = [$userModel->getById($userId)];
    } else {
        $users = $userModel->getAll();
    }
    
    if (empty($users)) {
        logInfo('No users found');
        return;
    }
    
    logInfo('Processing ' . count($users) . ' user(s)');
    
    $totalReports = 0;
    $totalSent = 0;
    $totalErrors = 0;
    
    $reportTypes = $reportType ? [$reportType] : ['portfolio_health', 'security', 'uptime', 'automation'];
    
    foreach ($users as $user) {
        if (!$user) continue;
        
        logInfo("Processing user: {$user['email']} (ID: {$user['id']})");
        
        foreach ($reportTypes as $type) {
            try {
                // Generate report
                $method = getReportMethod($type);
                
                if ($verbose) {
                    logInfo("Generating {$type} report for user {$user['id']}");
                }
                
                $report = $reportGenerator->$method($user['id'], 'month', 'html');
                
                if ($dryRun) {
                    logInfo("[DRY-RUN] Would send {$type} report to {$user['email']}");
                } else {
                    // Send report via email
                    $subject = ucfirst(str_replace('_', ' ', $type)) . " Report - " . date('Y-m-d');
                    $body = $report;
                    
                    if ($notificationService->sendEmail($user['email'], $subject, $body)) {
                        logInfo("✓ {$type} report sent to {$user['email']}");
                        $totalSent++;
                    } else {
                        logWarn("✗ Failed to send {$type} report to {$user['email']}");
                        $totalErrors++;
                    }
                }
                
                $totalReports++;
                
            } catch (Exception $e) {
                logError("Error generating {$type} report for user {$user['id']}: " . $e->getMessage());
                $totalErrors++;
            }
        }
    }
    
    // Summary
    logInfo('=== Summary ===');
    logInfo("Reports generated: $totalReports");
    logInfo("Reports sent: $totalSent");
    logInfo("Errors: $totalErrors");
    logInfo('=== Report Generation Finished ===');
}

/**
 * Get report method name
 */
function getReportMethod(string $type): string {
    $methods = [
        'portfolio_health' => 'generatePortfolioHealthReport',
        'security' => 'generateSecurityReport',
        'uptime' => 'generateUptimeReport',
        'automation' => 'generateAutomationReport'
    ];
    return $methods[$type] ?? 'generatePortfolioHealthReport';
}

/**
 * Log info
 */
function logInfo(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] INFO: $message\n";
}

/**
 * Log warning
 */
function logWarn(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] WARN: $message\n";
}

/**
 * Log error
 */
function logError(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    fwrite(STDERR, "[{$timestamp}] ERROR: $message\n");
}

/**
 * Print usage
 */
function printUsage() {
    echo <<<'USAGE'
Scheduled Report Generation Runner

Usage: php cli/generate_scheduled_reports.php [options]

Options:
  --user-id=ID        Generate reports for specific user ID
  --report-type=TYPE  Generate specific report type (portfolio_health, security, uptime, automation)
  --dry-run           Simulate without sending emails
  --verbose           Show detailed execution information
  -h, --help          Show this help message

Examples:
  # Generate all reports for all users
  php cli/generate_scheduled_reports.php
  
  # Generate portfolio health report for user 1
  php cli/generate_scheduled_reports.php --user-id=1 --report-type=portfolio_health
  
  # Dry-run with verbose output
  php cli/generate_scheduled_reports.php --dry-run --verbose
  
  # Setup cron job (daily at 2 AM)
  0 2 * * * /usr/bin/php /path/to/cli/generate_scheduled_reports.php >> /var/log/fullmidia-reports.log 2>&1

USAGE;
}
?>
