<?php
/**
 * License Validator - Verifies license validity with multiple checks
 * Runs on startup and before feature access
 */

class LicenseValidator
{
    private $pdo;
    private $license;
    private $licenseKey;

    public function __construct($pdo, $licenseKey = null)
    {
        $this->pdo = $pdo;
        $this->licenseKey = $licenseKey;
    }

    /**
     * Full validation check - runs on system startup
     * Returns comprehensive validation result
     */
    public function validate(string $validationType = 'STARTUP'): array
    {
        try {
            // Step 1: Get or locate license
            $this->license = $this->getLicenseFromSystem();

            if (!$this->license) {
                return $this->handleTrialMode();
            }

            // Step 2: Verify license key integrity (check for tampering)
            if (!$this->verifyIntegrity()) {
                $this->logValidation($validationType, 'INVALID', [
                    'reason' => 'License integrity check failed',
                    'issue' => 'Hash mismatch - possible tampering detected'
                ]);
                
                return [
                    'valid' => false,
                    'reason' => 'License integrity check failed',
                    'action' => 'BLOCK',
                    'severity' => 'CRITICAL'
                ];
            }

            // Step 3: Check expiration
            if ($this->isExpired()) {
                $this->logValidation($validationType, 'EXPIRED', [
                    'expires_at' => $this->license['expires_at']
                ]);

                return [
                    'valid' => false,
                    'reason' => 'License has expired',
                    'expired_at' => $this->license['expires_at'],
                    'action' => 'BLOCK',
                    'severity' => 'CRITICAL'
                ];
            }

            // Step 4: Check status (not revoked, suspended, etc)
            if ($this->license['status'] !== 'ACTIVE') {
                $this->logValidation($validationType, $this->license['status'], [
                    'status' => $this->license['status']
                ]);

                return [
                    'valid' => false,
                    'reason' => 'License status is ' . $this->license['status'],
                    'status' => $this->license['status'],
                    'action' => 'BLOCK',
                    'severity' => 'CRITICAL'
                ];
            }

            // Step 5: Check usage limits
            $limitCheck = $this->checkLimits();
            if (!$limitCheck['valid']) {
                $this->logValidation($validationType, 'LIMIT_EXCEEDED', $limitCheck);
                return $limitCheck;
            }

            // Step 6: Check hardware fingerprint (if applicable)
            if (!empty($this->license['hardware_fingerprint'])) {
                if (!$this->verifyHardwareFingerprint()) {
                    $this->logValidation($validationType, 'HARDWARE_MISMATCH', [
                        'reason' => 'Hardware fingerprint mismatch'
                    ]);

                    return [
                        'valid' => false,
                        'reason' => 'License is tied to different hardware',
                        'action' => 'WARN',  // Don't block completely
                        'severity' => 'HIGH'
                    ];
                }
            }

            // All checks passed - log success
            $this->logValidation($validationType, 'SUCCESS', [
                'websites' => $limitCheck['current_websites'],
                'users' => $limitCheck['current_users'],
                'days_remaining' => $this->daysRemaining()
            ]);

            // Update last validation timestamp
            $this->updateLastValidation();

            return [
                'valid' => true,
                'license_type' => $this->license['license_type'],
                'product_tier' => $this->license['product_tier'],
                'features' => $this->getEnabledFeatures(),
                'limits' => [
                    'max_websites' => $this->license['max_websites'],
                    'max_users' => $this->license['max_users'],
                    'current_websites' => $limitCheck['current_websites'],
                    'current_users' => $limitCheck['current_users']
                ],
                'expires_at' => $this->license['expires_at'],
                'days_remaining' => $this->daysRemaining(),
                'mode' => 'LICENSED'
            ];

        } catch (Exception $e) {
            $this->logValidation($validationType, 'FAILED', [
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'reason' => 'Validation error occurred',
                'error' => $e->getMessage(),
                'action' => 'WARN'  // Don't block on error
            ];
        }
    }

    /**
     * Verify license key integrity using hash
     */
    private function verifyIntegrity(): bool
    {
        $storedHash = $this->license['license_hash'];
        $calculatedHash = hash('SHA256', $this->license['license_key']);
        
        return hash_equals($storedHash, $calculatedHash);  // Timing-safe comparison
    }

    /**
     * Check if license is expired
     */
    private function isExpired(): bool
    {
        return strtotime($this->license['expires_at']) < time();
    }

    /**
     * Check usage limits (websites and users)
     */
    private function checkLimits(): array
    {
        // Count actual websites
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM websites WHERE is_active = 1");
        $websiteCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        // Count actual users
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
        $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        $exceedingWebsites = $websiteCount > $this->license['max_websites'];
        $exceedingUsers = $userCount > $this->license['max_users'];

        if ($exceedingWebsites || $exceedingUsers) {
            $reason = '';
            if ($exceedingWebsites) {
                $reason .= "Website limit exceeded ({$websiteCount}/{$this->license['max_websites']})";
            }
            if ($exceedingUsers) {
                $reason .= ($reason ? '; ' : '') . "User limit exceeded ({$userCount}/{$this->license['max_users']})";
            }

            return [
                'valid' => false,
                'reason' => $reason,
                'action' => 'WARN',
                'current_websites' => $websiteCount,
                'current_users' => $userCount
            ];
        }

        return [
            'valid' => true,
            'current_websites' => $websiteCount,
            'current_users' => $userCount
        ];
    }

    /**
     * Verify hardware fingerprint (if set)
     */
    private function verifyHardwareFingerprint(): bool
    {
        $currentFingerprint = $this->generateHardwareFingerprint();
        $allowedHosts = json_decode($this->license['allowed_hosts'] ?? '[]', true);

        // If specific hosts are allowed, check against them
        if (!empty($allowedHosts)) {
            return in_array($_SERVER['SERVER_ADDR'] ?? '', $allowedHosts) ||
                   in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedHosts);
        }

        // Otherwise compare full fingerprint
        return hash_equals($this->license['hardware_fingerprint'], $currentFingerprint);
    }

