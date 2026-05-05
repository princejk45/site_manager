#!/usr/bin/env php
<?php
/**
 * Automation Rules Cron Job Runner
 * 
 * Execute automation rules for all websites.
 * Processes diagnostics and triggers actions.
 * 
 * Usage: php cli/run_automation_rules.php [--website-id=ID] [--dry-run] [--verbose]
 * 
 * Cron command (every 5 minutes):
 * 0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/php /path/to/cli/run_automation_rules.php >> /var/log/fullmidia-automation.log 2>&1
 */

// Suppress output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Parse arguments
$options = getopt('h', ['website-id:', 'dry-run', 'verbose', 'help']);

if (isset($options['h']) || isset($options['help'])) {
    printUsage();
    exit(0);
}

$websiteId = $options['website-id'] ?? null;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

// Bootstrap application
require_once __DIR__ . '/../config/bootstrap.php';

// Load required services
require_once __DIR__ . '/../services/Automation/RuleEngine.php';
require_once __DIR__ . '/../services/Automation/RuleExecutor.php';
require_once __DIR__ . '/../services/Email/NotificationService.php';
require_once __DIR__ . '/../services/Diagnostics/BugReportGenerator.php';
require_once __DIR__ . '/../services/Health/HealthScoreCalculator.php';
require_once __DIR__ . '/../models/Website.php';

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

// Initialize audit trail (static initialization)
AuditTrail::initialize($pdo, 0, 'automation-cli');

// Initialize services
$ruleEngine = new RuleEngine($pdo, null, 1);
$notificationService = new NotificationService($pdo);
$ruleExecutor = new RuleExecutor($pdo, $ruleEngine, $notificationService, null, 1);
$websiteModel = new Website($pdo);
$bugGenerator = new BugReportGenerator($pdo, null, 1);
$healthCalculator = new HealthScoreCalculator($pdo, null, 1);

// Run automation
runAutomation($pdo, $websiteId, $dryRun, $verbose);

/**
 * Run automation for websites
 */
function runAutomation(PDO $pdo, ?int $websiteId = null, bool $dryRun = false, bool $verbose = false): void {
    global $ruleEngine, $ruleExecutor, $websiteModel, $healthCalculator, $bugGenerator, $notificationService;
    
    logInfo('=== Fullmidia Automation Rules Engine Started ===');
    
    if ($dryRun) {
        logInfo('[DRY-RUN MODE] No changes will be made');
    }
    
    // Get websites to process
    $websites = getWebsitesToProcess($pdo, $websiteId);
    
    if (empty($websites)) {
        logInfo('No websites to process');
        return;
    }
    
    logInfo('Processing ' . count($websites) . ' website(s)');
    
    $totalRulesExecuted = 0;
    $totalActionsExecuted = 0;
    $totalErrors = 0;
    
    foreach ($websites as $website) {
        logInfo("Processing website: {$website['domain']} (ID: {$website['id']})");
        
        try {
            // Get latest diagnostics
            $metric = $healthCalculator->getLatestMetric($website['id']);
            
            if (!$metric) {
                logWarn("No recent diagnostics for {$website['domain']}, skipping");
                continue;
            }
            
            // Prepare diagnostic data
            $bugs = $bugGenerator->getActiveBugs($website['id']);
            $diagnosticData = array_merge($metric, [
                'active_bugs' => $bugs,
                'bugs_count' => count($bugs)
            ]);
            
            if ($verbose) {
                logInfo("Diagnostic data: Health={$metric['health_score']}, Bugs={$diagnosticData['bugs_count']}");
            }
            
            // Get active rules for this website
            $rules = $ruleEngine->getActiveRules($website['id']);
            
            if (empty($rules)) {
                if ($verbose) {
                    logInfo("No active rules for {$website['domain']}");
                }
                continue;
            }
            
            logInfo("Found " . count($rules) . " active rule(s)");
            
            // Execute each rule
            foreach ($rules as $rule) {
                try {
                    if ($dryRun) {
                        // Just check if rule would execute
                        $canExecute = $ruleEngine->canExecute($rule['id']);
                        $conditionsMet = $ruleEngine->evaluateConditions($rule, $diagnosticData);
                        
                        if ($canExecute['can_execute'] && $conditionsMet) {
                            logInfo("[DRY-RUN] Rule '{$rule['name']}' would execute");
                        }
                    } else {
                        // Actually execute the rule
                        $result = $ruleExecutor->executeRule($rule['id'], $website['id'], $diagnosticData);
                        
                        if ($result['status'] === 'SUCCESS') {
                            logInfo("✓ Rule '{$rule['name']}' executed successfully");
                        } elseif ($result['status'] === 'PARTIAL') {
                            logWarn("⚠ Rule '{$rule['name']}' executed with partial failures");
                        } elseif ($result['status'] === 'SKIPPED') {
                            if ($verbose) {
                                logInfo("- Rule '{$rule['name']}' skipped: {$result['result']['reason']}");
                            }
                        } else {
                            logError("✗ Rule '{$rule['name']}' failed");
                            $totalErrors++;
                        }
                        
                        $totalRulesExecuted++;
                        
                        if (isset($result['result']['actions'])) {
                            $totalActionsExecuted += count($result['result']['actions']);
                        }
                    }
                } catch (Exception $e) {
                    logError("Error executing rule '{$rule['name']}': " . $e->getMessage());
                    $totalErrors++;
                }
            }
            
        } catch (Exception $e) {
            logError("Error processing website {$website['domain']}: " . $e->getMessage());
            $totalErrors++;
        }
    }
    
    // Process notification queue
    if (!$dryRun) {
        logInfo('Processing notification queue...');
        $queueResults = $notificationService->processQueue(100);
        logInfo("Queue processing: {$queueResults['sent']} sent, {$queueResults['failed']} failed");
    }
    
    // Summary
    logInfo('=== Summary ===');
    logInfo("Rules executed: $totalRulesExecuted");
    logInfo("Actions executed: $totalActionsExecuted");
    logInfo("Errors: $totalErrors");
    logInfo('=== Automation Rules Engine Finished ===');
}

/**
 * Get websites to process
 */
function getWebsitesToProcess(PDO $pdo, ?int $websiteId = null): array {
    try {
        $query = 'SELECT id, domain FROM websites WHERE is_active = 1';
        $params = [];
        
        if ($websiteId) {
            $query .= ' AND id = ?';
            $params[] = $websiteId;
        }
        
        $query .= ' ORDER BY id';
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Failed to get websites: " . $e->getMessage());
        return [];
    }
}

/**
 * Log info message
 */
function logInfo(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] INFO: $message\n";
}

/**
 * Log warning message
 */
function logWarn(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] WARN: $message\n";
}

/**
 * Log error message
 */
function logError(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    fwrite(STDERR, "[{$timestamp}] ERROR: $message\n");
}

/**
 * Print usage information
 */
function printUsage() {
    echo <<<'USAGE'
Automation Rules Cron Job Runner

Usage: php cli/run_automation_rules.php [options]

Options:
  --website-id=ID    Process only specific website ID
  --dry-run          Simulate execution without making changes
  --verbose          Show detailed execution information
  -h, --help         Show this help message

Examples:
  # Run for all websites
  php cli/run_automation_rules.php
  
  # Run for specific website
  php cli/run_automation_rules.php --website-id=1
  
  # Simulate without making changes
  php cli/run_automation_rules.php --dry-run --verbose
  
  # Setup cron job (every 5 minutes)
  */5 * * * * /usr/bin/php /path/to/cli/run_automation_rules.php >> /var/log/fullmidia-automation.log 2>&1

USAGE;
}
?>
