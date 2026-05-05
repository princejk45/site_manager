<?php
/**
 * License Manager - High-level license management operations
 * Combines generator, validator, and model for convenience
 */

class LicenseManager
{
    private PDO $pdo;
    private $licenseModel;
    private $generator;
    private $validator;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->licenseModel = new License($pdo);
        $this->generator = new LicenseGenerator();
        $this->validator = new LicenseValidator($pdo);
    }

    /**
     * Create and store a new license
     */
    public function createLicense(array $licenseData, int $createdBy): array
    {
        // Validate input
        if (empty($licenseData['customer_name']) || empty($licenseData['customer_email'])) {
            throw new Exception('Customer name and email required');
        }

        if (empty($licenseData['license_type']) || empty($licenseData['product_tier'])) {
            throw new Exception('License type and product tier required');
        }

        // Generate license key
        $generatedKey = $this->generator->generate($licenseData);

        // Prepare database data
        $dbData = array_merge($licenseData, $generatedKey, [
            'created_by' => $createdBy,
            'status' => 'INACTIVE'  // Requires activation
        ]);

        // Set defaults based on tier and type
        $this->setDefaultFeatures($dbData);
        $this->setDefaultLimits($dbData);

        // Store in database
        $licenseId = $this->licenseModel->create($dbData);

        return [
            'id' => $licenseId,
            'license_key' => $generatedKey['license_key'],
            'activation_code' => $generatedKey['activation_code'],
            'message' => 'License created successfully. Awaiting activation.',
            'next_step' => 'Send license key and activation code to customer'
        ];
    }

    /**
     * Activate a license using activation code
     */
    public function activateLicense(string $licenseKey, string $activationCode): bool
    {
        $license = $this->licenseModel->getByKey($licenseKey);

        if (!$license) {
            throw new Exception('License not found');
        }

        if ($license['status'] === 'ACTIVE') {
            throw new Exception('License is already active');
        }

        if ($license['activation_code'] !== $activationCode) {
            // Log failed attempt
            $stmt = $this->pdo->prepare("
                UPDATE licenses 
                SET activation_attempts = activation_attempts + 1,
                    last_activation_attempt = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$license['id']]);

            // Check if too many attempts
            if ($license['activation_attempts'] >= ($license['max_activation_attempts'] ?? 5)) {
                throw new Exception('Too many activation attempts. License suspended.');
            }

            throw new Exception('Invalid activation code');
        }

        // Activate the license
        $activated = $this->licenseModel->activate($license['id']);

        if ($activated) {
            // Deactivate any other active licenses
            $this->licenseModel->deactivateOthers($license['id']);
            
            // Save license key to config file
            $this->saveLicenseToConfig($licenseKey);
            
            // Log success
            $this->licenseModel->logValidation($license['id'], 'ACTIVATION', 'SUCCESS', [
                'activated_at' => date('Y-m-d H:i:s')
            ]);
        }

        return $activated;
    }

    /**
     * Save license key to config file (secure)
     */
    private function saveLicenseToConfig(string $licenseKey): void
    {
        $configPath = dirname(__DIR__) . '/../config';
        $licenseFile = $configPath . '/.license';

        if (!file_exists($configPath)) {
            mkdir($configPath, 0755, true);
        }

        file_put_contents($licenseFile, $licenseKey);
        chmod($licenseFile, 0600);  // Read/write for owner only
    }

    /**
     * Edit license details (customer can't change, but admin can adjust limits/features)
     */
    public function editLicense(int $licenseId, array $updates): bool
    {
        $license = $this->licenseModel->getById($licenseId);

        if (!$license) {
            throw new Exception('License not found');
        }

        // Only allow admin to change certain fields
        $allowedFields = [
            'max_websites',
            'max_users',
            'automation_rules',
            'advanced_reporting',
            'slack_integration',
            'priority_support',
            'custom_branding'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($updates[$field])) {
                $updateData[$field] = $updates[$field];
            }
        }

        if (empty($updateData)) {
            return true;  // Nothing to update
        }

        // Build UPDATE query
        $setClause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($updateData)));
        $values = array_values($updateData);
        $values[] = $licenseId;

        $stmt = $this->pdo->prepare("UPDATE licenses SET $setClause WHERE id = ?");
        
        return $stmt->execute($values);
    }

    /**
     * Renew a license (extend expiration)
     */
    public function renewLicense(int $licenseId, ?string $newLicenseType = null): bool
    {
        $license = $this->licenseModel->getById($licenseId);

        if (!$license) {
            throw new Exception('License not found');
        }

        $licenseType = $newLicenseType ?? $license['license_type'];
        
        // Calculate new expiration
        $newExpiration = $this->generator->calculateExpiration($licenseType) ?? time();

        $stmt = $this->pdo->prepare("
            UPDATE licenses 
            SET expires_at = FROM_UNIXTIME(?),
                license_type = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$newExpiration, $licenseType, $licenseId]);
    }

    /**
     * Revoke a license
     */
    public function revokeLicense(int $licenseId, ?string $reason = null): bool
    {
        $license = $this->licenseModel->getById($licenseId);

        if (!$license) {
            throw new Exception('License not found');
        }

        $activated = $this->licenseModel->revoke($licenseId, $reason);

        if ($activated) {
            // Log revocation
            $this->licenseModel->logValidation($licenseId, 'REVOCATION', 'SUCCESS', [
                'reason' => $reason,
                'revoked_at' => date('Y-m-d H:i:s')
            ]);

            // If this was the active license, remove config
            if ($license['status'] === 'ACTIVE') {
                $configFile = dirname(__DIR__) . '/../config/.license';
                if (file_exists($configFile)) {
                    unlink($configFile);
                }
            }
        }

        return $activated;
    }

    /**
     * Get validation status (for dashboard)
     */
    public function getValidationStatus(): array
    {
        return $this->validator->validate('PERIODIC');
    }

    /**
     * Check if feature is available
     */
    public function featureAvailable(string $feature): bool
    {
        $license = $this->licenseModel->getActive();

        if (!$license) {
            // Trial mode limitations
            return !in_array($feature, [
                'automation_rules',
                'diagnostics_center',
                'advanced_reporting',
                'slack_integration',
                'custom_branding'
            ]);
        }

        return $this->validator->hasFeature($feature);
    }

    /**
     * Log feature usage
     */
    public function recordFeatureUsage(string $feature, string $action, int $count = 1): void
    {
        $license = $this->licenseModel->getActive();

        if ($license) {
            $this->licenseModel->logUsage($license['id'], $feature, $action, $count);
        }
    }

    /**
     * Get all active limits
     */
    public function getLimits(): array
    {
        $status = $this->getValidationStatus();

        if (!$status['valid']) {
            // Trial limits
            return [
                'max_websites' => 5,
                'max_users' => 2,
                'mode' => 'TRIAL'
            ];
        }

        return [
            'max_websites' => $status['limits']['max_websites'] ?? 50,
            'max_users' => $status['limits']['max_users'] ?? 5,
            'current_websites' => $status['limits']['current_websites'] ?? 0,
            'current_users' => $status['limits']['current_users'] ?? 0,
            'mode' => $status['mode'] ?? 'LICENSED'
        ];
    }

    /**
     * Set default features based on product tier
     */
    private function setDefaultFeatures(array &$licenseData): void
    {
        $tier = $licenseData['product_tier'] ?? 'PROFESSIONAL';

        switch ($tier) {
            case 'LIGHT':
                $licenseData['google_sheets_sync'] = 0;
                $licenseData['automation_rules'] = 0;
                $licenseData['advanced_reporting'] = 0;
                $licenseData['webhooks_enabled'] = 0;
                $licenseData['slack_integration'] = 0;
                break;

            case 'PROFESSIONAL':
                $licenseData['google_sheets_sync'] = 1;
                $licenseData['automation_rules'] = 1;
                $licenseData['advanced_reporting'] = 1;
                $licenseData['diagnostics_center'] = 1;
                $licenseData['webhooks_enabled'] = 1;
                $licenseData['slack_integration'] = 0;
                break;

            case 'ENTERPRISE':
                $licenseData['google_sheets_sync'] = 1;
                $licenseData['automation_rules'] = 1;
                $licenseData['advanced_reporting'] = 1;
                $licenseData['diagnostics_center'] = 1;
                $licenseData['webhooks_enabled'] = 1;
                $licenseData['slack_integration'] = 1;
                $licenseData['priority_support'] = 1;
                $licenseData['custom_branding'] = 1;
                break;
        }
    }

    /**
     * Set default limits based on license type
     */
    private function setDefaultLimits(array &$licenseData): void
    {
        $type = $licenseData['license_type'] ?? 'PROFESSIONAL';

        $limitMap = [
            'TRIAL' => ['websites' => 5, 'users' => 2],
            'MONTHLY' => ['websites' => 50, 'users' => 5],
            'QUARTERLY' => ['websites' => 100, 'users' => 10],
            'YEARLY' => ['websites' => 500, 'users' => 25],
            'LIFETIME' => ['websites' => 999999, 'users' => 999999]
        ];

        $limits = $limitMap[$type] ?? ['websites' => 50, 'users' => 5];

        $licenseData['max_websites'] = $limits['websites'];
        $licenseData['max_users'] = $limits['users'];
    }
}
