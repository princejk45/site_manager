<?php
/**
 * TwoFactorAuthService
 * 2FA implementation via TOTP, SMS, and email with backup codes
 */

namespace Services\Auth;

use PDO;
use Exception;

class TwoFactorAuthService
{
    private $db;
    private $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Enable TOTP-based 2FA for user
     */
    public function enableTOTP($portfolio_id, $user_id)
    {
        try {
            $secret = $this->generateTOTPSecret();
            
            $stmt = $this->db->prepare("
                INSERT INTO two_factor_auth (portfolio_id, user_id, method, secret, is_enabled, created_at)
                VALUES (:portfolio_id, :user_id, 'totp', :secret, 0, NOW())
                ON DUPLICATE KEY UPDATE
                secret = :secret
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':secret' => $secret
            ]);

            // Generate QR code data
            $qr_data = "otpauth://totp/FullMedia:{$user_id}?secret={$secret}&issuer=FullMedia";

            return [
                'status' => 'success',
                'secret' => $secret,
                'qr_data' => $qr_data,
                'message' => 'Scan QR code with authenticator app'
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to enable TOTP: " . $e->getMessage());
        }
    }

    /**
     * Verify TOTP code
     */
    public function verifyTOTP($portfolio_id, $user_id, $code)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT secret FROM two_factor_auth
                WHERE portfolio_id = :portfolio_id AND user_id = :user_id AND method = 'totp' AND is_enabled = 1
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id, ':user_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return ['status' => 'error', 'valid' => false];
            }

            $valid = $this->verifyTOTPCode($result['secret'], $code);

            if ($valid) {
                // Log verification
                $this->logVerification($portfolio_id, $user_id, 'totp', true);
            }

            return [
                'status' => 'success',
                'valid' => $valid
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to verify TOTP: " . $e->getMessage());
        }
    }

    /**
     * Enable SMS-based 2FA
     */
    public function enableSMS($portfolio_id, $user_id, $phone_number)
    {
        try {
            // Validate and normalize phone number
            $normalized_phone = preg_replace('/[^0-9+]/', '', $phone_number);

            $stmt = $this->db->prepare("
                INSERT INTO two_factor_auth (portfolio_id, user_id, method, phone_number, is_enabled, created_at)
                VALUES (:portfolio_id, :user_id, 'sms', :phone, 0, NOW())
                ON DUPLICATE KEY UPDATE
                phone_number = :phone
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':phone' => $normalized_phone
            ]);

            // Send verification SMS
            $code = $this->generateCode();
            $this->storePendingCode($portfolio_id, $user_id, 'sms', $code);
            $this->sendSMS($normalized_phone, "Your FullMedia verification code is: {$code}");

            return [
                'status' => 'success',
                'message' => 'SMS sent to ' . substr($normalized_phone, -4)
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to enable SMS 2FA: " . $e->getMessage());
        }
    }

    /**
     * Enable email-based 2FA
     */
    public function enableEmail($portfolio_id, $user_id, $email)
    {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address");
            }

            $stmt = $this->db->prepare("
                INSERT INTO two_factor_auth (portfolio_id, user_id, method, email, is_enabled, created_at)
                VALUES (:portfolio_id, :user_id, 'email', :email, 0, NOW())
                ON DUPLICATE KEY UPDATE
                email = :email
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':email' => $email
            ]);

            // Send verification code
            $code = $this->generateCode();
            $this->storePendingCode($portfolio_id, $user_id, 'email', $code);
            $this->sendEmailCode($email, $code);

            return [
                'status' => 'success',
                'message' => 'Verification code sent to email'
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to enable email 2FA: " . $e->getMessage());
        }
    }

    /**
     * Verify SMS or email code
     */
    public function verifySMSOrEmailCode($portfolio_id, $user_id, $method, $code)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM two_factor_codes
                WHERE portfolio_id = :portfolio_id AND user_id = :user_id AND method = :method
                AND code = :code AND is_used = 0 AND expires_at > NOW()
            ");
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':method' => $method,
                ':code' => $code
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $this->logVerification($portfolio_id, $user_id, $method, false);
                return ['status' => 'error', 'valid' => false];
            }

            // Mark code as used
            $stmt = $this->db->prepare("
                UPDATE two_factor_codes SET is_used = 1, verified_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $result['id']]);

            // Enable method
            $stmt = $this->db->prepare("
                UPDATE two_factor_auth SET is_enabled = 1, verified_at = NOW()
                WHERE portfolio_id = :portfolio_id AND user_id = :user_id AND method = :method
            ");
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':method' => $method
            ]);

            $this->logVerification($portfolio_id, $user_id, $method, true);

            return ['status' => 'success', 'valid' => true];
        } catch (\PDOException $e) {
            throw new Exception("Failed to verify code: " . $e->getMessage());
        }
    }

    /**
     * Generate backup codes for account recovery
     */
    public function generateBackupCodes($portfolio_id, $user_id)
    {
        try {
            $codes = [];
            for ($i = 0; $i < 10; $i++) {
                $code = strtoupper(bin2hex(random_bytes(4)));
                $codes[] = $code;

                $stmt = $this->db->prepare("
                    INSERT INTO two_factor_backup_codes (portfolio_id, user_id, code, created_at)
                    VALUES (:portfolio_id, :user_id, :code, NOW())
                ");
                $stmt->execute([
                    ':portfolio_id' => $portfolio_id,
                    ':user_id' => $user_id,
                    ':code' => hash('sha256', $code)
                ]);
            }

            return [
                'status' => 'success',
                'backup_codes' => $codes,
                'message' => 'Save these codes in a secure location'
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to generate backup codes: " . $e->getMessage());
        }
    }

    /**
     * Verify backup code
     */
    public function verifyBackupCode($portfolio_id, $user_id, $code)
    {
        try {
            $code_hash = hash('sha256', $code);

            $stmt = $this->db->prepare("
                SELECT id FROM two_factor_backup_codes
                WHERE portfolio_id = :portfolio_id AND user_id = :user_id AND code = :code_hash AND is_used = 0
            ");
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':code_hash' => $code_hash
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return ['status' => 'error', 'valid' => false];
            }

            // Mark code as used
            $stmt = $this->db->prepare("
                UPDATE two_factor_backup_codes SET is_used = 1, used_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $result['id']]);

            $this->logVerification($portfolio_id, $user_id, 'backup_code', true);

            return [
                'status' => 'success',
                'valid' => true,
                'message' => 'Backup code accepted'
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to verify backup code: " . $e->getMessage());
        }
    }

    /**
     * Get 2FA methods for user
     */
    public function getUserMethods($portfolio_id, $user_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, method, is_enabled, verified_at, created_at
                FROM two_factor_auth
                WHERE portfolio_id = :portfolio_id AND user_id = :user_id
                ORDER BY created_at DESC
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id, ':user_id' => $user_id]);
            $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'methods' => $methods
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get user methods: " . $e->getMessage());
        }
    }

    /**
     * Disable 2FA method
     */
    public function disableMethod($portfolio_id, $user_id, $method)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE two_factor_auth
                SET is_enabled = 0, disabled_at = NOW()
                WHERE portfolio_id = :portfolio_id AND user_id = :user_id AND method = :method
            ");
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_id' => $user_id,
                ':method' => $method
            ]);

            return ['status' => 'success', 'message' => "2FA method {$method} disabled"];
        } catch (\PDOException $e) {
            throw new Exception("Failed to disable method: " . $e->getMessage());
        }
    }

    /**
     * Get 2FA statistics
     */
    public function get2FAStats($portfolio_id = null)
    {
        try {
            $where = $portfolio_id ? "WHERE portfolio_id = :portfolio_id" : "";
            $params = $portfolio_id ? [':portfolio_id' => $portfolio_id] : [];

            $stmt = $this->db->prepare("
                SELECT
                    method,
                    COUNT(*) as total_users,
                    SUM(CASE WHEN is_enabled = 1 THEN 1 ELSE 0 END) as enabled_users,
                    MAX(verified_at) as last_verification
                FROM two_factor_auth
                {$where}
                GROUP BY method
            ");
            $stmt->execute($params);
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'statistics' => $stats
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get 2FA stats: " . $e->getMessage());
        }
    }

    /**
     * Generate TOTP secret
     */
    private function generateTOTPSecret()
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Verify TOTP code
     */
    private function verifyTOTPCode($secret, $code)
    {
        // Check current and adjacent time windows (30 second variance)
        for ($i = -1; $i <= 1; $i++) {
            $time_counter = floor(time() / 30) + $i;
            $hash = hash_hmac('sha1', pack('N', $time_counter), $this->base32_decode($secret), true);
            $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
            $totp = (((ord($hash[$offset]) & 0x7F) << 24) |
                    ((ord($hash[$offset + 1]) & 0xFF) << 16) |
                    ((ord($hash[$offset + 2]) & 0xFF) << 8) |
                    (ord($hash[$offset + 3]) & 0xFF)) % 1000000;

            if ($totp == $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate random code
     */
    private function generateCode()
    {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Store pending code for verification
     */
    private function storePendingCode($portfolio_id, $user_id, $method, $code)
    {
        $stmt = $this->db->prepare("
            INSERT INTO two_factor_codes (portfolio_id, user_id, method, code, expires_at, created_at)
            VALUES (:portfolio_id, :user_id, :method, :code, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())
        ");
        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':user_id' => $user_id,
            ':method' => $method,
            ':code' => $code
        ]);
    }

    /**
     * Send SMS code
     */
    private function sendSMS($phone, $message)
    {
        // Integration with SMS provider (Twilio, etc.)
        // For now, just log
        error_log("SMS to {$phone}: {$message}");
    }

    /**
     * Send email code
     */
    private function sendEmailCode($email, $code)
    {
        // Integration with mail service
        $subject = "Your FullMedia Verification Code";
        $body = "Your verification code is: {$code}. Valid for 10 minutes.";
        mail($email, $subject, $body);
    }

    /**
     * Log verification attempt
     */
    private function logVerification($portfolio_id, $user_id, $method, $success)
    {
        $stmt = $this->db->prepare("
            INSERT INTO two_factor_logs (portfolio_id, user_id, method, success, ip_address, user_agent, verified_at)
            VALUES (:portfolio_id, :user_id, :method, :success, :ip, :ua, NOW())
        ");
        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':user_id' => $user_id,
            ':method' => $method,
            ':success' => $success ? 1 : 0,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * Decode base32
     */
    private function base32_decode($input)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper($input);
        $bits = '';
        $value = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $value = strpos($alphabet, $input[$i]);
            $bits .= sprintf('%05b', $value);
        }

        $output = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $output .= chr(bindec(substr($bits, $i, 8)));
        }

        return $output;
    }
}
