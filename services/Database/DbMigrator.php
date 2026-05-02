<?php
/**
 * Database Migration Utility
 * Safely creates database tables if they don't exist
 * Preserves existing data, skips tables that already exist
 */
class DbMigrator
{
    private $pdo;
    private $results = [];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Run all pending migrations
     * Returns array with migration results
     */
    public function migrate()
    {
        $this->results = [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'tables' => [],
            'errors' => []
        ];

        try {
            $this->createWordPressSitesTable();
            $this->createWordPressDiagnosticsTable();
            $this->createWordPressSecurityIssuesTable();
            $this->createWordPressKeyRotationLogTable();
        } catch (Exception $e) {
            $this->results['success'] = false;
            $this->results['errors'][] = $e->getMessage();
        }

        return $this->results;
    }

    /**
     * Check if table exists in database
     */
    private function tableExists($tableName)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ");
        $dbName = $this->getDatabase();
        $stmt->execute([$dbName, $tableName]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get current database name
     */
    private function getDatabase()
    {
        $result = $this->pdo->query("SELECT DATABASE()")->fetchColumn();
        return $result;
    }

    /**
     * Create wordpress_sites table
     */
    private function createWordPressSitesTable()
    {
        $tableName = 'wordpress_sites';

        if ($this->tableExists($tableName)) {
            $this->results['tables'][] = [
                'name' => $tableName,
                'status' => 'skipped',
                'reason' => 'Table already exists'
            ];
            return;
        }

        $sql = "
            CREATE TABLE wordpress_sites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                website_id INT NOT NULL UNIQUE,
                wordpress_url VARCHAR(255) NOT NULL,
                api_key VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                last_fetch_timestamp DATETIME,
                last_fetch_status ENUM('healthy', 'degraded', 'auth_failed', 'unreachable', 'invalid_response', 'timeout') DEFAULT NULL,
                last_fetch_error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
                INDEX idx_website (website_id),
                INDEX idx_status (last_fetch_status),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        try {
            $this->pdo->exec($sql);
            $this->results['tables'][] = [
                'name' => $tableName,
                'status' => 'created',
                'reason' => 'Table created successfully'
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to create $tableName: " . $e->getMessage());
        }
    }

    /**
     * Create wordpress_diagnostics table
     */
    private function createWordPressDiagnosticsTable()
    {
        $tableName = 'wordpress_diagnostics';

        if ($this->tableExists($tableName)) {
            $this->results['tables'][] = [
                'name' => $tableName,
                'status' => 'skipped',
                'reason' => 'Table already exists'
            ];
            return;
        }

        $sql = "
            CREATE TABLE wordpress_diagnostics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                wordpress_site_id INT NOT NULL,
                
                wordpress_version VARCHAR(20),
                php_version VARCHAR(20),
                mysql_version VARCHAR(20),
                theme_name VARCHAR(255),
                memory_limit VARCHAR(20),
                debug_mode TINYINT(1),
                health_score INT,
                health_status VARCHAR(50),
                wordfence_installed TINYINT(1),
                active_plugin_count INT,
                
                raw_payload JSON,
                fetch_method ENUM('on_demand', 'scheduled') DEFAULT 'on_demand',
                fetch_duration_ms INT,
                http_status_code INT,
                response_timestamp DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (wordpress_site_id) REFERENCES wordpress_sites(id) ON DELETE CASCADE,
                INDEX idx_site (wordpress_site_id),
                INDEX idx_created (created_at),
                INDEX idx_health (health_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        try {
            $this->pdo->exec($sql);
            $this->results['tables'][] = [
                'name' => $tableName,
                'status' => 'created',
                'reason' => 'Table created successfully'
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to create $tableName: " . $e->getMessage());
        }
    }

    /**
     * Create wordpress_security_issues table
     */
    private function createWordPressSecurityIssuesTable()
    {
        $tableName = 'wordpress_security_issues';

        if ($this->tableExists($tableName)) {
            $this->results['tables'][] = [
                'name' => $tableName,
                'status' => 'skipped',
                'reason' => 'Table already exists'
            ];
            return;
        }

        $sql = "
            CREATE TABLE wordpress_security_issues (
                id INT AUTO_INCREMENT PRIMARY KEY,
                diagnostics_id INT NOT NULL,
                wordpress_site_id INT NOT NULL,
                issue_category VARCHAR(100),
                issue_description TEXT,
                severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
                discovered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                acknowledged_at DATETIME,
                resolved_at DATETIME,
                notes TEXT,
                
                FOREIGN KEY (diagnostics_id) REFERENCES wordpress_diagnostics(id) ON DELETE CASCADE,
                FOREIGN KEY (wordpress_site_id) REFERENCES wordpress_sites(id) ON DELETE CASCADE,
                INDEX idx_site (wordpress_site_id),
                INDEX idx_severity (severity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        try {
            $this->pdo->exec($sql);
            $this->results['tables'][] = [
                'name' => $tableName,
                'status' => 'created',
                'reason' => 'Table created successfully'
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to create $tableName: " . $e->getMessage());
        }
    }

    /**
     * Create wordpress_api_key_rotation_log table
     */
    private function createWordPressKeyRotationLogTable()
    {
        $tableName = 'wordpress_api_key_rotation_log';

        if ($this->tableExists($tableName)) {
            $this->results['tables'][] = [
                'name' => $tableName,
                'status' => 'skipped',
                'reason' => 'Table already exists'
            ];
            return;
        }

        $sql = "
            CREATE TABLE wordpress_api_key_rotation_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                wordpress_site_id INT NOT NULL,
                rotation_reason VARCHAR(255),
                old_key_masked VARCHAR(50),
                new_key_masked VARCHAR(50),
                rotated_by INT,
                rotated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                confirmed_working_at DATETIME,
                status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
                
                FOREIGN KEY (wordpress_site_id) REFERENCES wordpress_sites(id) ON DELETE CASCADE,
                FOREIGN KEY (rotated_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_site (wordpress_site_id),
                INDEX idx_rotated_at (rotated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        try {
            $this->pdo->exec($sql);
            $this->results['tables'][] = [
                'name' => $tableName,
                'status' => 'created',
                'reason' => 'Table created successfully'
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to create $tableName: " . $e->getMessage());
        }
    }
}
