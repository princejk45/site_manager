<?php
/**
 * HealthScoreCalculator Service
 * 
 * Calculates a 0-100 health score for WordPress sites based on multiple metrics.
 * Uses weighted algorithm: Uptime 30% + Security 25% + Performance 20% + Plugins 15% + Backups 10%
 * 
 * @author Fullmidia
 * @version 1.0.0
 * @package Services\Health
 */

class HealthScoreCalculator {
    
    private PDO $pdo;
    private $auditTrail;
    private $userId;
    
    // Weight configuration for health score calculation
    const WEIGHT_UPTIME = 0.30;
    const WEIGHT_SECURITY = 0.25;
    const WEIGHT_PERFORMANCE = 0.20;
    const WEIGHT_PLUGINS = 0.15;
    const WEIGHT_BACKUP = 0.10;
    
    /**
     * Initialize HealthScoreCalculator
     * 
     * @param PDO $pdo Database connection
     * @param ?AuditTrail $auditTrail Audit trail service (null when using static initialization)
     * @param int $userId User ID
     */
    public function __construct(PDO $pdo, ?AuditTrail $auditTrail, int $userId = 1) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Calculate overall health score for a website
     * 
     * @param int $websiteId Website ID
     * @param array $diagnosticsData Raw diagnostics from WordPress
     * @return array Health metrics with score (0-100) and component breakdown
     */
    public function calculateScore($websiteId, $diagnosticsData) {
        // Calculate individual component scores
        $uptimeScore = $this->scoreUptime($diagnosticsData);
        $securityScore = $this->scoreSecurity($diagnosticsData);
        $performanceScore = $this->scorePerformance($diagnosticsData);
        $pluginsScore = $this->scorePlugins($diagnosticsData);
        $backupScore = $this->scoreBackup($diagnosticsData);
        
        // Calculate weighted overall score
        $overallScore = (
            ($uptimeScore * self::WEIGHT_UPTIME) +
            ($securityScore * self::WEIGHT_SECURITY) +
            ($performanceScore * self::WEIGHT_PERFORMANCE) +
            ($pluginsScore * self::WEIGHT_PLUGINS) +
            ($backupScore * self::WEIGHT_BACKUP)
        );
        
        // Ensure score is within 0-100 range
        $overallScore = max(0, min(100, round($overallScore, 1)));
        
        $metrics = [
            'website_id' => $websiteId,
            'overall_score' => $overallScore,
            'grade' => $this->scoreToGrade($overallScore),
            'status' => $this->scoreToStatus($overallScore),
            'components' => [
                'uptime' => [
                    'score' => $uptimeScore,
                    'weight' => self::WEIGHT_UPTIME * 100,
                    'icon' => '📡'
                ],
                'security' => [
                    'score' => $securityScore,
                    'weight' => self::WEIGHT_SECURITY * 100,
                    'icon' => '🔒'
                ],
                'performance' => [
                    'score' => $performanceScore,
                    'weight' => self::WEIGHT_PERFORMANCE * 100,
                    'icon' => '⚡'
                ],
                'plugins' => [
                    'score' => $pluginsScore,
                    'weight' => self::WEIGHT_PLUGINS * 100,
                    'icon' => '📦'
                ],
                'backup' => [
                    'score' => $backupScore,
                    'weight' => self::WEIGHT_BACKUP * 100,
                    'icon' => '💾'
                ]
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Store metrics in database — attach raw diagnostics so storeHealthMetrics
        // can persist detail columns (ssl, uptime %, response time, etc.)
        $metrics['_raw'] = $diagnosticsData;
        $this->storeHealthMetrics($websiteId, $metrics);
        unset($metrics['_raw']); // don't leak internal key to callers
        
        return $metrics;
    }
    
    /**
     * Score uptime metric (0-100)
     * Penalizes downtime, rewards high availability
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return float Uptime score
     */
    private function scoreUptime($diagnosticsData) {
        if (!isset($diagnosticsData['uptime'])) {
            return 50; // Default if no data
        }
        
        $uptime = $diagnosticsData['uptime'];
        
        // Get uptime percentage (e.g., 99.9)
        $uptimePercent = $uptime['uptime_percent'] ?? 99.0;
        
        // Score calculation:
        // 99%+ = 100
        // 95-98.9% = 80-99
        // 90-94.9% = 60-79
        // < 90% = proportional decline
        
        if ($uptimePercent >= 99.9) {
            return 100;
        } elseif ($uptimePercent >= 99.0) {
            return 95 + (($uptimePercent - 99.0) / 0.9 * 5); // 95-100
        } elseif ($uptimePercent >= 95.0) {
            return 80 + (($uptimePercent - 95.0) / 4.0 * 15); // 80-95
        } else {
            return max(0, ($uptimePercent / 95.0) * 80); // 0-80
        }
    }
    
    /**
     * Score security posture (0-100)
     * Evaluates SSL, updates, permissions, debug mode
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return float Security score
     */
    private function scoreSecurity($diagnosticsData) {
        $score = 100;
        
        if (!isset($diagnosticsData['security'])) {
            return 50;
        }
        
        $security = $diagnosticsData['security'];
        
        // SSL/HTTPS check (-20 points if missing)
        if (!isset($security['ssl_certificate']['valid']) || !$security['ssl_certificate']['valid']) {
            $score -= 20;
        }
        
        // WordPress core update check (-15 points if outdated)
        if (isset($security['wp_version_outdated']) && $security['wp_version_outdated']) {
            $score -= 15;
        }
        
        // Debug mode enabled (-10 points)
        if (isset($security['debug_mode']) && $security['debug_mode']) {
            $score -= 10;
        }
        
        // File permissions issues (-5 per issue, max -20)
        if (isset($security['file_permissions_issues'])) {
            $permIssues = min(4, count($security['file_permissions_issues']));
            $score -= ($permIssues * 5);
        }
        
        // Admin users with weak passwords (-10 points)
        if (isset($security['weak_admin_passwords']) && $security['weak_admin_passwords']) {
            $score -= 10;
        }
        
        // Known vulnerabilities (-20 per vulnerability)
        if (isset($security['known_vulnerabilities'])) {
            $vulnCount = min(3, count($security['known_vulnerabilities']));
            $score -= ($vulnCount * 20);
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Score performance metrics (0-100)
     * Evaluates page load time, memory usage, database health
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return float Performance score
     */
    private function scorePerformance($diagnosticsData) {
        $score = 100;
        
        if (!isset($diagnosticsData['performance'])) {
            return 50;
        }
        
        $perf = $diagnosticsData['performance'];
        
        // Page load time scoring
        // < 1s = 100, 1-2s = 80, 2-3s = 60, > 3s = 40, > 5s = 20
        if (isset($perf['page_load_time'])) {
            $loadTime = $perf['page_load_time'] / 1000; // Convert to seconds
            
            if ($loadTime < 1) {
                // Good performance
            } elseif ($loadTime < 2) {
                $score -= 20;
            } elseif ($loadTime < 3) {
                $score -= 40;
            } elseif ($loadTime < 5) {
                $score -= 60;
            } else {
                $score -= 80;
            }
        }
        
        // Memory usage scoring (-10 per 10% above 60%)
        if (isset($perf['memory_usage_percent'])) {
            $mem = $perf['memory_usage_percent'];
            if ($mem > 60) {
                $overagePercent = $mem - 60;
                $score -= min(30, ($overagePercent / 10) * 10);
            }
        }
        
        // Database fragmentation (-15 if high)
        if (isset($perf['database_fragmentation']) && $perf['database_fragmentation'] > 30) {
            $score -= 15;
        }
        
        // Query performance (-10 if slow queries exist)
        if (isset($perf['slow_queries_detected']) && $perf['slow_queries_detected']) {
            $score -= 10;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Score plugin health (0-100)
     * Evaluates outdated plugins, conflicts, inactive plugins
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return float Plugins score
     */
    private function scorePlugins($diagnosticsData) {
        $score = 100;
        
        if (!isset($diagnosticsData['plugins'])) {
            return 50;
        }
        
        $plugins = $diagnosticsData['plugins'];
        
        $totalPlugins = count($plugins);
        $outdatedPlugins = 0;
        $inactivePlugins = 0;
        $criticalUpdates = 0;
        
        foreach ($plugins as $plugin) {
            if (isset($plugin['outdated']) && $plugin['outdated']) {
                $outdatedPlugins++;
                if (isset($plugin['security_update']) && $plugin['security_update']) {
                    $criticalUpdates++;
                }
            }
            
            if (isset($plugin['active']) && !$plugin['active']) {
                $inactivePlugins++;
            }
        }
        
        // Critical security updates: -20 per update
        $score -= min(40, $criticalUpdates * 20);
        
        // Other outdated plugins: -2 per 10% of total plugins
        $outdatedPercent = ($outdatedPlugins / max(1, $totalPlugins)) * 100;
        $score -= min(20, ($outdatedPercent / 10) * 2);
        
        // Inactive plugins: -1 per plugin (max -10)
        $score -= min(10, $inactivePlugins);
        
        // Plugin conflicts: -15 per conflict
        if (isset($diagnosticsData['plugin_conflicts'])) {
            $conflictCount = min(2, count($diagnosticsData['plugin_conflicts']));
            $score -= ($conflictCount * 15);
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Score backup configuration (0-100)
     * Evaluates backup frequency, last backup time, backup integrity
     * 
     * @param array $diagnosticsData Diagnostics data
     * @return float Backup score
     */
    private function scoreBackup($diagnosticsData) {
        $score = 100;
        
        if (!isset($diagnosticsData['backups'])) {
            return 0; // Critical: no backup info
        }
        
        $backups = $diagnosticsData['backups'];
        
        // No backups enabled: -100 (critical failure)
        if (!isset($backups['backup_enabled']) || !$backups['backup_enabled']) {
            return 0;
        }
        
        // Last backup time evaluation
        if (isset($backups['last_backup_time'])) {
            $lastBackup = strtotime($backups['last_backup_time']);
            $daysSince = (time() - $lastBackup) / 86400;
            
            if ($daysSince > 30) {
                // More than 30 days: critical
                $score -= 80;
            } elseif ($daysSince > 7) {
                // More than 7 days: high penalty
                $score -= 40;
            } elseif ($daysSince > 1) {
                // More than 1 day: medium penalty
                $score -= 10;
            }
            // Less than 1 day: no penalty
        }
        
        // Backup integrity (-20 if failed backups exist)
        if (isset($backups['failed_backups']) && $backups['failed_backups'] > 0) {
            $score -= 20;
        }
        
        // Multiple backup locations (+5 if yes)
        if (isset($backups['multiple_locations']) && $backups['multiple_locations']) {
            $score = min(100, $score + 5);
        }
        
        // Backup encryption (+5 if yes)
        if (isset($backups['encrypted']) && $backups['encrypted']) {
            $score = min(100, $score + 5);
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Convert numeric score to letter grade
     * 
     * @param float $score Numeric score (0-100)
     * @return string Letter grade (A, B, C, D, F)
     */
    private function scoreToGrade($score) {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
    
    /**
     * Convert numeric score to status
     * 
     * @param float $score Numeric score (0-100)
     * @return string Status (Excellent, Good, Fair, Poor, Critical)
     */
    private function scoreToStatus($score) {
        if ($score >= 90) return 'Excellent';
        if ($score >= 75) return 'Good';
        if ($score >= 60) return 'Fair';
        if ($score >= 40) return 'Poor';
        return 'Critical';
    }
    
    /**
     * Store health metrics in database
     * 
     * @param int $websiteId Website ID
     * @param array $metrics Health metrics
     * @return bool Success
     */
    private function storeHealthMetrics($websiteId, $metrics) {
        try {
            // Pull raw detail fields that were attached by calculateScore()
            $d = $metrics['_raw'] ?? [];

            $uptimePercent       = $d['uptime']['uptime_percent']      ?? null;
            $responseTimeMs      = $d['uptime']['response_time_ms']    ?? null;
            $pageLoadMs          = $d['performance']['page_load_time'] ?? null;
            $sslValid            = isset($d['security']['ssl_certificate']['valid'])
                                       ? (int)(bool)$d['security']['ssl_certificate']['valid'] : null;
            $sslExpiryDays       = $d['security']['ssl_certificate']['expiry_days'] ?? null;
            $securityIssuesCount = isset($d['security'])
                ? (count($d['security']['known_vulnerabilities']   ?? [])
                 + count($d['security']['file_permissions_issues'] ?? [])
                 + (($d['security']['wp_config_writable'] ?? false) ? 1 : 0)
                 + (($d['security']['xmlrpc_enabled']     ?? false) ? 1 : 0)
                 + (($d['security']['default_admin_user'] ?? false) ? 1 : 0))
                : null;
            $outdatedPlugins     = isset($d['plugins'])
                ? count(array_filter($d['plugins'], fn($p) => $p['outdated'] ?? false))
                : null;

            // Map overall score to uptime_status ENUM
            $overallScore = $metrics['overall_score'];
            $uptimeStatus = $overallScore >= 90 ? 'EXCELLENT'
                          : ($overallScore >= 75 ? 'GOOD'
                          : ($overallScore >= 50 ? 'WARNING' : 'CRITICAL'));

            $stmt = $this->pdo->prepare("
                INSERT INTO health_metrics (
                    website_id,
                    health_score,
                    security_score,
                    performance_score,
                    backup_freshness_score,
                    plugin_status_score,
                    uptime_percent,
                    uptime_status,
                    average_response_time_ms,
                    page_load_time_ms,
                    ssl_valid,
                    ssl_expiry_days,
                    security_issues_count,
                    outdated_plugins,
                    recorded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $websiteId,
                $metrics['overall_score'],
                $metrics['components']['security']['score'],
                $metrics['components']['performance']['score'],
                $metrics['components']['backup']['score'],
                $metrics['components']['plugins']['score'],
                $uptimePercent,
                $uptimeStatus,
                $responseTimeMs,
                $pageLoadMs,
                $sslValid,
                $sslExpiryDays,
                $securityIssuesCount,
                $outdatedPlugins,
            ]);
            
            if ($this->auditTrail !== null) {
                $this->auditTrail->log(
                    $this->userId,
                    'health_metrics_calculated',
                    'website',
                    $websiteId,
                    ['score' => $metrics['overall_score'], 'grade' => $metrics['grade']]
                );
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Health metrics storage error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get health metrics for a website
     * 
     * @param int $websiteId Website ID
     * @param int $limit Number of records to fetch
     * @return array Health metrics records (newest first)
     */
    public function getMetrics($websiteId, $limit = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM health_metrics
                WHERE website_id = ?
                ORDER BY recorded_at DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $websiteId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get metrics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get latest health metric for a website
     * 
     * @param int $websiteId Website ID
     * @return array Latest metric or empty array
     */
    public function getLatestMetric($websiteId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM health_metrics
                WHERE website_id = ?
                ORDER BY recorded_at DESC
                LIMIT 1
            ");
            $stmt->execute([$websiteId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: [];
        } catch (PDOException $e) {
            error_log("Get latest metric error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate trend (improvement or decline)
     * Compares latest score with average of previous scores
     * 
     * @param int $websiteId Website ID
     * @return array Trend data with direction and percentage change
     */
    public function calculateTrend($websiteId) {
        try {
            $metrics = $this->getMetrics($websiteId, 2);
            
            if (count($metrics) < 2) {
                return [
                    'trend' => 'STABLE',
                    'direction' => '→',
                    'change_percent' => 0,
                    'message' => 'Insufficient data'
                ];
            }
            
            $latest = $metrics[0]['health_score'];
            $previous = $metrics[1]['health_score'];
            $change = $latest - $previous;
            $changePercent = round(($change / max(1, $previous)) * 100, 1);
            
            $trend = 'STABLE';
            $direction = '→';
            
            if ($change > 2) {
                $trend = 'IMPROVING';
                $direction = '📈';
            } elseif ($change < -2) {
                $trend = 'DECLINING';
                $direction = '📉';
            }
            
            return [
                'trend' => $trend,
                'direction' => $direction,
                'change_percent' => $changePercent,
                'change_points' => $change,
                'message' => sprintf(
                    'Score %s %s points (%s%s)',
                    $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'remained'),
                    abs($change),
                    $changePercent > 0 ? '+' : '',
                    $changePercent
                )
            ];
        } catch (Exception $e) {
            error_log("Calculate trend error: " . $e->getMessage());
            return [
                'trend' => 'ERROR',
                'direction' => '⚠️',
                'change_percent' => 0,
                'message' => 'Error calculating trend'
            ];
        }
    }
    
    /**
     * Get recommendations based on lowest scoring components
     * 
     * @param int $websiteId Website ID
     * @return array Recommendations prioritized by impact
     */
    public function getRecommendations($websiteId) {
        $metric = $this->getLatestMetric($websiteId);
        
        if (empty($metric)) {
            return [];
        }
        
        $recommendations = [];
        
        // Score components and sort by impact
        // Column names match health_metrics table schema
        $uptimeScore = $metric['uptime_percent'] !== null
            ? max(0, min(100, (float)$metric['uptime_percent']))
            : 100;
        $components = [
            'backup'      => ['score' => $metric['backup_freshness_score'] ?? 100, 'weight' => 0.10],
            'plugins'     => ['score' => $metric['plugin_status_score']    ?? 100, 'weight' => 0.15],
            'performance' => ['score' => $metric['performance_score']      ?? 100, 'weight' => 0.20],
            'security'    => ['score' => $metric['security_score']         ?? 100, 'weight' => 0.25],
            'uptime'      => ['score' => $uptimeScore,                             'weight' => 0.30],
        ];
        
        // Score by impact = (100 - score) * weight
        $impact = [];
        foreach ($components as $name => $comp) {
            $impact[$name] = (100 - $comp['score']) * $comp['weight'];
        }
        
        arsort($impact);
        
        // Generate recommendations for lowest performing areas
        foreach (array_slice($impact, 0, 3, true) as $component => $score) {
            $componentScore = $components[$component]['score'];
            
            if ($componentScore < 70) {
                $recommendations[] = $this->getComponentRecommendation($component, $componentScore);
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get specific recommendation for a component
     * 
     * @param string $component Component name
     * @param float $score Component score
     * @return array Recommendation
     */
    private function getComponentRecommendation($component, $score) {
        $recommendations = [
            'backup' => [
                'icon' => '💾',
                'title' => 'Improve Backup Strategy',
                'description' => 'Regular backups protect against data loss. Set up daily automated backups.',
                'priority' => 'CRITICAL'
            ],
            'plugins' => [
                'icon' => '📦',
                'title' => 'Update and Review Plugins',
                'description' => 'Update all plugins and remove inactive ones to improve performance.',
                'priority' => $score < 50 ? 'HIGH' : 'MEDIUM'
            ],
            'performance' => [
                'icon' => '⚡',
                'title' => 'Optimize Performance',
                'description' => 'Enable caching, optimize images, and reduce server response time.',
                'priority' => $score < 50 ? 'HIGH' : 'MEDIUM'
            ],
            'security' => [
                'icon' => '🔒',
                'title' => 'Enhance Security',
                'description' => 'Update WordPress, enable HTTPS, and implement security best practices.',
                'priority' => 'HIGH'
            ],
            'uptime' => [
                'icon' => '📡',
                'title' => 'Improve Site Availability',
                'description' => 'Monitor uptime, ensure hosting is reliable, and set up monitoring alerts.',
                'priority' => 'CRITICAL'
            ]
        ];
        
        $base = $recommendations[$component] ?? [];
        $base['score'] = round($score, 1);
        
        return $base;
    }
}
?>
