<?php
/**
 * Licensing Bootstrap - Initializes on every request
 * 
 * This file:
 * 1. Validates the license
 * 2. Checks feature availability
 * 3. Enforces usage limits
 * 4. Handles expiration/trial mode
 * 
 * Include this at the start of index.php after session_start()
 */

// Check if installer is running
if (basename($_SERVER['PHP_SELF']) === 'install.php') {
    return;  // Skip license check during installation
}

// Initialize licensing
require_once __DIR__ . '/../services/Licensing/LicenseGenerator.php';
require_once __DIR__ . '/../services/Licensing/LicenseValidator.php';
require_once __DIR__ . '/../models/License.php';
require_once __DIR__ . '/../services/Licensing/LicenseManager.php';

// Global license status (used throughout app)
global $LICENSE_STATUS, $LICENSE_MANAGER;

try {
    // Validate license on startup
    $validator = new LicenseValidator($GLOBALS['pdo']);
    $LICENSE_STATUS = $validator->validate('STARTUP');
    
    // Initialize manager for global use
    $LICENSE_MANAGER = new LicenseManager($GLOBALS['pdo']);
    
    // Store in session for quick access
    $_SESSION['license_status'] = $LICENSE_STATUS;
    
    // If license is invalid and action is BLOCK, show error
    if (!$LICENSE_STATUS['valid'] && ($LICENSE_STATUS['action'] === 'BLOCK')) {
        // Only block on certain pages
        $blockedPages = ['license', 'settings', 'dashboard'];
        $currentPage = $_GET['action'] ?? 'dashboard';
        
        if (in_array($currentPage, $blockedPages)) {
            $_SESSION['license_error'] = $LICENSE_STATUS['reason'];
            header('Location: index.php?action=license&error=license_invalid');
            exit;
        }
    }
    
} catch (Exception $e) {
    // License check failed - log but don't crash app
    error_log('License validation error: ' . $e->getMessage());
    
    // Set defaults
    $_SESSION['license_status'] = [
        'valid' => false,
        'mode' => 'ERROR',
        'features' => []
    ];
}

/**
 * Check if feature is available
 * Usage: if (FEATURE_AVAILABLE('automation_rules')) { ... }
 */
function FEATURE_AVAILABLE($feature) {
    global $LICENSE_MANAGER;
    
    if (!$LICENSE_MANAGER) {
        // Graceful fallback
        return !in_array($feature, ['automation_rules', 'advanced_reporting', 'slack_integration', 'custom_branding']);
    }
    
    return $LICENSE_MANAGER->featureAvailable($feature);
}

/**
 * Get license information
 */
function LICENSE_INFO() {
    return $_SESSION['license_status'] ?? [
        'valid' => false,
        'mode' => 'UNKNOWN'
    ];
}

/**
 * Check if in trial mode
 */
function IN_TRIAL_MODE() {
    $info = LICENSE_INFO();
    return $info['mode'] === 'TRIAL';
}

/**
 * Get days remaining in trial or license
 */
function DAYS_REMAINING() {
    $info = LICENSE_INFO();
    return $info['days_remaining'] ?? 0;
}

/**
 * Record feature usage for audit
 */
function RECORD_FEATURE_USAGE($feature, $action, $count = 1) {
    global $LICENSE_MANAGER;
    
    if ($LICENSE_MANAGER) {
        $LICENSE_MANAGER->recordFeatureUsage($feature, $action, $count);
    }
}
