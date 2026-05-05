<?php
/**
 * SSO Service
 * 
 * Manages Single Sign-On (SSO) authentication via OAuth2
 * Supports: Google, Microsoft, GitHub, Okta, Auth0, generic OIDC
 */

class SsoService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Providers
    const PROVIDER_GOOGLE = 'google';
    const PROVIDER_MICROSOFT = 'microsoft';
    const PROVIDER_GITHUB = 'github';
    const PROVIDER_OKTA = 'okta';
    const PROVIDER_AUTH0 = 'auth0';
    const PROVIDER_GENERIC = 'generic_oidc';
    
    // OAuth endpoints (configuration)
    private $providers = [
        'google' => [
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scopes' => 'openid profile email'
        ],
        'microsoft' => [
            'auth_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'userinfo_url' => 'https://graph.microsoft.com/v1.0/me',
            'scopes' => 'openid profile email'
        ],
        'github' => [
            'auth_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'userinfo_url' => 'https://api.github.com/user',
            'scopes' => 'user:email'
        ],
        'okta' => [
            'auth_url' => '{domain}/oauth2/v1/authorize',
            'token_url' => '{domain}/oauth2/v1/token',
            'userinfo_url' => '{domain}/oauth2/v1/userinfo',
            'scopes' => 'openid profile email'
        ],
        'auth0' => [
            'auth_url' => '{domain}/authorize',
            'token_url' => '{domain}/oauth/token',
            'userinfo_url' => '{domain}/userinfo',
            'scopes' => 'openid profile email'
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, $userId = null) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Register SSO provider
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $provider Provider name
     * @param array $config Provider configuration
     * @return int SSO provider ID
     */
    public function registerProvider($portfolioId, $provider, $config) {
        try {
            // Validate provider
            if (!in_array($provider, [self::PROVIDER_GOOGLE, self::PROVIDER_MICROSOFT, self::PROVIDER_GITHUB, self::PROVIDER_OKTA, self::PROVIDER_AUTH0, self::PROVIDER_GENERIC])) {
                throw new Exception("Invalid provider: $provider");
            }
            
            // Check if provider already registered
            $stmt = $this->pdo->prepare("
                SELECT id FROM sso_providers 
                WHERE portfolio_id = ? AND provider = ?
            ");
            $stmt->execute([$portfolioId, $provider]);
            
            if ($stmt->fetch()) {
                throw new Exception("Provider already registered for this portfolio");
            }
            
            // Encrypt sensitive data
            $clientSecret = $this->encryptSecret($config['client_secret']);
            
            // Insert provider
            $stmt = $this->pdo->prepare("
                INSERT INTO sso_providers (
                    portfolio_id,
                    provider,
                    provider_id,
                    client_id,
                    client_secret,
                    redirect_uri,
                    config,
                    is_enabled
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $portfolioId,
                $provider,
                $config['provider_id'] ?? uniqid(),
                $config['client_id'],
                $clientSecret,
                $config['redirect_uri'],
                json_encode($config),
                1
            ]);
            
            $providerId = $this->pdo->lastInsertId();
            $this->auditTrail->log('sso_provider_registered', 'portfolio_id=' . $portfolioId . ';provider=' . $provider);
            
            return $providerId;
            
        } catch (PDOException $e) {
            error_log("SsoService::registerProvider - " . $e->getMessage());
            throw new Exception("Failed to register provider");
        }
    }
    
    /**
     * Get authorization URL
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $provider Provider name
     * @param string $state CSRF token
     * @return string Authorization URL
     */
    public function getAuthorizationUrl($portfolioId, $provider, $state) {
        try {
            // Get provider config
            $stmt = $this->pdo->prepare("
                SELECT * FROM sso_providers 
                WHERE portfolio_id = ? AND provider = ? AND is_enabled = 1
            ");
            $stmt->execute([$portfolioId, $provider]);
            $providerConfig = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$providerConfig) {
                throw new Exception("Provider not configured");
            }
            
            $config = $providerConfig['config'] ? json_decode($providerConfig['config'], true) : [];
            
            // Build authorization URL
            $authUrl = $this->providers[$provider]['auth_url'] ?? null;
            if (!$authUrl) {
                throw new Exception("Unknown provider");
            }
            
            // Replace domain placeholders for Okta/Auth0
            $authUrl = str_replace('{domain}', $config['domain'] ?? '', $authUrl);
            
            $params = [
                'client_id' => $providerConfig['client_id'],
                'redirect_uri' => $providerConfig['redirect_uri'],
                'response_type' => 'code',
                'scope' => $this->providers[$provider]['scopes'] ?? 'openid profile email',
                'state' => $state,
                'access_type' => 'offline' // For Google refresh tokens
            ];
            
            return $authUrl . '?' . http_build_query($params);
            
        } catch (Exception $e) {
            error_log("SsoService::getAuthorizationUrl - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Exchange authorization code for token
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $provider Provider name
     * @param string $code Authorization code
     * @return array Token data with user info
     */
    public function exchangeCodeForToken($portfolioId, $provider, $code) {
        try {
            // Get provider config
            $stmt = $this->pdo->prepare("
                SELECT * FROM sso_providers 
                WHERE portfolio_id = ? AND provider = ? AND is_enabled = 1
            ");
            $stmt->execute([$portfolioId, $provider]);
            $providerConfig = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$providerConfig) {
                throw new Exception("Provider not configured");
            }
            
            $config = $providerConfig['config'] ? json_decode($providerConfig['config'], true) : [];
            $clientSecret = $this->decryptSecret($providerConfig['client_secret']);
            
            // Get token
            $tokenUrl = $this->providers[$provider]['token_url'];
            $tokenUrl = str_replace('{domain}', $config['domain'] ?? '', $tokenUrl);
            
            $tokenData = [
                'client_id' => $providerConfig['client_id'],
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $providerConfig['redirect_uri'],
                'grant_type' => 'authorization_code'
            ];
            
            $response = $this->postRequest($tokenUrl, $tokenData);
            
            if (!isset($response['access_token'])) {
                throw new Exception("Failed to get access token");
            }
            
            // Get user info
            $userinfoUrl = $this->providers[$provider]['userinfo_url'];
            $userinfoUrl = str_replace('{domain}', $config['domain'] ?? '', $userinfoUrl);
            
            $userInfo = $this->getRequest($userinfoUrl, $response['access_token']);
            
            // Map user info to standard format
            $user = $this->mapUserInfo($provider, $userInfo);
            
            return [
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'] ?? null,
                'expires_in' => $response['expires_in'] ?? 3600,
                'user' => $user,
                'provider' => $provider
            ];
            
        } catch (Exception $e) {
            error_log("SsoService::exchangeCodeForToken - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Map user info to standard format
     */
    private function mapUserInfo($provider, $userInfo) {
        switch ($provider) {
            case self::PROVIDER_GOOGLE:
                return [
                    'id' => $userInfo['sub'],
                    'email' => $userInfo['email'],
                    'name' => $userInfo['name'],
                    'picture' => $userInfo['picture'] ?? null
                ];
            case self::PROVIDER_MICROSOFT:
                return [
                    'id' => $userInfo['id'],
                    'email' => $userInfo['userPrincipalName'],
                    'name' => $userInfo['displayName'],
                    'picture' => null
                ];
            case self::PROVIDER_GITHUB:
                return [
                    'id' => $userInfo['id'],
                    'email' => $userInfo['email'],
                    'name' => $userInfo['name'],
                    'picture' => $userInfo['avatar_url'] ?? null
                ];
            case self::PROVIDER_OKTA:
            case self::PROVIDER_AUTH0:
                return [
                    'id' => $userInfo['sub'],
                    'email' => $userInfo['email'],
                    'name' => $userInfo['name'],
                    'picture' => $userInfo['picture'] ?? null
                ];
            default:
                return [
                    'id' => $userInfo['sub'] ?? $userInfo['id'],
                    'email' => $userInfo['email'],
                    'name' => $userInfo['name'],
                    'picture' => $userInfo['picture'] ?? null
                ];
        }
    }
    
    /**
     * Link SSO account to user
     * 
     * @param int $userId User ID
     * @param string $provider Provider name
     * @param array $ssoData SSO user data
     * @return bool Success
     */
    public function linkSsoAccount(int $userId, $provider, $ssoData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_sso_accounts (
                    user_id,
                    provider,
                    provider_user_id,
                    email,
                    name,
                    picture_url,
                    access_token,
                    refresh_token,
                    expires_at,
                    last_login_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    expires_at = VALUES(expires_at),
                    last_login_at = NOW()
            ");
            
            $expiresAt = $ssoData['expires_in'] ? date('Y-m-d H:i:s', time() + $ssoData['expires_in']) : null;
            
            $stmt->execute([
                $userId,
                $provider,
                $ssoData['user']['id'],
                $ssoData['user']['email'],
                $ssoData['user']['name'],
                $ssoData['user']['picture'] ?? null,
                $this->encryptSecret($ssoData['access_token']),
                $ssoData['refresh_token'] ? $this->encryptSecret($ssoData['refresh_token']) : null,
                $expiresAt
            ]);
            
            $this->auditTrail->log('sso_account_linked', 'user_id=' . $userId . ';provider=' . $provider);
            return true;
            
        } catch (PDOException $e) {
            error_log("SsoService::linkSsoAccount - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get SSO account
     */
    public function getSsoAccount(int $userId, $provider) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_sso_accounts 
                WHERE user_id = ? AND provider = ?
            ");
            $stmt->execute([$userId, $provider]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SsoService::getSsoAccount - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Encrypt secret
     */
    private function encryptSecret($secret) {
        // Use base64 for now - upgrade to AES in production
        return base64_encode($secret);
    }
    
    /**
     * Decrypt secret
     */
    private function decryptSecret($encrypted) {
        return base64_decode($encrypted);
    }
    
    /**
     * HTTP GET request
     */
    private function getRequest($url, $accessToken) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * HTTP POST request
     */
    private function postRequest($url, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
?>
