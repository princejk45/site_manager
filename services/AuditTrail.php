<?php
/**
 * Audit Trail Service - Logs all important actions
 * 
 * Usage:
 * AuditTrail::log($userId, 'website_created', 'website', $websiteId, [
 *     'domain' => $domain,
 *     'type' => $type
 * ]);
 */

class AuditTrail
{
    private static $pdo;
    private static $userId;
    private static $userName;

    /**
     * Initialize audit trail (call once at app startup)
     */
    public static function initialize(PDO $pdo, $userId = null, $userName = null)
    {
        self::$pdo = $pdo;
        self::$userId = $userId;
        self::$userName = $userName ?? 'system';
    }

    /**
     * Log an action
     */
    public static function log(
        int $userId,
        string $action,
        string $entityType,
        int $entityId,
        array $changes = [],
        string $result = 'SUCCESS'
    ): bool {
        if (!self::$pdo) {
            return false;
        }

        try {
            // Get old values (for comparison)
            $oldValues = self::getEntityOldValues($entityType, $entityId);

            // Get entity name for reference
            $entityName = self::getEntityName($entityType, $entityId);

            // Prepare changes array
            $changesData = [];
            foreach ($changes as $field => $newValue) {
                $oldValue = $oldValues[$field] ?? null;
                if ($oldValue !== $newValue) {
                    $changesData[$field] = [
                        'old' => $oldValue,
                        'new' => $newValue
                    ];
                }
            }

            // Store in audit trail
            $stmt = self::$pdo->prepare("
                INSERT INTO audit_trail (
                    user_id,
                    user_name,
                    action,
                    entity_type,
                    entity_id,
                    entity_name,
                    changes,
                    old_values,
                    new_values,
                    ip_address,
                    user_agent,
                    http_method,
                    request_url,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                self::$userName,
                $action,
                $entityType,
                $entityId,
                $entityName,
                json_encode($changesData),
                json_encode($oldValues),
                json_encode($changes),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                $_SERVER['REQUEST_URI'] ?? '',
                $result
            ]);

            return true;
        } catch (Exception $e) {
            error_log('Audit trail log error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log a feature access
     */
    public static function logFeatureAccess(
        int $userId,
        string $feature,
        string $action = 'access'
    ): bool {
        return self::log(
            $userId,
            'feature_access',
            'feature',
            0,
            [
                'feature' => $feature,
                'action' => $action
            ]
        );
    }

    /**
     * Log failed authentication
     */
    public static function logAuthFailure(string $username, string $reason = 'invalid_credentials'): bool
    {
        if (!self::$pdo) {
            return false;
        }

        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO audit_trail (
                    action,
                    entity_type,
                    ip_address,
                    user_agent,
                    status,
                    changes,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            return $stmt->execute([
                'auth_failure',
                'user',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                'FAILED',
                json_encode(['username' => $username, 'reason' => $reason])
            ]);
        } catch (Exception $e) {
            error_log('Audit trail auth failure log error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get audit trail for an entity
     */
    public static function getEntityHistory(string $entityType, int $entityId, int $limit = 50): array
    {
        if (!self::$pdo) {
            return [];
        }

        try {
            $stmt = self::$pdo->prepare("
                SELECT * FROM audit_trail
                WHERE entity_type = ? AND entity_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");

            $stmt->execute([$entityType, $entityId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Audit trail get history error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search audit trail
     */
    public static function search(array $filters = [], int $limit = 100): array
    {
        if (!self::$pdo) {
            return [];
        }

        try {
            $query = "SELECT * FROM audit_trail WHERE 1=1";
            $params = [];

            if (!empty($filters['user_id'])) {
                $query .= " AND user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['entity_type'])) {
                $query .= " AND entity_type = ?";
                $params[] = $filters['entity_type'];
            }

            if (!empty($filters['action'])) {
                $query .= " AND action = ?";
                $params[] = $filters['action'];
            }

            if (!empty($filters['from_date'])) {
                $query .= " AND created_at >= ?";
                $params[] = $filters['from_date'];
            }

            if (!empty($filters['to_date'])) {
                $query .= " AND created_at <= ?";
                $params[] = $filters['to_date'];
            }

            $query .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = self::$pdo->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Audit trail search error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get old entity values
     */
    private static function getEntityOldValues(string $entityType, int $entityId): array
    {
        try {
            switch ($entityType) {
                case 'website':
                    $stmt = self::$pdo->prepare("SELECT * FROM websites WHERE id = ?");
                    break;
                case 'automation_rule':
                    $stmt = self::$pdo->prepare("SELECT * FROM automation_rules WHERE id = ?");
                    break;
                case 'user':
                    $stmt = self::$pdo->prepare("SELECT * FROM users WHERE id = ?");
                    break;
                default:
                    return [];
            }

            $stmt->execute([$entityId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get entity display name
     */
    private static function getEntityName(string $entityType, int $entityId): ?string
    {
        try {
            switch ($entityType) {
                case 'website':
                    $stmt = self::$pdo->prepare("SELECT domain FROM websites WHERE id = ?");
                    break;
                case 'user':
                    $stmt = self::$pdo->prepare("SELECT username FROM users WHERE id = ?");
                    break;
                case 'automation_rule':
                    $stmt = self::$pdo->prepare("SELECT name FROM automation_rules WHERE id = ?");
                    break;
                default:
                    return null;
            }

            $stmt->execute([$entityId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? array_values($result)[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