    /**
     * Generate hardware fingerprint
     * Uses server info to create unique identifier
     */
    private function generateHardwareFingerprint(): string
    {
        $components = [
            $_SERVER['SERVER_NAME'] ?? '',
            $_SERVER['SERVER_ADDR'] ?? '',
            php_uname('a') ?? '',
            phpversion(),
            extension_loaded('openssl') ? 'ssl' : 'nossl'
        ];

        return hash('SHA256', implode('|', $components));
    }

    /**
     * Get license from system (environment, config file, or database)
     */
    private function getLicenseFromSystem(): ?array
    {
        // Priority 1: Environment variable (most secure)
        $licenseKey = getenv('FULLMIDIA_LICENSE_KEY');
        
        // Priority 2: Config file
        if (!$licenseKey) {
            $configFile = dirname(__DIR__) . '/../config/.license';
            if (file_exists($configFile)) {
                $licenseKey = trim(file_get_contents($configFile));
            }
        }

        // Priority 3: Parameter passed to constructor
        if (!$licenseKey) {
            $licenseKey = $this->licenseKey;
        }

        if (!$licenseKey) {
            return null;
        }

        // Look up license in database
        $licenseModel = new License($this->pdo);
        return $licenseModel->getByKey($licenseKey);
    }

    /**
     * Handle trial mode when no license is present
     */
    private function handleTrialMode(): array
    {
        $configPath = dirname(__DIR__) . '/../config';
        $trialFile = $configPath . '/.trial';
        
        if (!file_exists($configPath)) {
            mkdir($configPath, 0755, true);
        }

        if (!file_exists($trialFile)) {
            // First time - create trial
            $trialData = [
                'started_at' => time(),
                'trial_days' => 30
            ];
            file_put_contents($trialFile, json_encode($trialData));
            chmod($trialFile, 0600);
            
            $this->logValidation('STARTUP', 'TRIAL_STARTED', [
                'days' => 30
            ]);
            
            return [
                'valid' => true,
                'mode' => 'TRIAL',
                'days_remaining' => 30,
                'message' => 'Trial mode activated. Valid for 30 days.',
                'features' => [
                    'google_sheets_sync' => true,
                    'wordpress_integration' => true,
                    'automation_rules' => false,  // Limited in trial
                    'advanced_reporting' => false,
                    'webhooks_enabled' => false,
                    'slack_integration' => false,
                    'priority_support' => false,
                    'custom_branding' => false
                ]
            ];
        }

        $trialData = json_decode(file_get_contents($trialFile), true);
        $daysUsed = intdiv(time() - $trialData['started_at'], 86400);
        $daysRemaining = 30 - $daysUsed;

        if ($daysRemaining < 0) {
            $this->logValidation('STARTUP', 'TRIAL_EXPIRED', []);

            return [
                'valid' => false,
                'mode' => 'TRIAL_EXPIRED',
                'reason' => 'Trial period has expired',
                'action' => 'BLOCK',
                'message' => 'Please purchase a license to continue using Fullmidia.'
            ];
        }

        // Warning if approaching expiry
        if ($daysRemaining < 7) {
            $this->logValidation('STARTUP', 'TRIAL_EXPIRING_SOON', [
                'days_remaining' => $daysRemaining
            ]);
        }

        return [
            'valid' => true,
            'mode' => 'TRIAL',
            'days_remaining' => $daysRemaining,
            'message' => "Trial mode. {$daysRemaining} days remaining.",
            'features' => [
                'google_sheets_sync' => true,
                'wordpress_integration' => true,
                'automation_rules' => false,
                'advanced_reporting' => false,
                'webhooks_enabled' => false,
                'slack_integration' => false,
                'priority_support' => false,
                'custom_branding' => false
            ]
        ];
    }

