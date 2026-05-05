<?php
/**
 * License Model - Database operations for licenses
 */

class License
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new license in the database
     */
    public function create(array $licenseData): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO licenses (
                license_key,
                license_hash,
                license_type,
                product_tier,
                status,
                max_websites,
                max_users,
                google_sheets_sync,
                wordpress_integration,
                automation_rules,
                advanced_reporting,
                webhooks_enabled,
                slack_integration,
                priority_support,
                custom_branding,
                customer_name,
                customer_email,
                company_name,
                customer_website,
                activation_code,
                expires_at,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $licenseData['license_key'],
            $licenseData['license_hash'],
            $licenseData['license_type'],
            $licenseData['product_tier'] ?? 'PROFESSIONAL',
            $licenseData['status'] ?? 'INACTIVE',
            $licenseData['max_websites'] ?? 50,
            $licenseData['max_users'] ?? 5,
            $licenseData['google_sheets_sync'] ?? 1,
            $licenseData['wordpress_integration'] ?? 1,
            $licenseData['automation_rules'] ?? 1,
            $licenseData['advanced_reporting'] ?? 1,
            $licenseData['webhooks_enabled'] ?? 1,
            $licenseData['slack_integration'] ?? 0,
            $licenseData['priority_support'] ?? 0,
            $licenseData['custom_branding'] ?? 0,
            $licenseData['customer_name'],
            $licenseData['customer_email'],
            $licenseData['company_name'] ?? null,
            $licenseData['customer_website'] ?? null,
            $licenseData['activation_code'],
            $licenseData['expires_at'],
            $licenseData['created_by'] ?? null
        ]);

        return $this->pdo->lastInsertId();
    }

    /**
     * Get license by key
     */
    public function getByKey(string $licenseKey): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM licenses 
            WHERE license_key = ? 
            LIMIT 1
        ");
        
        $stmt->execute([$licenseKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Get license by ID
     */
    public function getById(int $licenseId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM licenses 
            WHERE id = ? 
            LIMIT 1
        ");
        
        $stmt->execute([$licenseId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Get active license (should be only one at a time)
     */
    public function getActive(): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM licenses 
            WHERE status = 'ACTIVE' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Update license status
     */
    public function updateStatus(int $licenseId, string $status, ?string $reason = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE licenses 
            SET status = ? 
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $licenseId]);
    }

    /**
     * Activate license
     */
    public function activate(int $licenseId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE licenses 
            SET status = 'ACTIVE',
                activated_at = NOW(),
                activation_attempts = 0
            WHERE id = ? AND status IN ('INACTIVE', 'INACTIVE')
        ");
        
        return $stmt->execute([$licenseId]);
    }

    /**
     * Deactivate all other licenses (only one can be active)
     */
    public function deactivateOthers(int $licenseId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE licenses 
            SET status = 'INACTIVE'
            WHERE id != ? AND status = 'ACTIVE'
        ");
        
        return $stmt->execute([$licenseId]);
    }

    /**
     * Update usage counts
     */
    public function updateUsageCounts(int $licenseId, int $websiteCount, int $userCount): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE licenses 
            SET current_websites_count = ?,
                current_users_count = ?,
                last_validation = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$websiteCount, $userCount, $licenseId]);
    }

    /**
     * Log validation attempt
     */
    public function logValidation(int $licenseId, string $validationType, string $status, array $result = []): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO license_validation_log 
            (license_id, validation_type, validation_status, validation_result) 
            VALUES (?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $licenseId,
            $validationType,
            $status,
            json_encode($result)
        ]);
    }

    /**
     * Log feature usage
     */
    public function logUsage(int $licenseId, string $feature, string $action, int $resourceCount = 1): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO license_usage_log 
            (license_id, feature_name, action_type, resource_count) 
            VALUES (?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $licenseId,
            $feature,
            $action,
            $resourceCount
        ]);
    }

    /**
     * Get all licenses (admin dashboard)
     */
    public function getAll(array $filters = []): array
    {
        $query = "SELECT * FROM licenses WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['license_type'])) {
            $query .= " AND license_type = ?";
            $params[] = $filters['license_type'];
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if license is expired
     */
    public function isExpired(int $licenseId): bool
    {
        $license = $this->getById($licenseId);
        
        if (!$license) {
            return true;
        }

        return strtotime($license['expires_at']) < time();
    }

    /**
     * Get days remaining until expiration
     */
    public function getDaysRemaining(int $licenseId): int
    {
        $license = $this->getById($licenseId);
        
        if (!$license) {
            return 0;
        }

        $remainingSeconds = strtotime($license['expires_at']) - time();
        return max(0, intdiv($remainingSeconds, 86400));
    }

    /**
     * Revoke license
     */
    public function revoke(int $licenseId, ?string $reason = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE licenses 
            SET status = 'REVOKED'
            WHERE id = ?
        ");
        
        return $stmt->execute([$licenseId]);
    }
}
