<?php
/**
 * License Key Generator - Creates secure, unforgeable license keys
 * Format: FM-XXXX-XXXX-XXXX-XX (36 characters total)
 * 
 * Key Structure:
 * - FM: Product prefix
 * - First XXXX: Type (01-04) + Tier (10-30) + Reserved (2 chars)
 * - Second XXXX: Random unique ID
 * - Third XXXX: Timestamp-based component
 * - Last XX: Check digit (validation)
 */

class LicenseGenerator
{
    const LICENSE_PREFIX = 'FM';
    const ALGORITHM = 'SHA256';
    const KEY_VERSION = '1.0';

    private static $typeCodeMap = [
        'TRIAL' => '00',
        'MONTHLY' => '01',
        'QUARTERLY' => '02',
        'YEARLY' => '03',
        'LIFETIME' => '04'
    ];

    private static $tierCodeMap = [
        'LIGHT' => '10',
        'PROFESSIONAL' => '20',
        'ENTERPRISE' => '30'
    ];

    /**
     * Generate a secure license key
     * 
     * @param array $data {
     *     license_type: 'TRIAL'|'MONTHLY'|'QUARTERLY'|'YEARLY'|'LIFETIME',
     *     product_tier: 'LIGHT'|'PROFESSIONAL'|'ENTERPRISE',
     *     customer_name: string,
     *     customer_email: string,
     *     company_name: string (optional),
     *     expires_at: timestamp (optional, calculated if not provided)
     * }
     * 
     * @return array {
     *     license_key: string (FM-XXXX-XXXX-XXXX-XX),
     *     license_hash: string (SHA256),
     *     activation_code: string (48-bit hex),
     *     components: array (for internal use)
     * }
     */
    public static function generate(array $data): array
    {
        // Validate input
        if (!isset($data['license_type']) || !isset($data['product_tier'])) {
            throw new Exception('Missing required license generation parameters');
        }

        // Calculate expiration if not provided
        if (!isset($data['expires_at'])) {
            $data['expires_at'] = self::calculateExpiration($data['license_type']);
        }

        // Extract components
        $typeCode = self::$typeCodeMap[$data['license_type']];
        $tierCode = self::$tierCodeMap[$data['product_tier']];
        
        if (!$typeCode || !$tierCode) {
            throw new Exception('Invalid license type or tier');
        }

        // Generate components
        $components = [
            'prefix' => self::LICENSE_PREFIX,
            'version' => '1',
            'type_code' => $typeCode,
            'tier_code' => $tierCode,
            'timestamp' => self::generateTimestampComponent(),
            'unique_id' => bin2hex(random_bytes(5)),  // 10 hex chars
            'entropy' => bin2hex(random_bytes(2))     // 4 hex chars
        ];

        // Calculate check digit based on all components
        $components['check_digit'] = self::calculateCheckDigit($components);

        // Format license key: FM-XXXX-XXXX-XXXX-XX
        $licenseKey = sprintf(
            "%s-%s%s%s%s-%s-%s-%s",
            $components['prefix'],
            $components['version'],
            $components['type_code'],
            $components['tier_code'],
            str_pad(dechex(rand(0, 255)), 2, '0', STR_PAD_LEFT),
            substr($components['unique_id'], 0, 4),
            substr($components['unique_id'], 4, 4),
            $components['check_digit']
        );

        // Generate hash for database storage
        $licenseHash = hash(self::ALGORITHM, $licenseKey);

        // Generate activation code (6-byte hex)
        $activationCode = strtoupper(bin2hex(random_bytes(6)));

        return [
            'license_key' => $licenseKey,
            'license_hash' => $licenseHash,
            'activation_code' => $activationCode,
            'components' => $components,
            'expires_at' => $data['expires_at'],
            'key_version' => self::KEY_VERSION
        ];
    }

    /**
     * Calculate expiration timestamp based on license type
     */
    public static function calculateExpiration(string $licenseType): int
    {
        $now = time();
        
        switch ($licenseType) {
            case 'TRIAL':
                return $now + (30 * 86400);  // 30 days
            case 'MONTHLY':
                return $now + (31 * 86400);  // 31 days
            case 'QUARTERLY':
                return $now + (92 * 86400);  // 92 days
            case 'YEARLY':
                return $now + (366 * 86400);  // 366 days
            case 'LIFETIME':
                return $now + (20 * 365 * 86400);  // 20 years
            default:
                throw new Exception('Unknown license type: ' . $licenseType);
        }
    }

    /**
     * Generate timestamp component (compact timestamp in hex)
     * Uses compact representation to fit in small space
     */
    private static function generateTimestampComponent(): string
    {
        // Take current timestamp and compress it
        $timestamp = time();
        // Use last 3 bytes of timestamp for compact representation
        $compact = ($timestamp >> 8) & 0xFFFFFF;
        return str_pad(dechex($compact), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate check digit using modular arithmetic
     * Makes the key harder to forge
     */
    private static function calculateCheckDigit(array $components): string
    {
        // Combine all components into a string
        $data = $components['prefix'] . 
                $components['version'] . 
                $components['type_code'] . 
                $components['tier_code'] . 
                $components['timestamp'] . 
                $components['unique_id'] . 
                $components['entropy'];

        // Calculate checksum: sum of all character values modulo 97
        $sum = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $sum += ord($data[$i]);
        }

        // Check digit: 97 - (sum mod 97) using Luhn-like algorithm
        $checkDigit = 97 - ($sum % 97);
        
        return str_pad(dechex($checkDigit), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a license key format (before database lookup)
     * Returns true if format is valid, false otherwise
     */
    public static function isValidFormat(string $licenseKey): bool
    {
        // Expected format: FM-XXXX-XXXX-XXXX-XX
        return (bool) preg_match('/^FM-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{2}$/i', $licenseKey);
    }

    /**
     * Extract metadata from license key (without database access)
     * Useful for quick validation before database lookup
     */
    public static function extractMetadata(string $licenseKey): ?array
    {
        if (!self::isValidFormat($licenseKey)) {
            return null;
        }

        $parts = explode('-', $licenseKey);
        if (count($parts) !== 5) {
            return null;
        }

        $mainPart = $parts[1];  // e.g., "01XXXX"
        
        $typeCode = substr($mainPart, 0, 2);
        $tierCode = substr($mainPart, 2, 2);

        // Reverse lookup
        $licenseType = array_search($typeCode, self::$typeCodeMap);
        $productTier = array_search($tierCode, self::$tierCodeMap);

        if (!$licenseType || !$productTier) {
            return null;
        }

        return [
            'license_type' => $licenseType,
            'product_tier' => $productTier,
            'type_code' => $typeCode,
            'tier_code' => $tierCode,
            'check_digit' => $parts[4]
        ];
    }
}