    /**
     * Get enabled features for current license
     */
    private function getEnabledFeatures(): array
    {
        return [
            'google_sheets_sync' => (bool)$this->license['google_sheets_sync'],
            'wordpress_integration' => (bool)$this->license['wordpress_integration'],
            'automation_rules' => (bool)$this->license['automation_rules'],
            'advanced_reporting' => (bool)$this->license['advanced_reporting'],
            'webhooks_enabled' => (bool)$this->license['webhooks_enabled'],
            'slack_integration' => (bool)$this->license['slack_integration'],
            'priority_support' => (bool)$this->license['priority_support'],
            'custom_branding' => (bool)$this->license['custom_branding']
        ];
    }

    /**
     * Calculate days remaining until expiration
     */
    private function daysRemaining(): int
    {
        $expiryTimestamp = strtotime($this->license['expires_at']);
        $remainingSeconds = $expiryTimestamp - time();
        return max(0, intdiv($remainingSeconds, 86400));
    }

    /**
     * Log validation attempt
     */
    private function logValidation(string $type, string $status, array $result): void
    {
        try {
            if ($this->license) {
                $licenseModel = new License($this->pdo);
                $licenseModel->logValidation($this->license['id'], $type, $status, $result);
            }
        } catch (Exception $e) {
            // Silently fail - don't break validation on logging error
        }
    }

    /**
     * Update last validation timestamp
     */
    private function updateLastValidation(): void
    {
        try {
            if ($this->license) {
                $stmt = $this->pdo->prepare("
                    UPDATE licenses 
                    SET last_validation = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$this->license['id']]);
            }
        } catch (Exception $e) {
            // Silently fail
        }
    }

    /**
     * Quick feature check (before detailed validation)
     * Used to gate features without full validation
     */
    public function hasFeature(string $feature): bool
    {
        if (!$this->license) {
            // Trial mode limitations
            return !in_array($feature, [
                'automation_rules',
                'advanced_reporting',
                'slack_integration',
                'custom_branding'
            ]);
        }

        $featureMap = [
            'google_sheets_sync' => $this->license['google_sheets_sync'],
            'wordpress_integration' => $this->license['wordpress_integration'],
            'automation_rules' => $this->license['automation_rules'],
            'advanced_reporting' => $this->license['advanced_reporting'],
            'webhooks_enabled' => $this->license['webhooks_enabled'],
            'slack_integration' => $this->license['slack_integration'],
            'priority_support' => $this->license['priority_support'],
            'custom_branding' => $this->license['custom_branding']
        ];

        return $featureMap[$feature] ?? false;
    }
}
