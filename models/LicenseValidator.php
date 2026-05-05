<?php
/**
 * LicenseValidator
 *
 * Validates the stored license key (FM-...) against the embedded RSA public key.
 * Results are cached in config/license_cache.json for 1 hour to avoid
 * repeated signature verification on every request.
 *
 * Files used:
 *   config/license.json        — stores the active license key string
 *   config/license_public.pem  — RSA public key (copy from License Server after install)
 *   config/license_cache.json  — auto-managed validation cache
 */
class LicenseValidator
{
    const KEY_FILE    = '/config/license.json';
    const PUB_FILE    = '/config/license_public.pem';
    const CACHE_FILE  = '/config/license_cache.json';
    const CACHE_TTL   = 3600;  // cache lifetime (seconds)
    const GRACE_TTL   = 86400; // how long offline fallback is trusted if server unreachable (seconds)

    // =========================================================================
    // Public interface
    // =========================================================================

    /**
     * Returns the license status, using a short-lived file cache.
     * Result: ['valid'=>bool, 'reason'=>string, 'expires_at'=>string|null, 'payload'=>array|null]
     */
    public static function check(): array
    {
        $cache_path = APP_PATH . self::CACHE_FILE;

        if (file_exists($cache_path)) {
            $raw = file_get_contents($cache_path);
            if ($raw) {
                $cache = json_decode($raw, true);
                if ($cache && isset($cache['checked_at']) && (time() - $cache['checked_at']) < self::CACHE_TTL) {
                    return $cache;
                }
            }
        }

        $result   = self::validate();
        $to_cache = array_merge($result, ['checked_at' => time()]);
        @file_put_contents($cache_path, json_encode($to_cache));

        return $result;
    }

    /**
     * Save a new license key and clear the cache.
     */
    public static function saveLicenseKey(string $key): void
    {
        $key_path = APP_PATH . self::KEY_FILE;
        $existing = [];

        if (file_exists($key_path)) {
            $raw = file_get_contents($key_path);
            if ($raw) {
                $existing = json_decode($raw, true) ?: [];
            }
        }

        $existing['license_key'] = trim($key);
        file_put_contents($key_path, json_encode($existing));
        self::clearCache();
    }

    /**
     * Save the optional online verify URL.
     */
    public static function saveVerifyUrl(string $url): void
    {
        $key_path = APP_PATH . self::KEY_FILE;
        $existing = [];
        if (file_exists($key_path)) {
            $raw = file_get_contents($key_path);
            if ($raw) $existing = json_decode($raw, true) ?: [];
        }
        $existing['verify_url'] = trim($url);
        file_put_contents($key_path, json_encode($existing));
        self::clearCache();
    }

    /**
     * Read the stored verify URL, or empty string if none.
     */
    public static function getVerifyUrl(): string
    {
        $key_path = APP_PATH . self::KEY_FILE;
        if (!file_exists($key_path)) return '';
        $data = json_decode(file_get_contents($key_path), true);
        return $data['verify_url'] ?? '';
    }

    /**
     * Clear cached result (forces re-validation on next request).
     */
    public static function clearCache(): void
    {
        $cache_path = APP_PATH . self::CACHE_FILE;
        if (file_exists($cache_path)) {
            @unlink($cache_path);
        }
    }

    /**
     * Read the stored license key string, or empty string if none.
     */
    public static function getStoredKey(): string
    {
        $key_path = APP_PATH . self::KEY_FILE;
        if (!file_exists($key_path)) return '';
        $data = json_decode(file_get_contents($key_path), true);
        return $data['license_key'] ?? '';
    }

    // =========================================================================
    // Validation logic
    // =========================================================================

