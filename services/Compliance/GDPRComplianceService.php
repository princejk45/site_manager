<?php
/**
 * GDPRComplianceService
 * GDPR compliance with data retention policies, right to deletion, and consent management
 */

namespace Services\Compliance;

use PDO;
use Exception;

class GDPRComplianceService
{
    private $db;
    private $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Record user consent
     */
    public function recordConsent($portfolio_id, $user_id, $consent_type, $version, $ip_address = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gdpr_consents (portfolio_id, user_id, consent_type, version, ip_address, recorded_at)
                VALUES (:portfolio_id, :user_id, :consent_type, :version, :ip_address, NOW())
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':consent_type' => $consent_type,
                ':version' => $version,
                ':ip_address' => $ip_address ?? ($_SERVER['REMOTE_ADDR'] ?? '')
            ]);

            return ['status' => 'success', 'message' => 'Consent recorded'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to record consent: " . $e->getMessage());
        }
    }

    /**
     * Revoke user consent
     */
    public function revokeConsent($portfolio_id, $user_id, $consent_type)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gdpr_consents (portfolio_id, user_id, consent_type, is_revoked, ip_address, recorded_at)
                VALUES (:portfolio_id, :user_id, :consent_type, 1, :ip_address, NOW())
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':consent_type' => $consent_type,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);

            return ['status' => 'success', 'message' => 'Consent revoked'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to revoke consent: " . $e->getMessage());
        }
    }

    /**
     * Get user consent status
     */
    public function getConsentStatus($portfolio_id, $user_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    consent_type, 
                    is_revoked, 
                    version, 
                    recorded_at
                FROM gdpr_consents
                WHERE portfolio_id = :portfolio_id AND user_id = :user_id
                ORDER BY recorded_at DESC
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id
            ]);

            $consents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'consents' => $consents
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get consent status: " . $e->getMessage());
        }
    }

    /**
     * Create data retention policy
     */
    public function createRetentionPolicy($portfolio_id, $policy_name, $data_category, $retention_days, $deletion_method = 'soft')
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gdpr_retention_policies (portfolio_id, policy_name, data_category, retention_days, deletion_method, is_active, created_at)
                VALUES (:portfolio_id, :policy_name, :data_category, :retention_days, :deletion_method, 1, NOW())
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':policy_name' => $policy_name,
                ':data_category' => $data_category,
                ':retention_days' => $retention_days,
                ':deletion_method' => $deletion_method
            ]);

            return ['status' => 'success', 'message' => 'Retention policy created'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to create retention policy: " . $e->getMessage());
        }
    }

    /**
     * Get data for right to access
     */
    public function getUserData($portfolio_id, $user_id)
    {
        try {
            // Collect all user data across tables
            $tables = [
                'users' => ['id', 'portfolio_id', 'name', 'email', 'created_at'],
                'user_profiles' => ['id', 'user_id', 'phone', 'address', 'created_at'],
                'audit_logs' => ['id', 'user_id', 'action', 'changes', 'created_at'],
                'user_activities' => ['id', 'user_id', 'activity_type', 'description', 'created_at']
            ];

            $user_data = [];

            foreach ($tables as $table => $columns) {
                $col_str = implode(',', $columns);
                $stmt = $this->db->prepare("SELECT {$col_str} FROM {$table} WHERE portfolio_id = :portfolio_id AND user_id = :user_id");
                $stmt->execute([
                    ':portfolio_id' => $portfolio_id,
                    ':user_id' => $user_id
                ]);

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($results)) {
                    $user_data[$table] = $results;
                }
            }

            // Log access request
            $stmt = $this->db->prepare("
                INSERT INTO gdpr_access_logs (portfolio_id, user_id, requested_at)
                VALUES (:portfolio_id, :user_id, NOW())
            ");
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id
            ]);

            return [
                'status' => 'success',
                'user_data' => $user_data
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get user data: " . $e->getMessage());
        }
    }

    /**
     * Process right to deletion request
     */
    public function deleteUserData($portfolio_id, $user_id, $deletion_method = 'soft')
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gdpr_deletion_requests (portfolio_id, user_id, deletion_method, requested_at)
                VALUES (:portfolio_id, :user_id, :deletion_method, NOW())
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':deletion_method' => $deletion_method
            ]);

            if ($deletion_method === 'soft') {
                // Anonymize user data
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET name = 'DELETED', email = CONCAT('deleted_', id, '@deleted.local'), 
                        is_deleted = 1, deleted_at = NOW()
                    WHERE id = :user_id AND portfolio_id = :portfolio_id
                ");
            } else {
                // Hard delete
                $tables = ['users', 'user_profiles', 'audit_logs'];
                foreach ($tables as $table) {
                    $stmt = $this->db->prepare("DELETE FROM {$table} WHERE user_id = :user_id AND portfolio_id = :portfolio_id");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':portfolio_id' => $portfolio_id
                    ]);
                }
            }

            // Log deletion
            $stmt = $this->db->prepare("
                INSERT INTO gdpr_deletion_logs (portfolio_id, user_id, deletion_method, deleted_at)
                VALUES (:portfolio_id, :user_id, :deletion_method, NOW())
            ");
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':deletion_method' => $deletion_method
            ]);

            return ['status' => 'success', 'message' => 'User data scheduled for deletion'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to delete user data: " . $e->getMessage());
        }
    }

    /**
     * Process data portability request
     */
    public function exportUserData($portfolio_id, $user_id, $format = 'json')
    {
        try {
            $user_data = $this->getUserData($portfolio_id, $user_id);

            if ($format === 'json') {
                $exported = json_encode($user_data['user_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $content_type = 'application/json';
                $filename = "user_data_{$user_id}.json";
            } elseif ($format === 'csv') {
                $exported = $this->convertToCSV($user_data['user_data']);
                $content_type = 'text/csv';
                $filename = "user_data_{$user_id}.csv";
            } else {
                throw new Exception("Unsupported export format");
            }

            // Log export
            $stmt = $this->db->prepare("
                INSERT INTO gdpr_export_logs (portfolio_id, user_id, format, exported_at)
                VALUES (:portfolio_id, :user_id, :format, NOW())
            ");
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':format' => $format
            ]);

            return [
                'status' => 'success',
                'data' => $exported,
                'format' => $format,
                'filename' => $filename,
                'content_type' => $content_type
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to export user data: " . $e->getMessage());
        }
    }

    /**
     * Execute automatic data retention cleanup
     */
    public function executeRetentionCleanup($portfolio_id = null)
    {
        try {
            $where = $portfolio_id ? "AND portfolio_id = :portfolio_id" : "";
            $params = $portfolio_id ? [':portfolio_id' => $portfolio_id] : [];

            // Get expired records
            $stmt = $this->db->prepare("
                SELECT id, portfolio_id, user_id, data_category, retention_days, deletion_method
                FROM gdpr_retention_policies
                WHERE is_active = 1 {$where}
            ");
            $stmt->execute($params);
            $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $deleted_count = 0;

            foreach ($policies as $policy) {
                $expiration_date = date('Y-m-d H:i:s', strtotime("-{$policy['retention_days']} days"));

                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count FROM audit_logs
                    WHERE portfolio_id = :portfolio_id AND created_at < :expiration_date
                ");
                $stmt->execute([
                    ':portfolio_id' => $policy['portfolio_id'],
                    ':expiration_date' => $expiration_date
                ]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result['count'] > 0) {
                    $stmt = $this->db->prepare("
                        DELETE FROM audit_logs
                        WHERE portfolio_id = :portfolio_id AND created_at < :expiration_date
                    ");
                    $stmt->execute([
                        ':portfolio_id' => $policy['portfolio_id'],
                        ':expiration_date' => $expiration_date
                    ]);

                    $deleted_count += $result['count'];
                }
            }

            return [
                'status' => 'success',
                'deleted_records' => $deleted_count,
                'message' => "Deleted {$deleted_count} expired records"
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to execute retention cleanup: " . $e->getMessage());
        }
    }

    /**
     * Get GDPR audit report
     */
    public function getAuditReport($portfolio_id, $start_date = null, $end_date = null)
    {
        try {
            $start_date = $start_date ?? date('Y-m-d', strtotime('-30 days'));
            $end_date = $end_date ?? date('Y-m-d');

            $stmt = $this->db->prepare("
                SELECT
                    'consents' as audit_type,
                    COUNT(*) as count,
                    DATE(recorded_at) as date
                FROM gdpr_consents
                WHERE portfolio_id = :portfolio_id AND DATE(recorded_at) BETWEEN :start_date AND :end_date
                GROUP BY DATE(recorded_at)
                UNION ALL
                SELECT
                    'access_requests',
                    COUNT(*),
                    DATE(requested_at)
                FROM gdpr_access_logs
                WHERE portfolio_id = :portfolio_id AND DATE(requested_at) BETWEEN :start_date AND :end_date
                GROUP BY DATE(requested_at)
                UNION ALL
                SELECT
                    'deletions',
                    COUNT(*),
                    DATE(deleted_at)
                FROM gdpr_deletion_logs
                WHERE portfolio_id = :portfolio_id AND DATE(deleted_at) BETWEEN :start_date AND :end_date
                GROUP BY DATE(deleted_at)
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':start_date' => $start_date,
                ':end_date' => $end_date
            ]);

            $report = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'audit_report' => $report,
                'period' => "{$start_date} to {$end_date}"
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get audit report: " . $e->getMessage());
        }
    }

    /**
     * Convert data to CSV
     */
    private function convertToCSV(array $data)
    {
        $csv = '';
        foreach ($data as $table => $rows) {
            $csv .= "\n{$table}\n";
            if (!empty($rows)) {
                $headers = array_keys($rows[0]);
                $csv .= implode(',', $headers) . "\n";
                foreach ($rows as $row) {
                    $values = array_map(function($v) {
                        return '"' . str_replace('"', '""', $v) . '"';
                    }, $row);
                    $csv .= implode(',', $values) . "\n";
                }
            }
        }
        return $csv;
    }
}
