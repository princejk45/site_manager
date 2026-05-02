<?php
class CronScheduler
{
    private $pdo;
    private $timezone;

    /**
     * Standard cron intervals
     */
    const INTERVALS = [
        'hourly' => '0 * * * *',
        'twicedaily' => '0 0,12 * * *',
        'daily' => '0 0 * * *',
        'weekly' => '0 0 * * 0',
        'monthly' => '0 0 1 * *'
    ];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->timezone = date_default_timezone_get();
    }

    /**
     * Get cPanel cron settings
     */
    public function getCpanelSettings()
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM cpanel_cron_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch();
            
            // Decrypt API token if exists
            if ($settings && !empty($settings['cpanel_api_token'])) {
                $settings['cpanel_api_token'] = $this->decryptToken($settings['cpanel_api_token']);
            }
            
            return $settings ?: null;
        } catch (Exception $e) {
            error_log("Error fetching cPanel settings: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Save cPanel cron settings
     */
    public function saveCpanelSettings($data)
    {
        try {
            // Encrypt API token
            $encryptedToken = !empty($data['cpanel_api_token']) ? $this->encryptToken($data['cpanel_api_token']) : null;

            $stmt = $this->pdo->prepare("
                INSERT INTO cpanel_cron_settings 
                (cpanel_host, cpanel_username, cpanel_api_token, cpanel_command, command_path)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                cpanel_host = VALUES(cpanel_host),
                cpanel_username = VALUES(cpanel_username),
                cpanel_api_token = VALUES(cpanel_api_token),
                cpanel_command = VALUES(cpanel_command),
                command_path = VALUES(command_path),
                updated_at = CURRENT_TIMESTAMP
            ");

            return $stmt->execute([
                $data['cpanel_host'] ?? null,
                $data['cpanel_username'] ?? null,
                $encryptedToken,
                $data['cpanel_command'] ?? null,
                $data['command_path'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Error saving cPanel settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Detect if running on localhost
     */
    public function isLocalhost()
    {
        $hostname = gethostname();
        if ($hostname === false) {
            $hostname = php_uname('n');
        }
        
        $localhost_patterns = [
            'localhost',
            '127.0.0.1',
            '::1',
            'localhost.localdomain',
        ];

        foreach ($localhost_patterns as $pattern) {
            if (stripos($hostname, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get next cron execution time
     * Supports both cPanel API and cron expression parsing
     */
    public function getNextExecutionTime()
    {
        // Check if localhost
        if ($this->isLocalhost()) {
            return [
                'is_localhost' => true,
                'next_run' => null,
                'message' => 'Local System - Schedule configured via system task scheduler'
            ];
        }

        // Try cPanel API
        $cpanelSettings = $this->getCpanelSettings();
        if ($cpanelSettings && !empty($cpanelSettings['cpanel_host'])) {
            return $this->getNextRunFromCpanelAPI($cpanelSettings);
        }

        // No cPanel configured
        return [
            'is_localhost' => false,
            'next_run' => null,
            'message' => 'cPanel credentials not configured - configure in Advanced Settings'
        ];
    }

    /**
     * Fetch next execution from cPanel API v2
     */
    private function getNextRunFromCpanelAPI($cpanelSettings)
    {
        try {
            $host = $cpanelSettings['cpanel_host'];
            $username = $cpanelSettings['cpanel_username'];
            $apiToken = $cpanelSettings['cpanel_api_token'];

            if (empty($host) || empty($username) || empty($apiToken)) {
                return [
                    'is_localhost' => false,
                    'next_run' => null,
                    'message' => 'Incomplete cPanel configuration',
                    'error' => true
                ];
            }

            // Prepare cPanel API URL
            $protocol = strpos($host, 'https') === 0 ? 'https' : 'https';
            if (strpos($host, '://') === false) {
                $host = 'https://' . $host;
            }

            // Ensure port is correct (usually 2087 for cPanel on secure connection)
            if (strpos($host, ':') === false || strrpos($host, ':') === strpos($host, '://') + 5) {
                $host = str_replace('https://', 'https://', $host) . ':2087';
            }

            $url = rtrim($host, '/') . '/json-api/cpanel?cpanel_jsonapi_user=' . urlencode($username) .
                   '&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Cron&cpanel_jsonapi_func=listcrons';

            // Make API request with token in header
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Authorization: WHM $username:$apiToken\r\n",
                    'timeout' => 5
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return [
                    'is_localhost' => false,
                    'next_run' => null,
                    'message' => 'Failed to connect to cPanel API',
                    'error' => true
                ];
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['data']['crons'])) {
                return [
                    'is_localhost' => false,
                    'next_run' => null,
                    'message' => 'No cron jobs found or API error',
                    'error' => true
                ];
            }

            // Find the matching cron job
            $cronJobs = $data['data']['crons'];
            $matchingCron = null;

            // Look for cron containing command path or command text
            $searchCmd = $cpanelSettings['cpanel_command'] ?? 'expiry_notifier';
            
            foreach ($cronJobs as $cron) {
                if (stripos($cron['command'] ?? '', $searchCmd) !== false) {
                    $matchingCron = $cron;
                    break;
                }
            }

            if (!$matchingCron) {
                return [
                    'is_localhost' => false,
                    'next_run' => null,
                    'message' => 'Scheduled cron job not found in cPanel',
                    'warning' => true
                ];
            }

            // Parse cron expression
            $nextRun = $this->calculateNextRun($matchingCron['minute'], $matchingCron['hour'], 
                                               $matchingCron['day'], $matchingCron['month'], 
                                               $matchingCron['weekday']);

            return [
                'is_localhost' => false,
                'next_run' => $nextRun,
                'cron_expression' => $matchingCron['minute'] . ' ' . $matchingCron['hour'] . ' ' . 
                                   $matchingCron['day'] . ' ' . $matchingCron['month'] . ' ' . 
                                   $matchingCron['weekday'],
                'command' => $matchingCron['command']
            ];
        } catch (Exception $e) {
            return [
                'is_localhost' => false,
                'next_run' => null,
                'message' => 'Error querying cPanel API: ' . $e->getMessage(),
                'error' => true
            ];
        }
    }

    /**
     * Parse cron expression and calculate next execution time
     * Based on: minute hour day month weekday
     */
    private function calculateNextRun($minute, $hour, $day, $month, $weekday)
    {
        try {
            $now = new DateTime('now', new DateTimeZone($this->timezone));
            $nextRun = clone $now;
            $nextRun->add(new DateInterval('PT1M'));
            $nextRun->setSeconds(0);

            // Simple calculation for common patterns
            // This is a simplified version - for production use a library like cronexpr
            
            $minute = (int)$minute;
            $hour = (int)$hour;

            // If this is a daily cron
            if ($day === '*' && $month === '*' && $weekday === '*') {
                $nextRun->setTime($hour, $minute, 0);
                if ($nextRun <= $now) {
                    $nextRun->add(new DateInterval('P1D'));
                }
                return $nextRun->format('Y-m-d H:i');
            }

            // If this is an hourly cron
            if ($hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
                $nextRun->setTime($nextRun->format('H'), $minute, 0);
                if ($nextRun <= $now) {
                    $nextRun->add(new DateInterval('PT1H'));
                }
                return $nextRun->format('Y-m-d H:i');
            }

            // For other patterns, return rough estimate
            return $nextRun->format('Y-m-d H:i') . ' (approx)';
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Simple encryption for API token
     */
    private function encryptToken($token)
    {
        // For production, use proper encryption like openssl_encrypt
        // This is a simple base64 encoding for now
        return base64_encode('cron_' . $token);
    }

    /**
     * Simple decryption for API token
     */
    private function decryptToken($encrypted)
    {
        try {
            $decoded = base64_decode($encrypted, true);
            if ($decoded === false) {
                return '';
            }
            return substr($decoded, 5); // Remove 'cron_' prefix
        } catch (Exception $e) {
            return '';
        }
    }
}
?>
