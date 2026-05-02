<?php
/**
 * WordPressSite Model
 * Handles database operations for WordPress site integration
 */
class WordPressSite
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get WordPress site config by website ID
     */
    public function getByWebsiteId($websiteId)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM wordpress_sites 
            WHERE website_id = ?
        ");
        $stmt->execute([$websiteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get WordPress site by ID
     */
    public function getById($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM wordpress_sites 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if WordPress site config exists for a website
     */
    public function exists($websiteId)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM wordpress_sites 
            WHERE website_id = ?
        ");
        $stmt->execute([$websiteId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Create or update WordPress site config
     */
    public function upsert($websiteId, $wordpressUrl, $apiKey, $isActive = 1)
    {
        if ($this->exists($websiteId)) {
            return $this->update($websiteId, $wordpressUrl, $apiKey, $isActive);
        } else {
            return $this->create($websiteId, $wordpressUrl, $apiKey, $isActive);
        }
    }

    /**
     * Create new WordPress site config
     */
    public function create($websiteId, $wordpressUrl, $apiKey, $isActive = 1)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wordpress_sites 
            (website_id, wordpress_url, api_key, is_active)
            VALUES (?, ?, ?, ?)
        ");

        return $stmt->execute([
            $websiteId,
            $wordpressUrl,
            $apiKey,
            $isActive ? 1 : 0
        ]);
    }

    /**
     * Update WordPress site config
     */
    public function update($websiteId, $wordpressUrl, $apiKey, $isActive = 1)
    {
        $stmt = $this->pdo->prepare("
            UPDATE wordpress_sites 
            SET wordpress_url = ?, api_key = ?, is_active = ?
            WHERE website_id = ?
        ");

        return $stmt->execute([
            $wordpressUrl,
            $apiKey,
            $isActive ? 1 : 0,
            $websiteId
        ]);
    }

    /**
     * Update API key for a WordPress site
     */
    public function updateApiKey($wordPressSiteId, $apiKey)
    {
        $stmt = $this->pdo->prepare("
            UPDATE wordpress_sites 
            SET api_key = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        return $stmt->execute([$apiKey, $wordPressSiteId]);
    }

    /**
     * Store fetch status and error after API call
     */
    public function updateFetchStatus($wordPressSiteId, $status, $errorMessage = null)
    {
        $stmt = $this->pdo->prepare("
            UPDATE wordpress_sites 
            SET last_fetch_status = ?, 
                last_fetch_error_message = ?, 
                last_fetch_timestamp = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        return $stmt->execute([
            $status,
            $errorMessage,
            $wordPressSiteId
        ]);
    }

    /**
     * Store diagnostics snapshot
     */
    public function storeDiagnostics($wordPressSiteId, $diagnosticsData)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wordpress_diagnostics 
            (wordpress_site_id, 
             wordpress_version,
             php_version,
             mysql_version,
             theme_name,
             memory_limit,
             debug_mode,
             health_score,
             health_status,
             wordfence_installed,
             active_plugin_count,
             raw_payload,
             fetch_method,
             fetch_duration_ms,
             http_status_code,
             response_timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $wordPressSiteId,
            $diagnosticsData['wordpress_version'] ?? null,
            $diagnosticsData['php_version'] ?? null,
            $diagnosticsData['mysql_version'] ?? null,
            $diagnosticsData['theme_name'] ?? null,
            $diagnosticsData['memory_limit'] ?? null,
            $diagnosticsData['debug_mode'] ?? 0,
            $diagnosticsData['health_score'] ?? null,
            $diagnosticsData['health_status'] ?? null,
            $diagnosticsData['wordfence_installed'] ?? 0,
            $diagnosticsData['active_plugin_count'] ?? 0,
            json_encode($diagnosticsData['raw_payload'] ?? []),
            $diagnosticsData['fetch_method'] ?? 'on_demand',
            $diagnosticsData['fetch_duration_ms'] ?? null,
            $diagnosticsData['http_status_code'] ?? null,
            $diagnosticsData['response_timestamp'] ?? null
        ]);
    }

    /**
     * Get latest diagnostics for a WordPress site
     */
    public function getLatestDiagnostics($wordPressSiteId)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM wordpress_diagnostics 
            WHERE wordpress_site_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$wordPressSiteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get diagnostics history for charting
     */
    public function getDiagnosticsHistory($wordPressSiteId, $limit = 20)
    {
        $stmt = $this->pdo->prepare("
            SELECT health_score, health_status, created_at 
            FROM wordpress_diagnostics 
            WHERE wordpress_site_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$wordPressSiteId, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Get active security issues for a diagnostics snapshot
     */
    public function getSecurityIssues($diagnosticsId)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM wordpress_security_issues 
            WHERE diagnostics_id = ?
            ORDER BY severity DESC, discovered_at DESC
        ");
        $stmt->execute([$diagnosticsId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Store security issue from diagnostics
     */
    public function storeSecurityIssue($diagnosticsId, $wordPressSiteId, $category, $description, $severity = 'info')
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wordpress_security_issues 
            (diagnostics_id, wordpress_site_id, issue_category, issue_description, severity)
            VALUES (?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $diagnosticsId,
            $wordPressSiteId,
            $category,
            $description,
            $severity
        ]);
    }

    /**
     * Log API key rotation
     */
    public function logKeyRotation($wordPressSiteId, $oldKeyMasked, $newKeyMasked, $reason = 'manual', $userId = null)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wordpress_api_key_rotation_log 
            (wordpress_site_id, old_key_masked, new_key_masked, rotation_reason, rotated_by)
            VALUES (?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $wordPressSiteId,
            $oldKeyMasked,
            $newKeyMasked,
            $reason,
            $userId
        ]);
    }

    /**
     * Get all WordPress sites for a website
     */
    public function getAllActive()
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM wordpress_sites 
            WHERE is_active = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete WordPress site config
     */
    public function delete($wordPressSiteId)
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM wordpress_sites 
            WHERE id = ?
        ");

        return $stmt->execute([$wordPressSiteId]);
    }
}
