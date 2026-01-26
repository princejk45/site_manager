<?php
require __DIR__ . '/config/bootstrap.php';

// Initialize controllers
$authController = new AuthController($GLOBALS['pdo']);
$dashboardController = new DashboardController($GLOBALS['pdo']);
$websiteController = new WebsiteController($GLOBALS['pdo']);
$hostingController = new HostingController($GLOBALS['pdo']);
$emailController = new EmailController($GLOBALS['pdo']);
$settingsController = new SettingsController($GLOBALS['pdo']);
$emailModel = new Email($pdo);

$messagingController = new MessagingController(
    new MessageThread($pdo),
    new Group($pdo),
    new User($pdo),
    $emailModel,
    $pdo  // Pass database connection for EmailTemplate model
);

// Get action from request
$action = $_GET['action'] ?? 'login';
$do = $_GET['do'] ?? '';
$id = $_GET['id'] ?? null;

// Route the request with role checks
switch ($action) {
    case 'login':
        $authController->login();
        break;
    case 'logout':
        $authController->logout();
        break;
    case 'forgot_password':
        $authController->forgotPassword();
        break;
    case 'reset_password':
        $authController->resetPassword();
        break;

    // Dashboard - All roles
    case 'dashboard':
        $authController->checkPermission('viewer');
        $dashboardController->index();
        break;

    // Websites - Viewer can view, Manager+ can edit
    case 'websites':
        $authController->checkPermission('viewer');
        switch ($do) {
            case 'create':
                $websiteController->create();
                break;
            case 'edit':
                $authController->checkPermission('manager');
                $websiteController->edit($id);
                break;
            case 'delete':
                $authController->checkPermission('manager');
                $websiteController->delete($id);
                break;
            case 'renew':
                $authController->checkPermission('manager');
                $websiteController->renew($id);
                break;
            case 'view':
                $websiteController->view($id);
                break;
            case 'import':
                $websiteController->import();
                break;
            case 'export':
                $websiteController->export();
                break;
            default:
                $websiteController->index();
        }
        break;

    // Hosting - Viewer can view, Manager+ can edit
    case 'hosting':
        $authController->checkPermission('viewer');
        switch ($do) {
            case 'create':
                $authController->checkPermission('manager');
                $hostingController->create();
                break;
            case 'edit':
                $authController->checkPermission('manager');
                $hostingController->edit($id);
                break;
            case 'delete':
                $authController->checkPermission('manager');
                $hostingController->delete($id);
                break;
            case 'view':
                $hostingController->view($id);
                break;
            case 'service_create':
                $hostingId = $_GET['id'] ?? null;
                if ($hostingId) {
                    $hostingController->serviceCreate($hostingId);
                } else {
                    header('Location: index.php?action=hosting');
                }
                break;
            case 'services':
                $hostingId = $_GET['hostingId'] ?? $id;
                if (!$hostingId) {
                    header('Location: index.php?action=hosting');
                    exit;
                }
                $hostingController->services($hostingId);
                break;
            default:
                $hostingController->index();
        }
        break;

    // Email - Manager+ only (viewers can't send emails)
    case 'email':
        $authController->checkPermission('manager');
        switch ($do) {
            case 'expiry':
                $emailController->sendExpiryNotification($id);
                break;
            case 'status':
                $emailController->sendStatusNotification($id);
                break;
            case 'logs':
                $emailController->showEmailLogs();
                break;
            default:
                header('Location: index.php?action=dashboard');
        }
        break;

    // Settings - Super Admin only (except password change which is handled above)
    case 'settings':
        // Special handling for password change
        if ($do === 'password') {
            if (!isset($_SESSION['user_id'])) {
                header('Location: index.php?action=login');
                exit;
            }
            $authController->changePassword();
            break;
        }

        // All other settings remain super_admin only
        $authController->checkPermission('super_admin');
        switch ($do) {
            case 'smtp':
                $settingsController->smtp();
                break;
            case 'test_smtp':
                $settingsController->testSmtp();
                break;
            case 'advanced':
                // This will handle both GET and POST requests
                $settingsController->advanced();
                break;
            case 'google_sheets':  // Add this new case
                $settingsController->handleGoogleSheets();
                break;
            case 'site_settings':
                $settingsController->siteSettings();
                break;
            case 'save_site_settings':
                $settingsController->siteSettings();
                break;
            case 'save_email_header_footer':
                $settingsController->saveEmailHeaderFooter();
                break;
            case 'email_templates':
                $settingsController->emailTemplates();
                break;
            case 'edit_email_template':
                $settingsController->editEmailTemplate();
                break;
            case 'compare_google':
                $settingsController->compareWithGoogle();
                break;
            case 'merge_google':
                $settingsController->mergeWithGoogle();
                break;
            default:
                header('Location: index.php?action=settings&do=smtp');
        }
        break;


    // User Management - Super Admin only
    case 'users':
        $authController->checkPermission('super_admin');
        switch ($do) {
            case 'create':
                $authController->showCreateForm();
                break;
            case 'store':
                $authController->createUser();
                break;
            case 'edit':
                $authController->showEditForm($id);
                break;
            case 'update':
                $authController->updateUser($id);
                break;
            case 'list':
            default:
                $authController->listUsers();
        }
        break;

    // Messaging
    case 'messaging':
        $authController->checkPermission('viewer'); //  minimum role
        switch ($do) {
            case 'test':  // Add this new case
                $this->showTestPage();
                break;
            case 'inbox':
                $messagingController->inbox();
                break;
            case 'view':
                $messagingController->viewThread($id);
                break;
            case 'compose':
                $messagingController->compose();
                break;
            case 'send':
                $messagingController->send();
                break;
            case 'reply':
                $messagingController->reply();
                break;
            case 'delete':
                $messagingController->delete();
                break;
            case 'toggle_star':
                $messagingController->toggleStar();
                break;
            case 'mark_read':
                $messagingController->markThreadRead();
                break;
            case 'bulk_mark':
                $messagingController->bulkMarkRead();
                break;
            case 'bulk_star':
                $messagingController->bulkStar();
                break;
            case 'groups':
                $messagingController->listGroups();
                break;
            case 'groups_create':
                $messagingController->showCreateGroup();
                break;
            case 'groups_store':
                $messagingController->storeGroup();
                break;
            case 'groups_edit':
                $messagingController->showEditGroup($id);
                break;
            case 'groups_update':
                $messagingController->updateGroup();
                break;
            case 'groups_delete':
                $messagingController->deleteGroup();
                break;
            default:
                $messagingController->inbox();
        }
        break;


    default:
        header('Location: index.php?action=login');
}
