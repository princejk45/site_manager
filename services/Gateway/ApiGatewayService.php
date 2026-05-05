<?php
/**
 * API Gateway Service
 * 
 * Central request routing, authentication, rate limiting, versioning,
 * request/response transformation, and API versioning management.
 */

class ApiGatewayService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // API versions
    const VERSION_V1 = 'v1';
    const VERSION_V2 = 'v2';
    
    // Request methods
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    const METHOD_PATCH = 'PATCH';
    
    // Rate limit strategies
    const STRATEGY_FIXED_WINDOW = 'fixed_window';
    const STRATEGY_SLIDING_WINDOW = 'sliding_window';
    const STRATEGY_TOKEN_BUCKET = 'token_bucket';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Route API request
     * 
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array $query Query parameters
     * @param array $body Request body
     * @return array Response
     */
    public function routeRequest($method, $path, $query = [], $body = []) {
        try {
            // Extract API version and endpoint
            $pathParts = explode('/', trim($path, '/'));
            $version = $pathParts[0] ?? self::VERSION_V2;
            $endpoint = $pathParts[1] ?? '';
            
            // Validate version
            $this->validateApiVersion($version);
            
            // Get authentication token
            $token = $this->extractAuthToken();
            
            // Authenticate request
            $authData = $this->authenticateRequest($token);
            if (!$authData) {
                return $this->errorResponse(401, 'Unauthorized', 'Invalid or expired token');
            }
            
            // Check rate limit
            $rateLimitCheck = $this->checkRateLimit($authData['portfolio_id'], $endpoint);
            if (!$rateLimitCheck['allowed']) {
                return $this->errorResponse(429, 'Too Many Requests', 'Rate limit exceeded');
            }
            
            // Log request
            $this->logGatewayRequest($method, $path, $authData);
            
            // Route to handler
            $response = $this->routeToHandler($method, $endpoint, $authData, $query, $body);
            
            // Add rate limit headers
            $response['headers'] = array_merge(
                $response['headers'] ?? [],
                [
                    'X-RateLimit-Limit' => $rateLimitCheck['limit'],
                    'X-RateLimit-Remaining' => $rateLimitCheck['remaining'],
                    'X-RateLimit-Reset' => $rateLimitCheck['reset'],
                    'X-API-Version' => $version
                ]
            );
            
            return $response;
            
        } catch (Exception $e) {
            error_log("ApiGatewayService::routeRequest - " . $e->getMessage());
            return $this->errorResponse(500, 'Internal Server Error', $e->getMessage());
        }
    }
    
    /**
     * Register API endpoint
     */
    public function registerEndpoint($portfolioId, $endpoint, $config) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO api_endpoints (
                    portfolio_id,
                    endpoint,
                    methods,
                    handler_class,
                    handler_method,
                    authentication_required,
                    rate_limit_enabled,
                    rate_limit_requests,
                    rate_limit_window_seconds,
                    cache_enabled,
                    cache_ttl_seconds,
                    documentation,
                    active,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $portfolioId,
                $endpoint,
                json_encode($config['methods'] ?? ['GET']),
                $config['handler_class'],
                $config['handler_method'],
                $config['authentication_required'] ? 1 : 0,
                $config['rate_limit_enabled'] ? 1 : 0,
                $config['rate_limit_requests'] ?? 1000,
                $config['rate_limit_window_seconds'] ?? 3600,
                $config['cache_enabled'] ? 1 : 0,
                $config['cache_ttl_seconds'] ?? 300,
                $config['documentation'] ?? '',
                1,
                $this->userId
            ]);
            
            $endpointId = $this->pdo->lastInsertId();
            $this->auditTrail->log('api_endpoint_registered', 'endpoint=' . $endpoint);
            
            return $endpointId;
            
        } catch (Exception $e) {
            error_log("ApiGatewayService::registerEndpoint - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get API documentation
     */
    public function getDocumentation($portfolioId, $endpoint = null) {
        try {
            if ($endpoint) {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM api_endpoints
                    WHERE portfolio_id = ? AND endpoint = ? AND active = 1
                ");
                $stmt->execute([$portfolioId, $endpoint]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            $stmt = $this->pdo->prepare("
                SELECT endpoint, methods, rate_limit_requests, rate_limit_window_seconds, documentation
                FROM api_endpoints
                WHERE portfolio_id = ? AND active = 1
                ORDER BY endpoint
            ");
            
            $stmt->execute([$portfolioId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("ApiGatewayService::getDocumentation - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get API usage statistics
     */
    public function getApiUsageStats($portfolioId, $hours = 24) {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    endpoint,
                    method,
                    COUNT(*) as request_count,
                    AVG(response_time_ms) as avg_response_time,
                    MAX(response_time_ms) as max_response_time,
                    MIN(response_time_ms) as min_response_time,
                    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count,
                    ROUND((SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as error_rate
                FROM api_gateway_logs
                WHERE portfolio_id = ? AND created_at > ?
                GROUP BY endpoint, method
                ORDER BY request_count DESC
            ");
            
            $stmt->execute([$portfolioId, $startTime]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("ApiGatewayService::getApiUsageStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get slow endpoints
     */
    public function getSlowEndpoints($portfolioId, $thresholdMs = 1000, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    endpoint,
                    method,
                    AVG(response_time_ms) as avg_response_time,
                    COUNT(*) as request_count
                FROM api_gateway_logs
                WHERE portfolio_id = ? AND response_time_ms > ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY endpoint, method
                ORDER BY avg_response_time DESC
                LIMIT ?
            ");
            
            $stmt->execute([$portfolioId, $thresholdMs, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("ApiGatewayService::getSlowEndpoints - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get endpoint errors
     */
    public function getEndpointErrors($portfolioId, $endpoint = null, $limit = 100) {
        try {
            $where = ['portfolio_id = ?', 'status_code >= 400'];
            $params = [$portfolioId];
            
            if ($endpoint) {
                $where[] = 'endpoint = ?';
                $params[] = $endpoint;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM api_gateway_logs
                WHERE $whereClause
                ORDER BY created_at DESC
                LIMIT ?
            ");
            
            $params[] = $limit;
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("ApiGatewayService::getEndpointErrors - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Private: Validate API version
     */
    private function validateApiVersion($version) {
        $validVersions = [self::VERSION_V1, self::VERSION_V2];
        if (!in_array($version, $validVersions)) {
            throw new Exception("Invalid API version: $version");
        }
    }
    
    /**
     * Private: Extract authentication token
     */
    private function extractAuthToken() {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Private: Authenticate request
     */
    private function authenticateRequest($token) {
        if (!$token) {
            return null;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT portfolio_id, user_id, expires_at
                FROM api_tokens
                WHERE token = SHA2(?, 256) AND active = 1 AND expires_at > NOW()
            ");
            
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("ApiGatewayService::authenticateRequest - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Private: Check rate limit
     */
    private function checkRateLimit($portfolioId, $endpoint) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT rate_limit_requests, rate_limit_window_seconds
                FROM api_endpoints
                WHERE portfolio_id = ? AND endpoint = ? AND active = 1
            ");
            
            $stmt->execute([$portfolioId, $endpoint]);
            $endpoint = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$endpoint || !$endpoint['rate_limit_requests']) {
                return ['allowed' => true, 'limit' => 'unlimited', 'remaining' => 'unlimited', 'reset' => null];
            }
            
            // Check request count in window
            $windowStart = date('Y-m-d H:i:s', time() - $endpoint['rate_limit_window_seconds']);
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as request_count
                FROM api_gateway_logs
                WHERE portfolio_id = ? AND endpoint = ? AND created_at > ?
            ");
            
            $stmt->execute([$portfolioId, $endpoint, $windowStart]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['request_count'];
            
            $remaining = max(0, $endpoint['rate_limit_requests'] - $count);
            $reset = time() + $endpoint['rate_limit_window_seconds'];
            
            return [
                'allowed' => $count < $endpoint['rate_limit_requests'],
                'limit' => $endpoint['rate_limit_requests'],
                'remaining' => $remaining,
                'reset' => $reset
            ];
            
        } catch (Exception $e) {
            error_log("ApiGatewayService::checkRateLimit - " . $e->getMessage());
            return ['allowed' => true];
        }
    }
    
    /**
     * Private: Log gateway request
     */
    private function logGatewayRequest($method, $path, $authData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO api_gateway_logs (
                    portfolio_id,
                    user_id,
                    method,
                    path,
                    response_time_ms,
                    status_code,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $authData['portfolio_id'],
                $authData['user_id'],
                $method,
                $path,
                0,
                200,
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ]);
            
        } catch (Exception $e) {
            error_log("ApiGatewayService::logGatewayRequest - " . $e->getMessage());
        }
    }
    
    /**
     * Private: Route to handler
     */
    private function routeToHandler($method, $endpoint, $authData, $query, $body) {
        // Implementation depends on registered handlers
        return [
            'status' => 200,
            'data' => [],
            'headers' => []
        ];
    }
    
    /**
     * Private: Error response
     */
    private function errorResponse($statusCode, $error, $message) {
        return [
            'status' => $statusCode,
            'error' => $error,
            'message' => $message,
            'headers' => ['Content-Type' => 'application/json']
        ];
    }
    
    /**
     * Generate API token
     */
    public function generateApiToken($portfolioId, $name, $expiresInDays = 90) {
        try {
            $token = bin2hex(random_bytes(32));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO api_tokens (
                    portfolio_id,
                    token,
                    name,
                    expires_at,
                    active,
                    created_by
                ) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), 1, ?)
            ");
            
            $stmt->execute([
                $portfolioId,
                hash('sha256', $token),
                $name,
                $expiresInDays,
                $this->userId
            ]);
            
            return $token;
            
        } catch (Exception $e) {
            error_log("ApiGatewayService::generateApiToken - " . $e->getMessage());
            return null;
        }
    }
}
?>
