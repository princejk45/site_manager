<?php
/**
 * BugReportGenerator Service
 * 
 * Auto-analyzes WordPress diagnostics and generates bug reports with severity levels.
 * Integrates with WordPress REST API to fetch diagnostic data and stores findings in bug_reports_auto table.
 * 
 * @author Fullmidia
 * @version 1.0.0
 * @package Services\Diagnostics
 */

class BugReportGenerator {
    
    private PDO $pdo;
    private $auditTrail;
    private $userId;
    
    /**
     * Initialize BugReportGenerator with database connection
     * 
     * @param PDO $pdo Database connection
     * @param ?AuditTrail $auditTrail Audit trail service (null when using static initialization)
     * @param int $userId Current user ID for logging
     */
    public function __construct(PDO $pdo, ?AuditTrail $auditTrail, int $userId = 1) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Generate bug reports for a specific website
     * Analyzes WordPress diagnostics and creates bug_reports_auto entries
     * 
     * @param int $websiteId Website ID to analyze
     * @param array $diagnosticsData Raw diagnostics from WordPress API
     * @return array Generated bug reports with details
     */
    public function generateReports($websiteId, $diagnosticsData) {
        $generatedBugs = [];
        
        // Analyze each diagnostic category
        $bugsByCategory = [
            'performance' => $this->analyzePerformance($diagnosticsData),
            'security' => $this->analyzeSecurity($diagnosticsData),
            'plugins' => $this->analyzePlugins($diagnosticsData),
            'backup' => $this->analyzeBackup($diagnosticsData),
            'database' => $this->analyzeDatabase($diagnosticsData),
            'ssl' => $this->analyzeSSL($diagnosticsData),
        ];
        
        // Process each detected bug
        foreach ($bugsByCategory as $category => $bugs) {
            foreach ($bugs as $bug) {
                $bugId = $this->storeBugReport($websiteId, $category, $bug);
                if ($bugId) {
                    $generatedBugs[] = $bugId;
                    if ($this->auditTrail !== null) {
                        $this->auditTrail->log(
                            $this->userId,
                            'bug_report_auto_generated',
                            'bug_report',
                            $bugId,
                            ['category' => $category, 'severity' => $bug['severity']]
                        );
                    }
                }
            }
        }
        
        return $generatedBugs;
    }
    
