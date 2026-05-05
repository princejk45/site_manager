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

        // Normalize top-level sections
        $diagnostics  = $rawResponse['diagnostics']  ?? [];
        $security     = $rawResponse['security']     ?? [];
        $health       = $rawResponse['health']       ?? [];
        $wordfence    = $rawResponse['wordfence']    ?? [];
        $uptime       = $rawResponse['uptime']       ?? [];
        $performance  = $rawResponse['performance']  ?? [];
        $backups      = $rawResponse['backups']      ?? [];
        // plugins may live inside diagnostics or at top level
        $rawPlugins   = $rawResponse['plugins'] ?? $diagnostics['active_plugins'] ?? [];

        // ── Core site fields ──────────────────────────────────────────────────
        $normalized = [
            'site_name'         => (string)($diagnostics['site_name'] ?? 'Unknown'),
            'site_url'          => (string)($diagnostics['site_url'] ?? ''),
            'wordpress_version' => self::normalizeVersion($diagnostics['wordpress_version'] ?? null),
            'php_version'       => self::normalizeVersion($diagnostics['php_version'] ?? null),
            'mysql_version'     => self::normalizeVersion($diagnostics['mysql_version'] ?? null),
            'theme_name'        => (string)($diagnostics['theme']['name'] ?? $diagnostics['theme'] ?? 'Unknown'),
            'theme_version'     => (string)($diagnostics['theme']['version'] ?? ''),
            'memory_limit'      => (string)($diagnostics['memory_limit'] ?? ''),
            'debug_mode'        => (bool)($diagnostics['debug_mode'] ?? false),
            'timestamp'         => $diagnostics['timestamp'] ?? date('c'),

            // ── Security ─────────────────────────────────────────────────────
            // Handles both the simple flags from the basic schema and the rich
            // fields expected by HealthScoreCalculator / BugReportGenerator.
            'security' => [
                // Basic flags (simple schema)
                'wp_config_writable'  => (bool)($security['wp_config_writable'] ?? false),
                'xmlrpc_enabled'      => (bool)($security['xmlrpc_enabled']     ?? false),
                'debug_mode'          => (bool)($security['debug_mode']         ?? $diagnostics['debug_mode'] ?? false),
                'directory_listing'   => (bool)($security['directory_listing']  ?? false),
                'default_admin_user'  => (bool)($security['default_admin_user'] ?? false),

                // Rich fields (advanced schema)
                'ssl_certificate' => [
                    'valid'       => (bool)($security['ssl_certificate']['valid']       ?? $security['ssl_valid'] ?? true),
                    'expiry_date' => (string)($security['ssl_certificate']['expiry_date'] ?? $security['ssl_expiry_date'] ?? ''),
                    'expiry_days' => (function() use ($security) {
                        $raw = $security['ssl_certificate']['expiry_date'] ?? $security['ssl_expiry_date'] ?? '';
                        if (!$raw) return null;
                        $ts = strtotime($raw);
                        return $ts ? max(0, (int)ceil(($ts - time()) / 86400)) : null;
                    })(),
                ],
                'wp_version_outdated'        => (bool)($security['wp_version_outdated']   ?? false),
                'current_wp_version'         => (string)($security['current_wp_version']  ?? $diagnostics['wordpress_version'] ?? ''),
                'latest_wp_version'          => (string)($security['latest_wp_version']   ?? ''),
                'file_permissions_issues'    => (array)($security['file_permissions_issues'] ?? []),
                'weak_admin_passwords'       => (bool)($security['weak_admin_passwords']  ?? false),
                'known_vulnerabilities'      => (array)($security['known_vulnerabilities'] ?? []),
            ],

            // ── Health summary ────────────────────────────────────────────────
            'health' => [
                'score'  => (int)($health['score']  ?? 0),
                'status' => self::normalizeHealthStatus($health['status'] ?? null),
                'issues' => $health['issues'] ?? [],
            ],

            // ── Wordfence ─────────────────────────────────────────────────────
            'wordfence' => [
                'installed' => (bool)($wordfence['installed'] ?? false),
            ],

            // ── Uptime ────────────────────────────────────────────────────────
            'uptime' => [
                'uptime_percent'   => (float)($uptime['uptime_percent']   ?? 99.0),
                'response_time_ms' => (int)($uptime['response_time_ms']   ?? $uptime['avg_response_ms'] ?? 0),
            ],

            // ── Performance ───────────────────────────────────────────────────
            'performance' => [
                'page_load_time'        => (int)($performance['page_load_time']        ?? 0),    // ms
                'memory_usage_percent'  => (float)($performance['memory_usage_percent'] ?? 0),
                'database_fragmentation'=> (float)($performance['database_fragmentation'] ?? 0),
                'slow_queries_detected' => (bool)($performance['slow_queries_detected'] ?? false),
            ],

            // ── Backups ───────────────────────────────────────────────────────
            'backups' => [
                'backup_enabled'    => (bool)($backups['backup_enabled']    ?? false),
                'last_backup_time'  => (string)($backups['last_backup_time'] ?? ''),
                'failed_backups'    => (int)($backups['failed_backups']     ?? 0),
                'multiple_locations'=> (bool)($backups['multiple_locations'] ?? false),
                'encrypted'         => (bool)($backups['encrypted']         ?? false),
            ],

            // ── Plugins (unified) ─────────────────────────────────────────────
            'active_plugins' => self::normalizePlugins($rawPlugins),
            'plugins'        => self::normalizePlugins($rawPlugins),   // alias for scorer
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
                    'name'            => (string)($plugin['name']            ?? 'Unknown'),
                    'version'         => (string)($plugin['version']         ?? ''),
                    'slug'            => (string)($plugin['slug']            ?? ''),
                    'active'          => (bool)($plugin['active']            ?? true),
                    'outdated'        => (bool)($plugin['outdated']          ?? false),
                    'security_update' => (bool)($plugin['security_update']   ?? false),
                    'latest_version'  => (string)($plugin['latest_version']  ?? ''),
                ];
            } elseif (is_string($plugin)) {
                // Simple plugin name string — basic schema fallback
                $normalized[] = [
                    'name' => $plugin, 'version' => '', 'slug' => '',
                    'active' => true, 'outdated' => false, 'security_update' => false, 'latest_version' => '',
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
            // Core WP environment
            'wordpress_version'    => $normalized['wordpress_version'],
            'php_version'          => $normalized['php_version'],
            'mysql_version'        => $normalized['mysql_version'],
            'theme_name'           => $normalized['theme_name'],
            'memory_limit'         => $normalized['memory_limit'],
            'debug_mode'           => $normalized['debug_mode'] ? 1 : 0,

            // WP-reported health
            'health_score'         => $normalized['health']['score'],
            'health_status'        => $normalized['health']['status'],

            // Security checks (flattened for storage)
            'ssl_valid'            => $normalized['security']['ssl_certificate']['valid'] ? 1 : 0,
            'wp_version_outdated'  => $normalized['security']['wp_version_outdated'] ? 1 : 0,
            'security_issues_count'=> count($normalized['security']['known_vulnerabilities'])
                                     + count($normalized['security']['file_permissions_issues'])
                                     + ($normalized['security']['wp_config_writable']  ? 1 : 0)
                                     + ($normalized['security']['xmlrpc_enabled']      ? 1 : 0)
                                     + ($normalized['security']['default_admin_user']  ? 1 : 0),

            // Uptime
            'uptime_percent'       => $normalized['uptime']['uptime_percent'],
            'average_response_time_ms' => $normalized['uptime']['response_time_ms'],

            // Performance
            'page_load_time_ms'    => $normalized['performance']['page_load_time'],

            // Plugins
            'wordfence_installed'  => $normalized['wordfence']['installed'] ? 1 : 0,
            'active_plugin_count'  => count($normalized['active_plugins']),

            // Backup
            'backup_enabled'       => $normalized['backups']['backup_enabled'] ? 1 : 0,

            // Audit
            'raw_payload'          => $rawResponse,
            'response_timestamp'   => $normalized['timestamp'],
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
