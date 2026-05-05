<?php
class WebsiteController
{
    private Website $websiteModel;
    private Hosting $hostingModel;
    private EmailController $emailController;
    private SettingsModel $settingsModel;
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->websiteModel = new Website($pdo);
        $this->hostingModel = new Hosting($pdo);
        $this->emailController = new EmailController($pdo);
        $this->settingsModel = new SettingsModel($pdo);
    }

    public function index()
    {
        // Check authentication
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // Input parameters
        $search  = trim($_GET['search'] ?? '');
        $page    = isset($_GET['page'])     ? max(1, (int)$_GET['page'])     : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page']         : 10;
        if (!in_array($perPage, [10, 30, 50])) { $perPage = 10; }

        // Domain-centric grouped summaries
        $expiryFilter    = in_array($_GET['expiry_filter'] ?? '', ['expiring'], true) ? $_GET['expiry_filter'] : '';
        $domainSummaries = $this->websiteModel->getDomainSummaries($search, $page, $perPage, $expiryFilter);

        // Annotate days_left and computed status for each service column
        $today = new DateTimeImmutable('today');
        foreach ($domainSummaries as &$row) {
            foreach (['dom', 'web', 'mail'] as $prefix) {
                $expiry = $row["{$prefix}_expiry"] ?? null;
                if ($expiry) {
                    $diff = $today->diff(new DateTimeImmutable($expiry));
                    $days = $diff->invert ? -$diff->days : $diff->days;
                    $row["{$prefix}_days_left"]       = $days;
                    $row["{$prefix}_computed_status"] = $days < 0 ? 'expired' : ($days <= 30 ? 'expiring_soon' : 'active');
                } else {
                    $row["{$prefix}_days_left"]       = null;
                    $row["{$prefix}_computed_status"] = null;
                }
            }
        }
        unset($row);

        $totalDomains = $this->websiteModel->getDomainCount($search, $expiryFilter);
        $totalPages   = $perPage > 0 ? max(1, ceil($totalDomains / $perPage)) : 1;
        $userRole     = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';

        require APP_PATH . '/views/websites/index.php';
    }

    // In WebsiteController.php
    public function getHostingEmail(int $id)
    {
        $hostingPlan = $this->hostingModel->getHostingPlanById($id);
        if (!$hostingPlan) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Cliente non trovato']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['email' => $hostingPlan['email_address'] ?? '']);
        exit;
    }


    // All other methods remain exactly the same as in your original file
    public function create()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // Initialize with default dynamic status
        $website = ['dynamic_status' => 'attivo'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'domain'             => trim($_POST['domain'] ?? ''),
                'service_type'       => $_POST['service_type']       ?? 'hosting_web',
                'hosting_id'         => $_POST['hosting_id']         ?? null,
                'hosting_account_id' => $_POST['hosting_account_id'] ?? null,
                'provider_id'        => $_POST['provider_id']        ?? null,
                'expiry_date'        => sm_normalize_date($_POST['expiry_date'] ?? null),
                'status'             => 'active',
                'notes'              => $_POST['notes']               ?? '',
            ];

            if (empty($data['domain'])) {
                $error = 'Domain is required.';
            } else {
                try {
                    $this->websiteModel->createWebsite($data);
                    $_SESSION['message'] = "Service '{$data['domain']}' created successfully.";
                    header('Location: index.php?action=websites');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Error creating service: ' . $e->getMessage();
                    $website = array_merge($website, $_POST);
                }
            }
        }

        $hostingPlans    = $this->hostingModel->getAllHostingPlans();
        $hostingAccounts = $this->pdo->query("
            SELECT ha.id, ha.cpanel_username, ha.package_name, h.name AS client_name, p.name AS provider_name
            FROM hosting_accounts ha
            LEFT JOIN hosting  h ON h.id  = ha.client_id
            LEFT JOIN providers p ON p.id = ha.provider_id
            WHERE ha.status = 'active'
            ORDER BY h.name, ha.cpanel_username
        ")->fetchAll(PDO::FETCH_ASSOC);
        $registrars    = $this->pdo->query("SELECT id, name FROM providers WHERE type = 'registrar' AND is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $mailProviders = $this->pdo->query("SELECT id, name FROM providers WHERE type = 'email'    AND is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        require APP_PATH . '/views/websites/form.php';
    }

    public function view(int $id)
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $website = $this->websiteModel->getWebsiteById($id);
        if (!$website) {
            header('Location: index.php?action=websites');
            exit;
        }

        // Calculate dynamic status for the view
        $website['dynamic_status'] = $this->websiteModel->calculateDynamicStatus($website['expiry_date']);

        // Check if WordPress integration is configured for this website
        $wordPressSiteModel = new WordPressSite($this->pdo);
        $hasWordPressConfig = $wordPressSiteModel->exists($id);

        require APP_PATH . '/views/websites/view.php';
    }

    /**
     * Fetch WordPress diagnostics for a website.
     * Called via AJAX.
     */
    public function fetch_diagnostics()
    {
        header('Content-Type: application/json');

        try {
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Unauthorized');
            }

            $websiteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($websiteId <= 0) {
                throw new Exception('Website ID required');
            }

            require_once APP_PATH . '/services/WordPress/Exceptions.php';
            require_once APP_PATH . '/services/WordPress/WordPressApiClient.php';
            require_once APP_PATH . '/services/WordPress/DiagnosticsNormalizer.php';
            require_once APP_PATH . '/services/WordPress/DiagnosticsService.php';
            require_once APP_PATH . '/models/WordPressSite.php';

            $diagnosticsService = new DiagnosticsService($this->pdo);
            $result = $diagnosticsService->fetchDiagnostics($websiteId);

            http_response_code(!empty($result['success']) ? 200 : 400);
            echo json_encode($result);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
            exit;
        }
    }

    public function edit(int $id)
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $website = $this->websiteModel->getWebsiteById($id);
        if (!$website) {
            header('Location: index.php?action=websites');
            exit;
        }

        // Make sure hosting_id is properly set
        if (!isset($website['hosting_id'])) {
            $website['hosting_id'] = null;
        }


        // Calculate dynamic status for the view
        $website['dynamic_status'] = $this->websiteModel->calculateDynamicStatus($website['expiry_date']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'domain'             => trim($_POST['domain'] ?? ''),
                'service_type'       => $_POST['service_type']       ?? 'hosting_web',
                'hosting_id'         => $_POST['hosting_id']         ?? null,
                'hosting_account_id' => $_POST['hosting_account_id'] ?? null,
                'provider_id'        => $_POST['provider_id']        ?? null,
                'expiry_date'        => sm_normalize_date($_POST['expiry_date'] ?? null),
                'status'             => 'active',
                'notes'              => $_POST['notes']               ?? '',
            ];

            try {
                $this->websiteModel->updateWebsite($id, $data);
                $_SESSION['message'] = "Service '{$data['domain']}' updated successfully.";
                header('Location: index.php?action=websites');
                exit;
            } catch (PDOException $e) {
                $error = 'Error updating service: ' . $e->getMessage();
            }
        }

        $hostingPlans    = $this->hostingModel->getAllHostingPlans();
        $hostingAccounts = $this->pdo->query("
            SELECT ha.id, ha.cpanel_username, ha.package_name, h.name AS client_name, p.name AS provider_name
            FROM hosting_accounts ha
            LEFT JOIN hosting  h ON h.id  = ha.client_id
            LEFT JOIN providers p ON p.id = ha.provider_id
            WHERE ha.status = 'active'
            ORDER BY h.name, ha.cpanel_username
        ")->fetchAll(PDO::FETCH_ASSOC);
        $registrars    = $this->pdo->query("SELECT id, name FROM providers WHERE type = 'registrar' AND is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $mailProviders = $this->pdo->query("SELECT id, name FROM providers WHERE type = 'email'    AND is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        require APP_PATH . '/views/websites/form.php';
    }

    public function delete(int $id)
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $this->websiteModel->deleteWebsite($id);
        $_SESSION['message'] = "Servizio eliminato con successo";
        header('Location: index.php?action=websites');
        exit;
    }

    public function bulk_delete()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ids'])) {
            header('Location: index.php?action=websites');
            exit;
        }

        $rawIds = trim((string)($_POST['ids'] ?? ''));
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawIds)))));
        
        if (empty($ids)) {
            $_SESSION['error'] = "No items selected";
            header('Location: index.php?action=websites');
            exit;
        }

        try {
            $this->pdo->beginTransaction();
            $deleted = 0;
            foreach ($ids as $id) {
                $this->websiteModel->deleteWebsite($id);
                $deleted++;
            }
            $this->pdo->commit();
            $_SESSION['message'] = "$deleted servizio/i eliminato/i con successo";
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $_SESSION['error'] = "Errore durante l'eliminazione: " . $e->getMessage();
        }

        header('Location: index.php?action=websites');
        exit;
    }


    public function renew(int $id)
    {

        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        try {
            // Debug logging
            error_log("Renewal initiated for website ID: $id");

            $website = $this->websiteModel->getWebsiteById($id);
            if (!$website) {
                throw new Exception("Sito web non trovato");
            }

            $newExpiry = $this->websiteModel->renewWebsite($id);
            error_log("New expiry date set: $newExpiry");

            // Send notification
            // $notificationSent = $this->emailController->sendRenewalNotification(
            //    $id,
            //     $newExpiry,
            //    $website['status']
            //);

            $_SESSION['message'] = "Il servizio '{$website['domain']}' viene rinnovato fino a " . date('F j, Y', strtotime($newExpiry));
            // if (!$notificationSent) {
            // $_SESSION['message'] .= " (Nessuna notifica inviata)";
            // }

            // Redirect back to edit page with success message
            header("Location: index.php?action=websites");
            exit;
        } catch (Exception $e) {
            error_log("Renewal Error: " . $e->getMessage());
            $_SESSION['error'] = "Renewal failed: " . $e->getMessage();
            header("Location: index.php?action=websites");
            exit;
        }
    }

    private function logTask(string $type, string $label, string $status = 'completed', ?array $result = null): void
    {
        try {
            $exists = $this->pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task_queue'"
            )->fetchColumn();
            if (!$exists) return;
            $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $stmt = $this->pdo->prepare(
                "INSERT INTO task_queue (type, label, status, created_by, started_at, completed_at, progress, result_json)
                 VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?)"
            );
            $stmt->execute([$type, $label, $status, $uid, $status === 'completed' ? 100 : 0,
                $result ? json_encode($result) : null]);
        } catch (Exception $e) {
            error_log("WebsiteController::logTask error: " . $e->getMessage());
        }
    }

    public function export()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $filename = $this->websiteModel->exportToExcel();
        $filepath = EXPORT_PATH . '/' . $filename;

        if (file_exists($filepath)) {
            $this->logTask('export_websites', 'Export Websites (XLSX)', 'completed', ['file' => $filename]);
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            $_SESSION['error'] = "Errore nella generazione del file di esportazione";
            header('Location: index.php?action=websites');
            exit;
        }
    }

    public function import()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
            $file = $_FILES['import_file'];

            if ($file['error'] === UPLOAD_ERR_OK) {
                $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);

                if (in_array(strtolower($fileType), ['xls', 'xlsx'])) {
                    $uploadPath = UPLOAD_PATH . '/' . basename($file['name']);

                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $result = $this->websiteModel->importFromExcel($uploadPath);

                        $this->logTask('import_websites', 'Import Websites (XLSX)', 'completed', [
                            'imported' => $result['imported'], 'updated' => $result['updated'],
                            'skipped' => $result['skipped'], 'errors' => count($result['errors'] ?? []),
                        ]);

                        $_SESSION['import_result'] = [
                            'imported' => $result['imported'],
                            'updated' => $result['updated'],
                            'skipped' => $result['skipped'],
                            'hosting_created' => $result['hosting_created'],
                            'errors' => $result['errors']
                        ];

                        unlink($uploadPath);
                    }
                }
            }
        }
        header('Location: index.php?action=websites');
        exit;
    }

    private function getUploadError($errorCode)
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        ];

        return $errors[$errorCode] ?? 'Unknown upload error';
    }

    public function exportToGoogleSheets(array $settings): array
    {
        $client = $this->initializeSheetsClient($settings);
        $service = new Google\Service\Sheets($client);

        try {
            // Prepare all data with headers
            $data = $this->prepareExportData();

            // Clear and update sheet
            $this->clearSheet($service, $settings);
            $this->updateSheet($service, $settings, $data);

            return [
                'exported' => count($data) - 2, // Exclude header rows
                'updated' => 0,
                'errors' => 0
            ];
        } catch (Exception $e) {
            error_log("Export failed: " . $e->getMessage());
            throw new Exception("Export to Google Sheets failed: " . $e->getMessage());
        }
    }

    /**
     * Import data from Google Sheets
     */
    public function importFromGoogleSheets(array $settings): array
    {
        $client = $this->initializeSheetsClient($settings);
        $service = new Google\Service\Sheets($client);

        try {
            $sheetData = $this->getSheetData($service, $settings);
            return $this->websiteModel->importFromSheets($sheetData);
        } catch (Exception $e) {
            error_log("Import failed: " . $e->getMessage());
            throw new Exception("Import from Google Sheets failed: " . $e->getMessage());
        }
    }

    /**
     * Synchronize data between database and Google Sheets
     */

    // In WebsiteController.php
    public function syncWithGoogleSheets(): array
    {
        $settingsController = new SettingsController($this->pdo);
        return $settingsController->syncWithGoogleSheets();
    }

    /**
     * Helper Methods
     */
    private function initializeSheetsClient(array $settings): Google\Client
    {
        $client = new Google\Client();
        $client->setApplicationName('Website Management System');
        $client->setScopes([Google\Service\Sheets::SPREADSHEETS]);

        try {
            $credentials = json_decode($settings['credentials'], true, 512, JSON_THROW_ON_ERROR);
            $client->setAuthConfig($credentials);
            return $client;
        } catch (Exception $e) {
            throw new Exception("Failed to initialize Google Client: " . $e->getMessage());
        }
    }

    private function prepareExportData(): array
    {
        $headers = [
            [
                'Clienti',
                'Informazioni per il cliente',
                '',
                '',
                'Informazioni sul servizio',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                ''
            ],
            [
                'Nome',
                'Indirizzo',
                'Email',
                'P.IVA',
                'Tipologia di Servizi',
                'Dettaglio Servizi',
                'Email Assegnata',
                'Propietario',
                'Registrante',
                'Scadenza',
                'Costo Server (iva inclusa)',
                'Prezzo di vendita (iva inclusa)',
                'Direct DNS A',
                'User Name cpanel',
                'Email panel',
                'Bug report',
                'Costo di manutenzione sito',
                'Notes'
            ]
        ];

        $prepared = $this->websiteModel->prepareForGoogleSheets();
        $rows = $prepared['data'] ?? [];

        return array_merge($headers, $rows);
    }

    private function clearSheet(Google\Service\Sheets $service, array $settings): void
    {
        try {
            $range = $settings['sheet_name'] . '!A:Z';
            $request = new Google\Service\Sheets\ClearValuesRequest();
            $service->spreadsheets_values->clear($settings['sheet_id'], $range, $request);
        } catch (Exception $e) {
            throw new Exception("Failed to clear sheet: " . $e->getMessage());
        }
    }

    private function updateSheet(Google\Service\Sheets $service, array $settings, array $data): void
    {
        $range = $settings['sheet_name'] . '!A1';
        $body = new Google\Service\Sheets\ValueRange(['values' => $data]);

        $service->spreadsheets_values->update(
            $settings['sheet_id'],
            $range,
            $body,
            ['valueInputOption' => 'USER_ENTERED']
        );
    }

    private function getSheetData(Google\Service\Sheets $service, array $settings): array
    {
        $range = $settings['sheet_name'] . '!A2:Z';
        $response = $service->spreadsheets_values->get($settings['sheet_id'], $range);
        return $response->getValues() ?: [];
    }

    private function analyzeSync(array $dbData, array $sheetData): array
    {
        $dbDomains = array_column($dbData, 'domain');
        $sheetDomains = [];
        foreach ($sheetData as $row) {
            $domain = strtolower(trim((string)($row[5] ?? '')));
            if ($domain === '' || $domain === 'dettaglio servizi') {
                continue;
            }
            $sheetDomains[] = $domain;
        }

        $toExport = array_diff($dbDomains, $sheetDomains);
        $toImport = array_diff($sheetDomains, $dbDomains);

        return [
            'needs_export' => !empty($toExport),
            'needs_import' => !empty($toImport),
            'export_domains' => $toExport,
            'import_domains' => $toImport,
            'exported' => 0,
            'imported' => 0,
            'updated' => 0,
            'conflicts' => 0,
            'errors' => 0
        ];
    }
}