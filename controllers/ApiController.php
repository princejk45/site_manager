<?php

class ApiController
{
    private PDO $pdo;
    private int $rateLimitPerMinute = 120;
    private int $rateWindowSeconds = 60;
    private string $currentEndpoint = 'status';
    private ?array $currentAuth = null;
    private float $requestStartedAt = 0.0;
    private bool $responseLogged = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function handle(string $do): void
    {
        $this->currentEndpoint = $do ?: 'status';
        $this->requestStartedAt = microtime(true);
        $this->responseLogged = false;
        $this->currentAuth = null;
        $this->ensureAuditTable();

        try {
            switch ($do) {
                case 'status':
                    $this->jsonResponse([
                        'ok' => true,
                        'service' => 'site_manager_api',
                        'time' => gmdate('c')
                    ]);
                    return;

                case 'websites':
                    $this->requireMethod('GET');
                    $auth = $this->requireApiKey('read_websites');
                    $this->websites($auth);
                    return;

                case 'notifications':
                    $this->requireMethod('GET');
                    $auth = $this->requireApiKey('read_notifications');
                    $this->notifications($auth);
                    return;

                case 'reports_summary':
                    $this->requireMethod('GET');
                    $auth = $this->requireApiKey('read_reports');
                    $this->reportsSummary($auth);
                    return;

                case 'export_websites':
                    $this->requireMethod('GET');
                    $auth = $this->requireApiKey('export_data');
                    $this->exportWebsites($auth);
                    return;

                default:
                    $this->jsonError('Endpoint not found', 404);
                    return;
            }
        } catch (Throwable $e) {
            error_log('ApiController error: ' . $e->getMessage());
            $this->jsonError('Internal server error', 500);
        }
    }

    private function websites(array $auth): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = (int)($_GET['limit'] ?? 50);
        $limit = max(1, min(200, $limit));
        $offset = ($page - 1) * $limit;

        $stmt = $this->pdo->prepare(
                'SELECT w.id, w.domain, w.service_type, w.expiry_date, w.status,
                    COALESCE(h.name, "") AS client_name
             FROM websites w
             LEFT JOIN hosting h ON h.id = w.hosting_id
             ORDER BY w.id DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->jsonResponse([
            'ok' => true,
            'scope' => 'read_websites',
            'key_prefix' => $auth['key_prefix'],
            'page' => $page,
            'limit' => $limit,
            'count' => count($rows),
            'data' => $rows,
        ]);
    }

    private function notifications(array $auth): void
    {
        $limit = (int)($_GET['limit'] ?? 100);
        $limit = max(1, min(500, $limit));

        $stmt = $this->pdo->prepare(
            'SELECT ne.id, ne.created_at, ne.event_type, ne.channel, ne.severity, ne.status,
                    COALESCE(w.domain, "") AS domain
             FROM notification_events ne
             LEFT JOIN websites w ON w.id = ne.website_id
             ORDER BY ne.created_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->jsonResponse([
            'ok' => true,
            'scope' => 'read_notifications',
            'key_prefix' => $auth['key_prefix'],
            'count' => count($rows),
            'data' => $rows,
        ]);
    }

    private function reportsSummary(array $auth): void
    {
        $totalWebsites = (int)$this->pdo->query('SELECT COUNT(*) FROM websites')->fetchColumn();
        $activeWebsites = (int)$this->pdo->query("SELECT COUNT(*) FROM websites WHERE status = 'active'")->fetchColumn();
        $expiring30 = (int)$this->pdo->query(
            'SELECT COUNT(*)
             FROM websites
                         WHERE expiry_date IS NOT NULL
                             AND expiry_date >= CURDATE()
                             AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)'
        )->fetchColumn();
        $notifications30 = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM notification_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        )->fetchColumn();

