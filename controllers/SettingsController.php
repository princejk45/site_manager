<?php
class SettingsController
{
    private Email $emailModel;
    private CronModel $cronModel;
    private SettingsModel $settingsModel;
    private Hosting $hostingModel;
    private Website $websiteModel;
    private SiteSettings $siteSettings;
    private EmailTemplate $emailTemplate;
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->emailModel = new Email($pdo);
        $this->cronModel = new CronModel($pdo);
        $this->settingsModel = new SettingsModel($pdo);
        $this->websiteModel = new Website($pdo);
        $this->hostingModel = new Hosting($pdo);
        $this->siteSettings = new SiteSettings($pdo);
        $this->emailTemplate = new EmailTemplate($pdo);
    }


    public function smtp()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'host' => $_POST['host'],
                'port' => $_POST['port'],
                'username' => $_POST['username'],
                'password' => $_POST['password'],
                'encryption' => $_POST['encryption'],
                'from_email' => $_POST['from_email'],
                'from_name' => $_POST['from_name'],
                'cc_email' => $_POST['cc_email'] ?? null,
                'test_email' => $_POST['test_email'] ?? null
            ];

            $success = $this->emailModel->updateSmtpSettings($data);

            if ($success) {
                $_SESSION['message'] = __('settings.smtp_settings_updated');
            } else {
                $error = __('settings.cron_settings_error');
            }
        }

        $smtpSettings = $this->emailModel->getSmtpSettings();
        require APP_PATH . '/views/settings/smtp.php';
    }

    public function testSmtp()
    {
        // Set JSON header first to ensure no HTML output
        header('Content-Type: application/json');

        try {
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Non autorizzato');
            }

            $smtpSettings = $this->emailModel->getSmtpSettings();
            if (!$smtpSettings) {
                throw new Exception('Impostazioni SMTP non configurate');
            }

            // Get test email from POST data
            $testEmail = $_POST['test_email'] ?? $smtpSettings['from_email'];

            // Validate email format
            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Indirizzo email di prova non valido');
            }

            require_once APP_PATH . '/vendor/autoload.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            // Enable debug mode to capture detailed error info
            $mail->SMTPDebug = 0; // Set to 2 for verbose debug output
            $mail->Debugoutput = 'error_log';

            // Server settings
            $mail->isSMTP();
            $mail->Host = trim($smtpSettings['host']);
            $mail->SMTPAuth = true;
            $mail->Username = trim($smtpSettings['username']);
            $mail->Password = $smtpSettings['password'];
            $mail->Port = (int)$smtpSettings['port'];

            // Handle encryption - key setting for STARTTLS
            if ($smtpSettings['encryption'] === 'starttls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAutoTLS = true;
            } elseif ($smtpSettings['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtpSettings['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
            }

            // Recipients
            $mail->setFrom(trim($smtpSettings['from_email']), $smtpSettings['from_name']);
            $mail->addAddress($testEmail);

            // Add CC email if configured
            if (!empty($smtpSettings['cc_email'])) {
                $mail->addCC(trim($smtpSettings['cc_email']));
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'E-mail di prova SMTP';
            $mail->Body = '<h1>Test SMTP riuscito</h1><p>Le tue impostazioni SMTP funzionano correttamente.</p>';
            $mail->AltBody = 'Test SMTP riuscito: le impostazioni SMTP funzionano correttamente.';

            if (!$mail->send()) {
                throw new Exception('PHPMailer Error: ' . $mail->ErrorInfo);
            }

            // Log the test email
            $logData = [
                'email_type' => 'manual',
                'sent_to' => $testEmail,
                'subject' => $mail->Subject,
                'body' => $mail->Body,
                'status' => 'sent'
            ];

            // Include CC in the log message if it exists
            $message = 'Email di prova inviata a  ' . $testEmail;
            if (!empty($smtpSettings['cc_email'])) {
                $message .= ' e in copia per conoscenza a ' . $smtpSettings['cc_email'];
                $logData['cc'] = $smtpSettings['cc_email'];
            }

            $this->emailModel->logEmail($logData);

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (Exception $e) {
            // Log the failed attempt
            if (isset($testEmail) && isset($smtpSettings)) {
                $logData = [
                    'email_type' => 'manual',
                    'sent_to' => $testEmail,
                    'subject' => 'E-mail di prova SMTP',
                    'body' => '',
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ];

                if (!empty($smtpSettings['cc_email'])) {
                    $logData['cc'] = $smtpSettings['cc_email'];
                }

                $this->emailModel->logEmail($logData);
            }

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => __('settings.smtp_test_error') . $e->getMessage()
            ]);
        }
        exit;
    }

    public function cronDiagnostics()
    {
        // Set JSON header - must be before any output
        header('Content-Type: application/json');

        try {
            if (!isset($_SESSION['user_id'])) {
                throw new Exception(__('auth.invalid_credentials'));
            }

            // Get diagnostics from CronModel
            $diagnostics = $this->cronModel->getDiagnostics();
            if (!is_array($diagnostics)) {
                throw new Exception(__('settings.diagnostic_error'));
            }

            if (!$diagnostics['success']) {
                throw new Exception($diagnostics['error'] ?? __('settings.diagnostic_error'));
            }

            // Return JSON response
            echo json_encode($diagnostics);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    public function advanced()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // Handle Google Sheets actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (
            isset($_POST['save_google_settings']) ||
            isset($_POST['export_to_google']) ||
            isset($_POST['import_from_google']) ||
            isset($_POST['sync_with_google'])
        )) {
            $this->handleGoogleSheets();
        }

        $googleSheetSettings = $this->settingsModel->getGoogleSheetsSettings();

        require APP_PATH . '/views/settings/advanced.php';
    }

    public function handleGoogleSheets()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handleGoogleSheetsPost();
                // Redirect back to advanced page after successful operation
                header('Location: index.php?action=import_export');
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['google_error'] = $e->getMessage();
            header('Location: index.php?action=import_export');
            exit;
        }
    }

    private function handleGoogleSheetsPost()
    {
        // Log the incoming POST data
        error_log("=== FORM SUBMISSION ===");
        error_log("POST data received: " . print_r($_POST, true));
        
        // Always ensure sheet_name is not empty
        $sheetName = trim($_POST['google_sheet_name'] ?? '');
        error_log("Sheet Name from POST: '" . $sheetName . "'");
        
        if (empty($sheetName)) {
            $sheetName = 'Sheet1';
            error_log("Sheet Name was empty, set to default: Sheet1");
        }

        $settings = [
            'sheet_id' => trim($_POST['google_sheet_id'] ?? ''),
            'sheet_name' => $sheetName,
            'credentials' => trim($_POST['google_credentials'] ?? ''),
            'enabled' => isset($_POST['google_sync_enabled']) ? 1 : 0
        ];

        error_log("Settings array created: " . print_r($settings, true));

        // Validate required fields for any action beyond just saving settings
        if (isset($_POST['export_to_google']) || isset($_POST['import_from_google']) || isset($_POST['sync_with_google'])) {
            if (empty($settings['sheet_id']) || empty($settings['credentials']) || empty($settings['sheet_name'])) {
                throw new Exception("Sheet ID, Sheet Name, and credentials are required");
            }
        }

        // Save settings
        $this->settingsModel->saveGoogleSheetsSettings($settings);
        error_log("Settings saved to database");

        // Handle specific actions - IMPORTANT: Use POST values directly, not retrieved settings
        if (isset($_POST['export_to_google'])) {
            error_log("Export action triggered");
            $result = $this->exportToGoogleSheets($settings);
            $_SESSION['google_sync_result'] = $result;
            // Only set success message if there were no errors
            if (empty($result['errors'])) {
                $_SESSION['message'] = __('settings.export_success');
            }
        } elseif (isset($_POST['import_from_google'])) {
            error_log("Import action triggered");
            $result = $this->importFromGoogleSheets($settings);
            $_SESSION['google_sync_result'] = $result;
            // Only set success message if there were no errors
            if (empty($result['errors'])) {
                $_SESSION['message'] = __('settings.import_success');
            }
        } elseif (isset($_POST['sync_with_google'])) {
            error_log("Sync action triggered");
            $result = $this->syncWithGoogleSheets($settings);
            $_SESSION['google_sync_result'] = $result;
            // Only set success message if there were no errors
            if (empty($result['errors'])) {
                $_SESSION['message'] = __('settings.sync_success');
            }
        }
        error_log("=== END FORM SUBMISSION ===");
    }

    private function exportToGoogleSheets($settings = null): array
    {
        require_once APP_PATH . '/vendor/autoload.php';

        if ($settings === null) {
            $settings = $this->settingsModel->getGoogleSheetsSettings();
        }

        $creds = json_decode($settings['credentials'] ?? '', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'exported' => 0,
                'updated' => 0,
                'skipped_not_found' => 0,
                'errors' => ['Invalid credentials format: ' . json_last_error_msg()],
            ];
        }

        try {
            $client = $this->getGoogleClient($settings);
            $service = new \Google\Service\Sheets($client);

            // Resolve the numeric sheet ID needed for formatting requests.
            $sheetId = $this->getSheetId($service, $settings);

            $preparedData = $this->websiteModel->prepareForGoogleSheets();
            $websiteData = $preparedData['data'] ?? [];

            // Update existing matched rows, and collect rows not yet in the sheet.
            $syncResult = $this->updateExistingGoogleRowsOnly($service, $settings, $websiteData);

            // Append DB records that have no matching row in the sheet yet.
            $exported = 0;
            $skippedRows = $syncResult['skipped_rows'] ?? [];
            if (!empty($skippedRows)) {
                $appendResult = $this->appendRowsToGoogleSheet($service, $settings, $skippedRows);
                $exported = $appendResult['appended'] ?? 0;
                if (!empty($appendResult['errors'])) {
                    $syncResult['errors'] = array_merge($syncResult['errors'] ?? [], $appendResult['errors']);
                }
            }

            // Re-apply header/column formatting so it is preserved after every export.
            if ($sheetId !== null) {
                $this->writeSheetHeaders($service, $settings);
                $totalRows = count($websiteData) + 2; // +2 for the two header rows
                $this->applySheetFormatting($service, $settings, $sheetId, $totalRows);
            }

            return [
                'exported' => $exported,
                'updated' => $syncResult['updated'] ?? 0,
                'skipped_not_found' => 0,
                'errors' => $syncResult['errors'] ?? [],
            ];
        } catch (Exception $e) {
            error_log("Google Sheets export error: " . $e->getMessage());
            return [
                'exported' => 0,
                'updated' => 0,
                'skipped_not_found' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }





    private function importFromGoogleSheets($settings = null)
    {
        // If no settings provided, retrieve from database
        if ($settings === null) {
            $settings = $this->settingsModel->getGoogleSheetsSettings();
        }

        try {
            // Use the same three-way engine in forward mode to keep behavior consistent.
            $mergeResult = $this->executeThreeWayMerge($settings, 'forward', [
                'conflict_policy' => 'manual',
                'dry_run' => false,
            ]);

            return [
                'imported' => (int)($mergeResult['added_to_db'] ?? 0),
                'updated' => (int)($mergeResult['updated_in_db'] ?? 0),
                'errors' => $mergeResult['errors'] ?? [],
                'baseline_initialized' => (bool)($mergeResult['baseline_initialized'] ?? false),
            ];
        } catch (Exception $e) {
            error_log("Google Sheets import error: " . $e->getMessage());
            return [
                'imported' => 0,
                'updated' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    public function syncWithGoogleSheets($settings = null)
    {
        if ($settings === null) {
            $settings = $this->settingsModel->getGoogleSheetsSettings();
        }

        return $this->executeThreeWayMerge($settings, 'together', [
            'conflict_policy' => 'manual',
            'dry_run' => false,
        ]);
    }

    private function getGoogleClient($settings = null)
    {
        if ($settings === null) {
            $settings = $this->settingsModel->getGoogleSheetsSettings();
        }

        if (empty($settings['credentials'])) {
            throw new Exception("Google Sheets credentials not configured");
        }

        $client = new Google\Client();
        $client->setApplicationName('Site Manager');
        $client->setScopes([Google\Service\Sheets::SPREADSHEETS]);
        
        // Add offline access to ensure token refresh
        $client->setAccessType('offline');

        try {
            // Log credential details for debugging
            error_log("DEBUG: Credentials string length: " . strlen($settings['credentials']));
            error_log("DEBUG: Credentials first 100 chars: " . substr($settings['credentials'], 0, 100));
            
            $credentials = json_decode($settings['credentials'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("ERROR: Invalid JSON credentials - " . json_last_error_msg());
                error_log("ERROR: Credentials content: " . $settings['credentials']);
                throw new Exception("Credenziali Google non valide JSON: " . json_last_error_msg());
            }

            error_log("DEBUG: Credentials decoded successfully");
            error_log("DEBUG: Credential type: " . ($credentials['type'] ?? 'unknown'));
            error_log("DEBUG: Credential client_email: " . ($credentials['client_email'] ?? 'unknown'));
            error_log("DEBUG: Setting auth config with credentials");
            $client->setAuthConfig($credentials);
            error_log("DEBUG: Auth config set successfully");
        } catch (Exception $e) {
            error_log("ERROR: Failed to initialize Google Client: " . $e->getMessage());
            throw new Exception("Impossibile inizializzare Google Client: " . $e->getMessage());
        }

        return $client;
    }

    /**
     * Display and save site settings form
     */
    public function siteSettings()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
                $logoFile = $_FILES['logo'];
                $uploadDir = APP_PATH . '/assets/images/';
                
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($logoFile['type'], $allowedTypes)) {
                    $error = "Logo must be an image file (JPG, PNG, GIF, WebP)";
                } else if ($logoFile['size'] > 2 * 1024 * 1024) {  // 2MB max
                    $error = "Logo file must be less than 2MB";
                } else {
                    // Generate unique filename
                    $extension = pathinfo($logoFile['name'], PATHINFO_EXTENSION);
                    $filename = 'logo_' . time() . '.' . $extension;
                    $uploadPath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($logoFile['tmp_name'], $uploadPath)) {
                        // Delete old logo if it exists and is in uploads
                        $currentLogo = $_POST['current_logo'] ?? '';
                        if (!empty($currentLogo) && strpos($currentLogo, 'assets/images/') !== false) {
                            $oldPath = APP_PATH . '/' . $currentLogo;
                            if (file_exists($oldPath) && strpos($oldPath, 'logo_') !== false) {
                                @unlink($oldPath);
                            }
                        }
                        
                        $this->siteSettings->updateSetting('logo_path', 'assets/images/' . $filename);
                        $_SESSION['message'] = __('settings.logo_updated');
                    } else {
                        $error = __('settings.logo_upload_error');
                    }
                }
            }
            
            // Handle other POST data
            foreach ($_POST as $key => $value) {
                if (!in_array($key, ['action', 'do', 'logo', 'current_logo'])) {
                    $this->siteSettings->updateSetting($key, $value);
                }
            }
            
            if (!isset($error)) {
                $_SESSION['message'] = $_SESSION['message'] ?? __('settings.general_settings_updated');
            }
            
            SiteSettings::clearCache();
        }

        $settings = $this->siteSettings->getAllSettings();

        // Provide language-aware textual defaults for email header/footer when empty
        $lang = $_SESSION['lang'] ?? DEFAULT_LANG;
        if (empty($settings['email_global_header'])) {
            if ($lang === 'it') {
                $settings['email_global_header'] = "<p>Ciao,</p><p>Di seguito trovi le informazioni importanti.</p>";
            } else {
                $settings['email_global_header'] = "<p>Hello,</p><p>Here are the important details.</p>";
            }
        }
        if (empty($settings['email_global_footer'])) {
            if ($lang === 'it') {
                $settings['email_global_footer'] = "<p>Questa e-mail è stata generata automaticamente. Puoi rispondere a questo messaggio o contattarci tramite Whatsapp.</p><p>Questa comunicazione è destinata esclusivamente al destinatario e potrebbe contenere informazioni riservate.</p>";
            } else {
                $settings['email_global_footer'] = "<p>This email was generated automatically. You may reply to this message or contact us via Whatsapp.</p><p>This communication is intended solely for the recipient and may contain confidential information.</p>";
            }
        }

        require APP_PATH . '/views/settings/site_settings.php';
    }

    /**
     * Save global email header and footer
     */
    public function saveEmailHeaderFooter()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $header = $_POST['email_global_header'] ?? '';
            $footer = $_POST['email_global_footer'] ?? '';
            
            $this->siteSettings->updateSetting('email_global_header', $header);
            $this->siteSettings->updateSetting('email_global_footer', $footer);
            
            $_SESSION['message'] = __('settings.header_footer_updated');
            SiteSettings::clearCache();
        }

        header('Location: index.php?action=settings&do=site_settings');
        exit;
    }

    /**
     * List all email templates
     */
    public function emailTemplates()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $templates = $this->emailTemplate->getAll(false);
        require APP_PATH . '/views/settings/email_templates.php';
    }

    /**
     * Edit email template form
     */
    public function editEmailTemplate()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $templateId = $_GET['id'] ?? null;
        $template = null;
        $error = null;

        if (!$templateId) {
            $error = "Template ID non specificato";
        } else {
            $template = $this->emailTemplate->getById($templateId);
            if (!$template) {
                $error = "Template non trovato";
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $template) {
            $data = [
                'name' => $_POST['name'] ?? '',
                'subject' => $_POST['subject'] ?? '',
                'body' => $_POST['body'] ?? '',
                'description' => $_POST['description'] ?? '',
                'status' => $_POST['status'] ?? 'active'
            ];

            // Preserve per-template header/footer if present in the database and not provided in POST
            if (!isset($_POST['header']) && isset($template['header'])) {
                $data['header'] = $template['header'];
            } elseif (isset($_POST['header'])) {
                $data['header'] = $_POST['header'];
            }

            if (!isset($_POST['footer']) && isset($template['footer'])) {
                $data['footer'] = $template['footer'];
            } elseif (isset($_POST['footer'])) {
                $data['footer'] = $_POST['footer'];
            }

            if ($this->emailTemplate->update($templateId, $data)) {
                $_SESSION['message'] = __('settings.template_updated');
                header('Location: index.php?action=settings&do=email_templates');
                exit;
            } else {
                $error = "Errore durante l'aggiornamento del template";
                $template = array_merge($template, $data);
            }
        }

        require APP_PATH . '/views/settings/email_template_form.php';
    }

    // Compare Google Sheets and Database data
    public function compareWithGoogle()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        try {
            $settings = $this->settingsModel->getGoogleSheetsSettings();
            
            if (empty($settings['sheet_id']) || empty($settings['credentials'])) {
                $_SESSION['error'] = "Google Sheets configuration incomplete";
                header('Location: index.php?action=import_export');
                exit;
            }

            // Get Google data
            $googleData = $this->getGoogleSheetsData();
            
            // Get database data
            $dbData = $this->websiteModel->getWebsites('', 'domain', 'asc', 1, PHP_INT_MAX);
            
            // Compare data
            $comparison = $this->compareDatasets($dbData, $googleData);
            
            $_SESSION['comparison_result'] = $comparison;
            header('Location: index.php?action=import_export');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Comparison failed: " . $e->getMessage();
            header('Location: index.php?action=import_export');
            exit;
        }
    }

    // Format merge results for display
    private function formatMergeResultsForDisplay($result)
    {
        $output = [];

        if (!empty($result['dry_run'])) {
            $output[] = "Dry Run: enabled (no changes were written)";
        }
        
        // Website/Service records
        if (isset($result['added']) && $result['added'] > 0) {
            $output[] = "Services Added: " . $result['added'];
        }
        if (isset($result['updated']) && $result['updated'] > 0) {
            $output[] = "Services Updated: " . $result['updated'];
        }
        
        // Hosting/Client records
        if (isset($result['hosting_created']) && $result['hosting_created'] > 0) {
            $output[] = "Clients Created: " . $result['hosting_created'];
        }
        if (isset($result['hosting_updated']) && $result['hosting_updated'] > 0) {
            $output[] = "Clients Updated: " . $result['hosting_updated'];
        }
        
        // Database operations
        if (isset($result['added_to_db']) && $result['added_to_db'] > 0) {
            $output[] = "Records Added to Database: " . $result['added_to_db'];
        }
        if (isset($result['updated_in_db']) && $result['updated_in_db'] > 0) {
            $output[] = "Records Updated in Database: " . $result['updated_in_db'];
        }
        
        // Google Sheets operations
        if (isset($result['updated_in_google']) && $result['updated_in_google'] > 0) {
            $output[] = "Records Updated in Google Sheets: " . $result['updated_in_google'];
        }
        if (isset($result['added_to_google']) && $result['added_to_google'] > 0) {
            $output[] = "Records Added to Google Sheets: " . $result['added_to_google'];
        }
        if (isset($result['skipped_in_google']) && $result['skipped_in_google'] > 0) {
            $output[] = "Records Skipped in Google Sheets (not found): " . $result['skipped_in_google'];
        }
        
        // Conflicts
        if (isset($result['conflicts_resolved']) && $result['conflicts_resolved'] > 0) {
            $output[] = "Conflicts Resolved: " . $result['conflicts_resolved'];
        }
        if (isset($result['conflicts_detected']) && $result['conflicts_detected'] > 0) {
            $output[] = "Conflicts Detected (manual review): " . $result['conflicts_detected'];
        }
        if (!empty($result['baseline_initialized'])) {
            $output[] = "Baseline initialized for safe future syncs";
        }
        
        // Errors
        if (!empty($result['errors']) && is_array($result['errors'])) {
            if (count($result['errors']) > 0) {
                $output[] = "Errors Encountered: " . count($result['errors']);
                foreach ($result['errors'] as $error) {
                    $output[] = "  • " . $error;
                }
            }
        }
        
        if (empty($output)) {
            $output[] = "Merge completed with no changes";
        }
        
        return implode("\n", $output);
    }

    // Merge data from Google Sheets or Database
    public function mergeWithGoogle()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        try {
            $mergeStrategy = $_POST['merge_strategy'] ?? 'forward';
            $conflictPolicy = strtolower(trim((string)($_POST['conflict_policy'] ?? 'manual')));
            $dryRun = isset($_POST['dry_run']) && (string)$_POST['dry_run'] === '1';

            if (!in_array($conflictPolicy, ['manual', 'prefer_db', 'prefer_google'], true)) {
                $conflictPolicy = 'manual';
            }
            
            if (!in_array($mergeStrategy, ['forward', 'backward', 'together'])) {
                throw new Exception("Invalid merge strategy");
            }

            $settings = $this->settingsModel->getGoogleSheetsSettings();

            if (empty($settings['sheet_id']) || empty($settings['credentials']) || empty($settings['sheet_name'])) {
                throw new Exception("Google Sheets configuration incomplete");
            }
            
            $result = [];
            $options = [
                'conflict_policy' => $conflictPolicy,
                'dry_run' => $dryRun,
            ];
            
            switch ($mergeStrategy) {
                case 'forward':
                    // Google Sheets → Database (three-way with baseline)
                    $result = $this->executeThreeWayMerge($settings, 'forward', $options);
                    break;
                case 'backward':
                    // Database → Google Sheets (three-way with baseline)
                    $result = $this->executeThreeWayMerge($settings, 'backward', $options);
                    break;
                case 'together':
                    // Merge both ways with conflict protection
                    $result = $this->executeThreeWayMerge($settings, 'together', $options);
                    break;
            }
            
            $_SESSION['message'] = __('settings.merge_completed') . ":\n" . $this->formatMergeResultsForDisplay($result);
            $_SESSION['merge_result'] = $result;
            header('Location: index.php?action=import_export');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Merge failed: " . $e->getMessage();
            header('Location: index.php?action=import_export');
            exit;
        }
    }

    // Helper function to get Google Sheets data with proper structure
    private function getGoogleSheetsData()
    {
        require_once APP_PATH . '/vendor/autoload.php';
        $settings = $this->settingsModel->getGoogleSheetsSettings();

        try {
            $client = $this->getGoogleClient($settings);
            $service = new \Google\Service\Sheets($client);

            // Fetch rows in chunks to avoid very large single responses.
            $rowEntries = $this->readSheetRowsInChunks($service, $settings, 1);
            if (empty($rowEntries)) {
                return [];
            }
            
            // Define consistent field mapping
            $fieldMap = [
                0 => 'server_name',      // Hosting/Client Name
                1 => 'ip_address',       // Address
                2 => 'email_address',    // Email
                3 => 'provider',         // P.IVA
                4 => 'name',             // Service Detail
                5 => 'domain',           // Domain (KEY FIELD)
                6 => 'assigned_email',   // Email Assegnata
                7 => 'proprietario',     // Proprietario
                8 => 'registrante_import', // Registrante
                9 => 'expiry_date',      // Scadenza
                10 => 'status',          // Status
                11 => 'vendita',         // Prezzo di vendita
                12 => 'dns',             // DNS
                13 => 'cpanel',          // Cpanel
                14 => 'epanel',          // Epanel
                15 => 'notes',           // Notes
                16 => 'manutenzione',    // Costo di manutenzione sito
                17 => 'remark'           // Remark
            ];

            $data = [];
            $currentHosting = null;

            foreach ($rowEntries as $entry) {
                if ((int)($entry['rowNo'] ?? 0) <= 2) {
                    continue;
                }

                $row = $entry['row'] ?? [];
                $row = array_pad($row, 18, '');
                
                // Skip completely empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $rowData = [];
                foreach ($fieldMap as $colIdx => $field) {
                    $rowData[$field] = trim($row[$colIdx] ?? '');
                }

                $rowData['service_type'] = $this->normalizeSheetServiceType($rowData['name'] ?? '');
                $rowData['expiry_date'] = sm_normalize_date($rowData['expiry_date'] ?? '', '') ?? '';

                // Track hosting for client grouping
                if (!empty($rowData['server_name'])) {
                    $currentHosting = $rowData['server_name'];
                }
                $rowData['_hosting_context'] = $currentHosting;

                $data[] = $rowData;
            }

            return $data;
        } catch (Exception $e) {
            error_log("Error fetching Google Sheets data: " . $e->getMessage());
            throw $e;
        }
    }

    private function normalizeSheetServiceType(string $raw): string
    {
        $v = strtolower(trim($raw));
        if ($v === '') {
            return 'hosting_web';
        }
        if (str_contains($v, 'mail') || str_contains($v, 'email')) {
            return 'hosting_mail';
        }
        if (str_contains($v, 'domain') || str_contains($v, 'registr') || str_contains($v, 'dominio')) {
            return 'domain';
        }
        return 'hosting_web';
    }

    private function buildSheetKey(string $domain, string $serviceType): ?string
    {
        $d = strtolower(trim($domain));
        if ($d === '') {
            return null;
        }
        return $d . '|' . $this->normalizeSheetServiceType($serviceType);
    }

    private function getSheetId(\Google\Service\Sheets $service, array $settings): ?int
    {
        try {
            $spreadsheet = $service->spreadsheets->get($settings['sheet_id'], ['includeGridData' => false]);
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() === $settings['sheet_name']) {
                    return (int)$sheet->getProperties()->getSheetId();
                }
            }
        } catch (Exception $e) {
            error_log("getSheetId error: " . $e->getMessage());
        }
        return null;
    }

    private function getSheetRowCount(\Google\Service\Sheets $service, array $settings): int
    {
        try {
            $spreadsheet = $service->spreadsheets->get($settings['sheet_id'], ['includeGridData' => false]);
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() === $settings['sheet_name']) {
                    $grid = $sheet->getProperties()->getGridProperties();
                    return (int)($grid ? $grid->getRowCount() : 0);
                }
            }
        } catch (Exception $e) {
            error_log("getSheetRowCount error: " . $e->getMessage());
        }

        return 0;
    }

    private function readSheetRowsInChunks(
        \Google\Service\Sheets $service,
        array $settings,
        int $startRow = 3,
        int $chunkSize = 2000
    ): array {
        $entries = [];
        $rowCount = $this->getSheetRowCount($service, $settings);

        if ($rowCount < $startRow || $chunkSize <= 0) {
            return $entries;
        }

        for ($chunkStart = $startRow; $chunkStart <= $rowCount; $chunkStart += $chunkSize) {
            $chunkEnd = min($rowCount, $chunkStart + $chunkSize - 1);
            $range = $settings['sheet_name'] . "!A{$chunkStart}:R{$chunkEnd}";
            $rows = $service->spreadsheets_values->get($settings['sheet_id'], $range)->getValues() ?? [];

            if (empty($rows)) {
                continue;
            }

            foreach ($rows as $idx => $row) {
                $entries[] = [
                    'rowNo' => $chunkStart + $idx,
                    'row' => $row,
                ];
            }
        }

        return $entries;
    }

    private function appendRowsToGoogleSheet(\Google\Service\Sheets $service, array $settings, array $rows): array
    {
        $result = ['appended' => 0, 'errors' => []];

        if (empty($rows)) {
            return $result;
        }

        try {
            $body = new \Google\Service\Sheets\ValueRange(['values' => $rows]);
            $params = [
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS',
            ];
            $service->spreadsheets_values->append(
                $settings['sheet_id'],
                // Anchor appends below the two header rows so row 1-2 are never overwritten.
                $settings['sheet_name'] . '!A3:R',
                $body,
                $params
            );
            $result['appended'] = count($rows);
        } catch (Exception $e) {
            $result['errors'][] = "Failed to append rows to Google Sheet: " . $e->getMessage();
        }

        return $result;
    }

    private function dbRowToSheetRow(array $dbRow): array
    {
        // Map service_type internal key → display label (matches prepareForGoogleSheets).
        $serviceType = (string)($dbRow['service_type'] ?? 'hosting_web');
        $serviceLabel = match ($this->normalizeSheetServiceType($serviceType)) {
            'domain'       => 'Domain',
            'hosting_mail' => 'Hosting Mail',
            default        => 'Hosting Web',
        };

        // Display-formatted expiry date (matches sm_format_date used in prepareForGoogleSheets).
        $expiryDisplay = sm_format_date($dbRow['expiry_date'] ?? '', '');

        // Provider / registrante name from the enriched JOIN in getDatabaseRowsByKey.
        $registranteName = trim((string)($dbRow['registrante_name'] ?? $dbRow['registrante_import'] ?? ''));

        return [
            trim((string)($dbRow['server_name']     ?? '')),  // col 0: client/server name
            trim((string)($dbRow['ip_address']      ?? '')),  // col 1: ip address
            trim((string)($dbRow['email_address']   ?? '')),  // col 2: email address
            trim((string)($dbRow['provider']        ?? '')),  // col 3: provider
            $serviceLabel,                                    // col 4: service type label
            trim((string)($dbRow['domain']          ?? '')),  // col 5: domain
            trim((string)($dbRow['assigned_email']  ?? '')),  // col 6: assigned email
            trim((string)($dbRow['proprietario']    ?? '')),  // col 7: proprietario
            $registranteName,                                 // col 8: registrante
            $expiryDisplay,                                   // col 9: expiry date (display)
            trim((string)($dbRow['status']          ?? '')),  // col 10: status
            trim((string)($dbRow['vendita']         ?? '')),  // col 11: vendita
            trim((string)($dbRow['dns']             ?? '')),  // col 12: dns
            trim((string)($dbRow['cpanel']          ?? '')),  // col 13: cpanel
            trim((string)($dbRow['epanel']          ?? '')),  // col 14: epanel
            trim((string)($dbRow['notes']           ?? '')),  // col 15: notes
            trim((string)($dbRow['manutenzione']    ?? '')),  // col 16: manutenzione
            trim((string)($dbRow['remark']          ?? '')),  // col 17: remark
        ];
    }

    /**
     * Fetch the DOMINI LIBERI email from SMTP settings (mirrors Website::getSmtpCcOrReplyToEmail).
     */
    private function getDominiLiberiEmail(): string
    {
        try {
            // Only query columns that exist in smtp_settings (cc_email, from_email).
            $stmt = $this->db->query("
                SELECT cc_email, from_email
                FROM smtp_settings ORDER BY id DESC LIMIT 1
            ");
            $row = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
            foreach (['cc_email', 'from_email'] as $field) {
                $value = trim((string)($row[$field] ?? ''));
                if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $value;
                }
            }
        } catch (Throwable $e) {
            error_log('getDominiLiberiEmail error: ' . $e->getMessage());
        }
        return '';
    }

    /**
     * Convert an array of raw DB rows (from getDatabaseRowsByKey / googleAppends) to
     * sheet row arrays, applying the same client-grouping logic as prepareForGoogleSheets:
     *  - Client name/email shown only on the FIRST row of each client group.
     *  - Unassigned rows (hosting_id = null) shown as DOMINI LIBERI with SMTP email.
     *  - Rows must already be sorted by COALESCE(h.name,'zzzzzzzz'), domain, service_type.
     */
    private function buildGroupedSheetRows(array $dbRows): array
    {
        $rows             = [];
        $lastGroupKey     = '__INIT__';
        $dominiLiberiEmail = null;

        foreach ($dbRows as $dbRow) {
            $hostingId = $dbRow['hosting_id'] ?? null;
            $groupKey  = $hostingId ? 'hosting_' . (int)$hostingId : 'unassigned';
            $isNewGroup = ($lastGroupKey !== $groupKey);
            $lastGroupKey = $groupKey;

            $clientName  = trim((string)($dbRow['server_name'] ?? ''));
            $clientEmail = '';

            if ($groupKey === 'unassigned') {
                $clientName = 'DOMINI LIBERI';
                if ($dominiLiberiEmail === null) {
                    $dominiLiberiEmail = $this->getDominiLiberiEmail();
                }
                $clientEmail = $dominiLiberiEmail;
            }

            $serviceType  = (string)($dbRow['service_type'] ?? 'hosting_web');
            $serviceLabel = match ($this->normalizeSheetServiceType($serviceType)) {
                'domain'       => 'Domain',
                'hosting_mail' => 'Hosting Mail',
                default        => 'Hosting Web',
            };

            $expiryDisplay   = sm_format_date($dbRow['expiry_date'] ?? '', '');
            $registranteName = trim((string)($dbRow['registrante_name'] ?? $dbRow['registrante_import'] ?? ''));

            $rows[] = [
                $isNewGroup ? $clientName  : '',                                               // col 0: client name (first row only)
                $isNewGroup ? trim((string)($dbRow['ip_address']    ?? '')) : '',             // col 1: address (first row of group)
                $isNewGroup ? ($clientEmail ?: trim((string)($dbRow['email_address'] ?? ''))) : '', // col 2: email (first row of group)
                $isNewGroup ? trim((string)($dbRow['provider']      ?? '')) : '',             // col 3: P.IVA/VAT (first row of group)
                $serviceLabel,                             // col 4: service type label
                trim((string)($dbRow['domain']          ?? '')),  // col 5
                trim((string)($dbRow['assigned_email']  ?? '')),  // col 6
                trim((string)($dbRow['proprietario']    ?? '')),  // col 7
                $registranteName,                                  // col 8
                $expiryDisplay,                                    // col 9
                trim((string)($dbRow['status']          ?? '')),  // col 10
                trim((string)($dbRow['vendita']         ?? '')),  // col 11
                trim((string)($dbRow['dns']             ?? '')),  // col 12
                trim((string)($dbRow['cpanel']          ?? '')),  // col 13
                trim((string)($dbRow['epanel']          ?? '')),  // col 14
                trim((string)($dbRow['notes']           ?? '')),  // col 15
                trim((string)($dbRow['manutenzione']    ?? '')),  // col 16
                trim((string)($dbRow['remark']          ?? '')),  // col 17
            ];
        }

        return $rows;
    }

    private function updateExistingGoogleRowsOnly(\Google\Service\Sheets $service, array $settings, array $websiteData): array
    {
        $result = ['updated' => 0, 'skipped_not_found' => 0, 'skipped_rows' => [], 'errors' => []];

        $sheetEntries = $this->readSheetRowsInChunks($service, $settings, 3);

        $sheetIndexByKey = [];
        $sheetRows = [];
        foreach ($sheetEntries as $entry) {
            $row = $entry['row'] ?? [];
            $row = array_pad($row, 18, '');
            $rowNo = (int)($entry['rowNo'] ?? 0);
            $sheetRows[$rowNo] = $row;

            $key = $this->buildSheetKey((string)($row[5] ?? ''), (string)($row[4] ?? ''));
            if ($key === null) {
                continue;
            }
            if (isset($sheetIndexByKey[$key])) {
                $result['errors'][] = "Duplicate key in Google Sheet for {$key} (rows {$sheetIndexByKey[$key]} and {$rowNo})";
                continue;
            }
            $sheetIndexByKey[$key] = $rowNo;
        }

        $updates = [];
        foreach ($websiteData as $dbRow) {
            $dbRow = array_pad($dbRow, 18, '');
            $key = $this->buildSheetKey((string)($dbRow[5] ?? ''), (string)($dbRow[4] ?? ''));
            if ($key === null) {
                continue;
            }

            if (!isset($sheetIndexByKey[$key])) {
                $result['skipped_not_found']++;
                $result['skipped_rows'][] = $dbRow;
                continue;
            }

            $rowNo = $sheetIndexByKey[$key];
            $current = $sheetRows[$rowNo] ?? array_fill(0, 18, '');
            $merged = $current;

            for ($i = 0; $i < 18; $i++) {
                $incoming = trim((string)($dbRow[$i] ?? ''));

                // Live sheet protection: never clear existing cell values.
                if ($incoming === '') {
                    continue;
                }

                if ((string)$merged[$i] !== $incoming) {
                    $merged[$i] = $incoming;
                }
            }

            if ($merged !== $current) {
                $updates[] = new \Google\Service\Sheets\ValueRange([
                    'range' => $settings['sheet_name'] . '!A' . $rowNo . ':R' . $rowNo,
                    'values' => [$merged],
                ]);
                $result['updated']++;
            }
        }

        if (!empty($updates)) {
            $batchBody = new \Google\Service\Sheets\BatchUpdateValuesRequest([
                'valueInputOption' => 'USER_ENTERED',
                'data' => $updates,
            ]);
            $service->spreadsheets_values->batchUpdate($settings['sheet_id'], $batchBody);
        }

        return $result;
    }

    // Compare two datasets and return differences
    private function compareDatasets($dbData, $googleData)
    {
        $comparison = [
            'only_in_db' => [],
            'only_in_google' => [],
            'different_values' => [],
            'summary' => [
                'db_total' => count($dbData),
                'google_total' => count($googleData),
                'matches' => 0,
                'conflicts' => 0
            ]
        ];

        // Map database services (domain + service_type) for quick lookup
        $dbByKey = [];
        foreach ($dbData as $item) {
            $key = $this->buildSheetKey((string)($item['domain'] ?? ''), (string)($item['service_type'] ?? 'hosting_web'));
            if ($key !== null) {
                $item['service_type'] = $this->normalizeSheetServiceType((string)($item['service_type'] ?? ''));
                $dbByKey[$key] = $item;
            }
        }

        // Map Google services
        $googleByKey = [];
        foreach ($googleData as $item) {
            $key = $this->buildSheetKey((string)($item['domain'] ?? ''), (string)($item['service_type'] ?? ($item['name'] ?? '')));
            if ($key !== null) {
                $item['service_type'] = $this->normalizeSheetServiceType((string)($item['service_type'] ?? ($item['name'] ?? '')));
                $googleByKey[$key] = $item;
            }
        }

        // Define fields to compare
        $compareFields = [
            'name', 'assigned_email', 'proprietario', 'vendita', 
            'expiry_date', 'cpanel', 'epanel', 'notes', 'remark', 'dns'
        ];

        // Find differences
        foreach ($dbByKey as $key => $dbItem) {
            if (isset($googleByKey[$key])) {
                $comparison['summary']['matches']++;
                
                // Check for value differences
                $diffs = [];
                foreach ($compareFields as $field) {
                    $dbVal = trim($dbItem[$field] ?? '');
                    $googleVal = trim($googleByKey[$key][$field] ?? '');

                    if ($field === 'expiry_date') {
                        $dbVal = sm_normalize_date($dbVal, '') ?? '';
                        $googleVal = sm_normalize_date($googleVal, '') ?? '';
                    }
                    
                    if ($dbVal !== $googleVal && (!empty($dbVal) || !empty($googleVal))) {
                        $diffs[$field] = [
                            'db_value' => $dbVal,
                            'google_value' => $googleVal
                        ];
                    }
                }
                
                if (!empty($diffs)) {
                    $comparison['different_values'][] = [
                        'key' => $key,
                        'domain' => $dbItem['domain'] ?? '',
                        'service_type' => $dbItem['service_type'] ?? '',
                        'db_values' => $dbItem,
                        'google_values' => $googleByKey[$key],
                        'differences' => $diffs
                    ];
                    $comparison['summary']['conflicts']++;
                }
                unset($googleByKey[$key]);
            } else {
                $comparison['only_in_db'][] = $dbItem;
            }
        }

        // Remaining items only in Google
        foreach ($googleByKey as $item) {
            $comparison['only_in_google'][] = $item;
        }

        return $comparison;
    }

    // -----------------------------------------------------------------------
    // Provider + hosting-account resolution helpers (shared by all import paths)
    // -----------------------------------------------------------------------

    /**
     * Infer the providers.type enum value from the normalised website service_type.
     */
    private function inferProviderType(string $serviceType): string
    {
        return match ($serviceType) {
            'domain'       => 'registrar',
            'hosting_mail' => 'email',
            'hosting_web'  => 'whm',
            default        => 'other',
        };
    }

    /**
     * Look up a provider by (name_key, type), creating it if absent.
     * name_key is LOWER(REPLACE(name,' ','')) stored as a generated column, so
     * "Serverplan", "Server Plan", "server plan" all resolve to the same record.
     * Lookup key is (name_key + type) so "Aruba" as registrar vs WHM = two records.
     *
     * @param string $name        Registrante name from the sheet (raw)
     * @param string $serviceType Normalised service_type
     * @param array  &$cache      In-memory cache — pass the same array across calls in one run
     * @return int|null           providers.id, or null when $name is blank
     */
    private function resolveOrCreateProvider(string $name, string $serviceType, array &$cache): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $type          = $this->inferProviderType($serviceType);
        // Normalised form used for lookup and cache key: lowercase, no spaces.
        $nameKey       = strtolower(preg_replace('/\\s+/', '', $name));
        // Canonical display form: ucwords with single spaces.
        $nameCanonical = ucwords(strtolower(trim(preg_replace('/\\s+/', ' ', $name))));
        // Enforce DB column length.
        $nameCanonical = mb_substr($nameCanonical, 0, 100);

        $cacheKey = $nameKey . '|' . $type;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        // Use the generated column name_key for the lookup (see migration 035).
        $stmt = $this->db->prepare(
            "SELECT id FROM providers WHERE name_key = ? AND type = ? LIMIT 1"
        );
        $stmt->execute([$nameKey, $type]);
        $id = $stmt->fetchColumn() ?: null;

        if (!$id) {
            try {
                $ins = $this->db->prepare("INSERT INTO providers (name, type) VALUES (?, ?)");
                $ins->execute([$nameCanonical, $type]);
                $id = (int)$this->db->lastInsertId();
            } catch (\PDOException $e) {
                // Race condition: another process inserted simultaneously — re-select.
                $stmt->execute([$nameKey, $type]);
                $id = $stmt->fetchColumn() ?: null;
                if (!$id) {
                    error_log("resolveOrCreateProvider failed for '{$nameCanonical}': " . $e->getMessage());
                    return null;
                }
            }
        }

        $cache[$cacheKey] = (int)$id;
        return (int)$id;
    }

    /**
     * Look up a hosting_account by (client_id, provider_id, cpanel_username), creating it if absent.
     * Using provider_id in the key means the same cPanel username can have separate accounts at
     * different providers (e.g. hosting_web at Aruba WHM, hosting_mail at Serverplan email).
     * When $cpanelUsername is blank the client name is used as the cPanel username.
     * Only called for hosting_web / hosting_mail (not domain) rows.
     * Expiry priority on existing records: hosting_web always overwrites; hosting_mail only if NULL.
     *
     * @param int    $clientId       hosting.id
     * @param int    $providerId     providers.id
     * @param string $cpanelUsername Col N value (may be blank)
     * @param string $fallbackName   Client name, used when $cpanelUsername is blank
     * @param string $expiryDate     YYYY-MM-DD or ''
     * @param string $serviceType    Normalised service_type (for expiry update priority)
     * @param array  &$cache         In-memory cache keyed by "clientId|providerId|cpanelUsername"
     * @return int|null              hosting_accounts.id, or null when client/provider is unknown
     */
    private function resolveOrCreateHostingAccount(
        int    $clientId,
        int    $providerId,
        string $cpanelUsername,
        string $fallbackName,
        string $packageName,
        string $expiryDate,
        string $serviceType,
        array  &$cache
    ): ?int {
        $username = $cpanelUsername !== '' ? $cpanelUsername : $fallbackName;
        if ($username === '' || $clientId <= 0 || $providerId <= 0) {
            return null;
        }

        // Truncate to match varchar(100) column limit
        $packageNameSafe = mb_substr($packageName, 0, 100);

        $cacheKey = $clientId . '|' . $providerId . '|' . $username;

        if (isset($cache[$cacheKey])) {
            // Still update expiry for hosting_web rows even on a cache hit.
            if ($serviceType === 'hosting_web' && $expiryDate !== '') {
                $upd = $this->db->prepare(
                    "UPDATE hosting_accounts SET expiry_date = ? WHERE id = ?"
                );
                $upd->execute([$expiryDate, $cache[$cacheKey]]);
            }
            return $cache[$cacheKey];
        }

        $stmt = $this->db->prepare(
            "SELECT id FROM hosting_accounts
             WHERE client_id = ? AND provider_id = ? AND cpanel_username = ?
             LIMIT 1"
        );
        $stmt->execute([$clientId, $providerId, $username]);
        $id = $stmt->fetchColumn() ?: null;

        if (!$id) {
            $expiry = $expiryDate !== '' ? $expiryDate : null;
            $ins = $this->db->prepare("
                INSERT INTO hosting_accounts (client_id, provider_id, cpanel_username, package_name, expiry_date, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $ins->execute([$clientId, $providerId, $username, ($packageNameSafe !== '' ? $packageNameSafe : null), $expiry]);
            $id = (int)$this->db->lastInsertId();
        } else {
            if ($packageNameSafe !== '') {
                $updPackage = $this->db->prepare(
                    "UPDATE hosting_accounts SET package_name = ? WHERE id = ? AND (package_name IS NULL OR package_name = '')"
                );
                $updPackage->execute([$packageNameSafe, $id]);
            }

            // Update expiry with priority:
            //   hosting_web  → always overwrite
            //   hosting_mail → only if expiry not yet set
            if ($expiryDate !== '') {
                if ($serviceType === 'hosting_web') {
                    $upd = $this->db->prepare(
                        "UPDATE hosting_accounts SET expiry_date = ? WHERE id = ?"
                    );
                    $upd->execute([$expiryDate, $id]);
                } elseif ($serviceType === 'hosting_mail') {
                    $upd = $this->db->prepare(
                        "UPDATE hosting_accounts SET expiry_date = ? WHERE id = ? AND expiry_date IS NULL"
                    );
                    $upd->execute([$expiryDate, $id]);
                }
            }
        }

        $cache[$cacheKey] = (int)$id;
        return (int)$id;
    }

    // -----------------------------------------------------------------------

    // Merge Google Sheets to Database (forward) - with hosting assignment
    private function mergeGoogleToDatabase($googleData)
    {
        $result = ['added' => 0, 'updated' => 0, 'hosting_created' => 0, 'hosting_updated' => 0, 'errors' => []];
        $currentHostingId = null;
        $hostingMap       = [];
        $providerCache    = [];   // keyed by "name|type"
        $haCache          = [];   // hosting_account cache keyed by "clientId|cpanelUsername"

        foreach ($googleData as $index => $item) {
            try {
                // Extract client data from Google Sheets structure
                // Field mapping: server_name, ip_address, email_address, provider
                $clientName = trim($item['server_name'] ?? '');
                $clientAddress = trim($item['ip_address'] ?? '');
                $clientEmail = trim($item['email_address'] ?? '');
                $clientPiva = trim($item['provider'] ?? '');

                // Treat DOMINI LIBERI as unassigned group (no hosting plan create/update).
                $isDominiLiberi = strtoupper($clientName) === 'DOMINI LIBERI';

                // Process hosting plan if client name exists and different from previous
                if (!empty($clientName) && !$isDominiLiberi && $clientName !== ($hostingMap['last_client'] ?? null)) {
                    // Look up the client in the `hosting` table (websites.hosting_id → hosting.id).
                    $stmt = $this->db->prepare("SELECT id FROM hosting WHERE name = ?");
                    $stmt->execute([$clientName]);
                    $currentHostingId = $stmt->fetchColumn() ?: null;

                    if (!$currentHostingId) {
                        // Create new hosting/client record including contact info.
                        $ins = $this->db->prepare("
                            INSERT INTO hosting (name, email_address, address, vat_number, status)
                            VALUES (?, ?, ?, ?, 'active')
                        ");
                        $ins->execute([$clientName, $clientEmail, $clientAddress, $clientPiva]);
                        $currentHostingId = (int)$this->db->lastInsertId();
                        $result['hosting_created']++;
                    } else {
                        // Update contact fields on existing client.
                        $upd = $this->db->prepare("
                            UPDATE hosting SET email_address = ?, address = ?, vat_number = ?
                            WHERE id = ?
                        ");
                        $upd->execute([$clientEmail, $clientAddress, $clientPiva, $currentHostingId]);
                        $result['hosting_updated']++;
                    }
                    $hostingMap['last_client'] = $clientName;
                } elseif ($isDominiLiberi) {
                    $currentHostingId = null;
                }

                // Extract domain
                $domain = trim($item['domain'] ?? '');
                if (empty($domain)) {
                    continue;
                }

                $rawServiceType = strtolower(trim((string)($item['service_type'] ?? $item['name'] ?? '')));
                if (str_contains($rawServiceType, 'mail') || str_contains($rawServiceType, 'email')) {
                    $serviceType = 'hosting_mail';
                } elseif (str_contains($rawServiceType, 'domain') || str_contains($rawServiceType, 'registr')) {
                    $serviceType = 'domain';
                } else {
                    $serviceType = 'hosting_web';
                }

                // Resolve / create provider from the registrante column.
                $registranteName = trim((string)($item['registrante_import'] ?? ''));
                $providerId = $this->resolveOrCreateProvider($registranteName, $serviceType, $providerCache);

                // Resolve / create hosting account for web and mail services.
                $hostingAccountId = null;
                if ($currentHostingId && $serviceType !== 'domain' && $providerId) {
                    $cpanelUsername   = trim((string)($item['cpanel'] ?? ''));
                    $expiryNormalized = $this->normalizeSyncValue('expiry_date', $item['expiry_date'] ?? '');
                    $hostingAccountId = $this->resolveOrCreateHostingAccount(
                        $currentHostingId,
                        $providerId,
                        $cpanelUsername,
                        $clientName,
                        trim((string)($item['domain'] ?? '')),
                        $expiryNormalized,
                        $serviceType,
                        $haCache
                    );
                }

                // Try to find existing website by domain + service type
                $website = $this->websiteModel->getWebsiteByDomain($domain, $serviceType);

                // Prepare update data with all Google values
                $data = [
                    'domain'             => $domain,
                    'name'               => trim($item['name'] ?? ''),
                    'service_type'       => $serviceType,
                    'assigned_email'     => trim($item['assigned_email'] ?? ''),
                    'proprietario'       => trim($item['proprietario'] ?? ''),
                    'vendita'            => trim($item['vendita'] ?? '') ?: '0',
                    'expiry_date'        => trim($item['expiry_date'] ?? ''),
                    'cpanel'             => trim($item['cpanel'] ?? ''),
                    'epanel'             => trim($item['epanel'] ?? ''),
                    'notes'              => trim($item['notes'] ?? ''),
                    'manutenzione'       => trim($item['manutenzione'] ?? ''),
                    'remark'             => trim($item['remark'] ?? ''),
                    'dns'                => trim($item['dns'] ?? ''),
                    'registrante_import' => $registranteName,
                    'hosting_id'         => $currentHostingId,
                    'provider_id'        => $providerId,
                    'hosting_account_id' => $hostingAccountId,
                ];

                if ($website) {
                    // Update existing record
                    if ($this->websiteModel->updateWebsite($website['id'], $data)) {
                        $result['updated']++;
                    } else {
                        $result['errors'][] = "Failed to update {$domain}";
                    }
                } else {
                    // Create new record
                    if ($this->websiteModel->createWebsite($data)) {
                        $result['added']++;
                    } else {
                        $result['errors'][] = "Failed to create {$domain}";
                    }
                }
            } catch (Exception $e) {
                $result['errors'][] = "Error processing " . ($item['domain'] ?? 'unknown') . ": " . $e->getMessage();
            }
        }

        return $result;
    }

    // Merge Database to Google Sheets (backward)
    private function mergeDatabaseToGoogle($dbData, $settings)
    {
        require_once APP_PATH . '/vendor/autoload.php';
        $result = ['updated' => 0, 'skipped_not_found' => 0, 'errors' => []];

        try {
            $client = $this->getGoogleClient($settings);
            $service = new \Google\Service\Sheets($client);
            $preparedData = $this->websiteModel->prepareForGoogleSheets();
            $websiteData = $preparedData['data'] ?? [];
            $result = $this->updateExistingGoogleRowsOnly($service, $settings, $websiteData);
        } catch (Exception $e) {
            $result['errors'][] = "Error during merge: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Public entrypoint used by cron to run a safe bidirectional sync.
     */
    public function runGoogleSheetsSafeSyncForCron(): array
    {
        $settings = $this->settingsModel->getGoogleSheetsSettings();
        return $this->executeThreeWayMerge($settings, 'together', [
            'conflict_policy' => 'manual',
            'dry_run' => false,
        ]);
    }

    private function getThreeWaySyncFields(): array
    {
        // All fields that exist in `websites` table and are shown in the Google Sheet.
        // Must match exactly what dbRowToSheetRow writes (col indices 4-17).
        return [
            'assigned_email',
            'proprietario',
            'vendita',
            'expiry_date',
            'status',
            'cpanel',
            'epanel',
            'notes',
            'remark',
            'dns',
            'manutenzione',
            'registrante_import',
        ];
        // Note: 'name' (col 4) is the service-type label derived from service_type;
        // it is not a separate DB column — service_type itself is the sync key and
        // already part of the row identity, so it is not in this list.
    }

    private function getGoogleSheetColumnMap(): array
    {
        return [
            'name' => 4,
            'domain' => 5,
            'assigned_email' => 6,
            'proprietario' => 7,
            'registrante_import' => 8,
            'expiry_date' => 9,
            'status' => 10,
            'vendita' => 11,
            'dns' => 12,
            'cpanel' => 13,
            'epanel' => 14,
            'notes' => 15,
            'manutenzione' => 16,
            'remark' => 17,
        ];
    }

    private function normalizeSyncValue(string $field, $value): string
    {
        $normalized = trim((string)($value ?? ''));
        if ($field === 'expiry_date') {
            return sm_normalize_date($normalized, '') ?? '';
        }
        return $normalized;
    }

    private function ensureGoogleSyncBaselineTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS google_sheets_sync_baseline (
                sync_key VARCHAR(255) PRIMARY KEY,
                snapshot_json LONGTEXT NOT NULL,
                synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function loadGoogleSyncBaseline(): array
    {
        $this->ensureGoogleSyncBaselineTable();

        $stmt = $this->db->query("SELECT sync_key, snapshot_json FROM google_sheets_sync_baseline");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $baseline = [];
        foreach ($rows as $row) {
            $decoded = json_decode($row['snapshot_json'] ?? '[]', true);
            if (is_array($decoded)) {
                $baseline[$row['sync_key']] = $decoded;
            }
        }

        return $baseline;
    }

    private function saveGoogleSyncBaseline(array $baseline): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO google_sheets_sync_baseline (sync_key, snapshot_json, synced_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                snapshot_json = VALUES(snapshot_json),
                synced_at = NOW()
        ");

        foreach ($baseline as $key => $snapshot) {
            $stmt->execute([$key, json_encode($snapshot)]);
        }
    }

    private function ensureGoogleSyncAuditTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS google_sheets_sync_audit (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                user_id BIGINT NULL,
                direction VARCHAR(16) NOT NULL,
                conflict_policy VARCHAR(16) NOT NULL,
                dry_run TINYINT(1) NOT NULL DEFAULT 0,
                added_to_db INT NOT NULL DEFAULT 0,
                updated_in_db INT NOT NULL DEFAULT 0,
                added_to_google INT NOT NULL DEFAULT 0,
                updated_in_google INT NOT NULL DEFAULT 0,
                conflicts_detected INT NOT NULL DEFAULT 0,
                conflicts_resolved INT NOT NULL DEFAULT 0,
                error_count INT NOT NULL DEFAULT 0,
                result_json LONGTEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function logGoogleSyncAudit(string $direction, string $conflictPolicy, bool $dryRun, array $result): void
    {
        try {
            $this->ensureGoogleSyncAuditTable();
            $stmt = $this->db->prepare(
                "INSERT INTO google_sheets_sync_audit
                (user_id, direction, conflict_policy, dry_run, added_to_db, updated_in_db, added_to_google, updated_in_google,
                 conflicts_detected, conflicts_resolved, error_count, result_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $stmt->execute([
                $userId,
                $direction,
                $conflictPolicy,
                $dryRun ? 1 : 0,
                (int)($result['added_to_db'] ?? 0),
                (int)($result['updated_in_db'] ?? 0),
                (int)($result['added_to_google'] ?? 0),
                (int)($result['updated_in_google'] ?? 0),
                (int)($result['conflicts_detected'] ?? 0),
                (int)($result['conflicts_resolved'] ?? 0),
                is_array($result['errors'] ?? null) ? count($result['errors']) : 0,
                json_encode($result),
            ]);
        } catch (Throwable $e) {
            error_log('Failed to log Google sync audit: ' . $e->getMessage());
        }
    }

    private function getDatabaseRowsByKey(): array
    {
        // Use the same JOIN as prepareForGoogleSheets so dbRowToSheetRow has all columns.
        // Sort mirrors prepareForGoogleSheets: clients alphabetically, DOMINI LIBERI last,
        // then domain, then service_type — so googleAppends preserve the export grouping.
        $stmt = $this->db->query("
            SELECT w.*,
                   h.id            AS hosting_plan_id,
                   h.name          AS server_name,
                   h.email_address AS email_address,
                   h.address       AS ip_address,
                   h.vat_number    AS provider,
                   p.name          AS registrante_name
            FROM websites w
            LEFT JOIN hosting   h ON h.id = w.hosting_id
            LEFT JOIN providers p ON p.id = w.provider_id
            ORDER BY COALESCE(h.name, 'zzzzzzzz') ASC, w.domain ASC, w.service_type ASC
        ");
        $dbData = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $rowsByKey = [];

        foreach ($dbData as $row) {
            $row['service_type'] = $this->normalizeSheetServiceType((string)($row['service_type'] ?? 'hosting_web'));
            // Keep the raw expiry for sync comparison; display formatting happens in dbRowToSheetRow.
            $row['expiry_date'] = $this->normalizeSyncValue('expiry_date', $row['expiry_date'] ?? '');

            $key = $this->buildSheetKey((string)($row['domain'] ?? ''), (string)($row['service_type'] ?? 'hosting_web'));
            if ($key === null) {
                continue;
            }
            $rowsByKey[$key] = $row;
        }

        return $rowsByKey;
    }

    private function getGoogleRowsByKeyIndexed(\Google\Service\Sheets $service, array $settings): array
    {
        $sheetEntries = $this->readSheetRowsInChunks($service, $settings, 3);

        $indexed = [];
        $errors = [];
        $lastServerName   = ''; // tracks client group across blank col-0 rows
        $lastIpAddress    = ''; // col 1 — blank after first row of each group
        $lastEmailAddress = ''; // col 2 — blank after first row of each group
        $lastVatNumber    = ''; // col 3 (P.IVA) — blank after first row of each group

        foreach ($sheetEntries as $entry) {
            $rawRow = $entry['row'] ?? [];
            $rawRow = array_pad($rawRow, 18, '');
            if (empty(array_filter($rawRow, static fn($v) => trim((string)$v) !== ''))) {
                continue;
            }

            $rawServerName = trim((string)($rawRow[0] ?? ''));
            // When col 0 is populated we are on the first row of a new client group —
            // capture all four client-level fields and propagate them to subsequent rows.
            if ($rawServerName !== '') {
                $lastServerName   = $rawServerName;
                $lastIpAddress    = trim((string)($rawRow[1] ?? ''));
                $lastEmailAddress = trim((string)($rawRow[2] ?? ''));
                $lastVatNumber    = trim((string)($rawRow[3] ?? ''));
            }

            $mapped = [
                'server_name'  => $lastServerName,   // propagated from group's first row
                'ip_address'   => $lastIpAddress,    // propagated from group's first row
                'email_address' => $lastEmailAddress, // propagated from group's first row
                'provider'     => $lastVatNumber,    // propagated (P.IVA) from group's first row
                'name' => trim((string)($rawRow[4] ?? '')),
                'domain' => trim((string)($rawRow[5] ?? '')),
                'assigned_email' => trim((string)($rawRow[6] ?? '')),
                'proprietario' => trim((string)($rawRow[7] ?? '')),
                'registrante_import' => trim((string)($rawRow[8] ?? '')),
                'expiry_date' => $this->normalizeSyncValue('expiry_date', $rawRow[9] ?? ''),
                'status' => trim((string)($rawRow[10] ?? '')),
                'vendita' => trim((string)($rawRow[11] ?? '')),
                'dns' => trim((string)($rawRow[12] ?? '')),
                'cpanel' => trim((string)($rawRow[13] ?? '')),
                'epanel' => trim((string)($rawRow[14] ?? '')),
                'notes' => trim((string)($rawRow[15] ?? '')),
                'manutenzione' => trim((string)($rawRow[16] ?? '')),
                'remark' => trim((string)($rawRow[17] ?? '')),
            ];

            $mapped['service_type'] = $this->normalizeSheetServiceType((string)($mapped['name'] ?? ''));
            $key = $this->buildSheetKey((string)($mapped['domain'] ?? ''), (string)($mapped['service_type'] ?? 'hosting_web'));
            if ($key === null) {
                continue;
            }

            $rowNo = (int)($entry['rowNo'] ?? 0);
            if (isset($indexed[$key])) {
                $errors[] = "Duplicate key in Google Sheet for {$key} (rows {$indexed[$key]['rowNo']} and {$rowNo})";
                continue;
            }

            $indexed[$key] = [
                'rowNo' => $rowNo,
                'raw' => $rawRow,
                'mapped' => $mapped,
            ];
        }

        return ['rows' => $indexed, 'errors' => $errors];
    }

    private function extractComparableSnapshot(array $row): array
    {
        $snapshot = [
            'domain' => trim((string)($row['domain'] ?? '')),
            'service_type' => $this->normalizeSheetServiceType((string)($row['service_type'] ?? 'hosting_web')),
        ];

        foreach ($this->getThreeWaySyncFields() as $field) {
            $snapshot[$field] = $this->normalizeSyncValue($field, $row[$field] ?? '');
        }

        return $snapshot;
    }

    private function applyDatabasePatches(array $dbRowsByKey, array $dbPatches, array $dbCreates): array
    {
        $result        = ['added_to_db' => 0, 'updated_in_db' => 0, 'errors' => []];
        $providerCache = [];  // shared across both loops
        $haCache       = [];  // hosting_account cache

        foreach ($dbPatches as $key => $patches) {
            if (!isset($dbRowsByKey[$key])) {
                continue;
            }

            $existing = $dbRowsByKey[$key];
            $websiteId = (int)($existing['id'] ?? 0);
            if ($websiteId <= 0) {
                continue;
            }

            foreach ($patches as $field => $value) {
                $existing[$field] = $value;
            }

            // Only refresh provider/hosting-account when a relevant field was actually patched.
            $relevantPatchFields = ['registrante_import', 'cpanel', 'expiry_date', 'service_type'];
            if (!empty(array_intersect(array_keys($patches), $relevantPatchFields))) {
                $pRegistrante = trim((string)($existing['registrante_import'] ?? ''));
                $pServiceType = $this->normalizeSheetServiceType((string)($existing['service_type'] ?? 'hosting_web'));
                if ($pRegistrante !== '') {
                    $existing['provider_id'] = $this->resolveOrCreateProvider($pRegistrante, $pServiceType, $providerCache);
                    $pHostingId = (int)($existing['hosting_id'] ?? 0);
                    if ($pHostingId > 0 && $pServiceType !== 'domain' && $existing['provider_id']) {
                        $pCpanel     = trim((string)($existing['cpanel'] ?? ''));
                        $pExpiry     = $this->normalizeSyncValue('expiry_date', $existing['expiry_date'] ?? '');
                        $pClientName = trim((string)($existing['server_name'] ?? ''));
                        $existing['hosting_account_id'] = $this->resolveOrCreateHostingAccount(
                            $pHostingId,
                            $existing['provider_id'],
                            $pCpanel,
                            $pClientName,
                            trim((string)($existing['domain'] ?? '')),
                            $pExpiry,
                            $pServiceType,
                            $haCache
                        );
                    }
                }
            }

            if ($this->websiteModel->updateWebsite($websiteId, $existing)) {
                $result['updated_in_db']++;
            } else {
                $result['errors'][] = "Failed to update database row for {$key}";
            }
        }

        foreach ($dbCreates as $key => $row) {
            $serverName = trim((string)($row['server_name'] ?? ''));
            $isDominiLiberi = strtoupper($serverName) === 'DOMINI LIBERI';

            // Resolve hosting_id from the client name (propagated by getGoogleRowsByKeyIndexed).
            $hostingId = null;
            if ($serverName !== '' && !$isDominiLiberi) {
                try {
                    $hStmt = $this->db->prepare("SELECT id FROM hosting WHERE name = ?");
                    $hStmt->execute([$serverName]);
                    $hostingId = $hStmt->fetchColumn() ?: null;

                    $clientEmail   = trim((string)($row['email_address'] ?? ''));
                    $clientAddress = trim((string)($row['ip_address']    ?? ''));
                    $clientVat     = trim((string)($row['provider']      ?? ''));

                    if (!$hostingId) {
                        // Client exists in sheet but not yet in DB — create it.
                        $hIns = $this->db->prepare("
                            INSERT INTO hosting (name, email_address, address, vat_number, status)
                            VALUES (?, ?, ?, ?, 'active')
                        ");
                        $hIns->execute([$serverName, $clientEmail, $clientAddress, $clientVat]);
                        $hostingId = (int)$this->db->lastInsertId() ?: null;
                    } else {
                        // Update contact info on existing client record.
                        $hUpd = $this->db->prepare("
                            UPDATE hosting SET email_address = ?, address = ?, vat_number = ?
                            WHERE id = ?
                        ");
                        $hUpd->execute([$clientEmail, $clientAddress, $clientVat, $hostingId]);
                    }
                } catch (Throwable $e) {
                    error_log("applyDatabasePatches hosting lookup error for '{$serverName}': " . $e->getMessage());
                }
            }

            $insert = [
                'hosting_id'         => $hostingId,
                'domain'             => trim((string)($row['domain'] ?? '')),
                'service_type'       => $this->normalizeSheetServiceType((string)($row['service_type'] ?? 'hosting_web')),
                'assigned_email'     => trim((string)($row['assigned_email'] ?? '')),
                'proprietario'       => trim((string)($row['proprietario'] ?? '')),
                'vendita'            => trim((string)($row['vendita'] ?? '')),
                'expiry_date'        => $this->normalizeSyncValue('expiry_date', $row['expiry_date'] ?? ''),
                'status'             => trim((string)($row['status'] ?? 'active')) ?: 'active',
                'cpanel'             => trim((string)($row['cpanel'] ?? '')),
                'epanel'             => trim((string)($row['epanel'] ?? '')),
                'notes'              => trim((string)($row['notes'] ?? '')),
                'manutenzione'       => trim((string)($row['manutenzione'] ?? '')),
                'remark'             => trim((string)($row['remark'] ?? '')),
                'dns'                => trim((string)($row['dns'] ?? '')),
                'registrante_import' => trim((string)($row['registrante_import'] ?? '')),
            ];

            if ($insert['domain'] === '') {
                continue;
            }

            // Resolve / create provider and hosting account for this new row.
            $cRegistrante = $insert['registrante_import'];
            $cServiceType = $insert['service_type'];
            $insert['provider_id'] = $this->resolveOrCreateProvider($cRegistrante, $cServiceType, $providerCache);

            $insert['hosting_account_id'] = null;
            if ($hostingId && $cServiceType !== 'domain' && $insert['provider_id']) {
                $insert['hosting_account_id'] = $this->resolveOrCreateHostingAccount(
                    $hostingId,
                    $insert['provider_id'],
                    $insert['cpanel'],
                    $serverName,
                    trim((string)($insert['domain'] ?? '')),
                    $insert['expiry_date'],
                    $cServiceType,
                    $haCache
                );
            }

            if ($this->websiteModel->createWebsite($insert)) {
                $result['added_to_db']++;
            } else {
                $result['errors'][] = "Failed to create database row for {$key}";
            }
        }

        return $result;
    }

    private function applyGooglePatches(\Google\Service\Sheets $service, array $settings, array $googleRowsByKey, array $googlePatches): array
    {
        $result = ['updated_in_google' => 0, 'skipped_in_google' => 0, 'errors' => []];

        if (empty($googlePatches)) {
            return $result;
        }

        $columnMap = $this->getGoogleSheetColumnMap();
        $updates = [];

        foreach ($googlePatches as $key => $patches) {
            if (!isset($googleRowsByKey[$key])) {
                $result['skipped_in_google']++;
                continue;
            }

            $entry = $googleRowsByKey[$key];
            $rowNo = (int)$entry['rowNo'];
            $raw = array_pad($entry['raw'], 18, '');
            $changed = false;

            foreach ($patches as $field => $value) {
                if (!isset($columnMap[$field])) {
                    continue;
                }

                $colIdx = $columnMap[$field];
                // expiry_date must be written in display format (DD-MM-YYYY) to match
                // the format used by export/dbRowToSheetRow. normalizeSyncValue returns
                // YYYY-MM-DD which is only for internal baseline comparisons.
                if ($field === 'expiry_date') {
                    $newValue = sm_format_date($this->normalizeSyncValue($field, $value), '');
                } else {
                    $newValue = $this->normalizeSyncValue($field, $value);
                }

                // Live sheet protection: do not clear populated cells during automated merge.
                if ($newValue === '' && trim((string)($raw[$colIdx] ?? '')) !== '') {
                    continue;
                }

                if ((string)($raw[$colIdx] ?? '') !== $newValue) {
                    $raw[$colIdx] = $newValue;
                    $changed = true;
                }
            }

            if ($changed) {
                $updates[] = new \Google\Service\Sheets\ValueRange([
                    'range' => $settings['sheet_name'] . '!A' . $rowNo . ':R' . $rowNo,
                    'values' => [$raw],
                ]);
                $result['updated_in_google']++;
            }
        }

        if (!empty($updates)) {
            $batchBody = new \Google\Service\Sheets\BatchUpdateValuesRequest([
                'valueInputOption' => 'USER_ENTERED',
                'data' => $updates,
            ]);
            $service->spreadsheets_values->batchUpdate($settings['sheet_id'], $batchBody);
        }

        return $result;
    }

    private function acquireGoogleSyncLock(string $lockName, int $timeoutSeconds = 10): bool
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, ?)');
        $stmt->execute([$lockName, $timeoutSeconds]);
        return (int)$stmt->fetchColumn() === 1;
    }

    private function releaseGoogleSyncLock(string $lockName): void
    {
        try {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lockName]);
        } catch (Throwable $e) {
            error_log('Failed to release Google sync lock: ' . $e->getMessage());
        }
    }

    private function executeThreeWayMerge(array $settings, string $direction = 'together', array $options = []): array
    {
        require_once APP_PATH . '/vendor/autoload.php';

        if (!in_array($direction, ['forward', 'backward', 'together'], true)) {
            throw new InvalidArgumentException('Invalid three-way merge direction');
        }

        $result = [
            'added_to_db' => 0,
            'updated_in_db' => 0,
            'updated_in_google' => 0,
            'added_to_google' => 0,
            'skipped_in_google' => 0,
            'conflicts_resolved' => 0,
            'conflicts_detected' => 0,
            'baseline_initialized' => false,
            'errors' => [],
        ];

        $conflictPolicy = strtolower(trim((string)($options['conflict_policy'] ?? 'manual')));
        if (!in_array($conflictPolicy, ['manual', 'prefer_db', 'prefer_google'], true)) {
            $conflictPolicy = 'manual';
        }
        $dryRun = !empty($options['dry_run']);
        $result['conflict_policy'] = $conflictPolicy;
        $result['dry_run'] = $dryRun;

        $lockKey = 'google_sync:' . ($settings['sheet_id'] ?? 'unknown') . ':' . ($settings['sheet_name'] ?? 'sheet');
        $lockAcquired = false;
        $preEntitySnapshot = [];

        try {
            $lockAcquired = $this->acquireGoogleSyncLock($lockKey, 15);
            if (!$lockAcquired) {
                throw new Exception('Another Google sync is already running. Please retry in a few seconds.');
            }

            // Ensure transactional support tables exist before opening any DB transaction.
            $this->ensureGoogleSyncBaselineTable();

            $baseline = $this->loadGoogleSyncBaseline();
            $baselineWasEmpty = empty($baseline);

            $client = $this->getGoogleClient($settings);
            $service = new \Google\Service\Sheets($client);

            $dbRowsByKey = $this->getDatabaseRowsByKey();
            // Full pre-sync DB snapshot so rollback can restore websites + clients + providers + hosting accounts.
            $preEntitySnapshot = $this->captureRollbackEntitySnapshot();
            $googleIndex = $this->getGoogleRowsByKeyIndexed($service, $settings);
            $googleRowsByKey = $googleIndex['rows'];
            if (!empty($googleIndex['errors'])) {
                $result['errors'] = array_merge($result['errors'], $googleIndex['errors']);
            }

            $allKeys = array_values(array_unique(array_merge(
                array_keys($dbRowsByKey),
                array_keys($googleRowsByKey),
                array_keys($baseline)
            )));

            $fields = $this->getThreeWaySyncFields();
            $dbPatches = [];
            $dbCreates = [];
            $googlePatches = [];
            $googleAppends = [];
            $nextBaseline = $baseline;

            foreach ($allKeys as $key) {
                $dbRow = $dbRowsByKey[$key] ?? null;
                $googleRow = $googleRowsByKey[$key]['mapped'] ?? null;
                $baseRow = $baseline[$key] ?? null;
                $resolvedSnapshot = [
                    'domain' => trim((string)($dbRow['domain'] ?? $googleRow['domain'] ?? '')),
                    'service_type' => $this->normalizeSheetServiceType((string)($dbRow['service_type'] ?? $googleRow['service_type'] ?? 'hosting_web')),
                ];

                if ($dbRow === null && $googleRow !== null) {
                    if (in_array($direction, ['forward', 'together'], true)) {
                        $dbCreates[$key] = $googleRow;
                    }
                    $nextBaseline[$key] = $this->extractComparableSnapshot($googleRow);
                    continue;
                }

                if ($dbRow !== null && $googleRow === null) {
                    if (in_array($direction, ['backward', 'together'], true)) {
                        $googleAppends[$key] = $dbRow;
                    }
                    $nextBaseline[$key] = $this->extractComparableSnapshot($dbRow);
                    continue;
                }

                if ($dbRow === null && $googleRow === null) {
                    continue;
                }

                foreach ($fields as $field) {
                    $dbVal = $this->normalizeSyncValue($field, $dbRow[$field] ?? '');
                    $googleVal = $this->normalizeSyncValue($field, $googleRow[$field] ?? '');

                    if ($baseRow === null) {
                        if ($dbVal === $googleVal) {
                            $resolvedSnapshot[$field] = $dbVal;
                            continue;
                        }

                        // First safe run with no baseline: keep divergent non-empty values as conflicts.
                        if ($dbVal !== '' && $googleVal !== '') {
                            $result['conflicts_detected']++;
                            $result['errors'][] = "Conflict on {$key} field {$field}: baseline missing and values differ";
                            continue;
                        }

                        if ($googleVal !== '' && $dbVal === '' && in_array($direction, ['forward', 'together'], true)) {
                            $dbPatches[$key][$field] = $googleVal;
                        } elseif ($dbVal !== '' && $googleVal === '' && in_array($direction, ['backward', 'together'], true)) {
                            $googlePatches[$key][$field] = $dbVal;
                        }

                        $resolvedSnapshot[$field] = $googleVal !== '' ? $googleVal : $dbVal;
                        continue;
                    }

                    $baseVal = $this->normalizeSyncValue($field, $baseRow[$field] ?? '');
                    $dbChanged = $dbVal !== $baseVal;
                    $googleChanged = $googleVal !== $baseVal;

                    if ($dbChanged && !$googleChanged) {
                        if (in_array($direction, ['backward', 'together'], true)) {
                            $googlePatches[$key][$field] = $dbVal;
                        }
                        $resolvedSnapshot[$field] = $dbVal;
                        continue;
                    }

                    if (!$dbChanged && $googleChanged) {
                        if (in_array($direction, ['forward', 'together'], true)) {
                            $dbPatches[$key][$field] = $googleVal;
                        }
                        $resolvedSnapshot[$field] = $googleVal;
                        continue;
                    }

                    if ($dbChanged && $googleChanged && $dbVal !== $googleVal) {
                        if ($conflictPolicy === 'prefer_db') {
                            if (in_array($direction, ['backward', 'together'], true)) {
                                $googlePatches[$key][$field] = $dbVal;
                                $result['conflicts_resolved']++;
                                $resolvedSnapshot[$field] = $dbVal;
                            } else {
                                $result['conflicts_detected']++;
                                $result['errors'][] = "Conflict on {$key} field {$field}: prefer_db is incompatible with '{$direction}' direction";
                                $resolvedSnapshot[$field] = $baseVal;
                            }
                        } elseif ($conflictPolicy === 'prefer_google') {
                            if (in_array($direction, ['forward', 'together'], true)) {
                                $dbPatches[$key][$field] = $googleVal;
                                $result['conflicts_resolved']++;
                                $resolvedSnapshot[$field] = $googleVal;
                            } else {
                                $result['conflicts_detected']++;
                                $result['errors'][] = "Conflict on {$key} field {$field}: prefer_google is incompatible with '{$direction}' direction";
                                $resolvedSnapshot[$field] = $baseVal;
                            }
                        } else {
                            $result['conflicts_detected']++;
                            $result['errors'][] = "Conflict on {$key} field {$field}: db='{$dbVal}' google='{$googleVal}' baseline='{$baseVal}'";
                            $resolvedSnapshot[$field] = $baseVal;
                        }
                        continue;
                    }

                    $resolvedSnapshot[$field] = $dbVal;
                }

                $nextBaseline[$key] = $resolvedSnapshot;
            }

            if (!$dryRun && in_array($direction, ['backward', 'together'], true)) {
                $googleApplyResult = $this->applyGooglePatches($service, $settings, $googleRowsByKey, $googlePatches);
                $result['updated_in_google'] += $googleApplyResult['updated_in_google'];
                $result['skipped_in_google'] += $googleApplyResult['skipped_in_google'];
                if (!empty($googleApplyResult['errors'])) {
                    $result['errors'] = array_merge($result['errors'], $googleApplyResult['errors']);
                }

                // Append DB-only records as new rows in Google Sheet (grouped like export).
                if (!empty($googleAppends)) {
                    $appendRows = $this->buildGroupedSheetRows(array_values($googleAppends));
                    $appendResult = $this->appendRowsToGoogleSheet($service, $settings, $appendRows);
                    $result['added_to_google'] += $appendResult['appended'] ?? 0;
                    if (!empty($appendResult['errors'])) {
                        $result['errors'] = array_merge($result['errors'], $appendResult['errors']);
                    }
                }
            }

            if ($dryRun) {
                $result['updated_in_db'] = count($dbPatches);
                $result['added_to_db'] = count($dbCreates);
                $result['updated_in_google'] = count($googlePatches);
                $result['added_to_google'] = count($googleAppends);
            }

            // Re-apply header/column formatting after any Google-side changes.
            if (!$dryRun && in_array($direction, ['backward', 'together'], true)) {
                $sheetId = $this->getSheetId($service, $settings);
                if ($sheetId !== null) {
                    $this->writeSheetHeaders($service, $settings);
                    $totalRows = count($dbRowsByKey) + 2; // data rows + 2 header rows
                    $this->applySheetFormatting($service, $settings, $sheetId, $totalRows);
                }
            }

            if (!$dryRun) {
                // Persist DB updates and baseline atomically.
                $this->db->beginTransaction();
                try {
                    if (in_array($direction, ['forward', 'together'], true)) {
                        $dbApplyResult = $this->applyDatabasePatches($dbRowsByKey, $dbPatches, $dbCreates);
                        $result['added_to_db'] += $dbApplyResult['added_to_db'];
                        $result['updated_in_db'] += $dbApplyResult['updated_in_db'];
                        if (!empty($dbApplyResult['errors'])) {
                            $result['errors'] = array_merge($result['errors'], $dbApplyResult['errors']);
                        }
                    }

                    $this->saveGoogleSyncBaseline($nextBaseline);
                    if ($baselineWasEmpty && !empty($nextBaseline)) {
                        $result['baseline_initialized'] = true;
                    }

                    $this->db->commit();
                } catch (Throwable $txe) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    throw $txe;
                }
            } else {
                $result['baseline_initialized'] = false;
            }

            // Backward-compatible keys used by current UI messaging.
            $result['added'] = $result['added_to_db'];
            $result['updated'] = $result['updated_in_db'];
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        } finally {
            // Embed rollback snapshots.
            // pre_merge_snapshot/added_keys are legacy website-only rollback fields.
            // pre_entity_snapshot is the full snapshot used by the new rollback path.
            $result['pre_merge_snapshot'] = $dbRowsByKey ?? [];
            $result['added_keys'] = array_keys($dbCreates ?? []);
            $result['pre_entity_snapshot'] = $preEntitySnapshot ?? [];
            $this->logGoogleSyncAudit($direction, $conflictPolicy, $dryRun, $result);
            if ($lockAcquired) {
                $this->releaseGoogleSyncLock($lockKey);
            }
        }

        return $result;
    }

    public function rollbackGoogleSync(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $lang     = $_GET['lang'] ?? 'en';
        $redirect = "index.php?action=import_export&lang={$lang}";
        $auditId  = (int)($_GET['audit_id'] ?? 0);

        if ($auditId <= 0) {
            $_SESSION['google_sync_result'] = ['errors' => ['Invalid rollback ID.']];
            header("Location: {$redirect}");
            exit;
        }

        try {
            if (!$this->tableExists('google_sheets_sync_audit')) {
                throw new Exception('Sync audit table does not exist.');
            }

            $stmt = $this->db->prepare("SELECT result_json, direction, dry_run FROM google_sheets_sync_audit WHERE id = ?");
            $stmt->execute([$auditId]);
            $audit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$audit) {
                throw new Exception("Audit record #{$auditId} not found.");
            }

            if ((int)$audit['dry_run']) {
                throw new Exception('Cannot roll back a dry-run operation — no changes were made.');
            }

            $resultData         = json_decode($audit['result_json'] ?? '{}', true);
            $preEntitySnapshot  = $resultData['pre_entity_snapshot'] ?? [];
            $preSnapshot        = $resultData['pre_merge_snapshot'] ?? [];
            $addedKeys          = $resultData['added_keys'] ?? [];

            $restored = 0;
            $deleted  = 0;
            $errors   = [];

            if (!empty($preEntitySnapshot)) {
                // New rollback path: restore full entities (providers, hosting, hosting_accounts, websites).
                $this->db->beginTransaction();
                try {
                    $entityRestore = $this->restoreRollbackEntitySnapshot($preEntitySnapshot);
                    $restored = (int)($entityRestore['restored_total'] ?? 0);
                    $deleted  = (int)($entityRestore['deleted_total'] ?? 0);
                    $errors   = $entityRestore['errors'] ?? [];

                    // Keep baseline consistent with restored DB state.
                    $this->saveGoogleSyncBaseline($this->buildCurrentDatabaseBaselineSnapshot());

                    $this->db->commit();
                } catch (Throwable $txe) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    throw $txe;
                }

                $_SESSION['google_sync_result'] = [
                    'rollback'  => true,
                    'audit_id'  => $auditId,
                    'restored'  => $restored,
                    'deleted'   => $deleted,
                    'errors'    => $errors,
                ];
            } else {
                // Legacy rollback path: websites only.
                if (empty($preSnapshot) && empty($addedKeys)) {
                    throw new Exception('No rollback snapshot available for this sync operation (it may predate rollback support).');
                }

                $this->db->beginTransaction();
                try {
                    // Restore rows that existed before the sync.
                    $addedKeySet = array_flip($addedKeys);
                    foreach ($preSnapshot as $key => $row) {
                        if (isset($addedKeySet[$key])) {
                            continue; // these will be deleted instead
                        }
                        $websiteId = (int)($row['id'] ?? 0);
                        if ($websiteId <= 0) {
                            continue;
                        }
                        if ($this->websiteModel->updateWebsite($websiteId, $row)) {
                            $restored++;
                        } else {
                            $errors[] = "Failed to restore row for {$key}";
                        }
                    }

                    // Delete rows that were created during the sync.
                    foreach ($addedKeys as $key) {
                        $parts       = explode('|', $key, 2);
                        $domain      = trim($parts[0] ?? '');
                        $serviceType = trim($parts[1] ?? '');
                        if ($domain === '') {
                            continue;
                        }
                        $del = $this->db->prepare(
                            "SELECT id FROM websites WHERE domain = ? AND service_type = ?"
                        );
                        $del->execute([$domain, $serviceType]);
                        foreach ($del->fetchAll(PDO::FETCH_COLUMN, 0) as $delId) {
                            if ($this->websiteModel->deleteWebsite((int)$delId)) {
                                $deleted++;
                            } else {
                                $errors[] = "Failed to delete synced row for {$key}";
                            }
                        }
                    }

                    // Keep baseline consistent with restored DB state.
                    $this->saveGoogleSyncBaseline($this->buildCurrentDatabaseBaselineSnapshot());

                    $this->db->commit();
                } catch (Throwable $txe) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    throw $txe;
                }

                $_SESSION['google_sync_result'] = [
                    'rollback'  => true,
                    'audit_id'  => $auditId,
                    'restored'  => $restored,
                    'deleted'   => $deleted,
                    'errors'    => $errors,
                ];
            }
        } catch (Exception $e) {
            $_SESSION['google_sync_result'] = ['errors' => [$e->getMessage()]];
        }

        header("Location: {$redirect}");
        exit;
    }

    private function buildCurrentDatabaseBaselineSnapshot(): array
    {
        $rowsByKey = $this->getDatabaseRowsByKey();
        $baseline = [];

        foreach ($rowsByKey as $key => $row) {
            $baseline[$key] = $this->extractComparableSnapshot($row);
        }

        return $baseline;
    }

    private function captureRollbackEntitySnapshot(): array
    {
        return [
            'providers'        => $this->fetchTableRowsForRollback('providers'),
            'hosting'          => $this->fetchTableRowsForRollback('hosting'),
            'hosting_accounts' => $this->fetchTableRowsForRollback('hosting_accounts'),
            'websites'         => $this->fetchTableRowsForRollback('websites'),
        ];
    }

    private function fetchTableRowsForRollback(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        try {
            $stmt = $this->db->query("SELECT * FROM `{$table}` ORDER BY id ASC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            error_log("fetchTableRowsForRollback error ({$table}): " . $e->getMessage());
            return [];
        }
    }

    private function rollbackInsertableColumns(string $table): array
    {
        $stmt = $this->db->prepare(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND EXTRA NOT LIKE '%GENERATED%'
             ORDER BY ORDINAL_POSITION ASC"
        );
        $stmt->execute([$table]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
    }

    private function restoreTableRowsFromSnapshot(string $table, array $rows): array
    {
        $stats = ['deleted' => 0, 'restored' => 0, 'errors' => []];

        if (!$this->tableExists($table)) {
            return $stats;
        }

        try {
            $countStmt = $this->db->query("SELECT COUNT(*) FROM `{$table}`");
            $stats['deleted'] = (int)($countStmt ? $countStmt->fetchColumn() : 0);

            $this->db->exec("DELETE FROM `{$table}`");

            if (empty($rows)) {
                return $stats;
            }

            $columns = $this->rollbackInsertableColumns($table);
            if (empty($columns)) {
                return $stats;
            }

            $columnSql = implode(', ', array_map(static fn($c) => "`{$c}`", $columns));
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $insertSql = "INSERT INTO `{$table}` ({$columnSql}) VALUES ({$placeholders})";
            $insertStmt = $this->db->prepare($insertSql);

            foreach ($rows as $row) {
                $params = [];
                foreach ($columns as $col) {
                    $params[] = $row[$col] ?? null;
                }
                $insertStmt->execute($params);
                $stats['restored']++;
            }
        } catch (Throwable $e) {
            $stats['errors'][] = "Failed restoring table {$table}: " . $e->getMessage();
        }

        return $stats;
    }

    private function restoreRollbackEntitySnapshot(array $snapshot): array
    {
        $result = [
            'restored_total' => 0,
            'deleted_total' => 0,
            'errors' => [],
        ];

        $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            // Delete in child->parent order, then restore parent->child.
            $deleteOrder = ['websites', 'hosting_accounts', 'hosting', 'providers'];
            $restoreOrder = ['providers', 'hosting', 'hosting_accounts', 'websites'];

            foreach ($deleteOrder as $table) {
                $stats = $this->restoreTableRowsFromSnapshot($table, []);
                $result['deleted_total'] += (int)($stats['deleted'] ?? 0);
                if (!empty($stats['errors'])) {
                    $result['errors'] = array_merge($result['errors'], $stats['errors']);
                }
            }

            foreach ($restoreOrder as $table) {
                $rows = $snapshot[$table] ?? [];
                $stats = $this->restoreTableRowsFromSnapshot($table, is_array($rows) ? $rows : []);
                $result['restored_total'] += (int)($stats['restored'] ?? 0);
                if (!empty($stats['errors'])) {
                    $result['errors'] = array_merge($result['errors'], $stats['errors']);
                }
            }
        } finally {
            $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return $result;
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Apply consistent formatting to Google Sheet
     * Used by both export and merge operations
     */
    private function writeSheetHeaders(\Google\Service\Sheets $service, array $settings): void
    {
        $row1 = [
            'Clienti',
            'Informazioni per il cliente', '', '',
            'Informazioni sul servizio', '', '', '', '', '', '', '', '', '', '', '', '', '',
        ];
        $row2 = [
            'Nome',
            'Indirizzo', 'Email', 'P.IVA',
            'Tipologia di Servizi', 'Dettaglio Servizi', 'Email Assegnata',
            'Propietario', 'Registrante', 'Scadenza',
            'Costo Server (iva esclusa)', 'Prezzo di vendita (iva esclusa)',
            'Direct DNS A', 'User Name cpanel', 'Email panel', 'Bug report',
            'Costo di manutezione sito', 'Notes',
        ];
        $service->spreadsheets_values->update(
            $settings['sheet_id'],
            $settings['sheet_name'] . '!A1:R2',
            new \Google\Service\Sheets\ValueRange(['values' => [$row1, $row2]]),
            ['valueInputOption' => 'USER_ENTERED']
        );
    }

    private function applySheetFormatting($service, $settings, $sheetId, $totalRows)
    {
        $lastRow = max($totalRows, 3);

        $hexToRgb = function ($hex) {
            $hex = ltrim($hex, '#');
            return [
                'red'   => hexdec(substr($hex, 0, 2)) / 255,
                'green' => hexdec(substr($hex, 2, 2)) / 255,
                'blue'  => hexdec(substr($hex, 4, 2)) / 255,
            ];
        };

        // Row 1: dark shades; Row 2: mid shades; both use white text
        $colors = [
            'row1Blue'  => '#1565c0',
            'row1Red'   => '#c62828',
            'row1Green' => '#1b5e20',
            'row2Blue'  => '#1e88e5',
            'row2Red'   => '#e53935',
            'row2Green' => '#2e7d32',
            'white'     => '#FFFFFF',
            'textDark'  => '#2E2E2E',
        ];

        // Column width specifications (in pixels) — A through R (18 columns)
        $columnWidths = [
            'A' => 200,  // Nome
            'B' => 220,  // Indirizzo
            'C' => 220,  // Email
            'D' => 150,  // P.IVA
            'E' => 150,  // Tipologia di Servizi
            'F' => 200,  // Dettaglio Servizi
            'G' => 220,  // Email Assegnata
            'H' => 140,  // Propietario
            'I' => 140,  // Registrante
            'J' => 130,  // Scadenza
            'K' => 160,  // Costo Server
            'L' => 170,  // Prezzo di vendita
            'M' => 130,  // Direct DNS A
            'N' => 160,  // User Name cpanel
            'O' => 220,  // Email panel
            'P' => 200,  // Bug report
            'Q' => 200,  // Costo di manutezione sito
            'R' => 200,  // Notes
        ];

        // Build column-width requests for all 18 columns (A–R)
        $colKeys  = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R'];
        $requests = [];
        foreach ($colKeys as $idx => $key) {
            $requests[] = new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId'    => $sheetId,
                        'dimension'  => 'COLUMNS',
                        'startIndex' => $idx,
                        'endIndex'   => $idx + 1,
                    ],
                    'properties' => ['pixelSize' => $columnWidths[$key]],
                    'fields'     => 'pixelSize',
                ],
            ]);
        }

        // Row-height: row 1 = 50px, row 2 = 60px (accommodates wrapped header text)
        $requests[] = new \Google\Service\Sheets\Request([
            'updateDimensionProperties' => [
                'range' => ['sheetId' => $sheetId, 'dimension' => 'ROWS', 'startIndex' => 0, 'endIndex' => 1],
                'properties' => ['pixelSize' => 50],
                'fields' => 'pixelSize',
            ],
        ]);
        $requests[] = new \Google\Service\Sheets\Request([
            'updateDimensionProperties' => [
                'range' => ['sheetId' => $sheetId, 'dimension' => 'ROWS', 'startIndex' => 1, 'endIndex' => 2],
                'properties' => ['pixelSize' => 60],
                'fields' => 'pixelSize',
            ],
        ]);
        if ($lastRow > 2) {
            $requests[] = new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => ['sheetId' => $sheetId, 'dimension' => 'ROWS', 'startIndex' => 2, 'endIndex' => $lastRow],
                    'properties' => ['pixelSize' => 34],
                    'fields' => 'pixelSize',
                ],
            ]);
        }

        // ── Unmerge first two rows, then re-apply merges ──────────────────────
        $requests[] = new \Google\Service\Sheets\Request([
            'unmergeCells' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 2,
                            'startColumnIndex' => 0, 'endColumnIndex' => 18],
            ],
        ]);
        // Merge B1:D1  (indices 1–3 inclusive → endIndex 4)
        $requests[] = new \Google\Service\Sheets\Request([
            'mergeCells' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1,
                            'startColumnIndex' => 1, 'endColumnIndex' => 4],
                'mergeType' => 'MERGE_ALL',
            ],
        ]);
        // Merge E1:R1  (indices 4–17 inclusive → endIndex 18)
        $requests[] = new \Google\Service\Sheets\Request([
            'mergeCells' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1,
                            'startColumnIndex' => 4, 'endColumnIndex' => 18],
                'mergeType' => 'MERGE_ALL',
            ],
        ]);

        // ── Row 1 cell formatting ─────────────────────────────────────────────
        // A1 — blue
        $requests[] = new \Google\Service\Sheets\Request([
            'repeatCell' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1,
                            'startColumnIndex' => 0, 'endColumnIndex' => 1],
                'cell' => ['userEnteredFormat' => [
                    'backgroundColor'    => $hexToRgb($colors['row1Blue']),
                    'textFormat'         => ['bold' => true, 'foregroundColor' => $hexToRgb($colors['white']), 'fontSize' => 13],
                    'horizontalAlignment'=> 'CENTER', 'verticalAlignment' => 'MIDDLE',
                    'wrapStrategy'       => 'WRAP',
                ]],
                'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,wrapStrategy)',
            ],
        ]);
        // B1:D1 — red
        $requests[] = new \Google\Service\Sheets\Request([
            'repeatCell' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1,
                            'startColumnIndex' => 1, 'endColumnIndex' => 4],
                'cell' => ['userEnteredFormat' => [
                    'backgroundColor'    => $hexToRgb($colors['row1Red']),
                    'textFormat'         => ['bold' => true, 'foregroundColor' => $hexToRgb($colors['white']), 'fontSize' => 15],
                    'horizontalAlignment'=> 'CENTER', 'verticalAlignment' => 'MIDDLE',
                    'wrapStrategy'       => 'WRAP',
                ]],
                'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,wrapStrategy)',
            ],
        ]);
        // E1:R1 — green
        $requests[] = new \Google\Service\Sheets\Request([
            'repeatCell' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1,
                            'startColumnIndex' => 4, 'endColumnIndex' => 18],
                'cell' => ['userEnteredFormat' => [
                    'backgroundColor'    => $hexToRgb($colors['row1Green']),
                    'textFormat'         => ['bold' => true, 'foregroundColor' => $hexToRgb($colors['white']), 'fontSize' => 14],
                    'horizontalAlignment'=> 'CENTER', 'verticalAlignment' => 'MIDDLE',
                    'wrapStrategy'       => 'WRAP',
                ]],
                'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,wrapStrategy)',
            ],
        ]);

        // ── Row 2 cell formatting ─────────────────────────────────────────────
        // A2 — blue
        $requests[] = new \Google\Service\Sheets\Request([
            'repeatCell' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 1, 'endRowIndex' => 2,
                            'startColumnIndex' => 0, 'endColumnIndex' => 1],
                'cell' => ['userEnteredFormat' => [
                    'backgroundColor'    => $hexToRgb($colors['row2Blue']),
                    'textFormat'         => ['bold' => true, 'foregroundColor' => $hexToRgb($colors['white']), 'fontSize' => 10],
                    'horizontalAlignment'=> 'CENTER', 'verticalAlignment' => 'MIDDLE',
                    'wrapStrategy'       => 'WRAP',
                ]],
                'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,wrapStrategy)',
            ],
        ]);
        // B2:D2 — red
        $requests[] = new \Google\Service\Sheets\Request([
            'repeatCell' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 1, 'endRowIndex' => 2,
                            'startColumnIndex' => 1, 'endColumnIndex' => 4],
                'cell' => ['userEnteredFormat' => [
                    'backgroundColor'    => $hexToRgb($colors['row2Red']),
                    'textFormat'         => ['bold' => true, 'foregroundColor' => $hexToRgb($colors['white']), 'fontSize' => 10],
                    'horizontalAlignment'=> 'CENTER', 'verticalAlignment' => 'MIDDLE',
                    'wrapStrategy'       => 'WRAP',
                ]],
                'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,wrapStrategy)',
            ],
        ]);
        // E2:R2 — green
        $requests[] = new \Google\Service\Sheets\Request([
            'repeatCell' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 1, 'endRowIndex' => 2,
                            'startColumnIndex' => 4, 'endColumnIndex' => 18],
                'cell' => ['userEnteredFormat' => [
                    'backgroundColor'    => $hexToRgb($colors['row2Green']),
                    'textFormat'         => ['bold' => true, 'foregroundColor' => $hexToRgb($colors['white']), 'fontSize' => 10],
                    'horizontalAlignment'=> 'CENTER', 'verticalAlignment' => 'MIDDLE',
                    'wrapStrategy'       => 'WRAP',
                ]],
                'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,wrapStrategy)',
            ],
        ]);

        // ── Data rows (row 3 onwards) ─────────────────────────────────────────
        $requests[] = new \Google\Service\Sheets\Request([
            'repeatCell' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 2, 'endRowIndex' => $lastRow,
                            'startColumnIndex' => 0, 'endColumnIndex' => 18],
                'cell' => ['userEnteredFormat' => [
                    'wrapStrategy'       => 'WRAP',
                    'horizontalAlignment'=> 'CENTER', 'verticalAlignment' => 'MIDDLE',
                    'textFormat'         => ['foregroundColor' => $hexToRgb($colors['textDark']), 'fontSize' => 11],
                ]],
                'fields' => 'userEnteredFormat(wrapStrategy,horizontalAlignment,verticalAlignment,textFormat)',
            ],
        ]);
        // Column A data rows — bold
        $requests[] = new \Google\Service\Sheets\Request([
            'repeatCell' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 2, 'endRowIndex' => $lastRow,
                            'startColumnIndex' => 0, 'endColumnIndex' => 1],
                'cell' => ['userEnteredFormat' => [
                    'textFormat'         => ['bold' => true, 'foregroundColor' => $hexToRgb($colors['textDark']), 'fontSize' => 11],
                    'wrapStrategy'       => 'WRAP',
                    'horizontalAlignment'=> 'CENTER', 'verticalAlignment' => 'MIDDLE',
                ]],
                'fields' => 'userEnteredFormat(textFormat,wrapStrategy,horizontalAlignment,verticalAlignment)',
            ],
        ]);

        $service->spreadsheets->batchUpdate(
            $settings['sheet_id'],
            new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest(['requests' => $requests])
        );
    }

    private function mergeBidirectional($googleData, $dbData, $settings)
    {
        return $this->executeThreeWayMerge($settings, 'together', [
            'conflict_policy' => 'manual',
            'dry_run' => false,
        ]);
    }

    // Diagnostic endpoint for debugging Google Sheets issues
    public function diagnosticGoogleSheets()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        
        try {
            $settings = $this->settingsModel->getGoogleSheetsSettings();
            
            // Build stored settings info
            $storedSettingsInfo = "=== STORED SETTINGS ===\n";
            $storedSettingsInfo .= "Sheet ID: " . $settings['sheet_id'] . "\n";
            $storedSettingsInfo .= "Sheet Name: '" . $settings['sheet_name'] . "'\n";
            $storedSettingsInfo .= "Sheet Name Length: " . strlen($settings['sheet_name']) . "\n";
            $storedSettingsInfo .= "Sheet Name Hex: " . bin2hex($settings['sheet_name']) . "\n";
            $storedSettingsInfo .= "Enabled: " . $settings['enabled'] . "\n";
            $storedSettingsInfo .= "Credentials Present: " . (!empty($settings['credentials']) ? "YES" : "NO") . "\n";
            
            require_once APP_PATH . '/vendor/autoload.php';
            
            $client = $this->getGoogleClient($settings);
            $service = new \Google\Service\Sheets($client);
            
            // Build connection info
            $googleConnectionInfo = "=== CONNECTING TO GOOGLE ===\n";
            $spreadsheet = $service->spreadsheets->get($settings['sheet_id'], ['includeGridData' => false]);
            $googleConnectionInfo .= "Spreadsheet Title: " . $spreadsheet->getProperties()->getTitle() . "\n";
            $googleConnectionInfo .= "Spreadsheet ID: " . $spreadsheet->getSpreadsheetId() . "\n";
            $googleConnectionInfo .= "Status: CONNECTED\n";
            
            // Build available sheets info
            $availableSheetsInfo = "=== AVAILABLE SHEETS ===\n";
            $sheets = $spreadsheet->getSheets();
            $availableSheetsInfo .= "Total Sheets: " . count($sheets) . "\n\n";
            
            foreach ($sheets as $sheet) {
                $title = $sheet->getProperties()->getTitle();
                $availableSheetsInfo .= "Sheet: '" . $title . "'\n";
                $availableSheetsInfo .= "  Length: " . strlen($title) . "\n";
                $availableSheetsInfo .= "  Hex: " . bin2hex($title) . "\n";
                $availableSheetsInfo .= "  Exact Match: " . ($title === $settings['sheet_name'] ? "YES" : "NO") . "\n";
                $availableSheetsInfo .= "  Case-Insensitive Match: " . (strtolower($title) === strtolower($settings['sheet_name']) ? "YES" : "NO") . "\n";
                $availableSheetsInfo .= "  Trim Match: " . (trim($title) === trim($settings['sheet_name']) ? "YES" : "NO") . "\n";
                $availableSheetsInfo .= "\n";
            }
            
        } catch (Exception $e) {
            $googleConnectionInfo = "ERROR: " . $e->getMessage();
            $availableSheetsInfo = "Unable to retrieve sheets due to connection error.";
        }
        
        require APP_PATH . '/includes/header.php';
        require APP_PATH . '/includes/sidebar.php';
        require APP_PATH . '/views/settings/diagnostic.php';
        require APP_PATH . '/includes/footer.php';
    }

    /**
     * Display WordPress API configuration page
     */
    public function wordpress()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $authController = new AuthController($this->db);
        $authController->checkPermission('super_admin');

        $wordPressSiteModel = new WordPressSite($this->db);
        
        // Get all websites and keep only domain-like records.
        // Previous code used $website['name'], which does not exist on websites rows,
        // causing the selector list to be empty.
        $allWebsites = $this->websiteModel->getAllWebsites();
        $websites = array_values(array_filter($allWebsites, function ($website) {
            $serviceType = strtolower(trim((string)($website['service_type'] ?? '')));
            if ($serviceType !== '') {
                return in_array($serviceType, ['domain', 'dominio', 'website', 'site'], true);
            }
            return !empty($website['domain']);
        }));
        // If no typed matches exist, gracefully fall back to all websites with a domain.
        if (empty($websites)) {
            $websites = array_values(array_filter($allWebsites, function ($website) {
                return !empty($website['domain']);
            }));
        }

        // Get configured WordPress sites with website info
        $wpSitesData = [];
        $tablesExist = true;
        $migrationError = null;

        try {
            $allWpSites = $wordPressSiteModel->getAllActive();
            
            foreach ($allWpSites as $wpSite) {
                $website = $this->websiteModel->getWebsiteById($wpSite['website_id']);
                $wpSite['website_domain'] = $website['domain'] ?? 'Unknown';
                $wpSitesData[] = $wpSite;
            }
        } catch (PDOException $e) {
            // Check if error is about missing table
            if (strpos($e->getMessage(), 'Base table or view not found') !== false || 
                strpos($e->getMessage(), "doesn't exist") !== false) {
                $tablesExist = false;
                $migrationError = 'WordPress integration tables not found. Please run database migrations first.';
                $_SESSION['warning'] = $migrationError;
            } else {
                throw $e;
            }
        }

        $wordpressSites = $wpSitesData;
        $editingId = null;
        $editingWebsiteId = null;
        $editingUrl = null;
        $editingKey = null;
        $editingActive = true;

        require APP_PATH . '/includes/header.php';
        require APP_PATH . '/includes/sidebar.php';
        require APP_PATH . '/views/settings/wordpress.php';
        require APP_PATH . '/includes/footer.php';
    }

    /**
     * Edit WordPress site configuration
     */
    public function wordpress_edit()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $authController = new AuthController($this->db);
        $authController->checkPermission('super_admin');

        $wpSiteId = $_GET['id'] ?? null;
        if (!$wpSiteId) {
            $_SESSION['error'] = 'Invalid WordPress site ID';
            header('Location: index.php?action=settings&do=wordpress');
            exit;
        }

        $wordPressSiteModel = new WordPressSite($this->db);
        $wpSite = $wordPressSiteModel->getById($wpSiteId);

        if (!$wpSite) {
            $_SESSION['error'] = 'WordPress site not found';
            header('Location: index.php?action=settings&do=wordpress');
            exit;
        }

        // Get all websites and apply same domain-focused filter used on create page.
        $allWebsites = $this->websiteModel->getAllWebsites();
        $websites = array_values(array_filter($allWebsites, function ($website) {
            $serviceType = strtolower(trim((string)($website['service_type'] ?? '')));
            if ($serviceType !== '') {
                return in_array($serviceType, ['domain', 'dominio', 'website', 'site'], true);
            }
            return !empty($website['domain']);
        }));
        if (empty($websites)) {
            $websites = array_values(array_filter($allWebsites, function ($website) {
                return !empty($website['domain']);
            }));
        }

        // Ensure currently edited website is present even if it doesn't match filter tags.
        $currentWebsite = $this->websiteModel->getWebsiteById((int)$wpSite['website_id']);
        if ($currentWebsite) {
            $exists = false;
            foreach ($websites as $website) {
                if ((int)$website['id'] === (int)$currentWebsite['id']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $websites[] = $currentWebsite;
            }
        }

        // Get all configured WordPress sites
        $allWpSites = $wordPressSiteModel->getAllActive();
        $wpSitesData = [];
        
        foreach ($allWpSites as $site) {
            $website = $this->websiteModel->getWebsiteById($site['website_id']);
            $site['website_domain'] = $website['domain'] ?? 'Unknown';
            $wpSitesData[] = $site;
        }

        $wordpressSites = $wpSitesData;
        $editingId = $wpSiteId;
        $editingWebsiteId = $wpSite['website_id'];
        $editingUrl = $wpSite['wordpress_url'];
        $editingKey = $wpSite['api_key'];
        $editingActive = (bool)$wpSite['is_active'];

        require APP_PATH . '/includes/header.php';
        require APP_PATH . '/includes/sidebar.php';
        require APP_PATH . '/views/settings/wordpress.php';
        require APP_PATH . '/includes/footer.php';
    }

    /**
     * Save WordPress site configuration
     */
    public function wordpress_save()
    {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=login');
            exit;
        }

        $authController = new AuthController($this->db);
        $authController->checkPermission('super_admin');

        try {
            $websiteId = (int)$_POST['website_id'] ?? 0;
            $wordpressUrl = trim($_POST['wordpress_url'] ?? '');
            $apiKey = trim($_POST['api_key'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $wordPressSiteId = $_POST['wordpress_site_id'] ?? null;

            // Validate inputs
            if (!$websiteId || empty($wordpressUrl) || empty($apiKey)) {
                throw new Exception('All fields are required');
            }

            if (!filter_var($wordpressUrl, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid WordPress URL');
            }

            $wordPressSiteModel = new WordPressSite($this->db);

            if ($wordPressSiteId) {
                // Update
                $wordPressSiteModel->update($websiteId, $wordpressUrl, $apiKey, $isActive);
                $_SESSION['message'] = 'WordPress configuration updated successfully';
            } else {
                // Create
                $wordPressSiteModel->create($websiteId, $wordpressUrl, $apiKey, $isActive);
                $_SESSION['message'] = 'WordPress configuration saved successfully';
            }

            header('Location: index.php?action=settings&do=wordpress');
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: index.php?action=settings&do=wordpress');
            exit;
        }
    }

    /**
     * Delete WordPress site configuration
     */
    public function wordpress_delete()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $authController = new AuthController($this->db);
        $authController->checkPermission('super_admin');

        try {
            $wpSiteId = $_GET['id'] ?? null;
            if (!$wpSiteId) {
                throw new Exception('Invalid WordPress site ID');
            }

            $wordPressSiteModel = new WordPressSite($this->db);
            $wordPressSiteModel->delete($wpSiteId);

            $_SESSION['message'] = 'WordPress configuration deleted successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: index.php?action=settings&do=wordpress');
        exit;
    }

    /**
     * Run database migrations for WordPress integration
     */
    public function migrate_database()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $authController = new AuthController($this->db);
        $authController->checkPermission('super_admin');

        try {
            require_once APP_PATH . '/services/Database/DbMigrator.php';
            
            $migrator = new DbMigrator($this->db);
            $results = $migrator->migrate();

            header('Content-Type: application/json');
            echo json_encode($results);
            exit;

        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
}