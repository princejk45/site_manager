<?php
/**
 * Diagnostics Service
 * Orchestrates fetching, normalizing, and storing diagnostics data
 * Handles all error cases per-site isolation
 */
class DiagnosticsService
{
    private $pdo;
    private $wordPressSiteModel;
    private $apiClient;

    public function __construct($pdo, $apiClient = null)
    {
        $this->pdo = $pdo;
        $this->wordPressSiteModel = new WordPressSite($pdo);
        $this->apiClient = $apiClient ?? new WordPressApiClient();
    }

    /**
     * Fetch diagnostics for a WordPress site
     * Main orchestration method - handles all errors, stores results
     *
     * @param int  $websiteId        ID from websites table
     * @param bool $scoreHealthMetrics  Write composite score to health_metrics.
     *                                  Pass false when the caller (e.g. DiagnosticsController)
     *                                  will score using its own HealthScoreCalculator instance
     *                                  (which has audit-trail context) to avoid a double-write.
     * @return array Result with status and data
     */
    public function fetchDiagnostics($websiteId, bool $scoreHealthMetrics = true)
    {
        $result = [
            'success' => false,
            'status' => 'unknown',
            'website_id' => $websiteId,
            'timestamp' => date('c'),
            'error' => null,
            'data' => null,
            'diagnostics' => null,
        ];

        try {
            // Load WordPress site config
            $wpSiteConfig = $this->wordPressSiteModel->getByWebsiteId($websiteId);

            if (!$wpSiteConfig) {
                $result['status'] = 'unreachable';
                $result['error'] = 'WordPress site not configured for this website';
                return $result;
            }

            if (!$wpSiteConfig['is_active']) {
                $result['status'] = 'degraded';
                $result['error'] = 'API connection is disabled';
                return $result;
            }

            // Fetch from WordPress API
            $apiResponse = $this->apiClient->fetchDiagnostics(
                $wpSiteConfig['wordpress_url'],
                $wpSiteConfig['api_key']
            );

            // Validate response structure
            DiagnosticsNormalizer::validate($apiResponse['data']);

            // Normalize response
            $normalized = DiagnosticsNormalizer::normalize($apiResponse['data']);

            // Extract fields for storage
            $storageData = DiagnosticsNormalizer::extractForStorage(
                $normalized,
                $apiResponse['data']
            );

            // Add API metadata
            $storageData['http_status_code'] = $apiResponse['http_code'];
            $storageData['fetch_duration_ms'] = $apiResponse['duration_ms'];

            // Store in database
            $this->wordPressSiteModel->storeDiagnostics($wpSiteConfig['id'], $storageData);

            // Store security issues if present
            $latestDiag = $this->wordPressSiteModel->getLatestDiagnostics($wpSiteConfig['id']);
            $this->storeSecurityIssues($wpSiteConfig['id'], $latestDiag['id'], $normalized);

            // ── Composite health score ─────────────────────────────────────────
            // Skipped when $scoreHealthMetrics is false (caller handles scoring).
            if ($scoreHealthMetrics) {
                $this->storeCompositeHealthScore($websiteId, $normalized);
            }

            // ── Auto bug reports ───────────────────────────────────────────────
            // Run BugReportGenerator so bug_reports_auto is kept current on every
            // fetch (scheduled or on-demand), not just when analyze() is called.
            $this->generateAutoBugReports($websiteId, $normalized);

            // Update success status
            $this->wordPressSiteModel->updateFetchStatus($wpSiteConfig['id'], 'healthy');

            $result['success'] = true;
            $result['status'] = 'healthy';
            $result['data'] = $normalized;
            $result['diagnostics'] = $storageData;
            $result['wpsite_id'] = $wpSiteConfig['id'];

            return $result;

        } catch (WordPressAuthenticationException $e) {
            return $this->handleException($websiteId, $e, 'auth_failed', 'API key is invalid or expired');
        } catch (WordPressForbiddenException $e) {
            return $this->handleException($websiteId, $e, 'auth_failed', 'API key lacks required permissions');
        } catch (WordPressTimeoutException $e) {
            return $this->handleException($websiteId, $e, 'timeout', 'Request timed out after 15 seconds');
        } catch (WordPressUnreachableException $e) {
            return $this->handleException($websiteId, $e, 'unreachable', 'WordPress site is not responding');
        } catch (WordPressInvalidResponseException $e) {
            return $this->handleException($websiteId, $e, 'invalid_response', 'WordPress returned unexpected response format');
        } catch (WordPressNetworkException $e) {
            return $this->handleException($websiteId, $e, 'unreachable', 'Network error: ' . $e->getMessage());
        } catch (WordPressConfigurationException $e) {
            return $this->handleException($websiteId, $e, 'unreachable', 'Configuration error: ' . $e->getMessage());
        } catch (Exception $e) {
            return $this->handleException($websiteId, $e, 'degraded', 'Unexpected error: ' . $e->getMessage());
        }
    }

