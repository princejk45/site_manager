<?php
/**
 * Caching Service
 * 
 * Multi-layer caching system with Redis support, cache invalidation,
 * TTL management, and cache strategies (LRU, LFU, FIFO).
 */

class CachingService {
    
    private $pdo;
    private $redis;
    private $auditTrail;
    private $userId;
    
    // Cache strategies
    const STRATEGY_LRU = 'lru';           // Least Recently Used
    const STRATEGY_LFU = 'lfu';           // Least Frequently Used
    const STRATEGY_FIFO = 'fifo';         // First In First Out
    const STRATEGY_TTL = 'ttl';           // Time To Live
    
    // Cache tiers
    const TIER_MEMORY = 'memory';
    const TIER_REDIS = 'redis';
    const TIER_DATABASE = 'database';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId, $redis = null) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
        $this->redis = $redis;
    }
    
    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @param int $portfolioId Portfolio ID
     * @return mixed Cached value or null
     */
    public function get($key, $portfolioId = null) {
        try {
            // Try Redis first
            if ($this->redis) {
                $value = $this->redis->get($key);
                if ($value !== false) {
                    $this->recordCacheHit($key, self::TIER_REDIS);
                    return json_decode($value, true);
                }
            }
            
            // Try database cache
            $stmt = $this->pdo->prepare("
                SELECT data, ttl
                FROM cache_storage
                WHERE key = ? AND (ttl IS NULL OR expires_at > NOW())
            ");
            
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->recordCacheHit($key, self::TIER_DATABASE);
                return json_decode($result['data'], true);
            }
            
            $this->recordCacheMiss($key);
            return null;
            
        } catch (Exception $e) {
            error_log("CachingService::get - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Set cached value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttlSeconds Time to live in seconds
     * @param int $portfolioId Portfolio ID
     */
    public function set($key, $value, $ttlSeconds = 3600, $portfolioId = null) {
        try {
            $encodedValue = json_encode($value);
            
            // Store in Redis if available
            if ($this->redis) {
                $this->redis->setex($key, $ttlSeconds, $encodedValue);
            }
            
            // Store in database
            $expiresAt = $ttlSeconds ? date('Y-m-d H:i:s', time() + $ttlSeconds) : null;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO cache_storage (key, data, ttl, expires_at, portfolio_id, created_at, last_accessed)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    data = VALUES(data),
                    ttl = VALUES(ttl),
                    expires_at = VALUES(expires_at),
                    last_accessed = NOW()
            ");
            
            $stmt->execute([
                $key,
                $encodedValue,
                $ttlSeconds,
                $expiresAt,
                $portfolioId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("CachingService::set - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete cache entry
     */
    public function delete($key) {
        try {
            if ($this->redis) {
                $this->redis->del($key);
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM cache_storage WHERE key = ?");
            $stmt->execute([$key]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("CachingService::delete - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear cache for portfolio
     */
    public function clearPortfolioCache($portfolioId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT key FROM cache_storage WHERE portfolio_id = ?
            ");
            
            $stmt->execute([$portfolioId]);
            $keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Clear from Redis
            if ($this->redis && !empty($keys)) {
                $this->redis->del(...$keys);
            }
            
            // Clear from database
            $stmt = $this->pdo->prepare("DELETE FROM cache_storage WHERE portfolio_id = ?");
            $stmt->execute([$portfolioId]);
            
            return count($keys);
            
        } catch (Exception $e) {
            error_log("CachingService::clearPortfolioCache - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Cache pattern matching
     */
    public function invalidatePattern($pattern, $portfolioId = null) {
        try {
            $where = [];
            $params = [];
            
            $where[] = 'key LIKE ?';
            $params[] = $pattern;
            
            if ($portfolioId) {
                $where[] = 'portfolio_id = ?';
                $params[] = $portfolioId;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT key FROM cache_storage WHERE " . implode(' AND ', $where)
            );
            
            $stmt->execute($params);
            $keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Clear from Redis
            if ($this->redis && !empty($keys)) {
                $this->redis->del(...$keys);
            }
            
            // Clear from database
            $stmt = $this->pdo->prepare("
                DELETE FROM cache_storage WHERE " . implode(' AND ', $where)
            );
            
            $stmt->execute($params);
            
            return count($keys);
            
        } catch (Exception $e) {
            error_log("CachingService::invalidatePattern - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats($portfolioId = null) {
        try {
            $where = [];
            $params = [];
            
            if ($portfolioId) {
                $where[] = 'portfolio_id = ?';
                $params[] = $portfolioId;
            }
            
            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            
            // Total entries
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM cache_storage $whereClause");
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total size
            $stmt = $this->pdo->prepare("
                SELECT SUM(LENGTH(data)) as total_size FROM cache_storage $whereClause
            ");
            $stmt->execute($params);
            $totalSize = $stmt->fetch(PDO::FETCH_ASSOC)['total_size'] ?? 0;
            
            // Expired entries
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as expired FROM cache_storage 
                WHERE expires_at < NOW() $whereClause
            ");
            $stmt->execute($params);
            $expired = $stmt->fetch(PDO::FETCH_ASSOC)['expired'];
            
            // Cache statistics
            $stmt = $this->pdo->prepare("
                SELECT 
                    hits,
                    misses,
                    ROUND((hits / (hits + misses)) * 100, 2) as hit_rate
                FROM cache_stats
                WHERE portfolio_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            
            $stmt->execute([$portfolioId]);
            $cacheStats = $stmt->fetch(PDO::FETCH_ASSOC) ?? [
                'hits' => 0,
                'misses' => 0,
                'hit_rate' => 0
            ];
            
            return [
                'total_entries' => $total,
                'total_size_bytes' => $totalSize,
                'expired_entries' => $expired,
                'hits' => $cacheStats['hits'],
                'misses' => $cacheStats['misses'],
                'hit_rate_percent' => $cacheStats['hit_rate']
            ];
            
        } catch (PDOException $e) {
            error_log("CachingService::getCacheStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Cleanup expired cache entries
     */
    public function cleanupExpired($maxAge = 86400) {
        try {
            $cutoffDate = date('Y-m-d H:i:s', time() - $maxAge);
            
            $stmt = $this->pdo->prepare("
                DELETE FROM cache_storage 
                WHERE expires_at < ? OR (last_accessed < ? AND expires_at IS NOT NULL)
            ");
            
            $stmt->execute([$cutoffDate, $cutoffDate]);
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("CachingService::cleanupExpired - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get top accessed cache keys
     */
    public function getTopKeys($limit = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    key,
                    portfolio_id,
                    access_count,
                    last_accessed
                FROM cache_storage
                ORDER BY access_count DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("CachingService::getTopKeys - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Warm cache with predefined data
     */
    public function warmCache($portfolioId, $dataProvider) {
        try {
            $count = 0;
            
            if (is_callable($dataProvider)) {
                $data = $dataProvider($portfolioId);
            } else {
                $data = $dataProvider;
            }
            
            foreach ($data as $key => $value) {
                if ($this->set($key, $value, 3600, $portfolioId)) {
                    $count++;
                }
            }
            
            $this->auditTrail->log('cache_warmed', 'portfolio_id=' . $portfolioId . ';entries=' . $count);
            return $count;
            
        } catch (Exception $e) {
            error_log("CachingService::warmCache - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Private: Record cache hit
     */
    private function recordCacheHit($key, $tier) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO cache_stats (key, tier, hits, misses, created_at)
                VALUES (?, ?, 1, 0, NOW())
                ON DUPLICATE KEY UPDATE
                    hits = hits + 1
            ");
            
            $stmt->execute([$key, $tier]);
            
        } catch (Exception $e) {
            // Silent fail on stats
        }
    }
    
    /**
     * Private: Record cache miss
     */
    private function recordCacheMiss($key) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO cache_stats (key, hits, misses, created_at)
                VALUES (?, 0, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    misses = misses + 1
            ");
            
            $stmt->execute([$key]);
            
        } catch (Exception $e) {
            // Silent fail on stats
        }
    }
}
?>
