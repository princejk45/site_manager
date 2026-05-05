<?php
/**
 * Load Balancing Service
 * 
 * Server health monitoring, traffic distribution strategies,
 * failover handling, and load balancer management.
 */

class LoadBalancingService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Load balancing strategies
    const STRATEGY_ROUND_ROBIN = 'round_robin';
    const STRATEGY_LEAST_CONN = 'least_connections';
    const STRATEGY_WEIGHTED = 'weighted';
    const STRATEGY_HASH = 'hash';
    const STRATEGY_RANDOM = 'random';
    
    // Health check types
    const HEALTH_HTTP = 'http';
    const HEALTH_TCP = 'tcp';
    const HEALTH_PING = 'ping';
    
    // Server status
    const STATUS_HEALTHY = 'healthy';
    const STATUS_UNHEALTHY = 'unhealthy';
    const STATUS_DEGRADED = 'degraded';
    const STATUS_MAINTENANCE = 'maintenance';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Register backend server
     */
    public function registerServer($portfolioId, $server) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO load_balancer_servers (
                    portfolio_id,
                    host,
                    port,
                    weight,
                    strategy,
                    health_check_type,
                    health_check_path,
                    health_check_interval_seconds,
                    max_connections,
                    status,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $portfolioId,
                $server['host'],
                $server['port'] ?? 80,
                $server['weight'] ?? 1,
                $server['strategy'] ?? self::STRATEGY_ROUND_ROBIN,
                $server['health_check_type'] ?? self::HEALTH_HTTP,
                $server['health_check_path'] ?? '/health',
                $server['health_check_interval_seconds'] ?? 30,
                $server['max_connections'] ?? 1000,
                self::STATUS_HEALTHY,
                $this->userId
            ]);
            
            $serverId = $this->pdo->lastInsertId();
            $this->auditTrail->log('server_registered', 'server_id=' . $serverId);
            
            return $serverId;
            
        } catch (Exception $e) {
            error_log("LoadBalancingService::registerServer - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get next healthy server
     */
    public function getNextServer($portfolioId, $clientId = null) {
        try {
            // Get active servers
            $stmt = $this->pdo->prepare("
                SELECT * FROM load_balancer_servers
                WHERE portfolio_id = ? AND status = ?
                ORDER BY weight DESC
            ");
            
            $stmt->execute([$portfolioId, self::STATUS_HEALTHY]);
            $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($servers)) {
                return null;
            }
            
            // Get load balancing strategy
            $stmt = $this->pdo->prepare("
                SELECT strategy FROM load_balancer_config
                WHERE portfolio_id = ? LIMIT 1
            ");
            
            $stmt->execute([$portfolioId]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            $strategy = $config['strategy'] ?? self::STRATEGY_ROUND_ROBIN;
            
            $server = null;
            
            switch ($strategy) {
                case self::STRATEGY_ROUND_ROBIN:
                    $server = $this->roundRobinSelect($portfolioId, $servers);
                    break;
                    
                case self::STRATEGY_LEAST_CONN:
                    $server = $this->leastConnectionsSelect($servers);
                    break;
                    
                case self::STRATEGY_WEIGHTED:
                    $server = $this->weightedSelect($servers);
                    break;
                    
                case self::STRATEGY_HASH:
                    $server = $this->hashSelect($clientId, $servers);
                    break;
                    
                case self::STRATEGY_RANDOM:
                    $server = $servers[array_rand($servers)];
                    break;
            }
            
            if ($server) {
                $this->recordServerSelection($server['id']);
            }
            
            return $server;
            
        } catch (Exception $e) {
            error_log("LoadBalancingService::getNextServer - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Perform health check
     */
    public function healthCheck($serverId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM load_balancer_servers WHERE id = ?
            ");
            
            $stmt->execute([$serverId]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$server) {
                return false;
            }
            
            $healthy = false;
            $responseTime = 0;
            
            switch ($server['health_check_type']) {
                case self::HEALTH_HTTP:
                    [$healthy, $responseTime] = $this->httpHealthCheck($server);
                    break;
                    
                case self::HEALTH_TCP:
                    [$healthy, $responseTime] = $this->tcpHealthCheck($server);
                    break;
                    
                case self::HEALTH_PING:
                    [$healthy, $responseTime] = $this->pingHealthCheck($server);
                    break;
            }
            
            // Update server status
            $newStatus = $healthy ? self::STATUS_HEALTHY : self::STATUS_UNHEALTHY;
            
            if ($server['status'] !== $newStatus) {
                $stmt = $this->pdo->prepare("
                    UPDATE load_balancer_servers
                    SET status = ?, last_check_time = NOW(), response_time_ms = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([$newStatus, $responseTime, $serverId]);
                
                // Log status change
                $this->auditTrail->log('server_status_changed', 
                    'server_id=' . $serverId . ';from=' . $server['status'] . ';to=' . $newStatus);
            }
            
            return $healthy;
            
        } catch (Exception $e) {
            error_log("LoadBalancingService::healthCheck - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get server statistics
     */
    public function getServerStats($serverId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    host,
                    port,
                    status,
                    total_requests,
                    failed_requests,
                    avg_response_time,
                    last_check_time
                FROM load_balancer_servers
                WHERE id = ?
            ");
            
            $stmt->execute([$serverId]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($server) {
                $server['success_rate'] = $server['total_requests'] > 0 ? 
                    round((($server['total_requests'] - $server['failed_requests']) / $server['total_requests']) * 100, 2) : 0;
            }
            
            return $server;
            
        } catch (PDOException $e) {
            error_log("LoadBalancingService::getServerStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all servers for portfolio
     */
    public function getServers($portfolioId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM load_balancer_servers
                WHERE portfolio_id = ?
                ORDER BY weight DESC
            ");
            
            $stmt->execute([$portfolioId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("LoadBalancingService::getServers - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Set server to maintenance mode
     */
    public function setMaintenance($serverId, $enable = true) {
        try {
            $status = $enable ? self::STATUS_MAINTENANCE : self::STATUS_HEALTHY;
            
            $stmt = $this->pdo->prepare("
                UPDATE load_balancer_servers
                SET status = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$status, $serverId]);
            $this->auditTrail->log('server_maintenance', 'server_id=' . $serverId . ';enabled=' . ($enable ? 1 : 0));
            
            return true;
            
        } catch (Exception $e) {
            error_log("LoadBalancingService::setMaintenance - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get load balancer statistics
     */
    public function getLoadBalancerStats($portfolioId, $hours = 24) {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_servers,
                    SUM(CASE WHEN status = 'healthy' THEN 1 ELSE 0 END) as healthy_servers,
                    SUM(CASE WHEN status = 'unhealthy' THEN 1 ELSE 0 END) as unhealthy_servers,
                    SUM(total_requests) as total_requests,
                    SUM(failed_requests) as failed_requests,
                    AVG(avg_response_time) as avg_response_time
                FROM load_balancer_servers
                WHERE portfolio_id = ?
            ");
            
            $stmt->execute([$portfolioId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stats) {
                $stats['success_rate'] = $stats['total_requests'] > 0 ?
                    round((($stats['total_requests'] - $stats['failed_requests']) / $stats['total_requests']) * 100, 2) : 0;
            }
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("LoadBalancingService::getLoadBalancerStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Private: Round robin selection
     */
    private function roundRobinSelect($portfolioId, $servers) {
        $stmt = $this->pdo->prepare("
            SELECT last_index FROM load_balancer_config WHERE portfolio_id = ?
        ");
        
        $stmt->execute([$portfolioId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastIndex = ($config['last_index'] ?? -1) + 1;
        
        $selectedIndex = $lastIndex % count($servers);
        
        // Update index for next call
        $stmt = $this->pdo->prepare("
            UPDATE load_balancer_config SET last_index = ? WHERE portfolio_id = ?
        ");
        $stmt->execute([$selectedIndex, $portfolioId]);
        
        return $servers[$selectedIndex];
    }
    
    /**
     * Private: Least connections selection
     */
    private function leastConnectionsSelect($servers) {
        usort($servers, function($a, $b) {
            return ($a['active_connections'] ?? 0) - ($b['active_connections'] ?? 0);
        });
        
        return $servers[0];
    }
    
    /**
     * Private: Weighted selection
     */
    private function weightedSelect($servers) {
        $totalWeight = array_sum(array_column($servers, 'weight'));
        $random = rand(1, $totalWeight);
        $sum = 0;
        
        foreach ($servers as $server) {
            $sum += $server['weight'];
            if ($random <= $sum) {
                return $server;
            }
        }
        
        return $servers[0];
    }
    
    /**
     * Private: Hash-based selection
     */
    private function hashSelect($clientId, $servers) {
        if (!$clientId) {
            return $servers[array_rand($servers)];
        }
        
        $hash = crc32($clientId);
        $index = $hash % count($servers);
        
        return $servers[$index];
    }
    
    /**
     * Private: HTTP health check
     */
    private function httpHealthCheck($server) {
        $url = "http://" . $server['host'] . ":" . $server['port'] . $server['health_check_path'];
        
        $start = microtime(true);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        
        @curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $responseTime = (int)((microtime(true) - $start) * 1000);
        
        return [$httpCode >= 200 && $httpCode < 400, $responseTime];
    }
    
    /**
     * Private: TCP health check
     */
    private function tcpHealthCheck($server) {
        $start = microtime(true);
        
        $connection = @fsockopen($server['host'], $server['port'], $errno, $errstr, 3);
        $healthy = is_resource($connection);
        
        if ($healthy) {
            fclose($connection);
        }
        
        $responseTime = (int)((microtime(true) - $start) * 1000);
        
        return [$healthy, $responseTime];
    }
    
    /**
     * Private: PING health check
     */
    private function pingHealthCheck($server) {
        $start = microtime(true);
        
        $output = shell_exec("ping -c 1 " . escapeshellarg($server['host']));
        $healthy = strpos($output, '1 packets transmitted') !== false;
        
        $responseTime = (int)((microtime(true) - $start) * 1000);
        
        return [$healthy, $responseTime];
    }
    
    /**
     * Private: Record server selection
     */
    private function recordServerSelection($serverId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE load_balancer_servers
                SET total_requests = total_requests + 1
                WHERE id = ?
            ");
            
            $stmt->execute([$serverId]);
            
        } catch (Exception $e) {
            // Silent fail on stats
        }
    }
}
?>
