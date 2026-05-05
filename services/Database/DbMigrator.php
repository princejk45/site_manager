<?php
/**
 * Database Migration Utility
 * Safely creates database tables if they don't exist
 * Preserves existing data, skips tables that already exist
 */
class DbMigrator
{
    private PDO $pdo;
    private array $results = [];
    private string $migrationsPath;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $basePath = defined('APP_PATH') ? APP_PATH : dirname(__DIR__, 2);
        $this->migrationsPath = $basePath . '/migrations';
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
            'migrations' => [],
            'errors' => []
        ];

        try {
            $this->ensureMigrationTrackingTable();
            $this->ensureCoreTables();
            $this->runSqlMigrations();
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

    private function ensureMigrationTrackingTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL UNIQUE,
                checksum VARCHAR(64) NOT NULL,
                executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_schema_migrations_executed_at (executed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $this->pdo->exec($sql);
    }

    private function ensureCoreTables(): void
    {
        $coreSql = [
            "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('viewer','manager','super_admin') NOT NULL DEFAULT 'viewer',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_users_username (username),
                UNIQUE KEY uk_users_email (email),
                INDEX idx_users_role (role),
                INDEX idx_users_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            "
            CREATE TABLE IF NOT EXISTS hosting (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                status ENUM('active','warning','expired','suspended','cancelled') NOT NULL DEFAULT 'active',
                expiry_date DATE NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_hosting_name (name),
                INDEX idx_hosting_status (status),
                INDEX idx_hosting_expiry (expiry_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            "
            CREATE TABLE IF NOT EXISTS websites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NULL,
                hosting_id INT NULL,
                domain VARCHAR(255) NOT NULL,
                service_type ENUM('domain','hosting_web','hosting_mail') NOT NULL DEFAULT 'hosting_web',
                status ENUM('active','warning','expired','suspended','cancelled') NOT NULL DEFAULT 'active',
                expiry_date DATE NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_websites_domain (domain),
                INDEX idx_websites_hosting_id (hosting_id),
                INDEX idx_websites_service_type (service_type),
                INDEX idx_websites_expiry (expiry_date),
                CONSTRAINT fk_websites_hosting_id
                    FOREIGN KEY (hosting_id) REFERENCES hosting(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            "
            CREATE TABLE IF NOT EXISTS smtp_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                host VARCHAR(255) NOT NULL,
                port INT NOT NULL DEFAULT 587,
                username VARCHAR(255) NULL,
                password TEXT NULL,
                encryption VARCHAR(20) NULL,
                from_email VARCHAR(255) NULL,
                from_name VARCHAR(255) NULL,
                cc_email VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            "
            CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                website_id INT NULL,
                email_type VARCHAR(100) NOT NULL,
                sent_to VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                body LONGTEXT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'sent',
                error_message TEXT NULL,
                sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email_logs_website_id (website_id),
                INDEX idx_email_logs_sent_at (sent_at),
                CONSTRAINT fk_email_logs_website
                    FOREIGN KEY (website_id) REFERENCES websites(id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            "
            CREATE TABLE IF NOT EXISTS groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_by INT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_groups_created_by
                    FOREIGN KEY (created_by) REFERENCES users(id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            "
            CREATE TABLE IF NOT EXISTS group_members (
                group_id INT NOT NULL,
                user_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (group_id, user_id),
                CONSTRAINT fk_group_members_group
                    FOREIGN KEY (group_id) REFERENCES groups(id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_group_members_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            "
            CREATE TABLE IF NOT EXISTS message_threads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject VARCHAR(255) NOT NULL,
                group_id INT NULL,
                service_id INT NULL,
                client_cc_email VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_message_threads_group_id (group_id),
                INDEX idx_message_threads_service_id (service_id),
                CONSTRAINT fk_message_threads_group
                    FOREIGN KEY (group_id) REFERENCES groups(id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_message_threads_service
                    FOREIGN KEY (service_id) REFERENCES websites(id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            "
            CREATE TABLE IF NOT EXISTS thread_participants (
                thread_id INT NOT NULL,
                user_id INT NOT NULL,
                last_read_at DATETIME NULL,
                is_starred TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (thread_id, user_id),
                INDEX idx_thread_participants_user (user_id),
                CONSTRAINT fk_thread_participants_thread
                    FOREIGN KEY (thread_id) REFERENCES message_threads(id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_thread_participants_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            "
            CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                thread_id INT NOT NULL,
                sender_id INT NOT NULL,
                content LONGTEXT NOT NULL,
                is_first TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_messages_thread_id (thread_id),
                INDEX idx_messages_sender_id (sender_id),
                INDEX idx_messages_created_at (created_at),
                CONSTRAINT fk_messages_thread
                    FOREIGN KEY (thread_id) REFERENCES message_threads(id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_messages_sender
                    FOREIGN KEY (sender_id) REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            "
            CREATE TABLE IF NOT EXISTS google_sheets_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sheet_id VARCHAR(255) NULL,
                sheet_name VARCHAR(255) NOT NULL DEFAULT 'Sheet1',
                credentials LONGTEXT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        ];

        foreach ($coreSql as $sql) {
            $this->pdo->exec($sql);
        }

        $this->results['tables'][] = [
            'name' => 'core_schema',
            'status' => 'created',
            'reason' => 'Core application tables ensured'
        ];
    }

    private function runSqlMigrations(): void
    {
        if (!is_dir($this->migrationsPath)) {
            $this->results['migrations'][] = [
                'name' => 'migrations_dir',
                'status' => 'skipped',
                'reason' => 'Migrations directory not found'
            ];
            return;
        }

        $files = glob($this->migrationsPath . '/*.sql') ?: [];
        usort($files, function (string $a, string $b): int {
            $aBase = basename($a);
            $bBase = basename($b);

            $aNum = (int) (preg_match('/^(\d+)/', $aBase, $mA) ? $mA[1] : 0);
            $bNum = (int) (preg_match('/^(\d+)/', $bBase, $mB) ? $mB[1] : 0);

            if ($aNum !== $bNum) {
                return $aNum <=> $bNum;
            }

            $rank = static function (string $name): int {
                $n = strtolower($name);
                if (strpos($n, 'create') !== false) return 1;
                if (strpos($n, 'add') !== false) return 2;
                if (strpos($n, 'update') !== false) return 3;
                if (strpos($n, 'normalize') !== false) return 4;
                return 5;
            };

            $aRank = $rank($aBase);
            $bRank = $rank($bBase);

            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            return strcmp($aBase, $bBase);
        });

        foreach ($files as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);

            if ($this->isMigrationAlreadyApplied($name, $checksum)) {
                $this->results['migrations'][] = [
                    'name' => $name,
                    'status' => 'skipped',
                    'reason' => 'Already applied'
                ];
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                $this->results['migrations'][] = [
                    'name' => $name,
                    'status' => 'skipped',
                    'reason' => 'Empty file'
                ];
                $this->upsertMigrationRecord($name, $checksum);
                continue;
            }

            try {
                $this->pdo->exec($sql);
                $this->upsertMigrationRecord($name, $checksum);
                $this->results['migrations'][] = [
                    'name' => $name,
                    'status' => 'applied',
                    'reason' => 'Migration executed successfully'
                ];
            } catch (Exception $e) {
                if ($this->isIgnorableMigrationError($e)) {
                    $this->upsertMigrationRecord($name, $checksum);
                    $this->results['migrations'][] = [
                        'name' => $name,
                        'status' => 'skipped',
                        'reason' => 'Detected as already applied (' . $e->getMessage() . ')'
                    ];
                    continue;
                }

                throw new Exception("Migration {$name} failed: " . $e->getMessage());
            }
        }
    }

    private function isIgnorableMigrationError(Exception $e): bool
    {
        if (!$e instanceof PDOException) {
            return false;
        }

        $driverCode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;

        // Common harmless codes for legacy, non-idempotent migration files:
        // 1050 table exists, 1060 duplicate column, 1061 duplicate key,
        // 1062 duplicate entry, 1091 cannot drop missing key/column,
        // 1826 duplicate foreign key constraint name.
        $ignorableCodes = [1050, 1060, 1061, 1062, 1091, 1826];
        if (in_array($driverCode, $ignorableCodes, true)) {
            return true;
        }

        $message = strtolower($e->getMessage());
        $fragments = [
            'already exists',
            'duplicate column',
            'duplicate key name',
            'duplicate entry',
            'duplicate foreign key',
            'check that column/key exists',
            'can\'t drop',
        ];

        foreach ($fragments as $fragment) {
            if (strpos($message, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isMigrationAlreadyApplied(string $filename, string $checksum): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT checksum FROM schema_migrations WHERE filename = ? LIMIT 1'
        );
        $stmt->execute([$filename]);
        $existing = $stmt->fetchColumn();

        return is_string($existing) && hash_equals($existing, $checksum);
    }

    private function upsertMigrationRecord(string $filename, string $checksum): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO schema_migrations (filename, checksum, executed_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), executed_at = VALUES(executed_at)'
        );
        $stmt->execute([$filename, $checksum]);
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
                ssl_valid TINYINT(1),
                wp_version_outdated TINYINT(1),
                security_issues_count INT,
                uptime_percent DECIMAL(5,2),
                average_response_time_ms INT,
                page_load_time_ms INT,
                backup_enabled TINYINT(1),
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
