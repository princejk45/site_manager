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
     * @param int $websiteId ID from websites table
     * @return array Result with status and data
     */
    public function fetchDiagnostics($websiteId)
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
