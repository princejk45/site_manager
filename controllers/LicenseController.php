<?php
/**
 * LicenseController
 * Handles the license gate (enter key) and the license settings page.
 */
class LicenseController
{
    // =========================================================================
    // License gate — shown when license is missing or expired
    // =========================================================================

    public function gate(): void
    {
        // If license is actually valid now, redirect to dashboard
        $status = LicenseValidator::check();
        if ($status['valid']) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        $error   = null;
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $key = trim($_POST['license_key'] ?? '');

            if (empty($key)) {
                $error = __('license_page.err_empty_key');
            } else {
                // Temporarily write the key and re-validate
                LicenseValidator::saveLicenseKey($key);
                $check = LicenseValidator::check();

                if ($check['valid']) {
                    header('Location: index.php?action=dashboard');
                    exit;
                } else {
                    // Key is invalid — remove it and show error
                    LicenseValidator::saveLicenseKey('');
                    $reasons = [
                        'invalid_signature' => __('license_page.err_signature_failed'),
                        'expired'           => __('license_page.err_expired'),
                        'invalid_format'    => __('license_page.err_invalid_format'),
                        'invalid_payload'   => __('license_page.err_invalid_format'),
                        'revoked'           => __('license_page.err_revoked'),
                    ];
                    $error = $reasons[$check['reason']] ?? __('license_page.err_invalid_key');
                }
            }
        }

        $status = LicenseValidator::check();
        require APP_PATH . '/views/license_gate.php';
    }

    // =========================================================================
    // Settings page — view / update license in admin panel
    // =========================================================================

    public function settings(): void
    {
        $error   = null;
        $success = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $key = trim($_POST['license_key'] ?? '');
            $verify_url = trim($_POST['verify_url'] ?? '');

            // Validate verify_url if provided
            if (!empty($verify_url) && !filter_var($verify_url, FILTER_VALIDATE_URL)) {
                $error = __('license_page.err_invalid_verify_url');
            }

            if (!$error && empty($key)) {
                $error = __('license_page.err_empty_key');
            }

            if (!$error) {
                LicenseValidator::saveLicenseKey($key);
                LicenseValidator::saveVerifyUrl($verify_url);
                $check = LicenseValidator::check();

                if ($check['valid']) {
                    $mode_label = match($check['mode'] ?? 'offline') {
                        'online'          => __('license_page.ok_mode_online'),
                        'online_fallback' => __('license_page.ok_mode_fallback'),
                        default           => '',
                    };
                    $success = __('license_page.ok_saved') . $mode_label . '.';
                } else {
                    LicenseValidator::saveLicenseKey('');
                    $reasons = [
                        'invalid_signature' => __('license_page.err_signature_failed'),
                        'expired'           => __('license_page.err_expired'),
                        'invalid_format'    => __('license_page.err_invalid_format'),
                        'revoked'           => __('license_page.err_revoked'),
                        'server_unreachable_grace_expired' => __('license_page.err_server_grace_expired'),
                    ];
                    $error = $reasons[$check['reason']] ?? __('license_page.err_invalid_key');
                }
            }
        }

        $status     = LicenseValidator::check();
        $stored_key = LicenseValidator::getStoredKey();
        $verify_url = LicenseValidator::getVerifyUrl();
        require APP_PATH . '/views/settings/license.php';
    }
}
