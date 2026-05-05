<?php
require __DIR__ . '/config/bootstrap.php';

// Initialize controllers
$authController = new AuthController($GLOBALS['pdo']);
$licenseController = new LicenseController();
$dashboardController = new DashboardController($GLOBALS['pdo']);
$websiteController = new WebsiteController($GLOBALS['pdo']);
$hostingController = new HostingController($GLOBALS['pdo']);
$providersController = new ProvidersController($GLOBALS['pdo']);
$hostingAccountsController = new HostingAccountsController($GLOBALS['pdo']);
$emailController = new EmailController($GLOBALS['pdo']);
$settingsController = new SettingsController($GLOBALS['pdo']);
$apiController = new ApiController($GLOBALS['pdo']);
$emailModel = new Email($pdo);

$messagingController = new MessagingController(
    new MessageThread($pdo),
    new Group($pdo),
    new User($pdo),
    $emailModel,
    $pdo  // Pass database connection for EmailTemplate model
);
$communicationsController = new CommunicationsController($GLOBALS['pdo']);

// Get action from request
$action = $_GET['action'] ?? 'login';
$do = $_GET['do'] ?? '';
$id = $_GET['id'] ?? null;

// Route the request with role checks
switch ($action) {
    case 'api':
        // API endpoints use Bearer API keys instead of session auth.
        $apiController->handle($do ?: 'status');
        break;

    case 'license_gate':
        // Requires login but skips license check (handled inside LicenseController::gate)
        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        $licenseController->gate();
        break;

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
            case 'bulk_delete':
                $authController->checkPermission('manager');
                $websiteController->bulk_delete();
                break;
            case 'renew':
                $authController->checkPermission('manager');
                $websiteController->renew($id);
                break;
            case 'view':
                $websiteController->view($id);
                break;
            case 'fetch_diagnostics':
                $websiteController->fetch_diagnostics();
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
            case 'bulk_delete':
                $authController->checkPermission('manager');
                $hostingController->bulk_delete();
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
            case 'client_services':
                $hostingController->clientServices((int)($id ?? 0));
                break;
            case 'assign_services':
                $authController->checkPermission('manager');
                $hostingController->assignServices();
                break;
            default:
                $hostingController->index();
        }
        break;

    // Providers — WHM servers, registrars, mail providers
    case 'providers':
        $authController->checkPermission('viewer');
        switch ($do) {
            case 'create':
                $authController->checkPermission('manager');
                $providersController->create();
                break;
            case 'edit':
                $authController->checkPermission('manager');
                $providersController->edit((int)($id ?? 0));
                break;
            case 'delete':
                $authController->checkPermission('manager');
                $providersController->delete((int)($id ?? 0));
                break;
            case 'toggle_active':
                $authController->checkPermission('manager');
                $providersController->toggleActive((int)($id ?? 0));
                break;
            default:
                $providersController->index();
        }
        break;

    // Hosting Accounts — cPanel accounts per client per WHM server
    case 'hosting_accounts':
        $authController->checkPermission('viewer');
        switch ($do) {
            case 'create':
                $authController->checkPermission('manager');
                $hostingAccountsController->create();
                break;
            case 'edit':
                $authController->checkPermission('manager');
                $hostingAccountsController->edit((int)($id ?? 0));
                break;
            case 'delete':
                $authController->checkPermission('manager');
                $hostingAccountsController->delete((int)($id ?? 0));
                break;
            case 'view':
                $hostingAccountsController->view((int)($id ?? 0));
                break;
            default:
                $hostingAccountsController->index();
        }
        break;

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
            case 'cron_diagnostics':
                $settingsController->cronDiagnostics();
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
            case 'rollback_google_sync':
                $settingsController->rollbackGoogleSync();
                break;
            case 'diagnostic_google_sheets':
                $settingsController->diagnosticGoogleSheets();
                break;
            case 'wordpress':
                $settingsController->wordpress();
                break;
            case 'wordpress_edit':
                $settingsController->wordpress_edit();
                break;
            case 'wordpress_save':
                $settingsController->wordpress_save();
                break;
            case 'wordpress_delete':
                $settingsController->wordpress_delete();
                break;
            case 'migrate_database':
                $settingsController->migrate_database();
                break;
            case 'license':
                $licenseController->settings();
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

    // Diagnostics Center
    case 'diagnostics':
        $authController->checkPermission('viewer');
        require_once __DIR__ . '/controllers/DiagnosticsController.php';
        $diagnosticsController = new DiagnosticsController(
            $GLOBALS['pdo'],
            $_SESSION['user_id'] ?? null
        );
        switch ($do) {
            case 'export':
                $dashboardController->diagnosticsExport();
                break;
            case 'data':
                $dashboardController->diagnosticsData();
                break;
            // AJAX: run full analysis (fetch WP data, score, generate bug reports)
            case 'analyze':
                $authController->checkPermission('manager');
                $diagnosticsController->analyze();
                break;
            // AJAX: get latest health metrics for one site
            case 'site_metrics':
                $diagnosticsController->getSiteMetrics();
                break;
            // AJAX: get active bug reports for one site
            case 'bugs':
                $diagnosticsController->getBugs();
                break;
            // AJAX: resolve a single bug report
            case 'resolve_bug':
                $authController->checkPermission('manager');
                $diagnosticsController->resolveBug();
                break;
            // AJAX: health score history for charting
            case 'metrics_history':
                $diagnosticsController->getMetricsHistory();
                break;
            // AJAX: bug timeline for one site
            case 'bug_timeline':
                $diagnosticsController->getBugTimeline();
                break;
            default:
                $dashboardController->diagnostics();
        }
        break;

    // Automation Center
    case 'automation':
        $authController->checkPermission('manager');
        switch ($do) {
            case 'create':
                $dashboardController->automationCreate();
                break;
            case 'edit':
                $dashboardController->automationEdit($id);
                break;
            case 'save_google_sync':
                $dashboardController->cronSaveGoogleSync();
                break;
            case 'run_google_sync':
                $dashboardController->cronRunGoogleSyncNow();
                break;
            case 'delete':
                $authController->checkPermission('manager');
                $dashboardController->automationDelete($id);
                break;
            case 'toggle':
                $dashboardController->automationToggle($id);
                break;
            case 'run':
                $dashboardController->automationRun($id);
                break;
            default:
                $dashboardController->automationIndex();
        }
        break;

    // Cron Scheduler
    case 'cron':
        $authController->checkPermission('manager');
        switch ($do) {
            case 'toggle':
                $dashboardController->cronToggle();
                break;
            case 'diagnostics':
                $dashboardController->cronDiagnostics();
                break;
            case 'save_cpanel':
                $dashboardController->cronSaveCpanel();
                break;
            case 'save_google_sync':
                $dashboardController->cronSaveGoogleSync();
                break;
            case 'run':
                $dashboardController->cronRunNow();
                break;
            case 'run_google_sync':
                $dashboardController->cronRunGoogleSyncNow();
                break;
            default:
                $dashboardController->cronIndex();
        }
        break;

    // Portfolio Center
    case 'portfolio':
        $authController->checkPermission('viewer');
        switch ($do) {
            case 'client_services':
                $dashboardController->portfolioClientServices((int)($id ?? 0));
                break;
            case 'assign_services':
                $authController->checkPermission('manager');
                $dashboardController->portfolioAssignServices();
                break;
            default:
                header('Location: index.php?action=hosting&lang=' . ($_SESSION['lang'] ?? 'it'));
                exit;
        }
        break;

    // Reports Center
    case 'reports':
        $authController->checkPermission('viewer');
        switch ($do) {
            case 'generate':
                $dashboardController->reportsGenerate();
                break;
            case 'download':
                $dashboardController->reportsDownload($id);
                break;
            case 'delete':
                $authController->checkPermission('manager');
                $dashboardController->reportsDelete($id);
                break;
            default:
                $dashboardController->reportsIndex();
        }
        break;

    // Notification Log
    case 'notifications':
        $authController->checkPermission('viewer');
        $dashboardController->notificationsIndex();
        break;

    // Client Communications CRM Log
    case 'comms':
        $authController->checkPermission('viewer');
        switch ($do) {
            case 'store':
                $communicationsController->store();
                break;
            case 'delete':
                $authController->checkPermission('manager');
                $communicationsController->delete();
                break;
            case 'websites':
                $communicationsController->ajaxWebsites();
                break;
            default:
                $communicationsController->index();
        }
        break;

    // Import / Export Hub
    case 'import_export':
        $authController->checkPermission('viewer');
        switch ($do) {
            case 'export_hosting':
                $authController->checkPermission('manager');
                $dashboardController->importExportExportHosting();
                break;
            case 'export_notifications':
                $dashboardController->importExportExportNotifications();
                break;
            default:
                $dashboardController->importExportIndex();
        }
        break;

    // Task Queue
    case 'tasks':
        $authController->checkPermission('viewer');
        $dashboardController->tasksIndex();
        break;

    // API Keys (super_admin only)
    case 'api_keys':
        $authController->checkPermission('super_admin');
        switch ($do) {
            case 'create':
                $dashboardController->apiKeysCreate();
                break;
            case 'revoke':
                $dashboardController->apiKeysRevoke($id ?? 0);
                break;
            case 'delete':
                $dashboardController->apiKeysDelete($id ?? 0);
                break;
            default:
                $dashboardController->apiKeysIndex();
        }
        break;

    // System Wiki / Documentation
    case 'wiki':
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        require APP_PATH . '/views/wiki/index.php';
        break;

    default:
        header('Location: index.php?action=login');
}