    /**
     * Analyze performance metrics
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return array Performance issues found
     */
    private function analyzePerformance($diagnosticsData) {
        $issues = [];
        
        // Page load time analysis
        if (isset($diagnosticsData['performance'])) {
            $perf = $diagnosticsData['performance'];
            
            if ($perf['page_load_time'] > 3000) {
                $issues[] = [
                    'title' => 'Slow Page Load Time Detected',
                    'description' => sprintf(
                        'Page load time is %dms (target: <3000ms). This impacts user experience and SEO.',
                        $perf['page_load_time']
                    ),
                    'severity' => $perf['page_load_time'] > 5000 ? 'CRITICAL' : 'HIGH',
                    'suggested_fix' => 'Enable caching, optimize images, reduce plugin load'
                ];
            }
            
            // Memory usage analysis
            if (isset($perf['memory_usage_percent']) && $perf['memory_usage_percent'] > 80) {
                $issues[] = [
                    'title' => 'High Memory Usage',
                    'description' => sprintf(
                        'Memory usage at %d%%. Consider removing unnecessary plugins.',
                        $perf['memory_usage_percent']
                    ),
                    'severity' => $perf['memory_usage_percent'] > 95 ? 'CRITICAL' : 'HIGH',
                    'suggested_fix' => 'Review active plugins and disable unused ones'
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * Analyze security vulnerabilities
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return array Security issues found
     */
    private function analyzeSecurity($diagnosticsData) {
        $issues = [];
        
        if (isset($diagnosticsData['security'])) {
            $sec = $diagnosticsData['security'];
            
            // WordPress version outdated
            if (isset($sec['wp_version_outdated']) && $sec['wp_version_outdated']) {
                $issues[] = [
                    'title' => 'WordPress Update Available',
                    'description' => sprintf(
                        'WordPress %s available. Current: %s. Updates include important security fixes.',
                        $sec['latest_wp_version'] ?? 'unknown',
                        $sec['current_wp_version'] ?? 'unknown'
                    ),
                    'severity' => 'HIGH',
                    'suggested_fix' => 'Update WordPress core immediately'
                ];
            }
            
            // SSL certificate issues
            if (isset($sec['ssl_certificate']) && !$sec['ssl_certificate']['valid']) {
                $issues[] = [
                    'title' => 'SSL Certificate Problem',
                    'description' => 'SSL certificate is invalid or expired. Visitors will see security warnings.',
                    'severity' => 'CRITICAL',
                    'suggested_fix' => 'Renew or fix SSL certificate configuration'
                ];
            }
            
            // Debug mode enabled
            if (isset($sec['debug_mode']) && $sec['debug_mode']) {
                $issues[] = [
                    'title' => 'Debug Mode Enabled',
                    'description' => 'WordPress debug mode is active. This exposes sensitive information.',
                    'severity' => 'MEDIUM',
                    'suggested_fix' => 'Set WP_DEBUG to false in wp-config.php'
                ];
            }
            
            // File permissions issues
            if (isset($sec['file_permissions_issues']) && count($sec['file_permissions_issues']) > 0) {
                $issues[] = [
                    'title' => 'Incorrect File Permissions',
                    'description' => sprintf(
                        '%d files have incorrect permissions. Could be exploited by attackers.',
                        count($sec['file_permissions_issues'])
                    ),
                    'severity' => 'HIGH',
                    'suggested_fix' => 'Review and correct file permissions (wp-config.php should be 644)'
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * Analyze plugin health
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return array Plugin issues found
     */
    private function analyzePlugins($diagnosticsData) {
        $issues = [];
        
        if (isset($diagnosticsData['plugins'])) {
            $plugins = $diagnosticsData['plugins'];
            
            // Outdated plugins
            $outdatedCount = 0;
            $criticalUpdates = [];
            
            foreach ($plugins as $plugin) {
                if (isset($plugin['outdated']) && $plugin['outdated']) {
                    $outdatedCount++;
                    if (isset($plugin['security_update']) && $plugin['security_update']) {
                        $criticalUpdates[] = $plugin['name'];
                    }
                }
            }
            
            if ($criticalUpdates) {
                $issues[] = [
                    'title' => 'Critical Plugin Updates Available',
                    'description' => sprintf(
                        'Plugins with security updates: %s. Update immediately.',
                        implode(', ', array_slice($criticalUpdates, 0, 3))
                    ),
                    'severity' => 'CRITICAL',
                    'suggested_fix' => 'Update all plugins with security updates'
                ];
            } elseif ($outdatedCount > 0) {
                $issues[] = [
                    'title' => sprintf('%d Plugins Need Updates', $outdatedCount),
                    'description' => 'Several plugins have updates available. These may include bug fixes and improvements.',
                    'severity' => $outdatedCount > 5 ? 'HIGH' : 'MEDIUM',
                    'suggested_fix' => 'Review and update all available plugin updates'
                ];
            }
            
            // Inactive plugins
            $inactiveCount = array_reduce($plugins, function($count, $p) {
                return $count + (isset($p['active']) && !$p['active'] ? 1 : 0);
            }, 0);
            
            if ($inactiveCount > 3) {
                $issues[] = [
                    'title' => sprintf('%d Inactive Plugins', $inactiveCount),
                    'description' => 'Having many inactive plugins can slow down your site. Consider removing unused ones.',
                    'severity' => 'LOW',
                    'suggested_fix' => 'Remove or delete inactive plugins'
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * Analyze backup status
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return array Backup issues found
     */
    private function analyzeBackup($diagnosticsData) {
        $issues = [];
        
        if (isset($diagnosticsData['backups'])) {
            $backups = $diagnosticsData['backups'];
            
            // No backups configured
            if (!isset($backups['backup_enabled']) || !$backups['backup_enabled']) {
                $issues[] = [
                    'title' => 'No Backups Configured',
                    'description' => 'This site has no automatic backups. You\'re at risk of permanent data loss.',
                    'severity' => 'CRITICAL',
                    'suggested_fix' => 'Enable automatic backups (daily recommended)'
                ];
            }
            
            // Last backup outdated
            if (isset($backups['last_backup_time'])) {
                $lastBackup = strtotime($backups['last_backup_time']);
                $daysSince = (time() - $lastBackup) / 86400;
                
                if ($daysSince > 7) {
                    $issues[] = [
                        'title' => sprintf('Last Backup: %d days ago', ceil($daysSince)),
                        'description' => 'Your most recent backup is outdated. Data changes since then may not be recoverable.',
                        'severity' => $daysSince > 30 ? 'CRITICAL' : 'HIGH',
                        'suggested_fix' => 'Run a backup immediately'
                    ];
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Analyze database health
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return array Database issues found
     */
    private function analyzeDatabase($diagnosticsData) {
        $issues = [];
        
        if (isset($diagnosticsData['database'])) {
            $db = $diagnosticsData['database'];
            
            // Database needs optimization
            if (isset($db['tables_need_repair']) && count($db['tables_need_repair']) > 0) {
                $issues[] = [
                    'title' => 'Database Tables Need Repair',
                    'description' => sprintf(
                        '%d tables are corrupted and need repair. Site stability may be affected.',
                        count($db['tables_need_repair'])
                    ),
                    'severity' => 'CRITICAL',
                    'suggested_fix' => 'Run database repair through WordPress Admin > Tools'
                ];
            }
            
            // Database optimization
            if (isset($db['optimization_score']) && $db['optimization_score'] < 70) {
                $issues[] = [
                    'title' => 'Database Could Be Optimized',
                    'description' => sprintf(
                        'Database optimization score: %d%%. Optimization could improve performance.',
                        $db['optimization_score']
                    ),
                    'severity' => 'MEDIUM',
                    'suggested_fix' => 'Run database optimization (removes unused data, fragments)'
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * Analyze SSL/HTTPS configuration
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return array SSL issues found
     */
    private function analyzeSSL($diagnosticsData) {
        $issues = [];
        
        if (isset($diagnosticsData['ssl'])) {
            $ssl = $diagnosticsData['ssl'];
            
            // No HTTPS
            if (!isset($ssl['https_enabled']) || !$ssl['https_enabled']) {
                $issues[] = [
                    'title' => 'HTTPS Not Enabled',
                    'description' => 'Your site is not using HTTPS. Visitor data is not encrypted and SEO may suffer.',
                    'severity' => 'HIGH',
                    'suggested_fix' => 'Install and enable SSL certificate'
                ];
            }
            
            // Mixed content
            if (isset($ssl['mixed_content_detected']) && $ssl['mixed_content_detected']) {
                $issues[] = [
                    'title' => 'Mixed Content Detected',
                    'description' => 'Your site loads both HTTPS and HTTP resources. Some content may be blocked.',
                    'severity' => 'MEDIUM',
                    'suggested_fix' => 'Update all URLs to use HTTPS'
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * Store bug report in database
     * 
     * @param int $websiteId Website ID
     * @param string $category Issue category
     * @param array $bug Bug details
     * @return int Bug report ID or false
     */
    private function storeBugReport($websiteId, $category, $bug) {
        try {
            // Check if similar bug already exists (avoid duplicates)
            $stmt = $this->pdo->prepare("
                SELECT id FROM bug_reports_auto 
                WHERE website_id = ? AND title = ? AND status != 'RESOLVED'
                LIMIT 1
            ");
            $stmt->execute([$websiteId, $bug['title']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing bug report
                $stmt = $this->pdo->prepare("
                    UPDATE bug_reports_auto SET
                        detected_count = detected_count + 1,
                        last_detected = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$existing['id']]);
                
                return $existing['id'];
            }
            
            // Create new bug report
            $stmt = $this->pdo->prepare("
                INSERT INTO bug_reports_auto (
                    website_id, title, description, severity, category,
                    suggested_fix, detected_count, last_detected, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            
            $stmt->execute([
                $websiteId,
                $bug['title'],
                $bug['description'],
                $bug['severity'],
                $category,
                $bug['suggested_fix']
            ]);
            
            $bugId = $this->pdo->lastInsertId();
            
            // Log history entry
            $historyStmt = $this->pdo->prepare("
                INSERT INTO bug_report_history (
                    bug_report_id, action, status, created_at
                ) VALUES (?, 'DETECTED', 'OPEN', NOW())
            ");
            $historyStmt->execute([$bugId]);
            
            return $bugId;
            
        } catch (PDOException $e) {
            error_log("Bug report storage error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Resolve/close a bug report
     * 
     * @param int $bugId Bug report ID
     * @param string $reason Resolution reason
     * @return bool Success
     */
    public function resolveBug($bugId, $reason = 'MANUAL_RESOLUTION') {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE bug_reports_auto SET
                    status = 'RESOLVED',
                    resolved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$bugId]);
            
            $historyStmt = $this->pdo->prepare("
                INSERT INTO bug_report_history (
                    bug_report_id, action, status, notes, created_at
                ) VALUES (?, ?, 'RESOLVED', ?, NOW())
            ");
            $historyStmt->execute([$bugId, 'RESOLVED', $reason]);
            
            if ($this->auditTrail !== null) {
                $this->auditTrail->log(
                    $this->userId,
                    'bug_report_resolved',
                    'bug_report',
                    $bugId,
                    ['reason' => $reason]
                );
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Bug resolution error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all active bugs for a website
     * 
     * @param int $websiteId Website ID
     * @param string $severity Filter by severity (null = all)
     * @return array Bug reports
     */
    public function getActiveBugs($websiteId, $severity = null) {
        try {
            $query = "
                SELECT * FROM bug_reports_auto
                WHERE website_id = ? AND status = 'OPEN'
            ";
            $params = [$websiteId];
            
            if ($severity) {
                $query .= " AND severity = ?";
                $params[] = $severity;
            }
            
            $query .= " ORDER BY 
                CASE severity 
                    WHEN 'CRITICAL' THEN 1
                    WHEN 'HIGH' THEN 2
                    WHEN 'MEDIUM' THEN 3
                    WHEN 'LOW' THEN 4
                END, last_detected DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get bugs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get bug severity counts for a website
     * 
     * @param int $websiteId Website ID
     * @return array Severity distribution
     */
    public function getSeveritySummary($websiteId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT severity, COUNT(*) as count
                FROM bug_reports_auto
                WHERE website_id = ? AND status = 'OPEN'
                GROUP BY severity
            ");
            $stmt->execute([$websiteId]);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $summary = [
                'CRITICAL' => 0,
                'HIGH' => 0,
                'MEDIUM' => 0,
                'LOW' => 0
            ];
            
            foreach ($results as $row) {
                $summary[$row['severity']] = $row['count'];
            }
            
            return $summary;
        } catch (PDOException $e) {
            error_log("Get severity summary error: " . $e->getMessage());
            return [];
        }
    }
}
?>