        $this->jsonResponse([
            'ok' => true,
            'scope' => 'read_reports',
            'key_prefix' => $auth['key_prefix'],
            'data' => [
                'total_websites' => $totalWebsites,
                'active_websites' => $activeWebsites,
                'expiring_in_30_days' => $expiring30,
                'notifications_last_30_days' => $notifications30,
            ],
        ]);
    }

    private function exportWebsites(array $auth): void
    {
        $format = strtolower(trim((string)($_GET['format'] ?? 'json')));

        $rows = $this->pdo->query(
                'SELECT w.id, w.domain, w.service_type, w.expiry_date, w.status,
                    COALESCE(h.name, "") AS client_name,
                    COALESCE(w.manutenzione, 0) AS maintenance_cost
             FROM websites w
             LEFT JOIN hosting h ON h.id = w.hosting_id
             ORDER BY w.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        if ($format === 'csv') {
            $filename = 'api_websites_export_' . gmdate('Ymd_His') . '.csv';
            $this->applyRateLimitHeaders();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $out = fopen('php://output', 'w');
            if ($out === false) {
                $this->jsonError('Unable to open output stream', 500);
                return;
            }

            fputcsv($out, ['id', 'domain', 'service_type', 'expiry_date', 'status', 'client_name', 'maintenance_cost']);
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
            $this->finalizeAuditLog(200);
            return;
        }

        $this->jsonResponse([
            'ok' => true,
            'scope' => 'export_data',
            'key_prefix' => $auth['key_prefix'],
            'format' => 'json',
            'count' => count($rows),
            'data' => $rows,
        ]);
    }

    private function requireApiKey(string $requiredScope): array
    {
        $token = $this->extractBearerToken();
        if ($token === null || $token === '') {
            $this->jsonError('Missing Authorization Bearer token', 401);
            exit;
        }

        if (!preg_match('/^fm_[a-f0-9]{8}_[a-f0-9]{40}$/i', $token)) {
            $this->jsonError('Malformed API key', 401);
            exit;
        }

        $keyHash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            'SELECT id, key_prefix, scopes_json, is_active, expires_at
             FROM api_keys
             WHERE key_hash = ?
             LIMIT 1'
        );
        $stmt->execute([$keyHash]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            $this->jsonError('Invalid API key', 401);
            exit;
        }

        if ((int)$record['is_active'] !== 1) {
            $this->jsonError('API key revoked/inactive', 403);
            exit;
        }

        if (!empty($record['expires_at']) && strtotime((string)$record['expires_at']) < strtotime('today')) {
            $this->jsonError('API key expired', 403);
            exit;
        }

        $scopes = [];
        if (!empty($record['scopes_json'])) {
            $decoded = json_decode((string)$record['scopes_json'], true);
            if (is_array($decoded)) {
                $scopes = $decoded;
            }
        }

        // Empty scopes means full access (legacy/admin behavior in current UI)
        if (!empty($scopes) && !in_array($requiredScope, $scopes, true)) {
            $this->jsonError('Insufficient scope: ' . $requiredScope, 403);
            exit;
        }

        $touch = $this->pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?');
        $touch->execute([(int)$record['id']]);

        $rate = $this->checkRateLimit((int)$record['id']);
        $this->currentAuth = [
            'id' => (int)$record['id'],
            'key_prefix' => (string)$record['key_prefix'],
            'scopes' => $scopes,
            'rate' => $rate,
        ];

        if (!$rate['allowed']) {
            $this->applyRateLimitHeaders();
            $this->jsonError('Rate limit exceeded', 429);
            exit;
        }

        return [
            'id' => (int)$record['id'],
            'key_prefix' => (string)$record['key_prefix'],
            'scopes' => $scopes,
        ];
    }

    private function extractBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

        if (!$header && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $k => $v) {
                    if (strtolower((string)$k) === 'authorization') {
                        $header = $v;
                        break;
                    }
                }
            }
        }

        if (!$header) {
            return null;
        }

        if (preg_match('/Bearer\s+(.+)$/i', (string)$header, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function requireMethod(string $method): void
    {
        $current = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($current !== strtoupper($method)) {
            $this->jsonError('Method not allowed', 405);
            exit;
        }
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        $this->applyRateLimitHeaders();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->finalizeAuditLog($status);
    }

    private function jsonError(string $message, int $status): void
    {
        $this->jsonResponse([
            'ok' => false,
            'error' => $message,
        ], $status);
    }

    private function ensureAuditTable(): void
    {
        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS api_request_audit (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    api_key_id INT NULL,
                    key_prefix VARCHAR(16) NULL,
                    endpoint VARCHAR(100) NOT NULL,
                    http_method VARCHAR(10) NOT NULL,
                    status_code INT NOT NULL,
                    ip_address VARCHAR(64) NULL,
                    user_agent VARCHAR(255) NULL,
                    response_ms INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_api_key_time (api_key_id, created_at),
                    INDEX idx_endpoint_time (endpoint, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (Throwable $e) {
            error_log('ApiController::ensureAuditTable - ' . $e->getMessage());
        }
    }

    private function checkRateLimit(int $apiKeyId): array
    {
        try {
            $windowStart = date('Y-m-d H:i:s', time() - $this->rateWindowSeconds);
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*)
                 FROM api_request_audit
                 WHERE api_key_id = ?
                   AND created_at >= ?'
            );
            $stmt->execute([$apiKeyId, $windowStart]);
            $count = (int)$stmt->fetchColumn();

            $remaining = max(0, $this->rateLimitPerMinute - $count - 1);
            $resetEpoch = time() + $this->rateWindowSeconds;

            return [
                'allowed' => $count < $this->rateLimitPerMinute,
                'limit' => $this->rateLimitPerMinute,
                'remaining' => $remaining,
                'reset' => $resetEpoch,
            ];
        } catch (Throwable $e) {
            error_log('ApiController::checkRateLimit - ' . $e->getMessage());
            return [
                'allowed' => true,
                'limit' => $this->rateLimitPerMinute,
                'remaining' => $this->rateLimitPerMinute,
                'reset' => time() + $this->rateWindowSeconds,
            ];
        }
    }

    private function applyRateLimitHeaders(): void
    {
        if (empty($this->currentAuth['rate']) || !is_array($this->currentAuth['rate'])) {
            return;
        }
        $rate = $this->currentAuth['rate'];
        header('X-RateLimit-Limit: ' . (int)($rate['limit'] ?? $this->rateLimitPerMinute));
        header('X-RateLimit-Remaining: ' . max(0, (int)($rate['remaining'] ?? 0)));
        header('X-RateLimit-Reset: ' . (int)($rate['reset'] ?? (time() + $this->rateWindowSeconds)));
    }

    private function finalizeAuditLog(int $statusCode): void
    {
        if ($this->responseLogged) {
            return;
        }
        $this->responseLogged = true;

        try {
            $elapsedMs = (int)round((microtime(true) - $this->requestStartedAt) * 1000);
            $stmt = $this->pdo->prepare(
                'INSERT INTO api_request_audit
                 (api_key_id, key_prefix, endpoint, http_method, status_code, ip_address, user_agent, response_ms)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                isset($this->currentAuth['id']) ? (int)$this->currentAuth['id'] : null,
                $this->currentAuth['key_prefix'] ?? null,
                $this->currentEndpoint,
                strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                $statusCode,
                $_SERVER['REMOTE_ADDR'] ?? null,
                isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
                max(0, $elapsedMs),
            ]);
        } catch (Throwable $e) {
            error_log('ApiController::finalizeAuditLog - ' . $e->getMessage());
        }
    }
}
