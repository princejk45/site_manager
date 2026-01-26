<?php
// Determine active states
$currentAction = $_GET['action'] ?? 'dashboard';
$currentDo = $_GET['do'] ?? '';
$isSettingsActive = ($currentAction === 'settings');
$userRole = $_SESSION['user_role'] ?? 'viewer'; // Set during login
$currentLang = $_SESSION['lang'] ?? 'it';
$langParam = '&lang=' . $currentLang;

// Load site settings for branding
require_once APP_PATH . '/models/SiteSettings.php';
$siteSettings = new SiteSettings($GLOBALS['pdo']);
$siteName = $siteSettings->getSetting('site_name', APP_NAME);
$logoPath = $siteSettings->getSetting('logo_path', 'assets/images/logo.png');

require_once APP_PATH . '/models/MessageThread.php';
$threadModel = new MessageThread($GLOBALS['pdo']);
$userThreads = $threadModel->getUserThreads($_SESSION['user_id']);
$unreadMessageCount = array_sum(array_column($userThreads, 'unread_count'));

?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="index.php?action=dashboard<?= $langParam ?>" class="brand-link">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light"><?= htmlspecialchars($siteName) ?></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="index.php?action=dashboard<?= $langParam ?>"
                        class="nav-link <?= ($currentAction === 'dashboard') ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p><?= __('menu.dashboard') ?></p>
                    </a>
                </li>

                <!-- Websites -->
                <li class="nav-item">
                    <a href="index.php?action=websites<?= $langParam ?>"
                        class="nav-link <?= ($currentAction === 'websites') ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-globe"></i>
                        <p><?= __('menu.websites') ?></p>
                    </a>
                </li>

                <!-- Hosting -->
                <li class="nav-item">
                    <a href="index.php?action=hosting<?= $langParam ?>"
                        class="nav-link <?= ($currentAction === 'hosting') ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-server"></i>
                        <p><?= __('menu.hosting') ?></p>
                    </a>
                </li>

                <!-- Messaging -->
                <li class="nav-item">
                    <a href="index.php?action=messaging<?= $langParam ?>"
                        class="nav-link <?= ($currentAction === 'messaging' && !$currentDo) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-envelope"></i>
                        <p>
                            <?= __('menu.messaging') ?>
                            <?php if (isset($unreadMessageCount) && $unreadMessageCount > 0): ?>
                                <span class="badge badge-danger right"><?= $unreadMessageCount ?></span>
                            <?php endif; ?>
                        </p>
                    </a>
                </li>

                <!-- Gruppi -->
                <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                    <li class="nav-item">
                        <a href="index.php?action=messaging&do=groups<?= $langParam ?>"
                            class="nav-link <?= ($currentAction === 'messaging' && $currentDo === 'groups') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-users"></i>
                            <p><?= __('menu.groups') ?></p>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Settings (Super Admin Only) -->
                <?php if ($userRole === 'super_admin'): ?>
                    <li
                        class="nav-item has-treeview <?= ($isSettingsActive || $currentAction === 'users') ? 'menu-open' : '' ?>">
                        <a href="#"
                            class="nav-link <?= ($isSettingsActive || $currentAction === 'users') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>
                                <?= __('menu.settings') ?>
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview"
                            style="<?= ($isSettingsActive || $currentAction === 'users') ? 'display: block;' : '' ?>">
                            <li class="nav-item">
                                <a href="index.php?action=settings&do=site_settings<?= $langParam ?>"
                                    class="nav-link <?= ($isSettingsActive && $currentDo === 'site_settings') ? 'active' : '' ?>">
                                    <i class="nav-icon fas fa-cogs"></i>
                                    <p><?= __('menu.site_settings') ?></p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?action=settings&do=email_templates<?= $langParam ?>"
                                    class="nav-link <?= ($isSettingsActive && $currentDo === 'email_templates') ? 'active' : '' ?>">
                                    <i class="nav-icon fas fa-envelope-square"></i>
                                    <p><?= __('menu.email_templates') ?></p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?action=settings&do=smtp<?= $langParam ?>"
                                    class="nav-link <?= ($isSettingsActive && $currentDo === 'smtp') ? 'active' : '' ?>">
                                    <i class="nav-icon fas fa-envelope"></i>
                                    <p><?= __('menu.smtp') ?></p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?action=settings&do=advanced<?= $langParam ?>"
                                    class="nav-link <?= ($isSettingsActive && $currentDo === 'advanced') ? 'active' : '' ?>">
                                    <i class="nav-icon fas fa-sliders-h"></i>
                                    <p><?= __('menu.advanced') ?></p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?action=users<?= $langParam ?>"
                                    class="nav-link <?= ($currentAction === 'users') ? 'active' : '' ?>">
                                    <i class="nav-icon fas fa-users-cog"></i>
                                    <p><?= __('menu.user_management') ?></p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?action=settings&do=password<?= $langParam ?>"
                                    class="nav-link <?= ($isSettingsActive && $currentDo === 'password') ? 'active' : '' ?>">
                                    <i class="nav-icon fas fa-key"></i>
                                    <p><?= __('menu.change_password') ?></p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- Logout -->
                <li class="nav-item">
                    <a href="index.php?action=logout" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p><?= __('menu.logout') ?></p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>

<style>
    /* Enhanced hover effects */
    .nav-sidebar .nav-item>.nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    /* Active item styling */
    .nav-sidebar .nav-item>.nav-link.active {
        background-color: rgba(255, 255, 255, 0.2);
        border-left: 3px solid #3c8dbc;
    }

    /* Submenu active item */
    .nav-treeview .nav-item>.nav-link.active {
        background-color: rgba(255, 255, 255, 0.15);
        font-weight: 600;
    }

    /* Keep submenu open when active */
    .nav-item.has-treeview.menu-open>.nav-treeview {
        display: block;
    }
</style>