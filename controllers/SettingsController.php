<?php
class SettingsController
{
    private $emailModel;
    private $cronModel;
    private $settingsModel;
    private $hostingModel;
    private $websiteModel;
    private $siteSettings;
    private $emailTemplate;
    private $db;

    public function __construct($pdo)
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
                'cc_email' => $_POST['cc_email'] ?? null // Add this line
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

    public function advanced()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // Handle Cron Job settings
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $isActive = isset($_POST['cron_active']) && $_POST['cron_active'] === '1';
            $success = $this->cronModel->updateCronStatus($isActive);

            if ($success) {
                $_SESSION['message'] = __('settings.cron_settings_updated');
            } else {
                $_SESSION['error'] = __('settings.cron_settings_error');
            }
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

        $cronStatus = $this->cronModel->getCronStatus();
        $lastRun = $this->cronModel->getLastRunTime();
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
                header('Location: index.php?action=settings&do=advanced');
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['google_error'] = $e->getMessage();
            header('Location: index.php?action=settings&do=advanced');
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
        
        // If no settings provided, retrieve from database
        if ($settings === null) {
            $settings = $this->settingsModel->getGoogleSheetsSettings();
        }
        
        // Debug: Log the settings being used
        error_log("=== DEBUG EXPORT START ===");
        error_log("DEBUG Export - Settings received:");
        error_log("  Sheet Name: '" . $settings['sheet_name'] . "'");
        error_log("  Sheet ID: '" . $settings['sheet_id'] . "'");
        error_log("  Enabled: " . $settings['enabled']);
        error_log("  Credentials Length: " . strlen($settings['credentials']));
        error_log("  Credentials Present: " . (!empty($settings['credentials']) ? 'YES' : 'NO'));
        
