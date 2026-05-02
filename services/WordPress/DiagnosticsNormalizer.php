<?php
/**
 * Diagnostics Normalizer
 * Transforms WordPress API response into stable internal data model
 */
class DiagnosticsNormalizer
{
    /**
     * Normalize raw diagnostics response
     * 
     * @param array $rawResponse Raw response from WordPress API
     * @return array Normalized diagnostics data
     * @throws WordPressInvalidResponseException
     */
    public static function normalize($rawResponse)
    {
        if (!is_array($rawResponse)) {
            throw new WordPressInvalidResponseException('Response must be an array');
        }

        // Normalize diagnostics section
        $diagnostics = $rawResponse['diagnostics'] ?? [];
        $security = $rawResponse['security'] ?? [];
        $health = $rawResponse['health'] ?? [];
        $wordfence = $rawResponse['wordfence'] ?? [];

        // Extract and normalize core fields
        $normalized = [
            'site_name' => (string)($diagnostics['site_name'] ?? 'Unknown'),
            'site_url' => (string)($diagnostics['site_url'] ?? ''),
            'wordpress_version' => self::normalizeVersion($diagnostics['wordpress_version'] ?? null),
            'php_version' => self::normalizeVersion($diagnostics['php_version'] ?? null),
            'mysql_version' => self::normalizeVersion($diagnostics['mysql_version'] ?? null),
            'theme_name' => (string)($diagnostics['theme']['name'] ?? $diagnostics['theme'] ?? 'Unknown'),
            'theme_version' => (string)($diagnostics['theme']['version'] ?? ''),
            'memory_limit' => (string)($diagnostics['memory_limit'] ?? ''),
            'debug_mode' => (bool)($diagnostics['debug_mode'] ?? false),
            'timestamp' => $diagnostics['timestamp'] ?? date('c'),
            
            // Security fields
            'security' => [
                'wp_config_writable' => (bool)($security['wp_config_writable'] ?? false),
                'xmlrpc_enabled' => (bool)($security['xmlrpc_enabled'] ?? false),
                'debug_mode' => (bool)($security['debug_mode'] ?? false),
                'directory_listing' => (bool)($security['directory_listing'] ?? false),
                'default_admin_user' => (bool)($security['default_admin_user'] ?? false),
            ],
            
            // Health fields
            'health' => [
                'score' => (int)($health['score'] ?? 0),
                'status' => self::normalizeHealthStatus($health['status'] ?? null),
                'issues' => $health['issues'] ?? [],
            ],
            
            // Wordfence
            'wordfence' => [
                'installed' => (bool)($wordfence['installed'] ?? false),
            ],
            
            // Active plugins
            'active_plugins' => self::normalizePlugins($diagnostics['active_plugins'] ?? []),
        ];

        return $normalized;
    }

    /**
     * Normalize version strings (handle nulls and empty)
     */
    private static function normalizeVersion($version)
    {
        if (empty($version)) {
            return null;
        }
        return (string)$version;
    }

    /**
     * Normalize health status to standard values
     */
    private static function normalizeHealthStatus($status)
    {
        if (empty($status)) {
            return 'unknown';
        }

        $status = strtolower((string)$status);
        
        // Map common statuses
        $validStatuses = ['healthy', 'degraded', 'critical', 'unknown'];
        
        if (in_array($status, $validStatuses)) {
            return $status;
        }

        // Default to degraded for unknown statuses
        return 'unknown';
    }

    /**
     * Normalize plugins array
     */
    private static function normalizePlugins($plugins)
    {
        if (!is_array($plugins)) {
            return [];
        }

        $normalized = [];
        foreach ($plugins as $plugin) {
            if (is_array($plugin)) {
                $normalized[] = [
                    'name' => (string)($plugin['name'] ?? 'Unknown'),
                    'version' => (string)($plugin['version'] ?? ''),
                    'slug' => (string)($plugin['slug'] ?? ''),
                ];
            }
        }

        return $normalized;
    }

    /**
     * Extract diagnostic fields for database storage
     */
    public static function extractForStorage($normalized, $rawResponse)
    {
        return [
            'wordpress_version' => $normalized['wordpress_version'],
            'php_version' => $normalized['php_version'],
            'mysql_version' => $normalized['mysql_version'],
            'theme_name' => $normalized['theme_name'],
            'memory_limit' => $normalized['memory_limit'],
            'debug_mode' => $normalized['debug_mode'] ? 1 : 0,
            'health_score' => $normalized['health']['score'],
            'health_status' => $normalized['health']['status'],
            'wordfence_installed' => $normalized['wordfence']['installed'] ? 1 : 0,
            'active_plugin_count' => count($normalized['active_plugins']),
            'raw_payload' => $rawResponse,  // Store raw for auditing
            'response_timestamp' => $normalized['timestamp'],
        ];
    }

    /**
     * Validate response has minimum required fields
     */
    public static function validate($rawResponse)
    {
        if (!is_array($rawResponse)) {
            throw new WordPressInvalidResponseException('Response must be an array');
        }

        // Check for at least diagnostics or health section
        if (empty($rawResponse['diagnostics']) && empty($rawResponse['health'])) {
            throw new WordPressInvalidResponseException('Response missing required sections (diagnostics, health)');
        }

        return true;
    }
}
