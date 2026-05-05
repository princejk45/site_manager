<?php
/**
 * Modern Navigation Sidebar - Phase 1
 * Fullmidia Site Manager
 * 
 * New Structure:
 * - Dashboard (Overview)
 * - Services (Websites management)
 * - Clients (Customer management)
 * - Operations (NEW - unified hub)
 * - Integrations
 * - Communications
 * - Settings
 */

// Get current page state
$currentAction = $_GET['action'] ?? 'dashboard';
$currentDo = $_GET['do'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'viewer';
$currentLang = $_SESSION['lang'] ?? 'it';
$langParam = '&lang=' . $currentLang;

// Load site branding
require_once APP_PATH . '/models/SiteSettings.php';
$siteSettings = new SiteSettings($GLOBALS['pdo']);
$siteName = $siteSettings->getSetting('site_name', APP_NAME);
$logoPath = $siteSettings->getSetting('logo_path', 'assets/images/logo.png');

// Get notification counts
require_once APP_PATH . '/models/MessageThread.php';
$threadModel = new MessageThread($GLOBALS['pdo']);
$userThreads = $threadModel->getUserThreads($_SESSION['user_id']);
$unreadMessageCount = array_sum(array_column($userThreads, 'unread_count'));

// Helper: Check if nav item is active
function isNavActive($action, $do = '', $currentAction = '', $currentDo = '') {
    $actionMatch = $currentAction === $action;
    $doMatch = empty($do) || $currentDo === $do;
    return $actionMatch && $doMatch;
}

// Helper: Check if nav section should be open
function isNavOpen($actions, $currentAction = '') {
    return in_array($currentAction, $actions);
}
?>

<nav class="modern-sidebar">
    <!-- Logo Section -->
    <div class="sidebar-brand" style="padding: 16px; border-bottom: 1px solid var(--border); margin-bottom: 16px;">
        <a href="index.php?action=dashboard<?= $langParam ?>" style="display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--text-primary);">
            <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" style="width: 40px; height: 40px; border-radius: 8px;">
            <div>
                <div style="font-weight: 700; font-size: 14px;"><?= htmlspecialchars($siteName) ?></div>
                <div style="font-size: 11px; color: var(--text-secondary);"><?= __('app.name') ?></div>
            </div>
        </a>
    </div>

    <!-- Main Navigation -->
    <div class="sidebar-section">
        <!-- Dashboard -->
        <a href="index.php?action=dashboard<?= $langParam ?>" 
            class="sidebar-item <?= isNavActive('dashboard', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('menu.dashboard') ?></span>
        </a>
    </div>

    <!-- Services Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-label">📦 <?= __('sidebar.section_services') ?></div>
        
        <!-- Websites -->
        <a href="index.php?action=websites<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('websites', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-globe sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('menu.websites') ?></span>
        </a>

        <!-- Hosting -->
        <a href="index.php?action=hosting<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('hosting', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-server sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('menu.hosting') ?></span>
        </a>

        <!-- Hosting Accounts -->
        <a href="index.php?action=hosting_accounts<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('hosting_accounts', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-hdd sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('menu.hosting_accounts') ?></span>
        </a>

        <!-- Providers -->
        <a href="index.php?action=providers<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('providers', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-network-wired sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('menu.providers') ?></span>
        </a>

        <!-- Diagnostics Center -->
        <a href="index.php?action=diagnostics<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('diagnostics', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-stethoscope sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('sidebar.diagnostics') ?></span>
        </a>
    </div>

    <!-- Communications Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-label">💬 <?= __('sidebar.section_communications') ?></div>

        <!-- Messaging -->
        <a href="index.php?action=messaging<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('messaging', '', $currentAction, $currentDo) && $currentDo !== 'groups' ? 'active' : '' ?>">
            <i class="fas fa-envelope sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('menu.messaging') ?></span>
            <?php if (isset($unreadMessageCount) && $unreadMessageCount > 0): ?>
                <span class="sidebar-item-badge"><?= $unreadMessageCount ?></span>
            <?php endif; ?>
        </a>

        <!-- Groups -->
        <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
            <a href="index.php?action=messaging&do=groups<?= $langParam ?>"
                class="sidebar-item <?= isNavActive('messaging', 'groups', $currentAction, $currentDo) ? 'active' : '' ?>">
                <i class="fas fa-users sidebar-item-icon"></i>
                <span class="sidebar-item-label"><?= __('menu.groups') ?></span>
            </a>
        <?php endif; ?>

        <!-- Reports Center -->
        <a href="index.php?action=reports<?= $langParam ?>" class="sidebar-item <?= isNavActive('reports', '', $_GET['action'] ?? '', $_GET['do'] ?? '') ?>">
            <i class="fas fa-chart-bar sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('sidebar.reports') ?></span>
        </a>

        <!-- Client Communications CRM -->
        <a href="index.php?action=comms<?= $langParam ?>" class="sidebar-item <?= isNavActive('comms', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-history sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('sidebar.client_comms') ?></span>
        </a>
    </div>

    <!-- Settings Section (Super Admin Only) -->
    <?php if ($userRole === 'super_admin'): ?>
        <div class="sidebar-section">
            <div class="sidebar-section-label">⚡ <?= __('sidebar.section_system') ?></div>

            <!-- Site Settings -->
            <a href="index.php?action=settings&do=site_settings<?= $langParam ?>"
                class="sidebar-item <?= isNavActive('settings', 'site_settings', $currentAction, $currentDo) ? 'active' : '' ?>">
                <i class="fas fa-cog sidebar-item-icon"></i>
                <span class="sidebar-item-label"><?= __('menu.site_settings') ?></span>
            </a>

            <!-- SMTP -->
            <a href="index.php?action=settings&do=smtp<?= $langParam ?>"
                class="sidebar-item <?= isNavActive('settings', 'smtp', $currentAction, $currentDo) ? 'active' : '' ?>">
                <i class="fas fa-mail-bulk sidebar-item-icon"></i>
                <span class="sidebar-item-label"><?= __('menu.smtp') ?></span>
            </a>

            <!-- Email Templates -->
            <a href="index.php?action=settings&do=email_templates<?= $langParam ?>"
                class="sidebar-item <?= isNavActive('settings', 'email_templates', $currentAction, $currentDo) ? 'active' : '' ?>">
                <i class="fas fa-envelope-open-text sidebar-item-icon"></i>
                <span class="sidebar-item-label"><?= __('menu.email_templates') ?></span>
            </a>

            <!-- Change Password -->
            <a href="index.php?action=settings&do=password<?= $langParam ?>"
                class="sidebar-item <?= isNavActive('settings', 'password', $currentAction, $currentDo) ? 'active' : '' ?>">
                <i class="fas fa-key sidebar-item-icon"></i>
                <span class="sidebar-item-label"><?= __('menu.change_password') ?></span>
            </a>

            <!-- Users -->
            <a href="index.php?action=users<?= $langParam ?>"
                class="sidebar-item <?= isNavActive('users', '', $currentAction, $currentDo) ? 'active' : '' ?>">
                <i class="fas fa-user-tie sidebar-item-icon"></i>
                <span class="sidebar-item-label"><?= __('sidebar.users') ?></span>
            </a>

        </div>

    <?php endif; ?>

    <!-- Operations Hub -->
    <div class="sidebar-section">
        <div class="sidebar-section-label">⚙️ <?= __('sidebar.section_operations') ?></div>

        <a href="index.php?action=import_export<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('import_export', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-exchange-alt sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('sidebar.import_export') ?></span>
        </a>

        <!-- Cron Scheduler -->
        <a href="index.php?action=cron<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('cron', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-clock sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('sidebar.cron_scheduler') ?></span>
        </a>

        <!-- Automation Rules -->
        <a href="index.php?action=automation<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('automation', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-robot sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('sidebar.automation') ?></span>
        </a>

        <!-- Task Queue -->
        <a href="index.php?action=tasks<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('tasks', '', $currentAction, $currentDo) ? 'active' : '' ?>">
            <i class="fas fa-tasks sidebar-item-icon"></i>
            <span class="sidebar-item-label"><?= __('sidebar.task_queue') ?></span>
        </a>
    </div>

    <?php if (in_array($_SESSION['user_role'] ?? '', ['manager', 'super_admin'])): ?>
        <!-- Integrations Section -->
        <div class="sidebar-section">
            <div class="sidebar-section-label">🔗 <?= __('sidebar.section_integrations') ?></div>

            <!-- WordPress -->
            <a href="index.php?action=settings&do=wordpress<?= $langParam ?>"
                class="sidebar-item <?= isNavActive('settings', 'wordpress', $currentAction, $currentDo) ? 'active' : '' ?>">
                <i class="fab fa-wordpress sidebar-item-icon"></i>
                <span class="sidebar-item-label">WordPress</span>
            </a>

            <!-- API Keys -->
            <a href="index.php?action=api_keys<?= $langParam ?>"
                class="sidebar-item <?= isNavActive('api_keys', '', $currentAction, $currentDo) ? 'active' : '' ?>">
                <i class="fas fa-key sidebar-item-icon"></i>
                <span class="sidebar-item-label"><?= __('sidebar.api_keys') ?></span>
            </a>

            <!-- License -->
            <a href="index.php?action=settings&do=license<?= $langParam ?>"
                class="sidebar-item <?= isNavActive('settings', 'license', $currentAction, $currentDo) ? 'active' : '' ?>">
                <i class="fas fa-certificate sidebar-item-icon"></i>
                <span class="sidebar-item-label"><?= __('sidebar.license') ?></span>
            </a>
        </div>
    <?php endif; ?>

    <!-- User Profile Section -->
    <div class="sidebar-section" style="margin-top: auto; border-top: 1px solid var(--border); border-bottom: none; padding-top: 12px;">
        <!-- Wiki / Documentation -->
        <a href="index.php?action=wiki<?= $langParam ?>"
            class="sidebar-item <?= isNavActive('wiki', '', $currentAction, $currentDo) ? 'active' : '' ?>"
            title="System documentation">
            <i class="fas fa-book-open sidebar-item-icon"></i>
            <span class="sidebar-item-label">Documentation</span>
        </a>
        <div style="padding: 12px 16px; display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
            </div>
            <div style="flex: 1; font-size: 12px;">
                <div style="font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
                <div style="color: var(--text-secondary);"><?= ucfirst($_SESSION['user_role'] ?? 'viewer') ?></div>
            </div>
            <a href="index.php?action=logout" style="color: var(--text-secondary); text-decoration: none; font-size: 14px;">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</nav>

<style>
    .modern-sidebar {
        display: flex;
        flex-direction: column;
    }

    .sidebar-section:last-child {
        margin-top: auto;
    }
</style>
