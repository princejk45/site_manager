<?php
/**
 * Rate Limiting Service
 * 
 * API rate limiting with tiered plans, quota management,
 * and traffic shaping for fair resource distribution.
 */

class RateLimitingService {
    
    private $pdo;
    private $auditTrail;
    private $redis; // Optional Redis for distributed rate limiting
    
    // Rate limit tiers
    const TIER_FREE = 'free';
    const TIER_STARTER = 'starter';
    const TIER_PROFESSIONAL = 'professional';
    const TIER_ENTERPRISE = 'enterprise';
    
    // Time windows
    const WINDOW_MINUTE = 60;
    const WINDOW_HOUR = 3600;
    const WINDOW_DAY = 86400;
    
    // Default limits per tier
    private $limits = [
        'free' => [
            'requests_per_minute' => 10,
            'requests_per_hour' => 100,
            'requests_per_day' => 1000,
            'concurrent_connections' => 2,
            'burst_capacity' => 20
        ],
        'starter' => [
            'requests_per_minute' => 100,
            'requests_per_hour' => 5000,
            'requests_per_day' => 100000,
            'concurrent_connections' => 10,
            'burst_capacity' => 200
        ],
        'professional' => [
            'requests_per_minute' => 500,
            'requests_per_hour' => 50000,
            'requests_per_day' => 1000000,
            'concurrent_connections' => 50,
            'burst_capacity' => 1000
        ],
        'enterprise' => [
            'requests_per_minute' => 10000,
            'requests_per_hour' => 1000000,
            'requests_per_day' => 100000000,
            'concurrent_connections' => 1000,
            'burst_capacity' => 50000
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct($pdo, $auditTrail, $redis = null) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->redis = $redis;
    }
    
    /**
     * Check rate limit
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $apiKey API key
     * @param string $endpoint API endpoint
     * @return array Rate limit status
     */
    public function checkRateLimit($portfolioId, $apiKey, $endpoint) {
        try {
            // Get API key tier
            $stmt = $this->pdo->prepare("
                SELECT tier, status FROM api_rate_limits
                WHERE portfolio_id = ? AND api_key_hash = SHA2(?, 256)
            ");
            
            $stmt->execute([$portfolioId, $apiKey]);
            $keyData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$keyData || $keyData['status'] !== 'active') {
                return ['allowed' => false, 'reason' => 'Invalid or inactive API key'];
            }
            
            $tier = $keyData['tier'] ?? self::TIER_FREE;
            $limits = $this->limits[$tier];
            
            // Check different time windows using token bucket algorithm
            $minuteKey = $this->getCacheKey($apiKey, 'minute');
            $hourKey = $this->getCacheKey($apiKey, 'hour');
            $dayKey = $this->getCacheKey($apiKey, 'day');
            
            $minuteCount = $this->getRequestCount($minuteKey, self::WINDOW_MINUTE);
            $hourCount = $this->getRequestCount($hourKey, self::WINDOW_HOUR);
            $dayCount = $this->getRequestCount($dayKey, self::WINDOW_DAY);
            
            // Check limits
            if ($minuteCount >= $limits['requests_per_minute']) {
                return [
                    'allowed' => false,
                    'reason' => 'Rate limit exceeded (per minute)',
                    'limit' => $limits['requests_per_minute'],
                    'current' => $minuteCount,
                    'reset_at' => date('Y-m-d H:i:s', time() + self::WINDOW_MINUTE)
                ];
            }
            
            if ($hourCount >= $limits['requests_per_hour']) {
                return [
                    'allowed' => false,
                    'reason' => 'Rate limit exceeded (per hour)',
                    'limit' => $limits['requests_per_hour'],
                    'current' => $hourCount,
                    'reset_at' => date('Y-m-d H:i:s', time() + self::WINDOW_HOUR)
                ];
            }
            
            if ($dayCount >= $limits['requests_per_day']) {
                return [
                    'allowed' => false,
                    'reason' => 'Rate limit exceeded (per day)',
                    'limit' => $limits['requests_per_day'],
                    'current' => $dayCount,
                    'reset_at' => date('Y-m-d H:i:s', time() + self::WINDOW_DAY)
                ];
            }
            
            // Increment counters
            $this->incrementRequestCount($minuteKey, self::WINDOW_MINUTE);
            $this->incrementRequestCount($hourKey, self::WINDOW_HOUR);
            $this->incrementRequestCount($dayKey, self::WINDOW_DAY);
            
            // Log request
            $this->logRequest($portfolioId, $apiKey, $endpoint);
            
            return [
                'allowed' => true,
                'tier' => $tier,
                'minute_remaining' => $limits['requests_per_minute'] - $minuteCount - 1,
                'hour_remaining' => $limits['requests_per_hour'] - $hourCount - 1,
                'day_remaining' => $limits['requests_per_day'] - $dayCount - 1,
                'minute_limit' => $limits['requests_per_minute'],
                'hour_limit' => $limits['requests_per_hour'],
                'day_limit' => $limits['requests_per_day']
            ];
            
        } catch (Exception $e) {
            error_log("RateLimitingService::checkRateLimit - " . $e->getMessage());
            // Fail open - allow request if rate limiting fails
            return ['allowed' => true, 'error' => 'Rate limit check failed'];
        }
    }
    
    /**
     * Set custom rate limit for portfolio
     */
    public function setCustomLimit($portfolioId, $limitType, $value) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limit_overrides (
                    portfolio_id,
                    limit_type,
                    limit_value
                ) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE limit_value = ?
            ");
            
            $stmt->execute([$portfolioId, $limitType, $value, $value]);
            $this->auditTrail->log('rate_limit_updated', 'portfolio_id=' . $portfolioId . ';type=' . $limitType);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("RateLimitingService::setCustomLimit - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get quota usage
     */
    public function getQuotaUsage($portfolioId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(request_time) as date,
                    COUNT(*) as request_count,
                    COUNT(DISTINCT api_key_hash) as unique_keys
                FROM api_request_logs
                WHERE portfolio_id = ? AND request_time > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(request_time)
                ORDER BY date DESC
            ");
            
            $stmt->execute([$portfolioId]);
            $usage = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalRequests = array_sum(array_column($usage, 'request_count'));
            
            return [
                'portfolio_id' => $portfolioId,
                'total_requests_30d' => $totalRequests,
                'daily_breakdown' => $usage,
                'current_tier' => $this->getCurrentTier($portfolioId)
            ];
            
        } catch (PDOException $e) {
            error_log("RateLimitingService::getQuotaUsage - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Enable/disable API key
     */
    public function toggleApiKey($apiKey, $enabled) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE api_rate_limits
                SET status = ?, updated_at = NOW()
                WHERE api_key_hash = SHA2(?, 256)
            ");
            
            $status = $enabled ? 'active' : 'disabled';
            $stmt->execute([$status, $apiKey]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("RateLimitingService::toggleApiKey - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get rate limit headers for response
     */
    public function getRateLimitHeaders($portfolioId, $apiKey) {
        try {
            $limits = $this->checkRateLimit($portfolioId, $apiKey, '');
            
            $headers = [
                'X-RateLimit-Limit' => $limits['minute_limit'] ?? 0,
                'X-RateLimit-Remaining' => $limits['minute_remaining'] ?? 0,
                'X-RateLimit-Reset' => time() + self::WINDOW_MINUTE
            ];
            
            return $headers;
            
        } catch (Exception $e) {
            error_log("RateLimitingService::getRateLimitHeaders - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Private: Get cache key
     */
    private function getCacheKey($apiKey, $window) {
        $keyHash = substr(hash('sha256', $apiKey), 0, 16);
        $bucket = floor(time() / ($window === 'minute' ? 60 : ($window === 'hour' ? 3600 : 86400)));
        return "ratelimit:{$keyHash}:{$window}:{$bucket}";
    }
    
    /**
     * Private: Get request count
     */
    private function getRequestCount($key, $window) {
        if ($this->redis) {
            return (int)$this->redis->get($key) ?? 0;
        }
        
        // Fallback: Use database (less efficient)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM api_request_logs
            WHERE cache_key = ? AND request_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        
        $stmt->execute([$key, $window]);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    /**
     * Private: Increment request count
     */
    private function incrementRequestCount($key, $window) {
        if ($this->redis) {
            $this->redis->incr($key);
            $this->redis->expire($key, $window + 60);
        }
    }
    
    /**
     * Private: Log request
     */
    private function logRequest($portfolioId, $apiKey, $endpoint) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO api_request_logs (
                    portfolio_id,
                    api_key_hash,
                    endpoint,
                    request_time
                ) VALUES (?, SHA2(?, 256), ?, NOW())
            ");
            
            $stmt->execute([$portfolioId, $apiKey, $endpoint]);
            
        } catch (PDOException $e) {
            // Log but don't fail
            error_log("RateLimitingService::logRequest - " . $e->getMessage());
        }
    }
    
    /**
     * Private: Get current tier
     */
    private function getCurrentTier($portfolioId) {
        $stmt = $this->pdo->prepare("
            SELECT tier FROM api_rate_limits
            WHERE portfolio_id = ?
            LIMIT 1
        ");
        
        $stmt->execute([$portfolioId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['tier'] ?? self::TIER_FREE;
    }
}
?>