    private static function validate(): array
    {
        $fail = function(string $reason, ?string $expires_at = null, ?array $payload = null) {
            return ['valid' => false, 'reason' => $reason, 'expires_at' => $expires_at, 'payload' => $payload, 'mode' => 'offline'];
        };

        // 1. No public key → enforcement not configured, bypass
        $pub_key_path = APP_PATH . self::PUB_FILE;
        if (!file_exists($pub_key_path)) {
            return ['valid' => true, 'reason' => 'no_enforcement', 'expires_at' => null, 'payload' => null, 'mode' => 'disabled'];
        }

        // 2. Read stored license data
        $key_path = APP_PATH . self::KEY_FILE;
        if (!file_exists($key_path)) return $fail('no_license');

        $data       = json_decode(file_get_contents($key_path), true) ?: [];
        $key        = trim($data['license_key'] ?? '');
        $verify_url = trim($data['verify_url'] ?? '');

        if (empty($key)) return $fail('no_license');

        // 3. Basic format check: FM-<payload>.<signature>
        if (substr($key, 0, 3) !== 'FM-') return $fail('invalid_format');

        $stripped = substr($key, 3);
        $dot      = strrpos($stripped, '.');
        if ($dot === false) return $fail('invalid_format');

        $b64_payload = substr($stripped, 0, $dot);
        $b64_sig     = substr($stripped, $dot + 1);

        $payload_json = base64_decode(strtr($b64_payload, '-_', '+/'));
        $signature    = base64_decode(strtr($b64_sig, '-_', '+/'));

        if (!$payload_json || !$signature) return $fail('invalid_format');

        $payload = json_decode($payload_json, true);
        if (!$payload) return $fail('invalid_payload');

        // 4. Verify RSA signature (always — even in online mode)
        $pubkey = openssl_pkey_get_public(file_get_contents($pub_key_path));
        if (!$pubkey) return $fail('invalid_public_key');

        $verify = openssl_verify($b64_payload, $signature, $pubkey, OPENSSL_ALGO_SHA256);
        if ($verify !== 1) return $fail('invalid_signature');

        // 5. Online mode — ask the license server for the live expiry
        if (!empty($verify_url)) {
            $online = self::checkOnline($verify_url, $key);

            if ($online !== null) {
                // Server responded — trust it completely (expiry is live from DB)
                $result = array_merge($online, ['mode' => 'online', 'payload' => $payload]);
                return $result;
            }

            // Server unreachable — fall back to offline with grace period
            // Read previous cache to see how long we've been offline
            $cache_path = APP_PATH . self::CACHE_FILE;
            $last_online_at = null;
            if (file_exists($cache_path)) {
                $prev = json_decode(file_get_contents($cache_path), true);
                if (!empty($prev['last_online_at'])) {
                    $last_online_at = $prev['last_online_at'];
                }
            }

            $grace_expired = $last_online_at && (time() - $last_online_at) > self::GRACE_TTL;

            if ($grace_expired) {
                return ['valid' => false, 'reason' => 'server_unreachable_grace_expired',
                        'expires_at' => null, 'payload' => $payload, 'mode' => 'online_fallback'];
            }

            // Within grace period — use offline expiry
            $expires_at = $payload['expires_at'] ?? null;
            if ($expires_at && strtotime($expires_at) < time()) {
                return ['valid' => false, 'reason' => 'expired', 'expires_at' => $expires_at,
                        'payload' => $payload, 'mode' => 'online_fallback'];
            }

            return ['valid' => true, 'reason' => 'valid_grace', 'expires_at' => $expires_at,
                    'payload' => $payload, 'mode' => 'online_fallback',
                    'last_online_at' => $last_online_at];
        }

        // 6. Pure offline mode — check baked-in expiry
        $expires_at = $payload['expires_at'] ?? null;
        if ($expires_at && strtotime($expires_at) < time()) {
            return $fail('expired', $expires_at, $payload);
        }

        return ['valid' => true, 'reason' => 'valid', 'expires_at' => $expires_at, 'payload' => $payload, 'mode' => 'offline'];
    }

    /**
     * POST to the license server verify endpoint.
     * Returns the decoded JSON array on success, or null if the server is unreachable.
     */
    private static function checkOnline(string $url, string $key): ?array
    {
        $domain = $_SERVER['HTTP_HOST'] ?? gethostname();
        $body   = json_encode(['license_key' => $key, 'domain' => $domain]);

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'POST',
                'header'          => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n",
                'content'         => $body,
                'timeout'         => 5,
                'ignore_errors'   => true,
            ],
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return null;  // unreachable

        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['valid'])) return null;

        // Track last successful contact time
        if ($json['valid']) {
            $cache_path = APP_PATH . self::CACHE_FILE;
            $prev = [];
            if (file_exists($cache_path)) {
                $prev = json_decode(file_get_contents($cache_path), true) ?: [];
            }
            $prev['last_online_at'] = time();
            @file_put_contents($cache_path, json_encode($prev));
        }

        return [
            'valid'      => (bool)$json['valid'],
            'reason'     => $json['reason'] ?? ($json['valid'] ? 'valid' : 'invalid'),
            'expires_at' => $json['expires_at'] ?? null,
        ];
    }
}
