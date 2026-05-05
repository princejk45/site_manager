<?php
/**
 * Database Optimization Service
 * 
 * Query optimization, connection pooling, query analysis,
 * index recommendations, and performance monitoring.
 */

class DatabaseOptimizationService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    private $slowQueryThresholdMs = 1000;
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Analyze query performance
     */
    public function analyzeQuery($sql) {
        try {
            $result = [];
            
            // Get EXPLAIN plan
            $explainSql = 'EXPLAIN FORMAT=JSON ' . $sql;
            $stmt = $this->pdo->query($explainSql);
            $explain = json_decode($stmt->fetch(PDO::FETCH_COLUMN), true);
            
            $result['explain'] = $explain;
            $result['full_scan'] = false;
            $result['recommendations'] = [];
            
            // Check for full table scans
            if (isset($explain['query_block']['table'])) {
                foreach ((array)$explain['query_block']['table'] as $table) {
                    if ($table['access_type'] === 'ALL') {
                        $result['full_scan'] = true;
                        $result['recommendations'][] = "Table '" . $table['table_name'] . "' is doing full table scan. Consider adding indexes.";
                    }
                    
                    if ($table['rows_examined'] > 100000) {
                        $result['recommendations'][] = "Query examines " . $table['rows_examined'] . " rows. Optimize query or add indexes.";
                    }
                }
            }
            
            // Check for missing indexes
            if (isset($explain['query_block']['possible_keys']) === null) {
                $result['recommendations'][] = "No suitable indexes found for this query.";
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("DatabaseOptimizationService::analyzeQuery - " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get slow queries
     */
    public function getSlowQueries($limit = 20, $thresholdMs = 1000) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    query,
                    COUNT(*) as execution_count,
                    AVG(duration_ms) as avg_duration,
                    MAX(duration_ms) as max_duration,
                    MIN(duration_ms) as min_duration,
                    SUM(duration_ms) as total_duration
                FROM slow_queries
                WHERE duration_ms > ?
                GROUP BY query
                ORDER BY total_duration DESC
                LIMIT ?
            ");
            
            $stmt->execute([$thresholdMs, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("DatabaseOptimizationService::getSlowQueries - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log query performance
     */
    public function logQueryPerformance($query, $durationMs) {
        try {
            if ($durationMs > $this->slowQueryThresholdMs) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO slow_queries (query, duration_ms, executed_at)
                    VALUES (?, ?, NOW())
                ");
                
                $stmt->execute([$query, $durationMs]);
            }
            
        } catch (Exception $e) {
            // Silent fail on performance logging
        }
    }
    
    /**
     * Get table statistics
     */
    public function getTableStats($database, $table = null) {
        try {
            $where = ['TABLE_SCHEMA = ?'];
            $params = [$database];
            
            if ($table) {
                $where[] = 'TABLE_NAME = ?';
                $params[] = $table;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $sql = "
                SELECT 
                    TABLE_NAME,
                    TABLE_ROWS as row_count,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
                    ROUND((data_length / 1024 / 1024), 2) as data_size_mb,
                    ROUND((index_length / 1024 / 1024), 2) as index_size_mb,
                    AUTO_INCREMENT,
                    CREATE_TIME,
                    UPDATE_TIME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE $whereClause
                ORDER BY size_mb DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("DatabaseOptimizationService::getTableStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get index statistics
     */
    public function getIndexStats($database, $table) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    INDEX_NAME,
                    COLUMN_NAME,
                    SEQ_IN_INDEX,
                    CARDINALITY
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY INDEX_NAME, SEQ_IN_INDEX
            ");
            
            $stmt->execute([$database, $table]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("DatabaseOptimizationService::getIndexStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Recommend missing indexes
     */
    public function recommendIndexes($database, $table) {
        try {
            // Analyze slow queries on this table
            $stmt = $this->pdo->prepare("
                SELECT query FROM slow_queries 
                WHERE query LIKE CONCAT('%', ?, '%')
                LIMIT 100
            ");
            
            $stmt->execute([$table]);
            $queries = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $recommendations = [];
            
            foreach ($queries as $query) {
                $analysis = $this->analyzeQuery($query);
                if (!empty($analysis['recommendations'])) {
                    $recommendations = array_merge($recommendations, $analysis['recommendations']);
                }
            }
            
            return array_unique($recommendations);
            
        } catch (Exception $e) {
            error_log("DatabaseOptimizationService::recommendIndexes - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Optimize table
     */
    public function optimizeTable($table) {
        try {
            $stmt = $this->pdo->prepare("OPTIMIZE TABLE " . $table);
            $stmt->execute();
            
            $this->auditTrail->log('table_optimized', 'table=' . $table);
            return true;
            
        } catch (Exception $e) {
            error_log("DatabaseOptimizationService::optimizeTable - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Analyze table
     */
    public function analyzeTable($table) {
        try {
            $stmt = $this->pdo->prepare("ANALYZE TABLE " . $table);
            $stmt->execute();
            
            $this->auditTrail->log('table_analyzed', 'table=' . $table);
            return true;
            
        } catch (Exception $e) {
            error_log("DatabaseOptimizationService::analyzeTable - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get database statistics
     */
    public function getDatabaseStats($database) {
        try {
            $stats = [];
            
            // Total tables
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_count FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = ?
            ");
            $stmt->execute([$database]);
            $stats['table_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['table_count'];
            
            // Total size
            $stmt = $this->pdo->prepare("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as total_size_mb
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = ?
            ");
            $stmt->execute([$database]);
            $stats['total_size_mb'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_size_mb'];
            
            // Total rows
            $stmt = $this->pdo->prepare("
                SELECT SUM(TABLE_ROWS) as total_rows
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = ?
            ");
            $stmt->execute([$database]);
            $stats['total_rows'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_rows'];
            
            // Get version
            $stmt = $this->pdo->query("SELECT VERSION() as version");
            $stats['mysql_version'] = $stmt->fetch(PDO::FETCH_ASSOC)['version'];
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("DatabaseOptimizationService::getDatabaseStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active connections
     */
    public function getActiveConnections() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    ID,
                    USER,
                    HOST,
                    DB,
                    COMMAND,
                    TIME,
                    STATE,
                    INFO
                FROM INFORMATION_SCHEMA.PROCESSLIST
                WHERE COMMAND != 'Sleep'
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("DatabaseOptimizationService::getActiveConnections - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Kill long-running query
     */
    public function killQuery($queryId) {
        try {
            $stmt = $this->pdo->prepare("KILL QUERY ?");
            $stmt->execute([$queryId]);
            
            $this->auditTrail->log('query_killed', 'query_id=' . $queryId);
            return true;
            
        } catch (Exception $e) {
            error_log("DatabaseOptimizationService::killQuery - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get query cache statistics
     */
    public function getQueryCacheStats() {
        try {
            $stmt = $this->pdo->query("SHOW STATUS LIKE 'Qcache%'");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats = [];
            foreach ($results as $row) {
                $stats[$row['Variable_name']] = $row['Value'];
            }
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("DatabaseOptimizationService::getQueryCacheStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Recommend schema improvements
     */
    public function getSchemaRecommendations($database) {
        try {
            $recommendations = [];
            
            // Check for unused indexes
            $stmt = $this->pdo->prepare("
                SELECT 
                    OBJECT_SCHEMA,
                    OBJECT_NAME,
                    INDEX_NAME
                FROM performance_schema.table_io_waits_summary_by_index_usage
                WHERE OBJECT_SCHEMA != 'mysql'
                  AND COUNT_STAR = 0
                  AND INDEX_NAME != 'PRIMARY'
            ");
            
            $stmt->execute();
            $unusedIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($unusedIndexes)) {
                $recommendations[] = [
                    'type' => 'unused_indexes',
                    'count' => count($unusedIndexes),
                    'details' => $unusedIndexes,
                    'action' => 'Consider dropping unused indexes to improve write performance'
                ];
            }
            
            // Check for missing primary keys
            $stmt = $this->pdo->prepare("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
                  AND TABLE_NAME NOT IN (
                    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = ? AND INDEX_NAME = 'PRIMARY'
                  )
            ");
            
            $stmt->execute([$database, $database]);
            $missingPks = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($missingPks)) {
                $recommendations[] = [
                    'type' => 'missing_primary_keys',
                    'count' => count($missingPks),
                    'tables' => $missingPks,
                    'action' => 'Add primary keys to these tables for better performance'
                ];
            }
            
            return $recommendations;
            
        } catch (Exception $e) {
            error_log("DatabaseOptimizationService::getSchemaRecommendations - " . $e->getMessage());
            return [];
        }
    }
}
?>
