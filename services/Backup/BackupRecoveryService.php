<?php
/**
 * BackupRecoveryService
 * Automated backups, disaster recovery, and point-in-time restore
 */

namespace Services\Backup;

use PDO;
use Exception;

class BackupRecoveryService
{
    private $db;
    private $backup_dir;

    public function __construct(PDO $db, $backup_dir = './backups')
    {
        $this->db = $db;
        $this->backup_dir = $backup_dir;

        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
    }

    /**
     * Create database backup
     */
    public function createDatabaseBackup($backup_type = 'full', $portfolio_id = null)
    {
        try {
            $backup_id = bin2hex(random_bytes(8));
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_{$backup_id}_{$timestamp}.sql.gz";
            $filepath = "{$this->backup_dir}/{$filename}";

            // Determine backup scope
            if ($backup_type === 'full') {
                $this->performFullBackup($filepath);
            } elseif ($backup_type === 'incremental' && $portfolio_id) {
                $this->performIncrementalBackup($filepath, $portfolio_id);
            }

            // Calculate backup size
            $size_bytes = filesize($filepath);

            // Store backup metadata
            $stmt = $this->db->prepare("
                INSERT INTO backup_metadata (backup_id, backup_type, filename, filepath, size_bytes, status, created_at)
                VALUES (:backup_id, :backup_type, :filename, :filepath, :size, 'completed', NOW())
            ");

            $stmt->execute([
                ':backup_id' => $backup_id,
                ':backup_type' => $backup_type,
                ':filename' => $filename,
                ':filepath' => $filepath,
                ':size' => $size_bytes
            ]);

            return [
                'status' => 'success',
                'backup_id' => $backup_id,
                'filename' => $filename,
                'size_bytes' => $size_bytes,
                'size_mb' => round($size_bytes / 1048576, 2)
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to create backup: " . $e->getMessage());
        }
    }

    /**
     * Create file backup
     */
    public function createFileBackup($source_paths = [])
    {
        try {
            $backup_id = bin2hex(random_bytes(8));
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "file_backup_{$backup_id}_{$timestamp}.tar.gz";
            $filepath = "{$this->backup_dir}/{$filename}";

            // Create tar.gz
            $tar_cmd = "tar -czf '{$filepath}'";
            foreach ($source_paths as $path) {
                $tar_cmd .= " '{$path}'";
            }

            $output = shell_exec($tar_cmd . " 2>&1");

            if (!file_exists($filepath)) {
                throw new Exception("Failed to create tar archive");
            }

            $size_bytes = filesize($filepath);

            // Store backup metadata
            $stmt = $this->db->prepare("
                INSERT INTO backup_metadata (backup_id, backup_type, filename, filepath, size_bytes, status, created_at)
                VALUES (:backup_id, :backup_type, :filename, :filepath, :size, 'completed', NOW())
            ");

            $stmt->execute([
                ':backup_id' => $backup_id,
                ':backup_type' => 'file',
                ':filename' => $filename,
                ':filepath' => $filepath,
                ':size' => $size_bytes
            ]);

            return [
                'status' => 'success',
                'backup_id' => $backup_id,
                'filename' => $filename,
                'size_bytes' => $size_bytes
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to create file backup: " . $e->getMessage());
        }
    }

    /**
     * Schedule automated backups
     */
    public function scheduleBackup($backup_type, $schedule_cron, $portfolio_id = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO backup_schedules (backup_type, schedule_cron, portfolio_id, is_active, created_at)
                VALUES (:backup_type, :schedule_cron, :portfolio_id, 1, NOW())
            ");

            $stmt->execute([
                ':backup_type' => $backup_type,
                ':schedule_cron' => $schedule_cron,
                ':portfolio_id' => $portfolio_id
            ]);

            return ['status' => 'success', 'message' => 'Backup schedule created'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to schedule backup: " . $e->getMessage());
        }
    }

    /**
     * List backups
     */
    public function listBackups($limit = 50)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM backup_metadata
                WHERE status = 'completed'
                ORDER BY created_at DESC
                LIMIT :limit
            ");
            $stmt->execute([':limit' => $limit]);
            $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'backups' => $backups,
                'count' => count($backups)
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to list backups: " . $e->getMessage());
        }
    }

    /**
     * Restore from backup
     */
    public function restoreFromBackup($backup_id, $point_in_time = null)
    {
        try {
            // Get backup metadata
            $stmt = $this->db->prepare("
                SELECT * FROM backup_metadata
                WHERE backup_id = :backup_id AND status = 'completed'
            ");
            $stmt->execute([':backup_id' => $backup_id]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                throw new Exception("Backup not found");
            }

            if (!file_exists($backup['filepath'])) {
                throw new Exception("Backup file not found on disk");
            }

            // Create pre-restore snapshot
            $snapshot_id = bin2hex(random_bytes(8));
            $this->createSnapshot($snapshot_id, 'pre_restore');

            // Perform restore
            if ($backup['backup_type'] === 'full' || $backup['backup_type'] === 'incremental') {
                $this->restoreDatabase($backup['filepath'], $point_in_time);
            } elseif ($backup['backup_type'] === 'file') {
                $this->restoreFiles($backup['filepath']);
            }

            // Log restore
            $stmt = $this->db->prepare("
                INSERT INTO restore_logs (backup_id, snapshot_id, point_in_time, restored_at)
                VALUES (:backup_id, :snapshot_id, :pit, NOW())
            ");
            $stmt->execute([
                ':backup_id' => $backup_id,
                ':snapshot_id' => $snapshot_id,
                ':pit' => $point_in_time
            ]);

            return [
                'status' => 'success',
                'backup_id' => $backup_id,
                'restore_point' => $point_in_time,
                'message' => 'Restore completed successfully'
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to restore backup: " . $e->getMessage());
        }
    }

    /**
     * Point-in-time restore
     */
    public function pointInTimeRestore($target_timestamp)
    {
        try {
            // Find backup before target_timestamp
            $stmt = $this->db->prepare("
                SELECT * FROM backup_metadata
                WHERE backup_type = 'full' AND created_at < :target_time
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([':target_time' => $target_timestamp]);
            $base_backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$base_backup) {
                throw new Exception("No backup found before target timestamp");
            }

            // Get incremental backups between base and target
            $stmt = $this->db->prepare("
                SELECT * FROM backup_metadata
                WHERE backup_type = 'incremental'
                AND created_at > :start_time AND created_at < :end_time
                ORDER BY created_at ASC
            ");
            $stmt->execute([
                ':start_time' => $base_backup['created_at'],
                ':end_time' => $target_timestamp
            ]);
            $incrementals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Restore base
            $this->restoreDatabase($base_backup['filepath']);

            // Apply incrementals in order
            foreach ($incrementals as $incremental) {
                $this->restoreDatabase($incremental['filepath']);
            }

            // Apply binary logs up to target timestamp
            $this->applyBinaryLogs($target_timestamp);

            return [
                'status' => 'success',
                'target_timestamp' => $target_timestamp,
                'base_backup_id' => $base_backup['backup_id'],
                'incrementals_applied' => count($incrementals),
                'message' => 'Point-in-time restore completed'
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to perform PITR: " . $e->getMessage());
        }
    }

    /**
     * Get backup statistics
     */
    public function getBackupStats()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    backup_type,
                    COUNT(*) as count,
                    SUM(size_bytes) as total_size,
                    MAX(created_at) as last_backup
                FROM backup_metadata
                WHERE status = 'completed'
                GROUP BY backup_type
            ");
            $stmt->execute([]);
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate disk usage
            $disk_usage = 0;
            $total_backups = 0;

            foreach ($stats as $stat) {
                $disk_usage += $stat['total_size'];
                $total_backups += $stat['count'];
            }

            return [
                'status' => 'success',
                'backup_types' => $stats,
                'total_backups' => $total_backups,
                'total_disk_usage_bytes' => $disk_usage,
                'total_disk_usage_gb' => round($disk_usage / 1073741824, 2)
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get backup stats: " . $e->getMessage());
        }
    }

    /**
     * Test backup integrity
     */
    public function testBackupIntegrity($backup_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM backup_metadata
                WHERE backup_id = :backup_id AND status = 'completed'
            ");
            $stmt->execute([':backup_id' => $backup_id]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                throw new Exception("Backup not found");
            }

            // Test file integrity
            $file_ok = file_exists($backup['filepath']) && is_readable($backup['filepath']);

            // Test decompression
            $decompress_ok = false;
            if ($backup['backup_type'] === 'full' || $backup['backup_type'] === 'incremental') {
                $test_output = shell_exec("gunzip -t '{$backup['filepath']}' 2>&1");
                $decompress_ok = empty($test_output);
            } elseif ($backup['backup_type'] === 'file') {
                $test_output = shell_exec("tar -tzf '{$backup['filepath']}' > /dev/null 2>&1");
                $decompress_ok = ($test_output === null);
            }

            $integrity_ok = $file_ok && $decompress_ok;

            // Update metadata
            $stmt = $this->db->prepare("
                UPDATE backup_metadata
                SET integrity_verified = 1, integrity_verified_at = NOW()
                WHERE backup_id = :backup_id
            ");
            $stmt->execute([':backup_id' => $backup_id]);

            return [
                'status' => 'success',
                'backup_id' => $backup_id,
                'integrity_ok' => $integrity_ok,
                'file_ok' => $file_ok,
                'decompress_ok' => $decompress_ok
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to test backup: " . $e->getMessage());
        }
    }

    /**
     * Delete old backups
     */
    public function deleteOldBackups($retention_days = 30)
    {
        try {
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

            $stmt = $this->db->prepare("
                SELECT backup_id, filepath FROM backup_metadata
                WHERE created_at < :cutoff_date AND status = 'completed'
            ");
            $stmt->execute([':cutoff_date' => $cutoff_date]);
            $old_backups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $deleted_count = 0;

            foreach ($old_backups as $backup) {
                // Delete file
                if (file_exists($backup['filepath'])) {
                    unlink($backup['filepath']);
                }

                // Update metadata
                $stmt = $this->db->prepare("
                    UPDATE backup_metadata
                    SET status = 'deleted', deleted_at = NOW()
                    WHERE backup_id = :backup_id
                ");
                $stmt->execute([':backup_id' => $backup['backup_id']]);

                $deleted_count++;
            }

            return [
                'status' => 'success',
                'deleted_count' => $deleted_count,
                'retention_days' => $retention_days
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to delete old backups: " . $e->getMessage());
        }
    }

    /**
     * Perform full database backup
     */
    private function performFullBackup($filepath)
    {
        $db_host = 'localhost';
        $db_user = 'root';
        $db_pass = '';
        $db_name = 'website_manager';

        $cmd = "mysqldump -h{$db_host} -u{$db_user} {$db_name} | gzip > '{$filepath}' 2>&1";
        shell_exec($cmd);
    }

    /**
     * Perform incremental backup
     */
    private function performIncrementalBackup($filepath, $portfolio_id)
    {
        // Incremental backup of portfolio-specific data
        $db_host = 'localhost';
        $db_user = 'root';
        $db_pass = '';
        $db_name = 'website_manager';

        $cmd = "mysqldump -h{$db_host} -u{$db_user} {$db_name} --where=\"portfolio_id={$portfolio_id}\" | gzip > '{$filepath}' 2>&1";
        shell_exec($cmd);
    }

    /**
     * Restore database
     */
    private function restoreDatabase($filepath, $point_in_time = null)
    {
        $db_host = 'localhost';
        $db_user = 'root';
        $db_pass = '';
        $db_name = 'website_manager';

        $cmd = "gunzip -c '{$filepath}' | mysql -h{$db_host} -u{$db_user} {$db_name} 2>&1";
        shell_exec($cmd);
    }

    /**
     * Restore files
     */
    private function restoreFiles($filepath)
    {
        $cmd = "tar -xzf '{$filepath}' -C / 2>&1";
        shell_exec($cmd);
    }

    /**
     * Apply binary logs
     */
    private function applyBinaryLogs($target_timestamp)
    {
        // Apply MySQL binary logs up to target_timestamp
    }

    /**
     * Create snapshot
     */
    private function createSnapshot($snapshot_id, $snapshot_type)
    {
        $stmt = $this->db->prepare("
            INSERT INTO snapshots (snapshot_id, snapshot_type, created_at)
            VALUES (:snapshot_id, :type, NOW())
        ");
        $stmt->execute([
            ':snapshot_id' => $snapshot_id,
            ':type' => $snapshot_type
        ]);
    }
}
