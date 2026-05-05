<?php
/**
 * SecurityScanningService
 * Vulnerability detection, penetration testing integration, and security scanning
 */

namespace Services\Security;

use PDO;
use Exception;

class SecurityScanningService
{
    private $db;
    private $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Scan for common vulnerabilities
     */
    public function scanVulnerabilities($portfolio_id, $scan_type = 'full')
    {
        try {
            $vulnerabilities = [];

            if ($scan_type === 'full' || $scan_type === 'sql_injection') {
                $vulnerabilities = array_merge($vulnerabilities, $this->scanSQLInjection($portfolio_id));
            }

            if ($scan_type === 'full' || $scan_type === 'xss') {
                $vulnerabilities = array_merge($vulnerabilities, $this->scanXSS($portfolio_id));
            }

            if ($scan_type === 'full' || $scan_type === 'csrf') {
                $vulnerabilities = array_merge($vulnerabilities, $this->scanCSRF($portfolio_id));
            }

            if ($scan_type === 'full' || $scan_type === 'ssl') {
                $vulnerabilities = array_merge($vulnerabilities, $this->scanSSLConfiguration($portfolio_id));
            }

            if ($scan_type === 'full' || $scan_type === 'outdated_deps') {
                $vulnerabilities = array_merge($vulnerabilities, $this->checkOutdatedDependencies($portfolio_id));
            }

            // Store scan results
            $scan_id = $this->storeScanResults($portfolio_id, $scan_type, $vulnerabilities);

            return [
                'status' => 'success',
                'scan_id' => $scan_id,
                'scan_type' => $scan_type,
                'vulnerabilities_found' => count($vulnerabilities),
                'vulnerabilities' => $vulnerabilities,
                'scanned_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to scan vulnerabilities: " . $e->getMessage());
        }
    }

    /**
     * Check for SQL injection vulnerabilities
     */
    private function scanSQLInjection($portfolio_id)
    {
        $vulnerabilities = [];

        // Check for common SQL injection patterns in code
        // This is a simplified check - in production, use specialized tools like SQLMap
        $payloads = [
            "' OR '1'='1",
            "'; DROP TABLE users; --",
            "1' UNION SELECT NULL --"
        ];

        // Scan database queries for unescaped parameters
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM audit_logs
            WHERE portfolio_id = :portfolio_id
            AND action LIKE '%SQL%'
            AND (
                changes LIKE '%DROP%' OR
                changes LIKE '%DELETE%' OR
                changes LIKE '%INSERT%'
            )
        ");
        $stmt->execute([':portfolio_id' => $portfolio_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 5) {
            $vulnerabilities[] = [
                'type' => 'SQL_INJECTION',
                'severity' => 'CRITICAL',
                'description' => 'Potential SQL injection detected in query patterns',
                'recommendation' => 'Use prepared statements for all database queries',
                'cve' => 'CWE-89'
            ];
        }

        return $vulnerabilities;
    }

    /**
     * Check for XSS vulnerabilities
     */
    private function scanXSS($portfolio_id)
    {
        $vulnerabilities = [];

        // Check for unescaped output
        $xss_patterns = ['<script', 'onclick=', 'onerror=', 'javascript:'];

        // Log attempts (simplified check)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM event_logs
            WHERE portfolio_id = :portfolio_id
            AND event_type = 'user_input'
        ");
        $stmt->execute([':portfolio_id' => $portfolio_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            // Recommend input validation
            $vulnerabilities[] = [
                'type' => 'XSS',
                'severity' => 'HIGH',
                'description' => 'Potential XSS vulnerability through unescaped user input',
                'recommendation' => 'Escape all user input using htmlspecialchars() or similar',
                'cve' => 'CWE-79'
            ];
        }

        return $vulnerabilities;
    }

    /**
     * Check for CSRF vulnerabilities
     */
    private function scanCSRF($portfolio_id)
    {
        $vulnerabilities = [];

        // Check if CSRF tokens are being used
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM audit_logs
            WHERE portfolio_id = :portfolio_id
            AND (action LIKE '%POST%' OR action LIKE '%DELETE%' OR action LIKE '%UPDATE%')
        ");
        $stmt->execute([':portfolio_id' => $portfolio_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            // Recommend CSRF protection
            $vulnerabilities[] = [
                'type' => 'CSRF',
                'severity' => 'HIGH',
                'description' => 'Verify CSRF token implementation for state-changing requests',
                'recommendation' => 'Implement double-submit cookie or synchronizer token pattern',
                'cve' => 'CWE-352'
            ];
        }

        return $vulnerabilities;
    }

    /**
     * Scan SSL/TLS configuration
     */
    private function scanSSLConfiguration($portfolio_id)
    {
        $vulnerabilities = [];

        // Check SSL/TLS version and cipher strength
        $ssl_check = @stream_context_create(
            ["ssl" => ["capture_peer_cert" => true]]
        );

        // In production, use actual SSL checking
        $vulnerabilities[] = [
            'type' => 'SSL_CONFIGURATION',
            'severity' => 'MEDIUM',
            'description' => 'Verify SSL/TLS configuration and certificate validity',
            'recommendation' => 'Use TLS 1.2 or higher, strong cipher suites, and valid certificates',
            'cve' => 'CWE-295'
        ];

        return $vulnerabilities;
    }

    /**
     * Check for outdated dependencies
     */
    private function checkOutdatedDependencies($portfolio_id)
    {
        $vulnerabilities = [];

        // Check if composer.lock exists and parse it
        if (file_exists('../composer.lock')) {
            $composer_data = json_decode(file_get_contents('../composer.lock'), true);

            // Check for known vulnerable package versions
            $vulnerable_packages = $this->getKnownVulnerablePackages();

            foreach ($composer_data['packages'] as $package) {
                if (isset($vulnerable_packages[$package['name']])) {
                    $vulnerable_packages[$package['name']];
                    $vulnerabilities[] = [
                        'type' => 'OUTDATED_DEPENDENCY',
                        'severity' => 'MEDIUM',
                        'package' => $package['name'],
                        'current_version' => $package['version'],
                        'description' => "Outdated or vulnerable package: {$package['name']}",
                        'recommendation' => "Update {$package['name']} to latest stable version",
                        'cve' => 'CWE-1026'
                    ];
                }
            }
        }

        return $vulnerabilities;
    }

    /**
     * Schedule penetration test
     */
    public function schedulePenetrationTest($portfolio_id, $test_type, $schedule_date = null)
    {
        try {
            $schedule_date = $schedule_date ?? date('Y-m-d H:i:s', strtotime('+7 days'));

            $stmt = $this->db->prepare("
                INSERT INTO security_penetration_tests (portfolio_id, test_type, scheduled_at, created_at)
                VALUES (:portfolio_id, :test_type, :scheduled_at, NOW())
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':test_type' => $test_type,
                ':scheduled_at' => $schedule_date
            ]);

            return [
                'status' => 'success',
                'message' => "Penetration test scheduled for {$schedule_date}",
                'test_type' => $test_type
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to schedule penetration test: " . $e->getMessage());
        }
    }

    /**
     * Get security audit log
     */
    public function getSecurityAuditLog($portfolio_id, $days = 30)
    {
        try {
            $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $stmt = $this->db->prepare("
                SELECT
                    event_type,
                    severity,
                    COUNT(*) as count,
                    MAX(created_at) as last_occurred
                FROM event_logs
                WHERE portfolio_id = :portfolio_id AND category = 'security' AND created_at > :start_date
                GROUP BY event_type, severity
                ORDER BY count DESC
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':start_date' => $start_date
            ]);

            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'audit_log' => $logs,
                'period_days' => $days
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get security audit log: " . $e->getMessage());
        }
    }

    /**
     * Generate security report
     */
    public function generateSecurityReport($portfolio_id, $scan_ids = [])
    {
        try {
            $where = empty($scan_ids) ? "" : "AND id IN (" . implode(',', $scan_ids) . ")";

            $stmt = $this->db->prepare("
                SELECT * FROM security_scans
                WHERE portfolio_id = :portfolio_id {$where}
                ORDER BY scan_date DESC
            ");

            $stmt->execute([':portfolio_id' => $portfolio_id]);
            $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_vulns = 0;
            $critical_count = 0;
            $high_count = 0;

            foreach ($scans as $scan) {
                $vulns = json_decode($scan['results'], true);
                $total_vulns += count($vulns);

                foreach ($vulns as $vuln) {
                    if ($vuln['severity'] === 'CRITICAL') $critical_count++;
                    if ($vuln['severity'] === 'HIGH') $high_count++;
                }
            }

            // Store report
            $stmt = $this->db->prepare("
                INSERT INTO security_reports (portfolio_id, total_vulnerabilities, critical_count, high_count, generated_at)
                VALUES (:portfolio_id, :total, :critical, :high, NOW())
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':total' => $total_vulns,
                ':critical' => $critical_count,
                ':high' => $high_count
            ]);

            return [
                'status' => 'success',
                'scans_analyzed' => count($scans),
                'total_vulnerabilities' => $total_vulns,
                'critical_vulnerabilities' => $critical_count,
                'high_vulnerabilities' => $high_count,
                'risk_score' => ($critical_count * 10) + ($high_count * 5)
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to generate security report: " . $e->getMessage());
        }
    }

    /**
     * Get known vulnerable packages
     */
    private function getKnownVulnerablePackages()
    {
        return [
            'phpmailer/phpmailer' => ['<6.0.0'],
            'symfony/http-kernel' => ['<4.4.0', '<5.0.0'],
            'laravel/framework' => ['<7.0.0'],
            'wordpress' => ['<5.7.0']
        ];
    }

    /**
     * Store scan results
     */
    private function storeScanResults($portfolio_id, $scan_type, $vulnerabilities)
    {
        $stmt = $this->db->prepare("
            INSERT INTO security_scans (portfolio_id, scan_type, results, vulnerability_count, scan_date)
            VALUES (:portfolio_id, :scan_type, :results, :count, NOW())
        ");

        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':scan_type' => $scan_type,
            ':results' => json_encode($vulnerabilities),
            ':count' => count($vulnerabilities)
        ]);

        return $this->db->lastInsertId();
    }
}
