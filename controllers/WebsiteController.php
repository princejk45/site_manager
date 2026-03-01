<?php
class WebsiteController
{
    private $websiteModel;
    private $hostingModel;
    private $emailController;
    private $settingsModel;
    private $pdo;
    public function __construct($pdo)
    {
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

        // Get and sanitize input parameters with proper null checks
        $search = trim($_GET['search'] ?? '');
        $sort = $_GET['sort'] ?? 'hosting_server';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

        // Validate per_page options
        $allowedPerPage = [10, 30, 50];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 10;
        }

        // Validate sort column to prevent SQL injection
        $allowedSorts = ['hosting_server', 'domain', 'name', 'email_server', 'expiry_date'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'hosting_server';
        }

        // Get websites with automatic secondary sorting by domain
        $websites = $this->websiteModel->getWebsites($search, $sort, $order, $page, $perPage);

        // Calculate dynamic status for each website
        foreach ($websites as &$website) {
            $website['dynamic_status'] = $this->websiteModel->calculateDynamicStatus($website['expiry_date']);
        }
        unset($website); // Break the reference

        // Pagination calculations with zero-division protection
        $totalWebsites = (int)$this->websiteModel->getWebsiteCount($search);
        $totalPages = $perPage > 0 ? max(1, ceil($totalWebsites / $perPage)) : 1;

        // Get hosting plans for dropdowns (if needed)
        $hostingPlans = $this->hostingModel->getAllHostingPlans();

        // Pass data to view
        require APP_PATH . '/views/websites/index.php';
    }

    // In WebsiteController.php
    public function getHostingEmail($id)
    {
        $hostingPlan = $this->hostingModel->getHostingPlanById($id);
        if (!$hostingPlan) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Cliente non trovato']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['email' => $hostingPlan['email_address']]); // Make sure this matches your DB column
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
                'name' => $_POST['name'],
                'domain' => $_POST['domain'],
                'hosting_id' => $_POST['hosting_id'] ?? null,
                'email_server' => $_POST['email_server'],
                'expiry_date' => $_POST['expiry_date'],
                'status' => $_POST['status'],
                'vendita' => $_POST['vendita'],
                'proprietario' => $_POST['proprietario'] ?? null,
                'dns' => $_POST['dns'] ?? null,
                'cpanel' => $_POST['cpanel'] ?? null,
                'epanel' => $_POST['epanel'] ?? null,
                'notes' => $_POST['notes'] ?? null,
                'remark' => $_POST['remark'] ?? null
            ];

            // Get assigned email from hosting plan if hosting_id is provided
            if (!empty($data['hosting_id'])) {
                $hostingPlan = $this->hostingModel->getHostingPlanById($data['hosting_id']);
                if ($hostingPlan) {
                    $data['assigned_email'] = $hostingPlan['email_address'];
                } else {
                    $error = "Il client selezionato non è stato trovato";
                    $website = array_merge($website, $_POST);
                    $hostingPlans = $this->hostingModel->getAllHostingPlans();
                    require APP_PATH . '/views/websites/form.php';
                    return;
                }
            } else {
                $data['assigned_email'] = ''; // No hosting plan selected
            }

            try {
                $this->websiteModel->createWebsite($data);
                $_SESSION['message'] = "Servizio ('{$data['domain']}') creato con successo";
                header('Location: index.php?action=websites');
                exit;
            } catch (PDOException $e) {
                $error = "Errore durante la creazione del servizio: " . $e->getMessage();
                $website = array_merge($website, $_POST); // Preserve form input
            }
        }

        $hostingPlans = $this->hostingModel->getAllHostingPlans();
        require APP_PATH . '/views/websites/form.php';
    }

    public function view($id)
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

        require APP_PATH . '/views/websites/view.php';
    }

    public function edit($id)
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
                'name' => $_POST['name'],
                'domain' => $_POST['domain'],
                'hosting_id' => $_POST['hosting_id'] ?? null,
                'email_server' => $_POST['email_server'],
                'expiry_date' => $_POST['expiry_date'],
                'status' => $_POST['status'],
                'vendita' => $_POST['vendita'],
                'proprietario' => $_POST['proprietario'] ?? null,
                'dns' => $_POST['dns'] ?? null,
                'cpanel' => $_POST['cpanel'] ?? null,
                'epanel' => $_POST['epanel'] ?? null,
                'notes' => $_POST['notes'] ?? null,
                'remark' => $_POST['remark'] ?? null
            ];

            // Get assigned email from hosting plan if hosting_id is provided
            if (!empty($data['hosting_id'])) {
                $hostingPlan = $this->hostingModel->getHostingPlanById($data['hosting_id']);
                if ($hostingPlan) {
                    $data['assigned_email'] = $hostingPlan['email_address'];
                } else {
                    $error = "Selected hosting plan not found";
                    $hostingPlans = $this->hostingModel->getAllHostingPlans();
                    require APP_PATH . '/views/websites/form.php';
                    return;
                }
            } else {
                $data['assigned_email'] = ''; // No hosting plan selected
            }

            try {
                $this->websiteModel->updateWebsite($id, $data);
                $_SESSION['message'] = "Il servizio '{$data['domain']}' è stato aggiornato con successo";
                header('Location: index.php?action=websites&do=view&id=' . $website['id']);
                exit;
            } catch (PDOException $e) {
                $error = "Errore durante l'aggiornamento del servizio:  '{$data['domain']}'  " . $e->getMessage();
            }
        }

        $hostingPlans = $this->hostingModel->getAllHostingPlans();
        require APP_PATH . '/views/websites/form.php';
    }

    public function delete($id)
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

        // Get and validate IDs
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'])));
        
        if (empty($ids)) {
            $_SESSION['error'] = "No items selected";
            header('Location: index.php?action=websites');
            exit;
        }

        try {
            $deleted = 0;
            foreach ($ids as $id) {
                $this->websiteModel->deleteWebsite($id);
                $deleted++;
            }
            $_SESSION['message'] = "$deleted servizio/i eliminato/i con successo";
        } catch (Exception $e) {
            $_SESSION['error'] = "Errore durante l'eliminazione: " . $e->getMessage();
        }

        header('Location: index.php?action=websites');
        exit;
    }


    public function renew($id)
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

    public function export()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $filename = $this->websiteModel->exportToExcel();
        $filepath = EXPORT_PATH . '/' . $filename;

        if (file_exists($filepath)) {
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
            ['Informazioni per il cliente', '', '', '', 'Informazioni sul servizio'],
            ['Name', 'Address', 'Email', 'P.IVA', 'TIPOLOGIA DI SERVIZI', 'DETTAGLIO SERVIZI', 'EMAIL ASSEGNATA']
        ];

        $data = $this->websiteModel->prepareForGoogleSheets();
        return array_merge($headers, $data);
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
        $range = $settings['sheet_name'] . '!A:Z';
        $response = $service->spreadsheets_values->get($settings['sheet_id'], $range);
        return $response->getValues() ?: [];
    }

    private function analyzeSync(array $dbData, array $sheetData): array
    {
        $dbDomains = array_column($dbData, 'domain');
        $sheetDomains = array_column(array_slice($sheetData, 2), 5); // Skip headers

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