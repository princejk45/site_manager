<?php
/**
 * Dashboard Version Configuration
 * 
 * Controls which dashboard version is loaded (v1 or v2)
 * Set DASHBOARD_VERSION to 'v2' to use the new Dashboard 2.0
 */

// Dashboard Version: 'v1' or 'v2'
define('DASHBOARD_VERSION', $_SESSION['dashboard_version'] ?? 'v2');

/**
 * Get the appropriate dashboard view file
 */
function getDashboardView() {
    $version = getDashboardVersion();
    
    if ($version === 'v2') {
        return __DIR__ . '/views/dashboard/dashboard-v2.php';
    } else {
        return __DIR__ . '/views/dashboard/index.php';
    }
}

/**
 * Get the appropriate sidebar file
 */
function getSidebarView() {
    $version = getDashboardVersion();
    
    if ($version === 'v2') {
        return __DIR__ . '/includes/sidebar-v2.php';
    } else {
        return __DIR__ . '/includes/sidebar.php';
    }
}

/**
 * Get current dashboard version
 */
function getDashboardVersion() {
    return DASHBOARD_VERSION;
}

/**
 * Switch dashboard version
 */
function switchDashboardVersion($version) {
    if (in_array($version, ['v1', 'v2'])) {
        $_SESSION['dashboard_version'] = $version;
        return true;
    }
    return false;
}

/**
 * Check if Dashboard 2.0 is enabled
 */
function isDashboardV2Enabled() {
    return getDashboardVersion() === 'v2';
}

/**
 * Register Dashboard 2.0 Assets
 * Call this in the header if using Dashboard v2
 */
function registerDashboardV2Assets() {
    if (isDashboardV2Enabled()) {
        echo <<<'HTML'
        <!-- Dashboard 2.0 Styles -->
        <link rel="stylesheet" href="assets/css/dashboard-v2.css">
        <!-- Dashboard 2.0 Scripts -->
        <script src="assets/js/dashboard-v2.js" defer></script>
HTML;
    }
}