    /**
     * Handle exceptions consistently
     */
    private function handleException($websiteId, Exception $e, $status, $errorMessage)
    {
        // Try to update status in DB if we have wpsite ID
        $wpSiteConfig = $this->wordPressSiteModel->getByWebsiteId($websiteId);
        if ($wpSiteConfig) {
            $this->wordPressSiteModel->updateFetchStatus($wpSiteConfig['id'], $status, $errorMessage);
        }

        return [
            'success' => false,
            'status' => $status,
            'website_id' => $websiteId,
            'timestamp' => date('c'),
            'error' => $errorMessage,
            'data' => null,
            'diagnostics' => null,
        ];
    }

    /**
     * Extract and store security issues from diagnostics
     */
    private function storeSecurityIssues($wpSiteId, $diagnosticsId, $normalized)
    {
        $security = $normalized['security'] ?? [];

        $issues = [];

        if ($security['wp_config_writable'] ?? false) {
            $issues[] = ['category' => 'wp_config_writable', 'description' => 'wp-config.php is writable', 'severity' => 'critical'];
        }

        if ($security['xmlrpc_enabled'] ?? false) {
            $issues[] = ['category' => 'xmlrpc_enabled', 'description' => 'XML-RPC is enabled', 'severity' => 'warning'];
        }

        if ($security['debug_mode'] ?? false) {
            $issues[] = ['category' => 'debug_mode', 'description' => 'WordPress debug mode is enabled', 'severity' => 'warning'];
        }

        if ($security['directory_listing'] ?? false) {
            $issues[] = ['category' => 'directory_listing', 'description' => 'Directory listing is enabled', 'severity' => 'warning'];
        }

        if ($security['default_admin_user'] ?? false) {
            $issues[] = ['category' => 'default_admin_user', 'description' => 'Default admin user still exists', 'severity' => 'info'];
        }

        foreach ($issues as $issue) {
            $this->wordPressSiteModel->storeSecurityIssue(
                $diagnosticsId,
                $wpSiteId,
                $issue['category'],
                $issue['description'],
                $issue['severity']
            );
        }
    }

    /**
     * Compute and persist a composite health score from freshly-normalised data.
     * Wrapped in try/catch so a health_metrics write failure never aborts the
     * main diagnostics fetch.
     *
     * @param int   $websiteId  ID from the websites table (used by HealthScoreCalculator)
     * @param array $normalized Normalised diagnostics array from DiagnosticsNormalizer::normalize()
     */
    private function storeCompositeHealthScore($websiteId, array $normalized)
    {
        try {
            require_once APP_PATH . '/services/Health/HealthScoreCalculator.php';

            // auditTrail is passed as null — storeHealthMetrics() must null-check it
            $scorer = new HealthScoreCalculator($this->pdo, null);
            $scorer->calculateScore($websiteId, $normalized);
        } catch (Exception $e) {
            error_log('DiagnosticsService: HealthScoreCalculator failed for website ' . $websiteId . ' — ' . $e->getMessage());
        }
    }
    /**
     * Run BugReportGenerator against fresh normalised data.
     * Wrapped in try/catch so a write failure never aborts the main fetch.
     *
     * @param int   $websiteId
     * @param array $normalized
     */
    private function generateAutoBugReports($websiteId, array $normalized)
    {
        try {
            require_once APP_PATH . '/services/Diagnostics/BugReportGenerator.php';
            // No audit trail or user context needed for automated background runs
            $generator = new BugReportGenerator($this->pdo, null, 1);
            $generator->generateReports($websiteId, $normalized);
        } catch (Exception $e) {
            error_log('DiagnosticsService: BugReportGenerator failed for website ' . $websiteId . ' — ' . $e->getMessage());
        }
    }
    /**
     * Get diagnostics for display
     */
    public function getDiagnosticsForDisplay($websiteId)
    {
        $wpSiteConfig = $this->wordPressSiteModel->getByWebsiteId($websiteId);

        if (!$wpSiteConfig) {
            return null;
        }

        $latest = $this->wordPressSiteModel->getLatestDiagnostics($wpSiteConfig['id']);
        $history = $this->wordPressSiteModel->getDiagnosticsHistory($wpSiteConfig['id'], 10);

        if (!$latest) {
            return [
                'config' => $wpSiteConfig,
                'latest' => null,
                'history' => [],
                'security_issues' => []
            ];
        }

        // Decode raw payload
        $rawPayload = json_decode($latest['raw_payload'], true) ?? [];

        // Normalize for display
        $displayData = [
            'config' => $wpSiteConfig,
            'latest' => $latest,
            'normalized' => DiagnosticsNormalizer::normalize($rawPayload),
            'history' => $history,
            'security_issues' => $this->wordPressSiteModel->getSecurityIssues($latest['id'])
        ];

        return $displayData;
    }
}
