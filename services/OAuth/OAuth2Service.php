<?php
/**
 * OAuth2Service
 * Multi-provider OAuth2 implementation supporting Google, Microsoft, GitHub, Okta, Auth0, and OIDC
 */

namespace Services\OAuth;

use PDO;
use Exception;

class OAuth2Service
{
    private $db;
    private $config;
    private $providers;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
        $this->initializeProviders();
    }

    /**
     * Initialize OAuth2 provider configurations
     */
    private function initializeProviders()
    {
        $this->providers = [
            'google' => [
                'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_endpoint' => 'https://www.googleapis.com/oauth2/v4/token',
                'userinfo_endpoint' => 'https://www.googleapis.com/oauth2/v3/userinfo',
                'scope' => 'openid email profile'
            ],
            'microsoft' => [
                'authorization_endpoint' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'token_endpoint' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'userinfo_endpoint' => 'https://graph.microsoft.com/v1.0/me',
                'scope' => 'openid email profile'
            ],
            'github' => [
                'authorization_endpoint' => 'https://github.com/login/oauth/authorize',
                'token_endpoint' => 'https://github.com/login/oauth/access_token',
                'userinfo_endpoint' => 'https://api.github.com/user',
                'scope' => 'user:email'
            ],
            'okta' => [
                'authorization_endpoint' => '%okta_domain%/oauth2/v1/authorize',
                'token_endpoint' => '%okta_domain%/oauth2/v1/token',
                'userinfo_endpoint' => '%okta_domain%/oauth2/v1/userinfo',
                'scope' => 'openid email profile'
            ],
            'auth0' => [
                'authorization_endpoint' => '%auth0_domain%/authorize',
                'token_endpoint' => '%auth0_domain%/oauth2/token',
                'userinfo_endpoint' => '%auth0_domain%/userinfo',
                'scope' => 'openid email profile'
            ],
            'oidc' => [
                'scope' => 'openid email profile'
            ]
        ];
    }

    /**
     * Register OAuth2 provider for portfolio
     */
    public function registerProvider($portfolio_id, $provider_name, $client_id, $client_secret, $provider_config = [])
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO oauth2_providers (portfolio_id, provider_name, client_id, client_secret, provider_config, is_enabled, created_at)
                VALUES (:portfolio_id, :provider_name, :client_id, :client_secret, :provider_config, 1, NOW())
                ON DUPLICATE KEY UPDATE
                client_id = :client_id,
                client_secret = :client_secret,
                provider_config = :provider_config,
                is_enabled = 1,
                updated_at = NOW()
            ");

            $merged_config = array_merge($this->providers[$provider_name] ?? [], $provider_config);
            
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':provider_name' => $provider_name,
                ':client_id' => $client_id,
                ':client_secret' => hash('sha256', $client_secret),
                ':provider_config' => json_encode($merged_config)
            ]);

            return ['status' => 'success', 'message' => "Provider {$provider_name} registered"];
        } catch (\PDOException $e) {
            throw new Exception("Failed to register OAuth2 provider: " . $e->getMessage());
        }
    }

    /**
     * Generate authorization URL for OAuth2 flow
     */
    public function getAuthorizationUrl($portfolio_id, $provider, $redirect_uri, $state = null)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT provider_config FROM oauth2_providers
                WHERE portfolio_id = :portfolio_id AND provider_name = :provider AND is_enabled = 1
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id, ':provider' => $provider]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new Exception("OAuth2 provider not configured");
            }

            $config = json_decode($row['provider_config'], true);
            $state = $state ?? bin2hex(random_bytes(16));

            // Store state for verification
            $this->storeOAuthState($portfolio_id, $state, $provider, $redirect_uri);

            $params = [
                'client_id' => $this->config[$provider]['client_id'] ?? '',
                'redirect_uri' => $redirect_uri,
                'response_type' => 'code',
                'scope' => $config['scope'],
                'state' => $state
            ];

            $auth_url = $config['authorization_endpoint'] . '?' . http_build_query($params);
            
            return [
                'status' => 'success',
                'authorization_url' => $auth_url,
                'state' => $state
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to generate authorization URL: " . $e->getMessage());
        }
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeAuthorizationCode($portfolio_id, $provider, $code, $redirect_uri, $state)
    {
        try {
            // Verify state
            if (!$this->verifyOAuthState($portfolio_id, $state, $provider)) {
                throw new Exception("Invalid state parameter");
            }

            // Get provider config
            $stmt = $this->db->prepare("
                SELECT client_id, client_secret, provider_config FROM oauth2_providers
                WHERE portfolio_id = :portfolio_id AND provider_name = :provider AND is_enabled = 1
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id, ':provider' => $provider]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new Exception("OAuth2 provider not configured");
            }

            $config = json_decode($row['provider_config'], true);
            $client_secret = $this->config[$provider]['client_secret'] ?? '';

            $token_params = [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
                'client_id' => $this->config[$provider]['client_id'] ?? '',
                'client_secret' => $client_secret
            ];

            $token_response = $this->makeTokenRequest($config['token_endpoint'], $token_params);
            
            // Store token and metadata
            $this->storeOAuthToken($portfolio_id, $provider, $token_response);

            return [
                'status' => 'success',
                'access_token' => $token_response['access_token'],
                'token_type' => $token_response['token_type'] ?? 'Bearer',
                'expires_in' => $token_response['expires_in'] ?? 3600
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to exchange authorization code: " . $e->getMessage());
        }
    }

    /**
     * Refresh OAuth2 access token
     */
    public function refreshAccessToken($portfolio_id, $provider)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT refresh_token, provider_config FROM oauth2_tokens
                WHERE portfolio_id = :portfolio_id AND provider_name = :provider AND refresh_token IS NOT NULL
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id, ':provider' => $provider]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token) {
                throw new Exception("No refresh token available");
            }

            $config = json_decode($token['provider_config'], true);
            $refresh_params = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token['refresh_token'],
                'client_id' => $this->config[$provider]['client_id'] ?? '',
                'client_secret' => $this->config[$provider]['client_secret'] ?? ''
            ];

            $token_response = $this->makeTokenRequest($config['token_endpoint'], $refresh_params);
            $this->storeOAuthToken($portfolio_id, $provider, $token_response);

            return [
                'status' => 'success',
                'access_token' => $token_response['access_token'],
                'expires_in' => $token_response['expires_in'] ?? 3600
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to refresh access token: " . $e->getMessage());
        }
    }

    /**
     * Get user info from OAuth2 provider
     */
    public function getUserInfo($portfolio_id, $provider)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT access_token, provider_config FROM oauth2_tokens
                WHERE portfolio_id = :portfolio_id AND provider_name = :provider
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id, ':provider' => $provider]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token) {
                throw new Exception("No access token available");
            }

            $config = json_decode($token['provider_config'], true);
            
            $headers = [
                'Authorization: Bearer ' . $token['access_token'],
                'Accept: application/json'
            ];

            $user_info = $this->makeRequest($config['userinfo_endpoint'], $headers);
            
            return [
                'status' => 'success',
                'user_info' => $user_info
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to get user info: " . $e->getMessage());
        }
    }

    /**
     * Get OAuth2 tokens for portfolio and provider
     */
    public function getTokens($portfolio_id, $provider)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, access_token, token_type, expires_at, refresh_token, scope
                FROM oauth2_tokens
                WHERE portfolio_id = :portfolio_id AND provider_name = :provider
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id, ':provider' => $provider]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token) {
                return ['status' => 'error', 'message' => 'No tokens found'];
            }

            return [
                'status' => 'success',
                'token' => $token
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get tokens: " . $e->getMessage());
        }
    }

    /**
     * Revoke OAuth2 token
     */
    public function revokeToken($portfolio_id, $provider)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE oauth2_tokens
                SET is_revoked = 1, revoked_at = NOW()
                WHERE portfolio_id = :portfolio_id AND provider_name = :provider AND is_revoked = 0
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id, ':provider' => $provider]);

            return ['status' => 'success', 'message' => 'Token revoked'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to revoke token: " . $e->getMessage());
        }
    }

    /**
     * Disconnect OAuth2 provider
     */
    public function disconnectProvider($portfolio_id, $provider)
    {
        try {
            // Revoke all tokens
            $this->revokeToken($portfolio_id, $provider);

            // Disable provider
            $stmt = $this->db->prepare("
                UPDATE oauth2_providers
                SET is_enabled = 0, disconnected_at = NOW()
                WHERE portfolio_id = :portfolio_id AND provider_name = :provider
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id, ':provider' => $provider]);

            return ['status' => 'success', 'message' => "Provider {$provider} disconnected"];
        } catch (\PDOException $e) {
            throw new Exception("Failed to disconnect provider: " . $e->getMessage());
        }
    }

    /**
     * Get OAuth2 statistics
     */
    public function getOAuth2Stats($portfolio_id = null)
    {
        try {
            $where = $portfolio_id ? "WHERE portfolio_id = :portfolio_id" : "";
            $params = $portfolio_id ? [':portfolio_id' => $portfolio_id] : [];

            $stmt = $this->db->prepare("
                SELECT
                    provider_name,
                    COUNT(*) as total_authorizations,
                    SUM(CASE WHEN is_revoked = 0 THEN 1 ELSE 0 END) as active_tokens,
                    SUM(CASE WHEN is_revoked = 1 THEN 1 ELSE 0 END) as revoked_tokens,
                    MAX(created_at) as last_authorization
                FROM oauth2_tokens
                {$where}
                GROUP BY provider_name
            ");
            $stmt->execute($params);
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'statistics' => $stats,
                'total_providers' => count($stats)
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get OAuth2 stats: " . $e->getMessage());
        }
    }

    /**
     * Store OAuth2 state for CSRF prevention
     */
    private function storeOAuthState($portfolio_id, $state, $provider, $redirect_uri)
    {
        $stmt = $this->db->prepare("
            INSERT INTO oauth2_states (portfolio_id, state, provider_name, redirect_uri, expires_at)
            VALUES (:portfolio_id, :state, :provider, :redirect_uri, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ");
        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':state' => $state,
            ':provider' => $provider,
            ':redirect_uri' => $redirect_uri
        ]);
    }

    /**
     * Verify OAuth2 state
     */
    private function verifyOAuthState($portfolio_id, $state, $provider)
    {
        $stmt = $this->db->prepare("
            SELECT id FROM oauth2_states
            WHERE portfolio_id = :portfolio_id AND state = :state AND provider_name = :provider
            AND expires_at > NOW()
        ");
        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':state' => $state,
            ':provider' => $provider
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($result);
    }

    /**
     * Store OAuth2 token
     */
    private function storeOAuthToken($portfolio_id, $provider, $token_response)
    {
        $stmt = $this->db->prepare("
            INSERT INTO oauth2_tokens
            (portfolio_id, provider_name, access_token, token_type, expires_at, refresh_token, scope, provider_config, created_at)
            VALUES (:portfolio_id, :provider, :access_token, :token_type, 
                    DATE_ADD(NOW(), INTERVAL :expires_in SECOND), :refresh_token, :scope, :provider_config, NOW())
        ");

        $expires_in = $token_response['expires_in'] ?? 3600;
        $provider_config = json_encode($this->providers[$provider] ?? []);

        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':provider' => $provider,
            ':access_token' => $token_response['access_token'],
            ':token_type' => $token_response['token_type'] ?? 'Bearer',
            ':expires_in' => $expires_in,
            ':refresh_token' => $token_response['refresh_token'] ?? null,
            ':scope' => $token_response['scope'] ?? '',
            ':provider_config' => $provider_config
        ]);
    }

    /**
     * Make token request to provider
     */
    private function makeTokenRequest($endpoint, $params)
    {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("Token endpoint returned HTTP {$http_code}");
        }

        return json_decode($response, true);
    }

    /**
     * Make HTTP request
     */
    private function makeRequest($url, $headers = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("Request returned HTTP {$http_code}");
        }

        return json_decode($response, true);
    }
}