        // Validate credentials are valid JSON
        $creds = json_decode($settings['credentials'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("  Credentials JSON Error: " . json_last_error_msg());
            return [
                'exported' => 0,
                'updated' => 0,
                'errors' => ['Invalid credentials format: ' . json_last_error_msg()]
            ];
        }
        error_log("  Credentials Valid JSON: YES");
        error_log("=== DEBUG EXPORT END ===");

        // Column width definitions (in pixels)
        $columnWidths = [
            'A' => 180,  // Name
            'B' => 200,  // Address
            'C' => 220,  // Email
            'D' => 120,  // P.IVA
            'E' => 120,  // TIPOLOGIA DI SERVIZI
            'F' => 200,  // DETTAGLIO SERVIZI
            'G' => 220,  // EMAIL ASSEGNATA
            'H' => 120,  // PROPRIETARIO
            'I' => 120,  // REGISTRANTE
            'J' => 120,  // SCADENZA
            'K' => 120,  // COSTO SERVER
            'L' => 120,  // PREZZO DI VENDITA
            'M' => 120,  // Direct DNS A
            'N' => 120,  // User Name cpanel
            'O' => 150,  // Email panel
            'P' => 200,  // Bug report
            'Q' => 200   // Notes
        ];

        // Updated Color definitions (hex RGB)
        $colors = [
            'blueHeaderDark' => ['rgb' => '1565C0'],  // Dark blue
            'blueHeaderLight' => ['rgb' => '1E88E5'], // Light blue
            'redHeaderDark' => ['rgb' => 'C62828'],   // Dark red
            'redHeaderLight' => ['rgb' => 'E53935'],  // Light red
            'greenHeaderDark' => ['rgb' => '1B5E20'], // Dark green
            'greenHeaderLight' => ['rgb' => '2E7D32'], // Medium dark green
            'textLight' => ['rgb' => 'FFFFFF'],       // White text
            'textDark' => ['rgb' => '000000']        // Black text
        ];

        // Helper function to convert hex color to RGB
        $hexToRgb = function ($hex) {
            $hex = str_replace('#', '', $hex);
            if (strlen($hex) == 3) {
                $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
                $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
                $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
            } else {
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
            }
            return [
                'red' => $r / 255,
                'green' => $g / 255,
                'blue' => $b / 255
            ];
        };

        try {
            $client = $this->getGoogleClient($settings);
            $service = new \Google\Service\Sheets($client);

            error_log("DEBUG: Fetching spreadsheet with ID: '" . $settings['sheet_id'] . "'");
            try {
                $spreadsheet = $service->spreadsheets->get($settings['sheet_id'], ['includeGridData' => false]);
            } catch (\Exception $e) {
                error_log("ERROR: Failed to fetch spreadsheet: " . $e->getMessage());
                throw $e;
            }
            
            error_log("DEBUG: Spreadsheet Title: " . $spreadsheet->getProperties()->getTitle());
            error_log("DEBUG: Spreadsheet ID: " . $spreadsheet->getSpreadsheetId());
            
            $sheetsCollection = $spreadsheet->getSheets();
            error_log("DEBUG: Sheets collection type: " . gettype($sheetsCollection));
            error_log("DEBUG: Sheets collection count: " . (is_array($sheetsCollection) ? count($sheetsCollection) : 'not array'));
            
            // Debug: List all available sheets
            error_log("DEBUG: Available sheets in spreadsheet:");
            $sheet = null;
            $availableSheets = [];
            $sheetCount = 0;
            
            if ($sheetsCollection) {
                foreach ($sheetsCollection as $s) {
                    $sheetTitle = $s->getProperties()->getTitle();
                    $sheetCount++;
                    $availableSheets[] = $sheetTitle;
                    error_log("  Sheet $sheetCount: '" . $sheetTitle . "'");
                    error_log("    Comparing with: '" . $settings['sheet_name'] . "'");
                    error_log("    Match: " . ($sheetTitle === $settings['sheet_name'] ? 'YES' : 'NO'));
                    
                    if ($sheetTitle === $settings['sheet_name']) {
                        $sheet = $s;
                        break;
                    }
                }
            } else {
                error_log("ERROR: Sheets collection is null or empty!");
            }

            if ($sheet === null) {
                $errorMsg = "Sheet '{$settings['sheet_name']}' not found.";
                if (!empty($availableSheets)) {
                    $errorMsg .= " Available sheets: " . implode(", ", array_map(function($s) { return "'" . $s . "'"; }, $availableSheets));
                }
                // Add debug info to error message
                $errorMsg .= " [DEBUG: sheet_name='" . $settings['sheet_name'] . "', length=" . strlen($settings['sheet_name']) . "]";
                error_log("ERROR: " . $errorMsg);
                throw new Exception($errorMsg);
            }

            $sheetId = $sheet->getProperties()->getSheetId();

            // Prepare headers - Row 1 with category headers (17 columns total)
            // Row 1 spans: A1 (Clienti), B1-E1 (Informazioni per il cliente), E1-Q1 (Informazioni sul servizio)
            $headers = [
                [
                    'Clienti',                           // A1
                    'Informazioni per il cliente',       // B1 (will merge B1:E1)
                    '',                                  // C1 (part of merge)
                    '',                                  // D1 (part of merge)
                    'Informazioni sul servizio',         // E1 (will merge E1:Q1)
                    '',                                  // F1 (part of merge)
                    '',                                  // G1 (part of merge)
                    '',                                  // H1 (part of merge)
                    '',                                  // I1 (part of merge)
                    '',                                  // J1 (part of merge)
                    '',                                  // K1 (part of merge)
                    '',                                  // L1 (part of merge)
                    '',                                  // M1 (part of merge)
                    '',                                  // N1 (part of merge)
                    '',                                  // O1 (part of merge)
                    '',                                  // P1 (part of merge)
                    ''                                   // Q1 (part of merge)
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
                    'Notes'
                ]
            ];

            // Get the prepared data with client grouping information
            $preparedData = $this->websiteModel->prepareForGoogleSheets();
            $websiteData = $preparedData['data'];
            $clientRowGroups = $preparedData['clientRows'];

            error_log("DEBUG: Website data count: " . count($websiteData));
            error_log("DEBUG: First website row (if exists): " . print_r($websiteData[0] ?? 'NO DATA', true));

            $allData = array_merge($headers, $websiteData);
            $lastRow = count($allData);
            error_log("DEBUG: Total rows to export (including headers): " . $lastRow);

            // Clear existing values
            $service->spreadsheets_values->clear(
                $settings['sheet_id'],
                $settings['sheet_name'] . '!A:Z',
                new \Google\Service\Sheets\ClearValuesRequest()
            );

            // Insert new values
            $service->spreadsheets_values->update(
                $settings['sheet_id'],
                $settings['sheet_name'] . '!A1',
                new \Google\Service\Sheets\ValueRange(['values' => $allData]),
                ['valueInputOption' => 'USER_ENTERED']
            );


            // Apply consistent formatting using helper function
            $this->applySheetFormatting($service, $settings, $sheetId, $lastRow);

            return [
                'exported' => count($websiteData),
                'updated' => 0,
                'errors' => []
            ];
        } catch (Exception $e) {
            error_log("Google Sheets export error: " . $e->getMessage());
            return [
                'exported' => 0,
                'updated' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }





    private function importFromGoogleSheets($settings = null)
    {
        require_once APP_PATH . '/vendor/autoload.php';
        
        // If no settings provided, retrieve from database
        if ($settings === null) {
            $settings = $this->settingsModel->getGoogleSheetsSettings();
        }

        try {
            // Initialize Google Client
            $client = $this->getGoogleClient($settings);
            $service = new Google\Service\Sheets($client);

            // Read data from Google Sheets
            $range = $settings['sheet_name'] . '!A2:Z';
            $response = $service->spreadsheets_values->get($settings['sheet_id'], $range);
            $values = $response->getValues();

            if (empty($values)) {
                throw new Exception("No data found in Google Sheet");
            }

            // Process and import data
            $result = $this->websiteModel->importFromSheets($values);

            return $result;
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
        require_once APP_PATH . '/vendor/autoload.php';
        
        // If no settings provided, retrieve from database
        if ($settings === null) {
            $settings = $this->settingsModel->getGoogleSheetsSettings();
        }

        // 1. Get data from both sources
        $dbData = $this->websiteModel->getAllWebsites();
        $client = $this->getGoogleClient($settings);
        $service = new Google\Service\Sheets($client);
        $range = $settings['sheet_name'] . '!A2:Z';
        $response = $service->spreadsheets_values->get($settings['sheet_id'], $range);
        $sheetData = $response->getValues();

        // 2. Perform two-way sync with conflict resolution
        $result = [
            'exported' => 0,
            'imported' => 0,
            'updated' => 0,
            'conflicts' => 0,
            'errors' => 0
        ];

        // 3. Update Google Sheet with merged data
        if ($result['exported'] > 0) {
            $this->exportToGoogleSheets();
        }

        return $result;
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
                header('Location: index.php?action=settings&do=advanced');
                exit;
            }

            // Get Google data
            $googleData = $this->getGoogleSheetsData();
            
            // Get database data
            $dbData = $this->websiteModel->getWebsites('', 'domain', 'asc', 1, PHP_INT_MAX);
            
            // Compare data
            $comparison = $this->compareDatasets($dbData, $googleData);
            
            $_SESSION['comparison_result'] = $comparison;
            header('Location: index.php?action=settings&do=advanced&view=comparison');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Comparison failed: " . $e->getMessage();
            header('Location: index.php?action=settings&do=advanced');
            exit;
        }
    }

    // Format merge results for display
    private function formatMergeResultsForDisplay($result)
    {
        $output = [];
        
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
        if (isset($result['added_to_google']) && $result['added_to_google'] > 0) {
            $output[] = "Records Added to Google Sheets: " . $result['added_to_google'];
        }
        
        // Conflicts
        if (isset($result['conflicts_resolved']) && $result['conflicts_resolved'] > 0) {
            $output[] = "Conflicts Resolved: " . $result['conflicts_resolved'];
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
            
            if (!in_array($mergeStrategy, ['forward', 'backward', 'together'])) {
                throw new Exception("Invalid merge strategy");
            }

            $settings = $this->settingsModel->getGoogleSheetsSettings();
            
            // Get data
            $googleData = $this->getGoogleSheetsData();
            $dbData = $this->websiteModel->getWebsites('', 'domain', 'asc', 1, PHP_INT_MAX);
            
            $result = [];
            
            switch ($mergeStrategy) {
                case 'forward':
                    // Google Sheets → Database
                    $result = $this->mergeGoogleToDatabase($googleData);
                    break;
                case 'backward':
                    // Database → Google Sheets
                    $result = $this->mergeDatabaseToGoogle($dbData, $settings);
                    break;
                case 'together':
                    // Merge both ways with conflict resolution
                    $result = $this->mergeBidirectional($googleData, $dbData, $settings);
                    break;
            }
            
            $_SESSION['message'] = __('settings.merge_completed') . ":\n" . $this->formatMergeResultsForDisplay($result);
            $_SESSION['merge_result'] = $result;
            header('Location: index.php?action=settings&do=advanced');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Merge failed: " . $e->getMessage();
            header('Location: index.php?action=settings&do=advanced');
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

            // Fetch data from sheet - read all data including headers
            $range = $settings['sheet_name'] . '!A1:Q1000';
            $response = $service->spreadsheets_values->get($settings['sheet_id'], $range);
            $values = $response->getValues() ?? [];

            if (empty($values)) {
                return [];
            }

            // Skip header rows (first 2 rows)
            $dataRows = array_slice($values, 2);
            
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
                8 => 'email_server',     // Registrant
                9 => 'expiry_date',      // Scadenza
                10 => 'status',          // Status
                11 => 'vendita',         // Prezzo di vendita
                12 => 'dns',             // DNS
                13 => 'cpanel',          // Cpanel
                14 => 'epanel',          // Epanel
                15 => 'notes',           // Notes
                16 => 'remark'           // Remark
            ];

            $data = [];
            $currentHosting = null;

            foreach ($dataRows as $row) {
                $row = array_pad($row, 17, '');
                
                // Skip completely empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $rowData = [];
                foreach ($fieldMap as $colIdx => $field) {
                    $rowData[$field] = trim($row[$colIdx] ?? '');
                }

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

        // Map database domains for quick lookup
        $dbByDomain = [];
        foreach ($dbData as $item) {
            $domain = strtolower(trim($item['domain'] ?? ''));
            if ($domain) {
                $dbByDomain[$domain] = $item;
            }
        }

        // Map Google domains
        $googleByDomain = [];
        foreach ($googleData as $item) {
            $domain = strtolower(trim($item['domain'] ?? ''));
            if ($domain) {
                $googleByDomain[$domain] = $item;
            }
        }

        // Define fields to compare
        $compareFields = [
            'name', 'assigned_email', 'proprietario', 'vendita', 
            'expiry_date', 'cpanel', 'epanel', 'notes', 'remark', 'dns'
        ];

        // Find differences
        foreach ($dbByDomain as $domain => $dbItem) {
            if (isset($googleByDomain[$domain])) {
                $comparison['summary']['matches']++;
                
                // Check for value differences
                $diffs = [];
                foreach ($compareFields as $field) {
                    $dbVal = trim($dbItem[$field] ?? '');
                    $googleVal = trim($googleByDomain[$domain][$field] ?? '');
                    
                    if ($dbVal !== $googleVal && (!empty($dbVal) || !empty($googleVal))) {
                        $diffs[$field] = [
                            'db_value' => $dbVal,
                            'google_value' => $googleVal
                        ];
                    }
                }
                
                if (!empty($diffs)) {
                    $comparison['different_values'][] = [
                        'domain' => $domain,
                        'db_values' => $dbItem,
                        'google_values' => $googleByDomain[$domain],
                        'differences' => $diffs
                    ];
                    $comparison['summary']['conflicts']++;
                }
                unset($googleByDomain[$domain]);
            } else {
                $comparison['only_in_db'][] = $dbItem;
            }
        }

        // Remaining items only in Google
        foreach ($googleByDomain as $item) {
            $comparison['only_in_google'][] = $item;
        }

        return $comparison;
    }

    // Merge Google Sheets to Database (forward) - with hosting assignment
    private function mergeGoogleToDatabase($googleData)
    {
        $result = ['added' => 0, 'updated' => 0, 'hosting_created' => 0, 'hosting_updated' => 0, 'errors' => []];
        $currentHostingId = null;
        $hostingMap = []; // Cache for hosting lookups

        foreach ($googleData as $index => $item) {
            try {
                // Extract client data from Google Sheets structure
                // Field mapping: server_name, ip_address, email_address, provider
                $clientName = trim($item['server_name'] ?? '');
                $clientAddress = trim($item['ip_address'] ?? '');
                $clientEmail = trim($item['email_address'] ?? '');
                $clientPiva = trim($item['provider'] ?? '');

                // Process hosting plan if client name exists and different from previous
                if (!empty($clientName) && $clientName !== ($hostingMap['last_client'] ?? null)) {
                    // Check if hosting plan already exists
                    $stmt = $this->db->prepare("SELECT id FROM hosting_plans WHERE server_name = ?");
                    $stmt->execute([$clientName]);
                    $currentHostingId = $stmt->fetchColumn();

                    if ($currentHostingId) {
                        // Update existing hosting plan
                        $updateStmt = $this->db->prepare("
                        UPDATE hosting_plans 
                        SET email_address = ?, ip_address = ?, provider = ?
                        WHERE id = ?
                    ");
                        $updateStmt->execute([$clientEmail, $clientAddress, $clientPiva, $currentHostingId]);
                        $result['hosting_updated']++;
                    } else {
                        // Create new hosting plan
                        $stmt = $this->db->prepare("
                        INSERT INTO hosting_plans 
                        (server_name, ip_address, email_address, provider) 
                        VALUES (?, ?, ?, ?)
                    ");
                        $stmt->execute([$clientName, $clientAddress, $clientEmail, $clientPiva]);
                        $currentHostingId = $this->db->lastInsertId();
                        $result['hosting_created']++;
                    }
                    $hostingMap['last_client'] = $clientName;
                }

                // Extract domain
                $domain = trim($item['domain'] ?? '');
                if (empty($domain)) {
                    continue;
                }

                // Try to find existing website by domain
                $website = $this->websiteModel->getWebsiteByDomain($domain);

                // Prepare update data with all Google values
                $data = [
                    'domain' => $domain,
                    'name' => trim($item['name'] ?? ''),
                    'assigned_email' => trim($item['assigned_email'] ?? ''),
                    'proprietario' => trim($item['proprietario'] ?? ''),
                    'vendita' => trim($item['vendita'] ?? '') ?: '0',
                    'expiry_date' => trim($item['expiry_date'] ?? ''),
                    'cpanel' => trim($item['cpanel'] ?? ''),
                    'epanel' => trim($item['epanel'] ?? ''),
                    'notes' => trim($item['notes'] ?? ''),
                    'remark' => trim($item['remark'] ?? ''),
                    'dns' => trim($item['dns'] ?? ''),
                    'email_server' => trim($item['email_server'] ?? ''),
                    'hosting_id' => $currentHostingId
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
        $result = ['updated' => 0, 'errors' => []];

        try {
            $client = $this->getGoogleClient($settings);
            $service = new \Google\Service\Sheets($client);

            $spreadsheet = $service->spreadsheets->get($settings['sheet_id'], ['includeGridData' => false]);
            
            // Find the target sheet
            $sheet = null;
            foreach ($spreadsheet->getSheets() as $s) {
                if ($s->getProperties()->getTitle() === $settings['sheet_name']) {
                    $sheet = $s;
                    break;
                }
            }
            
            if ($sheet === null) {
                throw new Exception("Sheet '{$settings['sheet_name']}' not found");
            }

            $sheetId = $sheet->getProperties()->getSheetId();

            // Use the same data format as export
            $preparedData = $this->websiteModel->prepareForGoogleSheets();
            $websiteData = $preparedData['data'];

            // Prepare headers (same as export)
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
                    'Notes'
                ]
            ];

            $allData = array_merge($headers, $websiteData);

            // Clear existing values
            $service->spreadsheets_values->clear(
                $settings['sheet_id'],
                $settings['sheet_name'] . '!A:Z',
                new \Google\Service\Sheets\ClearValuesRequest()
            );

            // Insert new values
            $service->spreadsheets_values->update(
                $settings['sheet_id'],
                $settings['sheet_name'] . '!A1',
                new \Google\Service\Sheets\ValueRange(['values' => $allData]),
                ['valueInputOption' => 'USER_ENTERED']
            );

            // Apply formatting (same as export - for seamless merge)
            $this->applySheetFormatting($service, $settings, $sheetId, count($allData));

            $result['updated'] = count($websiteData);
        } catch (Exception $e) {
            $result['errors'][] = "Error during merge: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Apply consistent formatting to Google Sheet
     * Used by both export and merge operations
     */
    private function applySheetFormatting($service, $settings, $sheetId, $totalRows)
    {
        $lastRow = $totalRows;

        // Helper function to convert hex color to RGB
        $hexToRgb = function ($hex) {
            $hex = ltrim($hex, '#');
            return [
                'red' => hexdec(substr($hex, 0, 2)) / 255,
                'green' => hexdec(substr($hex, 2, 2)) / 255,
                'blue' => hexdec(substr($hex, 4, 2)) / 255
            ];
        };

        // Professional color scheme
        $colors = [
            'blueHeaderDark' => ['rgb' => '#003D7A'],
            'redHeaderDark' => ['rgb' => '#C1402B'],
            'greenHeaderLight' => ['rgb' => '#70AD47'],
            'greenHeaderDark' => ['rgb' => '#54873B'],
            'textLight' => ['rgb' => '#FFFFFF'],
            'textDark' => ['rgb' => '#2E2E2E']
        ];

        // Column width specifications (in pixels)
        // Wide columns for text data: A (Nome), B (Indirizzo), C (Email), D (P.IVA), 
        // F (Dettaglio Servizi), G (Email Assegnata), O (Email panel), P (Bug report), Q (Notes)
        // Narrow columns: E (Tipologia), H (Proprietario), I (Registrante), J (Scadenza), K (Costo), L (Prezzo), M (DNS), N (Cpanel)
        $columnWidths = [
            'A' => 200,  // Nome - WIDE
            'B' => 220,  // Indirizzo - WIDE
            'C' => 220,  // Email - WIDE
            'D' => 150,  // P.IVA - WIDE
            'E' => 140,  // Tipologia - NARROW
            'F' => 200,  // Dettaglio Servizi - WIDE
            'G' => 220,  // Email Assegnata - WIDE
            'H' => 140,  // Proprietario - NARROW
            'I' => 140,  // Registrante - NARROW
            'J' => 130,  // Scadenza - NARROW
            'K' => 140,  // Costo Server - NARROW
            'L' => 150,  // Prezzo di Vendita - NARROW
            'M' => 130,  // Direct DNS - NARROW
            'N' => 150,  // User Name cpanel - NARROW
            'O' => 220,  // Email panel - WIDE
            'P' => 200,  // Bug report - WIDE
            'Q' => 200   // Notes - WIDE
        ];

        // Build formatting requests
        $requests = [
            // Set column widths
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 0,
                        'endIndex' => 1
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['A']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 1,
                        'endIndex' => 2
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['B']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 2,
                        'endIndex' => 3
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['C']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 3,
                        'endIndex' => 4
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['D']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 4,
                        'endIndex' => 5
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['E']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 5,
                        'endIndex' => 6
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['F']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 6,
                        'endIndex' => 7
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['G']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 7,
                        'endIndex' => 8
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['H']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 8,
                        'endIndex' => 9
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['I']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 9,
                        'endIndex' => 10
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['J']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 10,
                        'endIndex' => 11
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['K']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 11,
                        'endIndex' => 12
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['L']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 12,
                        'endIndex' => 13
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['M']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 13,
                        'endIndex' => 14
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['N']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 14,
                        'endIndex' => 15
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['O']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 15,
                        'endIndex' => 16
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['P']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 16,
                        'endIndex' => 17
                    ],
                    'properties' => [
                        'pixelSize' => $columnWidths['Q']
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),

            // Unmerge any existing cells first
            new \Google\Service\Sheets\Request([
                'unmergeCells' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 2,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => 17
                    ]
                ]
            ]),
            // Merge B1-D1
            new \Google\Service\Sheets\Request([
                'mergeCells' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                        'startColumnIndex' => 1,
                        'endColumnIndex' => 4
                    ],
                    'mergeType' => 'MERGE_ALL'
                ]
            ]),
            // Merge E1-Q1
            new \Google\Service\Sheets\Request([
                'mergeCells' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                        'startColumnIndex' => 4,
                        'endColumnIndex' => 17
                    ],
                    'mergeType' => 'MERGE_ALL'
                ]
            ]),

            // A1 - Dark blue header
            new \Google\Service\Sheets\Request([
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => 1
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'backgroundColor' => $hexToRgb($colors['blueHeaderDark']['rgb']),
                            'textFormat' => [
                                'bold' => true,
                                'foregroundColor' => $hexToRgb($colors['textLight']['rgb']),
                                'fontSize' => 13
                            ],
                            'horizontalAlignment' => 'CENTER',
                            'verticalAlignment' => 'MIDDLE'
                        ]
                    ],
                    'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment)'
                ]
            ]),

            // B1-D1 - Dark red header (customer info)
            new \Google\Service\Sheets\Request([
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                        'startColumnIndex' => 1,
                        'endColumnIndex' => 4
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'backgroundColor' => $hexToRgb($colors['redHeaderDark']['rgb']),
                            'textFormat' => [
                                'bold' => true,
                                'foregroundColor' => $hexToRgb($colors['textLight']['rgb']),
                                'fontSize' => 15
                            ],
                            'horizontalAlignment' => 'CENTER',
                            'verticalAlignment' => 'MIDDLE'
                        ]
                    ],
                    'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment)'
                ]
            ]),

            // E1-Q1 - Dark green header (service info)
            new \Google\Service\Sheets\Request([
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                        'startColumnIndex' => 4,
                        'endColumnIndex' => 17
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'backgroundColor' => $hexToRgb($colors['greenHeaderDark']['rgb']),
                            'textFormat' => [
                                'bold' => true,
                                'foregroundColor' => $hexToRgb($colors['textLight']['rgb']),
                                'fontSize' => 14
                            ],
                            'horizontalAlignment' => 'CENTER',
                            'verticalAlignment' => 'MIDDLE'
                        ]
                    ],
                    'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment)'
                ]
            ]),

            // A2-D2 - Light blue (customer column headers)
            new \Google\Service\Sheets\Request([
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 1,
                        'endRowIndex' => 2,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => 1
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'backgroundColor' => $hexToRgb('#D6E4F5'),
                            'textFormat' => [
                                'bold' => true,
                                'foregroundColor' => $hexToRgb($colors['textDark']['rgb']),
                                'fontSize' => 10
                            ],
                            'horizontalAlignment' => 'CENTER',
                            'verticalAlignment' => 'MIDDLE'
                        ]
                    ],
                    'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment)'
                ]
            ]),

            // B2-D2 - Light red (customer column headers)
            new \Google\Service\Sheets\Request([
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 1,
                        'endRowIndex' => 2,
                        'startColumnIndex' => 1,
                        'endColumnIndex' => 4
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'backgroundColor' => $hexToRgb('#F4CCCC'),
                            'textFormat' => [
                                'bold' => true,
                                'foregroundColor' => $hexToRgb($colors['textDark']['rgb']),
                                'fontSize' => 10
                            ],
                            'horizontalAlignment' => 'CENTER',
                            'verticalAlignment' => 'MIDDLE'
                        ]
                    ],
                    'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment)'
                ]
            ]),

            // E2-Q2 - Light green (service column headers)
            new \Google\Service\Sheets\Request([
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 1,
                        'endRowIndex' => 2,
                        'startColumnIndex' => 4,
                        'endColumnIndex' => 17
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'backgroundColor' => $hexToRgb('#E2EFDA'),
                            'textFormat' => [
                                'bold' => true,
                                'foregroundColor' => $hexToRgb($colors['textDark']['rgb']),
                                'fontSize' => 10
                            ],
                            'horizontalAlignment' => 'CENTER',
                            'verticalAlignment' => 'MIDDLE',
                            'wrapStrategy' => 'WRAP'
                        ]
                    ],
                    'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,wrapStrategy)'
                ]
            ]),

            // Data cell formatting - centered with padding
            new \Google\Service\Sheets\Request([
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 2,
                        'endRowIndex' => $lastRow,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => 17
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'wrapStrategy' => 'WRAP',
                            'horizontalAlignment' => 'CENTER',
                            'verticalAlignment' => 'MIDDLE',
                            'textFormat' => [
                                'foregroundColor' => $hexToRgb($colors['textDark']['rgb']),
                                'fontSize' => 11
                            ]
                        ]
                    ],
                    'fields' => 'userEnteredFormat(wrapStrategy,horizontalAlignment,verticalAlignment,textFormat)'
                ]
            ]),

            // Column A (Nome) - Make all data rows bold
            new \Google\Service\Sheets\Request([
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 2,
                        'endRowIndex' => $lastRow,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => 1
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'textFormat' => [
                                'bold' => true,
                                'foregroundColor' => $hexToRgb($colors['textDark']['rgb']),
                                'fontSize' => 11
                            ],
                            'wrapStrategy' => 'WRAP',
                            'horizontalAlignment' => 'CENTER',
                            'verticalAlignment' => 'MIDDLE'
                        ]
                    ],
                    'fields' => 'userEnteredFormat(textFormat,wrapStrategy,horizontalAlignment,verticalAlignment)'
                ]
            ])
        ];

        // Execute batch update
        $batchUpdateRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
            'requests' => $requests
        ]);

        $service->spreadsheets->batchUpdate($settings['sheet_id'], $batchUpdateRequest);
    }

    /**
     * Bidirectional merge - merge both Google Sheets and Database
     * Combines Google→DB and DB→Google operations with conflict resolution
     */
    private function mergeBidirectional($googleData, $dbData, $settings)
    {
        $result = [
            'added_to_db' => 0,
            'updated_in_db' => 0,
            'added_to_google' => 0,
            'updated_in_google' => 0,
            'conflicts_resolved' => 0,
            'errors' => []
        ];

        try {
            // First: merge Google to DB (imports new/updated records from Google)
            $dbResult = $this->mergeGoogleToDatabase($googleData);
            $result['added_to_db'] = $dbResult['added'] ?? 0;
            $result['updated_in_db'] = $dbResult['updated'] ?? 0;
            if (!empty($dbResult['errors'])) {
                $result['errors'] = array_merge($result['errors'], $dbResult['errors']);
            }

            // Second: merge DB to Google (exports all DB records to Google)
            $googleResult = $this->mergeDatabaseToGoogle($dbData, $settings);
            $result['updated_in_google'] = $googleResult['updated'] ?? 0;
            if (!empty($googleResult['errors'])) {
                $result['errors'] = array_merge($result['errors'], $googleResult['errors']);
            }
        } catch (Exception $e) {
            $result['errors'][] = "Bidirectional merge failed: " . $e->getMessage();
        }

        return $result;
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
}