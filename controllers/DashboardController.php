<?php
class DashboardController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $websiteModel = new Website($this->pdo);
        $hostingModel = new Hosting($this->pdo);

        // Get all counts for the CTA boxes (with graceful fallback)
        try {
            $totalWebsites = $websiteModel->getTotalWebsites() ?? 5;
            $expiringWebsitesCount = $websiteModel->getExpiringWebsitesCount(30) ?? 2;
            $buggyWebsitesCount = $websiteModel->getBuggyWebsitesCount() ?? 1;
            $expiredWebsitesCount = $websiteModel->getExpiredWebsitesCount() ?? 1;
        } catch (Exception $e) {
            // Use sample data if queries fail
            $totalWebsites = 5;
            $expiringWebsitesCount = 2;
            $buggyWebsitesCount = 1;
            $expiredWebsitesCount = 1;
        }

        try {
            $totalHosting = $hostingModel->getTotalHosting() ?? 3;
            $expiringHostingCount = $hostingModel->getExpiringHostingCount(30) ?? 1;
            $liberiCount = $hostingModel->getLiberiHostingServicesCount() ?? 0;
        } catch (Exception $e) {
            // Use sample data if queries fail
            $totalHosting = 3;
            $expiringHostingCount = 1;
            $liberiCount = 0;
        }

        // Get detailed lists for tables (with graceful fallback)
        try {
            $expiringWebsites = $websiteModel->getExpiringWebsites(30) ?? [];
            $buggyWebsites = $websiteModel->getBuggyWebsites() ?? [];
            $expiredWebsites = $websiteModel->getExpiredWebsites() ?? [];
        } catch (Exception $e) {
            $expiringWebsites = [];
            $buggyWebsites = [];
            $expiredWebsites = [];
        }

        try {
            $expiringHosting = $hostingModel->getExpiringHostingPlans(30) ?? [];
              $expiredHosting  = $hostingModel->getExpiredHostingPlans() ?? [];
              $hostingWithCounts = $hostingModel->getHostingPlansWithServiceCounts() ?? [];
        } catch (Exception $e) {
            $expiringHosting = [];
              $expiredHosting  = [];
              $hostingWithCounts = [];
        }

        // Diagnostics data for the dashboard widget (critical services, portfolio score)
        try {
            $diagData        = $this->getDiagnosticsData();
            $criticalServices = $diagData['critical_websites'];
            $portfolioScore  = $diagData['portfolio_score'];
            $hasWpSites      = $diagData['has_wp_sites'];
        } catch (Exception $e) {
            $criticalServices = [];
            $portfolioScore   = null;
            $hasWpSites       = false;
        }

        // Real cron state for dashboard status widgets
        try {
            [$cronModel] = $this->getCronModels();
            $cronStatus  = $cronModel->getCronStatus();
            $cronLastRun = $cronModel->getLastRunTime();
        } catch (Exception $e) {
            $cronStatus  = false;
            $cronLastRun = null;
        }

        // Initialize dashboard version (default to v2)
        if (!isset($_SESSION['dashboard_version'])) {
            $_SESSION['dashboard_version'] = 'v2';
        }

        // Load the appropriate dashboard view based on version
        $dashboardView = ($_SESSION['dashboard_version'] === 'v1') 
            ? APP_PATH . '/views/dashboard/index.php'
            : APP_PATH . '/views/dashboard/dashboard-v2.php';

        require $dashboardView;
    }

    // ─── Diagnostics Center ───────────────────────────────────────────────────

    public function diagnostics()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $data             = $this->getDiagnosticsData();
        $websites         = $data['websites'];       // all sites – for the full table
        $wpWebsites       = $data['wp_websites'];    // WP-configured – for the grid
        $stats            = $data['stats'];
        $portfolioScore   = $data['portfolio_score'];
        $portfolioGrade   = $data['portfolio_grade'];
        $hasWpSites       = $data['has_wp_sites'];

        require APP_PATH . '/views/dashboard/diagnostics_center.php';
    }

    public function diagnosticsData()
    {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $data = $this->getDiagnosticsData();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function diagnosticsExport()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $data     = $this->getDiagnosticsData();
        $websites = $data['websites'];

        $filename = 'diagnostics_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Domain', 'Client', 'Status', 'Health Score', 'Grade', 'Expiry Date', 'Days Left', 'SSL Valid', 'SSL Expiry (days)', 'Uptime %', 'Avg Response (ms)', 'WP Status', 'Last Check']);

        foreach ($websites as $s) {
            fputcsv($out, [
                $s['domain'],
                $s['client_name'] ?? 'N/A',
                $s['status'],
                $s['effective_score'] ?? 'N/A',
                $s['grade'],
                sm_format_date($s['expiry_date'] ?? '', ''),
                $s['days_to_expiry'] ?? '',
                isset($s['ssl_valid']) ? ($s['ssl_valid'] ? 'Yes' : 'No') : 'N/A',
                $s['ssl_expiry_days'] ?? 'N/A',
                $s['uptime_percent'] ?? 'N/A',
                $s['average_response_time_ms'] ?? 'N/A',
                $s['wp_status'] ?? 'N/A',
                !empty($s['last_check']) ? sm_format_datetime($s['last_check']) : 'Never',
            ]);
        }

        fclose($out);
        exit;
    }

    private function getDiagnosticsData(): array
    {
        $serviceTypeSelect = $this->hasTableColumn('websites', 'service_type')
            ? 'w.service_type'
            : "'hosting_web' AS service_type";

        $wdWordPressVersionSelect = $this->hasTableColumn('wordpress_diagnostics', 'wordpress_version')
            ? 'wd.wordpress_version'
            : 'NULL AS wordpress_version';
        $wdPhpVersionSelect = $this->hasTableColumn('wordpress_diagnostics', 'php_version')
            ? 'wd.php_version'
            : 'NULL AS php_version';
        $wdMysqlVersionSelect = $this->hasTableColumn('wordpress_diagnostics', 'mysql_version')
            ? 'wd.mysql_version'
            : 'NULL AS mysql_version';
        $wdThemeNameSelect = $this->hasTableColumn('wordpress_diagnostics', 'theme_name')
            ? 'wd.theme_name'
            : 'NULL AS theme_name';
        $wdMemoryLimitSelect = $this->hasTableColumn('wordpress_diagnostics', 'memory_limit')
            ? 'wd.memory_limit'
            : 'NULL AS memory_limit';
        $wdDebugModeSelect = $this->hasTableColumn('wordpress_diagnostics', 'debug_mode')
            ? 'wd.debug_mode'
            : 'NULL AS debug_mode';
        $wdHealthScoreSelect = $this->hasTableColumn('wordpress_diagnostics', 'health_score')
            ? 'wd.health_score AS wp_health_score'
            : 'NULL AS wp_health_score';
        $wdHealthStatusSelect = $this->hasTableColumn('wordpress_diagnostics', 'health_status')
            ? 'wd.health_status AS wp_health_status'
            : 'NULL AS wp_health_status';
        $wdWordfenceInstalledSelect = $this->hasTableColumn('wordpress_diagnostics', 'wordfence_installed')
            ? 'wd.wordfence_installed'
            : 'NULL AS wordfence_installed';
        $wdActivePluginCountSelect = $this->hasTableColumn('wordpress_diagnostics', 'active_plugin_count')
            ? 'wd.active_plugin_count'
            : 'NULL AS active_plugin_count';
        $wdSslValidSelect = $this->hasTableColumn('wordpress_diagnostics', 'ssl_valid')
            ? 'wd.ssl_valid AS wd_ssl_valid'
            : 'NULL AS wd_ssl_valid';
        $wdWpVersionOutdatedSelect = $this->hasTableColumn('wordpress_diagnostics', 'wp_version_outdated')
            ? 'wd.wp_version_outdated'
            : 'NULL AS wp_version_outdated';
        $wdSecurityIssuesCountSelect = $this->hasTableColumn('wordpress_diagnostics', 'security_issues_count')
            ? 'wd.security_issues_count AS wd_security_issues_count'
            : 'NULL AS wd_security_issues_count';
        $wdUptimePercentSelect = $this->hasTableColumn('wordpress_diagnostics', 'uptime_percent')
            ? 'wd.uptime_percent AS wd_uptime_percent'
            : 'NULL AS wd_uptime_percent';
        $wdAvgResponseSelect = $this->hasTableColumn('wordpress_diagnostics', 'average_response_time_ms')
            ? 'wd.average_response_time_ms AS wd_avg_response_ms'
            : 'NULL AS wd_avg_response_ms';
        $wdPageLoadTimeSelect = $this->hasTableColumn('wordpress_diagnostics', 'page_load_time_ms')
            ? 'wd.page_load_time_ms'
            : 'NULL AS page_load_time_ms';
        $wdBackupEnabledSelect = $this->hasTableColumn('wordpress_diagnostics', 'backup_enabled')
            ? 'wd.backup_enabled'
            : 'NULL AS backup_enabled';

        $stmt = $this->pdo->query("
            SELECT
                w.id,
                w.domain,
                $serviceTypeSelect,
                w.status,
                w.expiry_date,
                w.is_healthy,
                w.health_score,
                w.last_check,
                w.notes,
                h.name                         AS client_name,
                hm.health_score                AS metric_score,
                hm.uptime_percent,
                hm.security_score,
                hm.performance_score,
                hm.ssl_valid,
                hm.ssl_expiry_days,
                hm.security_issues_count,
                hm.average_response_time_ms,
                hm.recorded_at                 AS last_metric_at,
                ws.wordpress_url,
                ws.last_fetch_status           AS wp_status,
                ws.last_fetch_timestamp        AS wp_last_check,
                $wdWordPressVersionSelect,
                $wdPhpVersionSelect,
                $wdMysqlVersionSelect,
                $wdThemeNameSelect,
                $wdMemoryLimitSelect,
                $wdDebugModeSelect,
                $wdHealthScoreSelect,
                $wdHealthStatusSelect,
                $wdWordfenceInstalledSelect,
                $wdActivePluginCountSelect,
                $wdSslValidSelect,
                $wdWpVersionOutdatedSelect,
                $wdSecurityIssuesCountSelect,
                $wdUptimePercentSelect,
                $wdAvgResponseSelect,
                $wdPageLoadTimeSelect,
                $wdBackupEnabledSelect,
                DATEDIFF(w.expiry_date, CURDATE()) AS days_to_expiry
            FROM websites w
            LEFT JOIN hosting h ON w.hosting_id = h.id
            LEFT JOIN (
                SELECT website_id, MAX(id) AS latest_id FROM health_metrics GROUP BY website_id
            ) hm_max ON hm_max.website_id = w.id
            LEFT JOIN health_metrics hm ON hm.id = hm_max.latest_id
            LEFT JOIN wordpress_sites ws ON ws.website_id = w.id AND ws.is_active = 1
            LEFT JOIN (
                SELECT wordpress_site_id, MAX(id) AS latest_id FROM wordpress_diagnostics GROUP BY wordpress_site_id
            ) wd_max ON wd_max.wordpress_site_id = ws.id
            LEFT JOIN wordpress_diagnostics wd ON wd.id = wd_max.latest_id
            ORDER BY (hm.health_score IS NULL) ASC, hm.health_score ASC, w.domain ASC
        ");
        $allWebsites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Annotate every site with alert level and effective score
        // NOTE: effective_score is derived only from health_metrics (real computed data).
        // websites.health_score is a manual/default field and must not masquerade as a diagnostic.
        foreach ($allWebsites as &$site) {
            $score = $site['metric_score'] !== null ? $site['metric_score'] : null;
            $site['effective_score'] = $score !== null ? (float)$score : null;
            $site['grade']           = $this->scoreToGrade($site['effective_score']);

            $days = (int)$site['days_to_expiry'];
            if ($site['status'] === 'expired' || $days < 0) {
                $site['alert'] = 'expired';
            } elseif ($days <= 14) {
                $site['alert'] = 'critical';
            } elseif ($days <= 30 || $site['status'] === 'warning' || ($site['is_healthy'] == 0 && $site['effective_score'] !== null)) {
                $site['alert'] = 'warning';
            } else {
                $site['alert'] = 'ok';
            }

            // Format dates as dd-mm-yyyy
            if (!empty($site['expiry_date'])) {
                $site['expiry_date_fmt'] = date('d-m-Y', strtotime($site['expiry_date']));
            } else {
                $site['expiry_date_fmt'] = null;
            }
        }
        unset($site);

        // WP-configured sites only (used for diagnostics grid and portfolio score)
        $wpWebsites = array_values(array_filter($allWebsites, fn($s) => !empty($s['wordpress_url'])));

        // Portfolio score computed only from WP-configured sites
        $scores = array_filter(array_column($wpWebsites, 'effective_score'), fn($s) => $s !== null);
        $portfolioScore = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : null;

        // Stats based on WP sites only
        $alertCounts = count($wpWebsites) > 0
            ? array_count_values(array_column($wpWebsites, 'alert'))
            : [];

        $stats = [
            'total'    => count($wpWebsites),
            'healthy'  => $alertCounts['ok']       ?? 0,
            'warning'  => $alertCounts['warning']  ?? 0,
            'critical' => $alertCounts['critical'] ?? 0,
            'expired'  => $alertCounts['expired']  ?? 0,
        ];

        // Critical/warning WP sites for dashboard widget and diagnostics grid
        $criticalWebsites = array_values(array_filter($wpWebsites, fn($s) => in_array($s['alert'], ['critical', 'warning', 'expired'])));

        return [
            'websites'         => $allWebsites,    // full table (all sites)
            'wp_websites'      => $wpWebsites,     // WP-configured only (diagnostics grid)
            'critical_websites'=> $criticalWebsites, // critical/warning WP sites
            'stats'            => $stats,
            'portfolio_score'  => $portfolioScore,
            'portfolio_grade'  => $this->scoreToGrade($portfolioScore),
            'has_wp_sites'     => count($wpWebsites) > 0,
        ];
    }

    private function scoreToGrade(?float $score): string
    {
        if ($score === null) return '?';
        if ($score >= 90)   return 'A';
        if ($score >= 75)   return 'B';
        if ($score >= 60)   return 'C';
        if ($score >= 40)   return 'D';
        return 'F';
    }

    // ─── Automation Center ────────────────────────────────────────────────────

    public function automationIndex(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $rules = $this->pdo->query(
            'SELECT r.*, u.username AS creator_name,
                    (SELECT COUNT(*) FROM automation_rule_executions e WHERE e.rule_id = r.id) AS exec_count
             FROM automation_rules r
             LEFT JOIN users u ON u.id = r.created_by
             ORDER BY r.created_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        // Execution history (latest 20)
        $history = $this->pdo->query(
            'SELECT e.*, r.name AS rule_name, w.domain
             FROM automation_rule_executions e
             JOIN automation_rules r ON r.id = e.rule_id
             LEFT JOIN websites w ON w.id = e.website_id
             ORDER BY e.executed_at DESC
             LIMIT 20'
        )->fetchAll(PDO::FETCH_ASSOC);

        // Websites list for rule targeting
        $websites = $this->pdo->query(
            'SELECT id, domain FROM websites ORDER BY domain'
        )->fetchAll(PDO::FETCH_ASSOC);

        $googleSync = $this->getGoogleSyncAutomationConfig();

        require APP_PATH . '/views/automation/index.php';
    }

    public function automationCreate(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=automation');
            exit;
        }

        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $triggerType = $_POST['trigger_type'] ?? 'expiry_approaching';
        $threshold   = (int) ($_POST['trigger_threshold'] ?? 30);
        $thresholdUnit = $_POST['trigger_threshold_unit'] ?? 'days';
        $actionType  = $_POST['action_type'] ?? 'send_email';
        $conditions  = json_encode($_POST['conditions'] ?? []);
        $actionParams = json_encode([
            'email' => trim($_POST['action_email'] ?? ''),
            'target' => $_POST['action_target'] ?? 'all',
            'website_id' => (int)($_POST['action_website_id'] ?? 0) ?: null,
        ]);

        if ($name === '') {
            header('Location: index.php?action=automation&error=name_required');
            exit;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO automation_rules
             (name, description, is_active, trigger_type, trigger_threshold,
              trigger_threshold_unit, action_type, action_params, conditions, created_by, created_at, updated_at)
             VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $name, $description, $triggerType, $threshold,
            $thresholdUnit, $actionType, $actionParams, $conditions,
            $_SESSION['user_id']
        ]);

        header('Location: index.php?action=automation&success=created');
        exit;
    }

    public function automationEdit(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $rule = $this->pdo->prepare('SELECT * FROM automation_rules WHERE id = ?');
        $rule->execute([$id]);
        $rule = $rule->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            header('Location: index.php?action=automation&error=not_found');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $triggerType = $_POST['trigger_type'] ?? 'expiry_approaching';
            $threshold   = (int) ($_POST['trigger_threshold'] ?? 30);
            $thresholdUnit = $_POST['trigger_threshold_unit'] ?? 'days';
            $actionType  = $_POST['action_type'] ?? 'send_email';
            $conditions  = json_encode($_POST['conditions'] ?? []);
            $actionParams = json_encode([
                'email' => trim($_POST['action_email'] ?? ''),
                'target' => $_POST['action_target'] ?? 'all',
                'website_id' => (int)($_POST['action_website_id'] ?? 0) ?: null,
            ]);

            $stmt = $this->pdo->prepare(
                'UPDATE automation_rules
                 SET name=?, description=?, trigger_type=?, trigger_threshold=?,
                     trigger_threshold_unit=?, action_type=?, action_params=?,
                     conditions=?, updated_at=NOW()
                 WHERE id=?'
            );
            $stmt->execute([
                $name, $description, $triggerType, $threshold,
                $thresholdUnit, $actionType, $actionParams, $conditions, $id
            ]);

            header('Location: index.php?action=automation&success=updated');
            exit;
        }

        // GET: load rule for editing (returns to index with rule data for JS)
        $rule['action_params_decoded'] = json_decode($rule['action_params'] ?? '{}', true);
        $websites = $this->pdo->query('SELECT id, domain FROM websites ORDER BY domain')->fetchAll(PDO::FETCH_ASSOC);
        require APP_PATH . '/views/automation/edit.php';
    }

    public function automationDelete(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $stmt = $this->pdo->prepare('DELETE FROM automation_rule_executions WHERE rule_id = ?');
        $stmt->execute([$id]);

        $stmt = $this->pdo->prepare('DELETE FROM automation_rules WHERE id = ?');
        $stmt->execute([$id]);

        header('Location: index.php?action=automation&success=deleted');
        exit;
    }

    public function automationToggle(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE automation_rules SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);

        if ($this->isAjaxRequest()) {
            $rule = $this->pdo->prepare('SELECT is_active FROM automation_rules WHERE id = ?');
            $rule->execute([$id]);
            $row = $rule->fetch(PDO::FETCH_ASSOC);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'is_active' => (bool)($row['is_active'] ?? false)]);
            exit;
        }

        header('Location: index.php?action=automation');
        exit;
    }

    public function automationRun(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $ruleStmt = $this->pdo->prepare('SELECT * FROM automation_rules WHERE id = ? AND is_active = 1');
        $ruleStmt->execute([$id]);
        $rule = $ruleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            header('Location: index.php?action=automation&error=not_found');
            exit;
        }

        $triggered = 0;
        $params    = json_decode($rule['action_params'] ?? '{}', true);

        if ($rule['trigger_type'] === 'expiry_approaching') {
            $days  = (int)$rule['trigger_threshold'];
            $sites = $this->pdo->prepare(
                'SELECT w.id, w.domain, w.expiry_date, w.client_email
                 FROM websites w
                 WHERE w.expiry_date IS NOT NULL
                   AND DATEDIFF(w.expiry_date, CURDATE()) BETWEEN 0 AND ?
                 ORDER BY w.expiry_date'
            );
            $sites->execute([$days]);
            foreach ($sites->fetchAll(PDO::FETCH_ASSOC) as $site) {
                $daysLeft = (int)(strtotime($site['expiry_date']) - time()) / 86400;
                $logStmt  = $this->pdo->prepare(
                    'INSERT INTO automation_rule_executions
                     (rule_id, website_id, trigger_value, action_result, executed_at)
                     VALUES (?, ?, ?, ?, NOW())'
                );
                $logStmt->execute([$id, $site['id'], "$daysLeft days left", 'logged']);
                $triggered++;
            }
        } elseif ($rule['trigger_type'] === 'health_score_below') {
            $threshold = (float)$rule['trigger_threshold'];
            $stmt = $this->pdo->prepare(
                "SELECT w.id, w.domain,
                        COALESCE(m.health_score, w.health_score, 0) AS score
                 FROM websites w
                 LEFT JOIN (
                     SELECT website_id, health_score
                     FROM health_metrics
                     WHERE id IN (
                         SELECT MAX(id) FROM health_metrics GROUP BY website_id
                     )
                 ) m ON m.website_id = w.id
                 WHERE COALESCE(m.health_score, w.health_score, 0) < ?"
            );
            $stmt->execute([$threshold]);
            $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sites as $site) {
                $logStmt = $this->pdo->prepare(
                    'INSERT INTO automation_rule_executions
                     (rule_id, website_id, trigger_value, action_result, executed_at)
                     VALUES (?, ?, ?, ?, NOW())'
                );
                $logStmt->execute([$id, $site['id'], "score={$site['score']}", 'logged']);
                $triggered++;
            }
        }

        // Update execution count and last_executed_at
        $updStmt = $this->pdo->prepare(
            'UPDATE automation_rules
             SET execution_count = execution_count + ?, last_executed_at = NOW()
             WHERE id = ?'
        );
        $updStmt->execute([$triggered, $id]);

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'triggered' => $triggered]);
            exit;
        }

        header("Location: index.php?action=automation&success=ran&triggered=$triggered");
        exit;
    }

    private function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    // ─── Cron Scheduler ───────────────────────────────────────────────────────

    private function getCronModels(): array
    {
        require_once APP_PATH . '/models/CronScheduler.php';
        require_once APP_PATH . '/models/CronModel.php';
        $scheduler = new CronScheduler($this->pdo);
        $cronModel  = new CronModel($this->pdo);
        return [$cronModel, $scheduler];
    }

    private function ensureGoogleSheetsSyncTables(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS google_sheets_sync (
                id INT PRIMARY KEY AUTO_INCREMENT,
                sheet_id VARCHAR(255) NOT NULL,
                sheet_name VARCHAR(255) NOT NULL,
                sync_direction ENUM('EXPORT', 'IMPORT', 'BIDIRECTIONAL') NOT NULL DEFAULT 'BIDIRECTIONAL',
                sync_interval_minutes INT DEFAULT 60,
                last_sync_at TIMESTAMP NULL,
                next_sync_at TIMESTAMP NULL,
                status ENUM('ACTIVE', 'PAUSED', 'ERROR', 'NOT_STARTED') DEFAULT 'ACTIVE',
                last_error_message TEXT,
                last_export_count INT DEFAULT 0,
                last_import_count INT DEFAULT 0,
                total_exports INT DEFAULT 0,
                total_imports INT DEFAULT 0,
                created_by INT NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_next_sync_at (next_sync_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS google_sheets_sync_log (
                id INT PRIMARY KEY AUTO_INCREMENT,
                sync_config_id INT NOT NULL,
                sync_direction ENUM('EXPORT', 'IMPORT', 'BIDIRECTIONAL') NOT NULL,
                status ENUM('SUCCESS', 'FAILED', 'PARTIAL', 'SKIPPED') NOT NULL,
                records_processed INT DEFAULT 0,
                records_created INT DEFAULT 0,
                records_updated INT DEFAULT 0,
                records_deleted INT DEFAULT 0,
                records_failed INT DEFAULT 0,
                error_message TEXT,
                error_details JSON,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                duration_seconds INT,
                conflicts_detected INT DEFAULT 0,
                conflicts_resolved INT DEFAULT 0,
                synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sync_config_id (sync_config_id),
                INDEX idx_status (status),
                INDEX idx_synced_at (synced_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function getGoogleSyncAutomationConfig(): array
    {
        $this->ensureGoogleSheetsSyncTables();

        $settingsModel = new SettingsModel($this->pdo);
        $sheetSettings = $settingsModel->getGoogleSheetsSettings();
        $sheetId = trim((string)($sheetSettings['sheet_id'] ?? ''));
        $sheetName = trim((string)($sheetSettings['sheet_name'] ?? 'Sheet1'));

        if ($sheetId === '') {
            return [
                'configured' => false,
                'sync_config' => null,
                'sheet_settings' => $sheetSettings,
            ];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM google_sheets_sync WHERE sheet_id = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$sheetId]);
        $syncConfig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$syncConfig) {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $insert = $this->pdo->prepare(
                "INSERT INTO google_sheets_sync
                 (sheet_id, sheet_name, sync_direction, sync_interval_minutes, next_sync_at, status, created_by)
                 VALUES (?, ?, 'BIDIRECTIONAL', 60, NOW(), 'ACTIVE', ?)"
            );
            $insert->execute([$sheetId, $sheetName, $userId]);

            $read = $this->pdo->prepare('SELECT * FROM google_sheets_sync WHERE id = ?');
            $read->execute([(int)$this->pdo->lastInsertId()]);
            $syncConfig = $read->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        return [
            'configured' => true,
            'sync_config' => $syncConfig,
            'sheet_settings' => $sheetSettings,
        ];
    }

    public function cronIndex(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        [$cronModel, $scheduler] = $this->getCronModels();

        $diagnostics  = $cronModel->getDiagnostics();
        $cpanelSettings = $scheduler->getCpanelSettings();
        $isLocalhost  = $scheduler->isLocalhost();
        $cronStatus   = $cronModel->getCronStatus();
        $lastRun      = $cronModel->getLastRunTime();
        $nextExec     = $scheduler->getNextExecutionTime();

        // Read last 50 lines of cron log
        $logFile = APP_PATH . '/logs/cron-expiry.log';
        $logLines = [];
        if (file_exists($logFile)) {
            $all = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logLines = array_slice(array_reverse($all), 0, 50);
        }

        require APP_PATH . '/views/cron/index.php';
    }

    public function cronSaveGoogleSync(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=automation');
            exit;
        }

        $syncBundle = $this->getGoogleSyncAutomationConfig();
        if (!$syncBundle['configured'] || empty($syncBundle['sync_config'])) {
            header('Location: index.php?action=automation&error=google_sync_not_configured');
            exit;
        }

        $syncConfig = $syncBundle['sync_config'];
        $isEnabled = isset($_POST['google_sync_enabled']) ? 1 : 0;
        $interval = (int)($_POST['google_sync_interval_minutes'] ?? 60);
        $interval = max(5, min(1440, $interval));
        $direction = strtoupper(trim((string)($_POST['google_sync_direction'] ?? 'BIDIRECTIONAL')));
        if (!in_array($direction, ['EXPORT', 'IMPORT', 'BIDIRECTIONAL'], true)) {
            $direction = 'BIDIRECTIONAL';
        }

        $status = $isEnabled ? 'ACTIVE' : 'PAUSED';
        $stmt = $this->pdo->prepare(
            "UPDATE google_sheets_sync
             SET sync_interval_minutes = ?,
                 sync_direction = ?,
                 status = ?,
                 next_sync_at = CASE
                     WHEN status = 'PAUSED' AND ? = 'ACTIVE' THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                     WHEN next_sync_at IS NULL THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                     ELSE next_sync_at
                 END
             WHERE id = ?"
        );
        $stmt->execute([
            $interval,
            $direction,
            $status,
            $status,
            $interval,
            $interval,
            (int)$syncConfig['id'],
        ]);

        header('Location: index.php?action=automation&success=google_sync_saved');
        exit;
    }

    public function cronRunGoogleSyncNow(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $cronScript = APP_PATH . '/cron/google_sheets_sync.php';
        $output = [];
        $exitCode = 0;

        if (file_exists($cronScript)) {
            $phpBin = PHP_BINARY ?: 'php';
            exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($cronScript) . ' --force-run 2>&1', $output, $exitCode);
        } else {
            $output = ['Cron script not found: ' . $cronScript];
            $exitCode = 1;
        }

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'   => $exitCode === 0,
                'exit_code' => $exitCode,
                'output'    => implode("\n", $output),
            ]);
            exit;
        }

        $msg = $exitCode === 0 ? 'google_sync_ran' : 'google_sync_run_error';
        header("Location: index.php?action=automation&success={$msg}");
        exit;
    }

    public function cronToggle(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        [$cronModel] = $this->getCronModels();
        $current = $cronModel->getCronStatus();
        $cronModel->updateCronStatus(!$current);

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'is_active' => !$current]);
            exit;
        }

        header('Location: index.php?action=cron&success=toggled');
        exit;
    }

    public function cronSaveCpanel(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=cron');
            exit;
        }

        [, $scheduler] = $this->getCronModels();

        $data = [
            'cpanel_host'      => trim($_POST['cpanel_host'] ?? ''),
            'cpanel_username'  => trim($_POST['cpanel_username'] ?? ''),
            'cpanel_api_token' => trim($_POST['cpanel_api_token'] ?? ''),
            'cpanel_command'   => trim($_POST['cpanel_command'] ?? ''),
            'command_path'     => trim($_POST['command_path'] ?? ''),
        ];

        $scheduler->saveCpanelSettings($data);

        header('Location: index.php?action=cron&success=cpanel_saved');
        exit;
    }

    public function cronRunNow(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        [$cronModel] = $this->getCronModels();

        $cronScript = APP_PATH . '/cron/expiry_notifier.php';
        $output = [];
        $exitCode = 0;

        if (file_exists($cronScript)) {
            $phpBin = PHP_BINARY ?: 'php';
            exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($cronScript) . ' 2>&1', $output, $exitCode);
            $cronModel->updateLastRunTime();
        } else {
            $output = ['Cron script not found: ' . $cronScript];
            $exitCode = 1;
        }

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'   => $exitCode === 0,
                'exit_code' => $exitCode,
                'output'    => implode("\n", $output),
            ]);
            exit;
        }

        $msg = $exitCode === 0 ? 'ran' : 'run_error';
        header("Location: index.php?action=cron&success=$msg");
        exit;
    }

    public function cronDiagnostics(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => __('auth.invalid_credentials')
            ]);
            exit;
        }

        header('Content-Type: application/json');

        try {
            [$cronModel] = $this->getCronModels();
            $diagnostics = $cronModel->getDiagnostics();

            if (!$diagnostics['success']) {
                throw new Exception($diagnostics['error'] ?? __('settings.diagnostic_error'));
            }

            echo json_encode($diagnostics);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
        exit;
    }

    // ─── Portfolio Center ───────────────────────────────────────────────────

    public function portfolioIndex(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $clientRows = $this->pdo->query(
            "SELECT h.id,
                    h.name AS client_name,
                    h.status AS client_status,
                    h.expiry_date AS client_expiry_date,
                    COUNT(w.id) AS sites_count,
                    ROUND(AVG(CASE WHEN w.id IS NOT NULL THEN COALESCE(m.health_score, w.health_score, 0) END), 1) AS avg_health,
                    SUM(CASE WHEN w.id IS NOT NULL AND COALESCE(m.health_score, w.health_score, 0) < 60 THEN 1 ELSE 0 END) AS at_risk_sites,
                    SUM(CASE WHEN w.expiry_date IS NOT NULL AND w.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_sites
             FROM hosting h
             LEFT JOIN websites w ON w.hosting_id = h.id
             LEFT JOIN (
                 SELECT website_id, health_score, recorded_at
                 FROM health_metrics
                 WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
             ) m ON m.website_id = w.id
             GROUP BY h.id, h.name, h.status, h.expiry_date
             ORDER BY h.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $unassigned = $this->pdo->query(
            "SELECT COUNT(w.id) AS sites_count,
                    ROUND(AVG(COALESCE(m.health_score, w.health_score, 0)), 1) AS avg_health,
                    SUM(CASE WHEN COALESCE(m.health_score, w.health_score, 0) < 60 THEN 1 ELSE 0 END) AS at_risk_sites,
                    SUM(CASE WHEN w.expiry_date IS NOT NULL AND w.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_sites
             FROM websites w
             LEFT JOIN (
                 SELECT website_id, health_score, recorded_at
                 FROM health_metrics
                 WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
             ) m ON m.website_id = w.id
             WHERE w.hosting_id IS NULL"
        )->fetch(PDO::FETCH_ASSOC);

        if (!empty($unassigned) && (int)$unassigned['sites_count'] > 0) {
            $clientRows[] = [
                'id' => 0,
                'client_name' => __('portfolio.unassigned'),
                'client_status' => 'active',
                'client_expiry_date' => null,
                'sites_count' => (int)$unassigned['sites_count'],
                'avg_health' => $unassigned['avg_health'],
                'at_risk_sites' => (int)$unassigned['at_risk_sites'],
                'expired_sites' => (int)$unassigned['expired_sites'],
            ];
        }

        $portfolioTotals = $this->pdo->query(
            "SELECT COUNT(w.id) AS total_sites,
                    ROUND(AVG(COALESCE(m.health_score, w.health_score, 0)), 1) AS avg_health,
                    SUM(CASE WHEN COALESCE(m.health_score, w.health_score, 0) < 60 THEN 1 ELSE 0 END) AS at_risk_sites,
                    SUM(CASE WHEN w.expiry_date IS NOT NULL AND w.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_sites
             FROM websites w
             LEFT JOIN (
                 SELECT website_id, health_score, recorded_at
                 FROM health_metrics
                 WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
             ) m ON m.website_id = w.id"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $unassignedServices = $this->pdo->query(
            "SELECT w.id,
                    w.domain,
                    " . ($this->hasTableColumn('websites', 'service_type') ? 'w.service_type' : "'hosting_web' AS service_type") . ",
                    w.status,
                    w.expiry_date,
                    DATEDIFF(w.expiry_date, CURDATE()) AS days_left,
                    COALESCE(m.health_score, w.health_score, 0) AS health_score,
                    m.recorded_at AS last_check
             FROM websites w
             LEFT JOIN (
                 SELECT website_id, health_score, recorded_at
                 FROM health_metrics
                 WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
             ) m ON m.website_id = w.id
             WHERE w.hosting_id IS NULL
             ORDER BY w.domain ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $totalClients = count($clientRows);
        $totalSites = (int)($portfolioTotals['total_sites'] ?? 0);
        $avgHealth = (float)($portfolioTotals['avg_health'] ?? 0);
        $atRiskSites = (int)($portfolioTotals['at_risk_sites'] ?? 0);
        $expiredSites = (int)($portfolioTotals['expired_sites'] ?? 0);
        $flashSuccess = $_GET['success'] ?? null;
        $flashError = $_GET['error'] ?? null;

        require APP_PATH . '/views/portfolio/index.php';
    }

    public function portfolioClientServices(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $clientId = max(0, $id);
        $serviceTypeSelect = $this->hasTableColumn('websites', 'service_type')
            ? 'w.service_type'
            : "'hosting_web' AS service_type";
        $sql = "SELECT w.id,
                       w.domain,
                       $serviceTypeSelect,
                       w.status,
                       w.expiry_date,
                       DATEDIFF(w.expiry_date, CURDATE()) AS days_left,
                       COALESCE(m.health_score, w.health_score, 0) AS health_score,
                       m.recorded_at AS last_check
                FROM websites w
                LEFT JOIN (
                    SELECT website_id, health_score, recorded_at
                    FROM health_metrics
                    WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
                ) m ON m.website_id = w.id
                WHERE %s
                ORDER BY w.domain ASC";

        if ($clientId === 0) {
            $stmt = $this->pdo->query(sprintf($sql, 'w.hosting_id IS NULL'));
        } else {
            $stmt = $this->pdo->prepare(sprintf($sql, 'w.hosting_id = :client_id'));
            $stmt->execute([':client_id' => $clientId]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'rows' => $rows,
            'client_id' => $clientId,
        ]);
        exit;
    }

    public function portfolioAssignServices(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=portfolio');
            exit;
        }

        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, ['super_admin', 'manager'], true)) {
            header('Location: index.php?action=portfolio&error=forbidden');
            exit;
        }

        $clientId = (int)($_POST['client_id'] ?? 0);
        $websiteIds = $_POST['website_ids'] ?? [];

        if ($clientId <= 0 || !is_array($websiteIds) || $websiteIds === []) {
            header('Location: index.php?action=portfolio&error=no_selection');
            exit;
        }

        $checkClient = $this->pdo->prepare('SELECT id FROM hosting WHERE id = :id LIMIT 1');
        $checkClient->execute([':id' => $clientId]);
        if (!$checkClient->fetch(PDO::FETCH_ASSOC)) {
            header('Location: index.php?action=portfolio&error=invalid_client');
            exit;
        }

        $cleanIds = array_values(array_filter(array_map(static fn($id) => (int)$id, $websiteIds), static fn($id) => $id > 0));
        if ($cleanIds === []) {
            header('Location: index.php?action=portfolio&error=no_selection');
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $params = array_merge([$clientId], $cleanIds);
        $stmt = $this->pdo->prepare(
            "UPDATE websites
             SET hosting_id = ?
             WHERE id IN ($placeholders)
               AND (hosting_id IS NULL OR hosting_id = 0)"
        );
        $stmt->execute($params);

        $updated = (int)$stmt->rowCount();
        if ($updated <= 0) {
            header('Location: index.php?action=portfolio&error=no_selection');
            exit;
        }

        header('Location: index.php?action=portfolio&success=assigned&count=' . $updated);
        exit;
    }

    // ─── Reports Center ───────────────────────────────────────────────────────

    public function reportsIndex(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        try {
            $reportsStmt = $this->pdo->query(
                'SELECT r.*, u.username AS generator_name
                 FROM generated_reports r
                 LEFT JOIN users u ON u.id = r.generated_by
                 ORDER BY r.generated_at DESC'
            );
            $reports = $reportsStmt ? $reportsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            error_log('reportsIndex reports query error: ' . $e->getMessage());
            $reports = [];
        }

        // WP-configured sites for per-site health report selector
        try {
            $wpSitesStmt = $this->pdo->query(
                'SELECT w.id, w.domain, h.name AS client_name
                 FROM websites w
                 INNER JOIN wordpress_sites ws ON ws.website_id = w.id AND ws.is_active = 1
                 LEFT JOIN hosting h ON h.id = w.hosting_id
                 ORDER BY w.domain ASC'
            );
            $wpSitesForReport = $wpSitesStmt ? $wpSitesStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            error_log('reportsIndex wpSites query error: ' . $e->getMessage());
            $wpSitesForReport = [];
        }

        require APP_PATH . '/views/reports/index.php';
    }

    public function reportsGenerate(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=reports');
            exit;
        }

        $type   = $_POST['report_type'] ?? 'portfolio_summary';
        $format = strtolower($_POST['report_format'] ?? 'xlsx');
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $from   = sm_normalize_date($_POST['date_from'] ?? null);
        $to     = sm_normalize_date($_POST['date_to'] ?? null);
        $serviceTypeFilter = trim((string)($_POST['service_type_filter'] ?? 'all'));
        if (!in_array($serviceTypeFilter, ['all', 'domain', 'hosting_web', 'hosting_mail'], true)) {
            $serviceTypeFilter = 'all';
        }
        $websiteId = isset($_POST['website_id']) && ctype_digit((string)$_POST['website_id'])
            ? (int)$_POST['website_id'] : null;

        if (!in_array($format, ['xlsx', 'pdf'], true)) {
            $format = 'xlsx';
        }

        if ($title === '') {
            $title = ucwords(str_replace('_', ' ', $type)) . ' — ' . sm_format_date(date('Y-m-d'));
        }

        // Build report data based on type
        [$rows, $headers] = $this->buildReportData($type, $from ?: null, $to ?: null, $serviceTypeFilter, $websiteId);

        // Write file
        $exportsDir = APP_PATH . '/exports/';
        if (!is_dir($exportsDir)) {
            mkdir($exportsDir, 0755, true);
        }

        $slug     = preg_replace('/[^a-z0-9]+/', '_', strtolower($type));
        $filename = $slug . '_' . date('Ymd_His') . '.' . $format;
        $filepath = $exportsDir . $filename;

        if ($format === 'xlsx') {
            $this->writeReportXlsx($filepath, $headers, $rows);
        } else {
            $this->writeReportPdf($filepath, $title, $headers, $rows);
        }

        $fileSize = file_exists($filepath) ? filesize($filepath) : 0;

        // Store metadata
        $stmt = $this->pdo->prepare(
            'INSERT INTO generated_reports
             (report_type, report_format, title, description, generated_by,
              generated_at, date_from, date_to, file_path, file_size_bytes, status)
             VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $type, $format, $title, $desc,
            $_SESSION['user_id'],
            $from ?: null, $to ?: null,
            $filename,
            $fileSize,
            'GENERATED',
        ]);

        $generatedReportId = (int)$this->pdo->lastInsertId();
        if ($this->tableExists('report_runs')) {
            $runStmt = $this->pdo->prepare(
                'INSERT INTO report_runs
                 (generated_report_id, client_id, report_type, report_format, service_type_filter,
                  filters_json, generated_by, generated_at, file_path, file_size_bytes, status)
                 VALUES (?, NULL, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)'
            );
            $runStmt->execute([
                $generatedReportId,
                $type,
                $format,
                $serviceTypeFilter === 'all' ? null : $serviceTypeFilter,
                json_encode([
                    'date_from' => $from ?: null,
                    'date_to' => $to ?: null,
                    'service_type_filter' => $serviceTypeFilter,
                ]),
                $_SESSION['user_id'],
                $filename,
                $fileSize,
                'GENERATED',
            ]);
        }

        $this->logTask('report_generation', ucwords(str_replace('_', ' ', $type)) . ' (' . strtoupper($format) . ')', 'completed', [
            'report_type' => $type, 'format' => $format, 'file' => $filename
        ]);

        header('Location: index.php?action=reports&success=generated');
        exit;
    }

    public function reportsDownload(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM generated_reports WHERE id = ?');
        $stmt->execute([$id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            header('Location: index.php?action=reports&error=not_found');
            exit;
        }

        $filepath = APP_PATH . '/exports/' . $report['file_path'];
        if (!file_exists($filepath)) {
            header('Location: index.php?action=reports&error=file_missing');
            exit;
        }

        // Update download count
        $this->pdo->prepare(
            'UPDATE generated_reports SET download_count = download_count + 1, last_accessed_at = NOW() WHERE id = ?'
        )->execute([$id]);

        $ext = strtolower(pathinfo($report['file_path'], PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf'  => 'application/pdf',
            'csv'  => 'text/csv',
            'html' => 'text/html',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($report['file_path']) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    public function reportsDelete(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $stmt = $this->pdo->prepare('SELECT file_path FROM generated_reports WHERE id = ?');
        $stmt->execute([$id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($report) {
            $filepath = APP_PATH . '/exports/' . $report['file_path'];
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
            $this->pdo->prepare('DELETE FROM generated_reports WHERE id = ?')->execute([$id]);
        }

        header('Location: index.php?action=reports&success=deleted');
        exit;
    }

    private function buildReportData(string $type, ?string $from, ?string $to, string $serviceTypeFilter = 'all', ?int $websiteId = null): array
    {
        $hasServiceType = $this->hasTableColumn('websites', 'service_type');
        $serviceTypeExpr = $hasServiceType ? 'w.service_type' : "'hosting_web'";
        $serviceTypeWhere = '';
        $serviceTypeParams = [];
        if ($hasServiceType && $serviceTypeFilter !== 'all') {
            $serviceTypeWhere = ' AND w.service_type = ?';
            $serviceTypeParams[] = $serviceTypeFilter;
        }

        switch ($type) {
            case 'expiry_report':
                $where = 'WHERE w.expiry_date IS NOT NULL';
                $params = [];
                if ($from) { $where .= ' AND w.expiry_date >= ?'; $params[] = $from; }
                if ($to)   { $where .= ' AND w.expiry_date <= ?'; $params[] = $to; }
                $where .= $serviceTypeWhere;
                $params = array_merge($params, $serviceTypeParams);
                $stmt = $this->pdo->prepare(
                    "SELECT w.domain, h.name AS client, w.status,
                            $serviceTypeExpr AS service_type,
                            w.expiry_date, DATEDIFF(w.expiry_date, CURDATE()) AS days_left,
                            w.health_score, w.is_healthy
                     FROM websites w
                     LEFT JOIN hosting h ON h.id = w.hosting_id
                     $where
                     ORDER BY w.expiry_date ASC"
                );
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as &$row) {
                    $row['expiry_date'] = sm_format_date($row['expiry_date'] ?? '', '');
                }
                unset($row);
                $headers = ['Domain', 'Client', 'Status', 'Service Type', 'Expiry Date', 'Days Left', 'Health Score', 'Healthy'];
                return [$rows, $headers];

            case 'health_report':
                $healthWhere = 'WHERE 1=1' . $serviceTypeWhere;
                $healthParams = $serviceTypeParams;
                if ($websiteId !== null) {
                    $healthWhere .= ' AND w.id = ?';
                    $healthParams[] = $websiteId;
                }
                $stmt = $this->pdo->prepare(
                    "SELECT w.domain, h.name AS client, w.status,
                            $serviceTypeExpr AS service_type,
                            COALESCE(m.health_score, w.health_score, 0)   AS score,
                            COALESCE(m.uptime_percent, wd.uptime_percent, 0)  AS uptime,
                            COALESCE(m.security_score, 0)                  AS security,
                            COALESCE(m.performance_score, 0)               AS performance,
                            wd.wordpress_version,
                            wd.php_version,
                            wd.mysql_version,
                            wd.theme_name,
                            wd.memory_limit,
                            wd.debug_mode,
                            wd.active_plugin_count,
                            wd.wordfence_installed,
                            wd.wp_version_outdated,
                            wd.page_load_time_ms,
                            wd.backup_enabled,
                            wd.ssl_valid,
                            wd.security_issues_count,
                            m.recorded_at AS last_check
                     FROM websites w
                     LEFT JOIN hosting h ON h.id = w.hosting_id
                     LEFT JOIN (
                         SELECT website_id, health_score, uptime_percent, security_score, performance_score, recorded_at
                         FROM health_metrics
                         WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
                     ) m ON m.website_id = w.id
                     LEFT JOIN wordpress_sites ws ON ws.website_id = w.id AND ws.is_active = 1
                     LEFT JOIN (
                         SELECT wordpress_site_id, MAX(id) AS latest_id FROM wordpress_diagnostics GROUP BY wordpress_site_id
                     ) wd_max ON wd_max.wordpress_site_id = ws.id
                     LEFT JOIN wordpress_diagnostics wd ON wd.id = wd_max.latest_id
                     $healthWhere
                     ORDER BY score ASC"
                );
                $stmt->execute($healthParams);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as &$row) {
                    $row['last_check']        = sm_format_datetime($row['last_check'] ?? '', false, '');
                    $row['debug_mode']        = $row['debug_mode'] !== null ? ($row['debug_mode'] ? 'Yes' : 'No') : '';
                    $row['wordfence_installed'] = $row['wordfence_installed'] !== null ? ($row['wordfence_installed'] ? 'Yes' : 'No') : '';
                    $row['wp_version_outdated'] = $row['wp_version_outdated'] !== null ? ($row['wp_version_outdated'] ? 'Yes' : 'No') : '';
                    $row['backup_enabled']    = $row['backup_enabled'] !== null ? ($row['backup_enabled'] ? 'Yes' : 'No') : '';
                    $row['ssl_valid']         = $row['ssl_valid'] !== null ? ($row['ssl_valid'] ? 'Valid' : 'Invalid') : '';
                }
                unset($row);
                $headers = [
                    'Domain', 'Client', 'Status', 'Service Type',
                    'Score', 'Uptime %', 'Security', 'Performance',
                    'WP Version', 'PHP', 'MySQL', 'Theme', 'Memory',
                    'Debug', 'Active Plugins', 'Wordfence', 'WP Outdated',
                    'Page Load (ms)', 'Backups', 'SSL', 'Security Issues',
                    'Last Check',
                ];
                return [$rows, $headers];

            case 'automation_report':
                $stmt = $this->pdo->query(
                    'SELECT r.name AS rule, r.trigger_type, r.action_type,
                            r.execution_count AS total_runs,
                            r.last_executed_at, r.is_active,
                            COUNT(e.id) AS recent_runs
                     FROM automation_rules r
                     LEFT JOIN automation_rule_executions e ON e.rule_id = r.id
                     GROUP BY r.id
                     ORDER BY r.execution_count DESC'
                );
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as &$row) {
                    $row['last_executed_at'] = sm_format_datetime($row['last_executed_at'] ?? '', false, '');
                }
                unset($row);
                $headers = ['Rule', 'Trigger Type', 'Action Type', 'Total Runs', 'Last Executed', 'Active', 'Recent Runs'];
                return [$rows, $headers];

            case 'portfolio_summary':
            default:
                $stmt = $this->pdo->prepare(
                    "SELECT w.domain, h.name AS client, w.status,
                            $serviceTypeExpr AS service_type,
                            w.expiry_date, DATEDIFF(w.expiry_date, CURDATE()) AS days_left,
                            COALESCE(m.health_score, w.health_score, 0) AS health_score,
                            CASE
                                WHEN DATEDIFF(w.expiry_date, CURDATE()) < 0 THEN 'Expired'
                                WHEN DATEDIFF(w.expiry_date, CURDATE()) <= 14 THEN 'Critical'
                                WHEN COALESCE(m.health_score, w.health_score, 0) < 60 THEN 'Warning'
                                ELSE 'OK'
                            END AS alert,
                            m.recorded_at AS last_check
                     FROM websites w
                     LEFT JOIN hosting h ON h.id = w.hosting_id
                     LEFT JOIN (
                         SELECT website_id, health_score, recorded_at
                         FROM health_metrics
                         WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
                     ) m ON m.website_id = w.id
                     WHERE 1=1 $serviceTypeWhere
                     ORDER BY health_score ASC"
                );
                $stmt->execute($serviceTypeParams);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as &$row) {
                    $row['expiry_date'] = sm_format_date($row['expiry_date'] ?? '', '');
                    $row['last_check'] = sm_format_datetime($row['last_check'] ?? '', false, '');
                }
                unset($row);
                $headers = ['Domain', 'Client', 'Status', 'Service Type', 'Expiry Date', 'Days Left', 'Health Score', 'Alert', 'Last Check'];
                return [$rows, $headers];
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ─── Notification Log ────────────────────────────────────────────────────

    public function notificationsIndex(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $allowedEventTypes = [
            'expiry_notification', 'status_notification', 'renewal_notification',
            'expiry_scaduto', 'expiry_30-day', 'expiry_15-day', 'expiry_1-day',
        ];
        $allowedStatuses = ['sent', 'failed', 'queued'];

        $eventType = $_GET['event_type'] ?? '';
        $status    = $_GET['status'] ?? '';
        $dateFrom  = $_GET['date_from'] ?? '';
        $dateTo    = $_GET['date_to'] ?? '';

        // Sanitise inputs
        if ($eventType && !in_array($eventType, $allowedEventTypes, true)) $eventType = '';
        if ($status    && !in_array($status,    $allowedStatuses,   true)) $status    = '';
        $dateFrom = sm_normalize_date($dateFrom, '') ?? '';
        $dateTo   = sm_normalize_date($dateTo, '') ?? '';

        $notifications = [];

        if ($this->tableExists('notification_events')) {
            $where  = ['1=1'];
            $params = [];

            if ($eventType) { $where[] = 'ne.event_type = ?';              $params[] = $eventType; }
            if ($status)    { $where[] = 'ne.status = ?';                  $params[] = $status;    }
            if ($dateFrom)  { $where[] = 'DATE(ne.created_at) >= ?';       $params[] = $dateFrom;  }
            if ($dateTo)    { $where[] = 'DATE(ne.created_at) <= ?';       $params[] = $dateTo;    }

            $whereStr = implode(' AND ', $where);
            $stmt = $this->pdo->prepare(
                "SELECT ne.*,
                        w.domain,
                        COALESCE(h.hosting_server, '—') AS client_name
                 FROM notification_events ne
                 LEFT JOIN websites w  ON w.id = ne.website_id
                 LEFT JOIN hosting  h  ON h.id = ne.client_id
                 WHERE $whereStr
                 ORDER BY ne.created_at DESC
                 LIMIT 1000"
            );
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $filters = compact('eventType', 'status', 'dateFrom', 'dateTo');
        require APP_PATH . '/views/notifications/index.php';
    }

    // ─── Import / Export Hub ─────────────────────────────────────────────────

    public function importExportIndex(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // Recent task_queue entries for this user (last 20)
        $recentTasks = [];
        if ($this->tableExists('task_queue')) {
            $uid  = (int)$_SESSION['user_id'];
            $stmt = $this->pdo->prepare(
                "SELECT * FROM task_queue
                 WHERE created_by = ?
                 ORDER BY created_at DESC
                 LIMIT 20"
            );
            $stmt->execute([$uid]);
            $recentTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Recent Google Sheets sync/export/import/merge audit records (last 20)
        $recentSyncJobs = [];
        if ($this->tableExists('google_sheets_sync_audit')) {
            $stmt = $this->pdo->query(
                "SELECT id, executed_at, direction, conflict_policy, dry_run,
                        added_to_db, updated_in_db, added_to_google, updated_in_google,
                        conflicts_detected, error_count
                 FROM google_sheets_sync_audit
                 ORDER BY executed_at DESC
                 LIMIT 20"
            );
            $recentSyncJobs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        $googleSheetSettings = (new SettingsModel($this->pdo))->getGoogleSheetsSettings();
        $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';

        require APP_PATH . '/views/import_export/index.php';
    }

    public function importExportExportHosting(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $rows = $this->pdo->query(
            "SELECT h.hosting_server AS client, h.email, h.expiry_date, h.status,
                    COUNT(w.id) AS services
             FROM hosting h
             LEFT JOIN websites w ON w.hosting_id = h.id
             GROUP BY h.id
             ORDER BY h.hosting_server"
        )->fetchAll(PDO::FETCH_ASSOC);

        $filename = 'hosting_export_' . date('Ymd_His') . '.xlsx';
        $filepath = EXPORT_PATH . '/' . $filename;

        $headers = ['Client', 'Email', 'Expiry Date', 'Status', 'Services'];
        $this->writeReportXlsx($filepath, $headers, $rows);

        $this->logTask('export_hosting', 'Export Hosting Clients', 'completed', [
            'rows' => count($rows), 'file' => $filename
        ]);

        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        @unlink($filepath);
        exit;
    }

    public function importExportExportNotifications(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $rows = [];
        if ($this->tableExists('notification_events')) {
            $rows = $this->pdo->query(
                "SELECT ne.created_at, w.domain, ne.event_type, ne.channel,
                        ne.severity, ne.status, ne.sent_at
                 FROM notification_events ne
                 LEFT JOIN websites w ON w.id = ne.website_id
                 ORDER BY ne.created_at DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="notifications_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Domain', 'Event', 'Channel', 'Severity', 'Status', 'Sent At']);
        foreach ($rows as $r) {
            $r['created_at'] = sm_format_datetime($r['created_at'] ?? '', false, '');
            $r['sent_at'] = sm_format_datetime($r['sent_at'] ?? '', false, '');
            fputcsv($out, array_values($r));
        }
        fclose($out);
        exit;
    }

    // ─── Task Queue ───────────────────────────────────────────────────────────

    public function tasksIndex(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $tasks = [];
        if ($this->tableExists('task_queue')) {
            $allowedStatuses = ['pending', 'running', 'completed', 'failed'];
            $allowedTypes    = [
                'export_websites', 'import_websites', 'export_hosting',
                'report_generation', 'cron_run',
            ];

            $statusFilter = $_GET['status'] ?? '';
            $typeFilter   = $_GET['type'] ?? '';

            if ($statusFilter && !in_array($statusFilter, $allowedStatuses, true)) $statusFilter = '';
            if ($typeFilter   && !in_array($typeFilter,   $allowedTypes,    true)) $typeFilter   = '';

            $where  = ['1=1'];
            $params = [];
            if ($statusFilter) { $where[] = 't.status = ?'; $params[] = $statusFilter; }
            if ($typeFilter)   { $where[] = 't.type = ?';   $params[] = $typeFilter;   }

            $stmt = $this->pdo->prepare(
                "SELECT t.*, u.username AS user_name
                 FROM task_queue t
                 LEFT JOIN users u ON u.id = t.created_by
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY t.created_at DESC
                 LIMIT 500"
            );
            $stmt->execute($params);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $filters = ['status' => $statusFilter ?? '', 'type' => $typeFilter ?? ''];
        require APP_PATH . '/views/tasks/index.php';
    }

    /**
     * Internal helper — write a row to task_queue (best-effort, never fatal).
     */
    private function logTask(
        string $type,
        string $label,
        string $status = 'completed',
        ?array $result = null,
        ?string $error = null
    ): void {
        try {
            if (!$this->tableExists('task_queue')) return;
            $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $stmt = $this->pdo->prepare(
                "INSERT INTO task_queue
                 (type, label, status, created_by, started_at, completed_at, progress, result_json, error_message)
                 VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)"
            );
            $stmt->execute([
                $type, $label, $status, $uid,
                $status === 'completed' ? 100 : 0,
                $result ? json_encode($result) : null,
                $error,
            ]);
        } catch (Exception $e) {
            error_log("logTask error: " . $e->getMessage());
        }
    }

    // ─── API Keys ─────────────────────────────────────────────────────────────

    public function apiKeysIndex(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $keys = [];
        if ($this->tableExists('api_keys')) {
            $stmt = $this->pdo->query(
                "SELECT k.*, u.username AS created_by_name
                 FROM api_keys k
                 LEFT JOIN users u ON u.id = k.created_by
                 ORDER BY k.created_at DESC"
            );
            $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $newKey = $_SESSION['new_api_key'] ?? null;
        unset($_SESSION['new_api_key']);

        require APP_PATH . '/views/api_keys/index.php';
    }

    public function apiKeysCreate(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=api_keys');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['error'] = 'Key name is required.';
            header('Location: index.php?action=api_keys');
            exit;
        }
        $name = substr($name, 0, 100);

        // Allowed scopes whitelist
        $allowedScopes = ['read_websites', 'export_data', 'read_reports', 'read_notifications'];
        $requestedScopes = $_POST['scopes'] ?? [];
        $scopes = is_array($requestedScopes)
            ? array_values(array_intersect($requestedScopes, $allowedScopes))
            : [];

        $expiresAt = null;
        if (!empty($_POST['expires_at'])) {
            $d = sm_parse_date_value($_POST['expires_at']);
            $expiresAt = $d ? $d->format('Y-m-d') : null;
        }

        // Generate: prefix (8 hex) + secret (40 hex) = "fm_<prefix>_<secret>"
        $prefix = substr(bin2hex(random_bytes(4)), 0, 8);
        $secret = bin2hex(random_bytes(20));
        $fullKey = 'fm_' . $prefix . '_' . $secret;
        $keyHash = hash('sha256', $fullKey);

        if ($this->tableExists('api_keys')) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO api_keys (name, key_prefix, key_hash, scopes_json, expires_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $name, $prefix, $keyHash,
                !empty($scopes) ? json_encode($scopes) : null,
                $expiresAt,
                (int)$_SESSION['user_id'],
            ]);
        }

        // Show key once via session flash
        $_SESSION['new_api_key'] = ['key' => $fullKey, 'name' => $name];
        header('Location: index.php?action=api_keys');
        exit;
    }

    public function apiKeysRevoke(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($this->tableExists('api_keys')) {
            $stmt = $this->pdo->prepare(
                "UPDATE api_keys SET is_active = 0 WHERE id = ?"
            );
            $stmt->execute([$id]);
        }

        $_SESSION['message'] = 'API key revoked.';
        header('Location: index.php?action=api_keys');
        exit;
    }

    public function apiKeysDelete(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($this->tableExists('api_keys')) {
            $stmt = $this->pdo->prepare("DELETE FROM api_keys WHERE id = ?");
            $stmt->execute([$id]);
        }

        $_SESSION['message'] = 'API key deleted.';
        header('Location: index.php?action=api_keys');
        exit;
    }

    private function hasTableColumn(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function writeReportXlsx(string $filepath, array $headers, array $rows): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $col => $header) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue($column . '1', $header);
        }

        $rowIndex = 2;
        foreach ($rows as $row) {
            foreach (array_values($row) as $col => $value) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
                $sheet->setCellValue($column . $rowIndex, (string)($value ?? ''));
            }
            $rowIndex++;
        }

        for ($i = 1; $i <= count($headers); $i++) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);
    }

    private function writeReportPdf(string $filepath, string $title, array $headers, array $rows): void
    {
        $th = implode('', array_map(fn($h) => '<th>' . htmlspecialchars((string)$h) . '</th>', $headers));
        $tbody = '';
        foreach ($rows as $row) {
            $tds = implode('', array_map(fn($v) => '<td>' . htmlspecialchars((string)($v ?? '')) . '</td>', array_values($row)));
            $tbody .= "<tr>$tds</tr>";
        }
        $generated = sm_format_datetime(date('Y-m-d H:i'));
        $count = count($rows);
        $safeTitle = htmlspecialchars($title);
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>{$safeTitle}</title>
<style>
  body{font-family:Arial,sans-serif;margin:2rem;color:#333;}
  h1{font-size:1.4rem;margin-bottom:.25rem;}
  .meta{color:#666;font-size:.85rem;margin-bottom:1.5rem;}
  table{width:100%;border-collapse:collapse;font-size:.9rem;}
  th{background:#17a2b8;color:#fff;padding:.5rem .75rem;text-align:left;}
  td{padding:.4rem .75rem;border-bottom:1px solid #dee2e6;}
  tr:nth-child(even) td{background:#f8f9fa;}
</style>
</head>
<body>
<h1>{$safeTitle}</h1>
<p class="meta">Generated: {$generated} &nbsp;|&nbsp; {$count} records</p>
<table><thead><tr>{$th}</tr></thead><tbody>{$tbody}</tbody></table>
</body></html>
HTML;

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    file_put_contents($filepath, $dompdf->output());
    }
}
