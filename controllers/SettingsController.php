<?php
class SettingsController
{
    private $emailModel;
    private $cronModel;
    private $settingsModel;
    private $hostingModel;
    private $websiteModel;

    public function __construct($pdo)
    {
        $this->emailModel = new Email($pdo);
        $this->cronModel = new CronModel($pdo);
        $this->settingsModel = new SettingsModel($pdo);
        $this->websiteModel = new Website($pdo);
        $this->hostingModel = new Hosting($pdo);
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
                $_SESSION['message'] = "Impostazioni SMTP aggiornate correttamente";
            } else {
                $error = "Errore durante l'aggiornamento delle impostazioni SMTP";
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
                $_SESSION['message'] = "Impostazioni cron aggiornate correttamente";
            } else {
                $_SESSION['error'] = "Errore durante l'aggiornamento delle impostazioni cron";
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
        $settings = [
            'sheet_id' => trim($_POST['google_sheet_id'] ?? ''),
            'sheet_name' => trim($_POST['google_sheet_name'] ?? 'Sheet1'),
            'credentials' => trim($_POST['google_credentials'] ?? ''),
            'enabled' => isset($_POST['google_sync_enabled']) ? 1 : 0
        ];

        // Validate required fields
        if (empty($settings['sheet_id']) || empty($settings['credentials'])) {
            throw new Exception("Sheet ID and credentials are required");
        }

        // Save settings
        $this->settingsModel->saveGoogleSheetsSettings($settings);

        // Handle specific actions
        if (isset($_POST['export_to_google'])) {
            $result = $this->exportToGoogleSheets();
            $_SESSION['google_sync_result'] = $result;
            $_SESSION['message'] = "Esportazione completata con successo";
        } elseif (isset($_POST['import_from_google'])) {
            $result = $this->importFromGoogleSheets();
            $_SESSION['google_sync_result'] = $result;
            $_SESSION['message'] = "Importazione completata con successo";
        } elseif (isset($_POST['sync_with_google'])) {
            $result = $this->syncWithGoogleSheets();
            $_SESSION['google_sync_result'] = $result;
            $_SESSION['message'] = "Sincronizzazione completata con successo";
        }
    }

    private function exportToGoogleSheets(): array
    {
        require_once APP_PATH . '/vendor/autoload.php';
        $settings = $this->settingsModel->getGoogleSheetsSettings();

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
            $client = $this->getGoogleClient();
            $service = new \Google\Service\Sheets($client);

            $spreadsheet = $service->spreadsheets->get($settings['sheet_id'], ['includeGridData' => true]);
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

            // Prepare headers
            $headers = [
                ['Clienti', 'Informazioni per il cliente', '', '', 'Informazioni sul servizio'],
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
                    'Prezzo di vendita  (iva inclusa)',
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

            $allData = array_merge($headers, $websiteData);
            $lastRow = count($allData);

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

            // Start building formatting requests
            $requests = [
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
                // Merge E1-P1
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

                // ===== HEADER ROW 1 FORMATTING =====
                // A1 - Dark blue
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
                                'verticalAlignment' => 'MIDDLE',
                                'padding' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,padding)'
                    ]
                ]),
                // B1-D1 - Dark red (merged cells)
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
                                'verticalAlignment' => 'MIDDLE',
                                'padding' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,padding)'
                    ]
                ]),
                // E1-P1 - Dark green (merged cells)
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
                                    'fontSize' => 15
                                ],
                                'horizontalAlignment' => 'CENTER',
                                'verticalAlignment' => 'MIDDLE',
                                'padding' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,padding)'
                    ]
                ]),

                // ===== HEADER ROW 2 FORMATTING =====
                // A2 - Light blue
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
                                'backgroundColor' => $hexToRgb($colors['blueHeaderLight']['rgb']),
                                'textFormat' => [
                                    'bold' => true,
                                    'foregroundColor' => $hexToRgb($colors['textLight']['rgb']),
                                    'fontSize' => 10
                                ],
                                'horizontalAlignment' => 'CENTER',
                                'verticalAlignment' => 'MIDDLE',
                                'padding' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,padding)'
                    ]
                ]),
                // B2-D2 - Light red
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
                                'backgroundColor' => $hexToRgb($colors['redHeaderLight']['rgb']),
                                'textFormat' => [
                                    'bold' => true,
                                    'foregroundColor' => $hexToRgb($colors['textLight']['rgb']),
                                    'fontSize' => 10
                                ],
                                'horizontalAlignment' => 'CENTER',
                                'verticalAlignment' => 'MIDDLE',
                                'padding' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,padding)'
                    ]
                ]),
                // E2-P2 - Light green
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
                                'backgroundColor' => $hexToRgb($colors['greenHeaderLight']['rgb']),
                                'textFormat' => [
                                    'bold' => true,
                                    'foregroundColor' => $hexToRgb($colors['textLight']['rgb']),
                                    'fontSize' => 10
                                ],
                                'horizontalAlignment' => 'CENTER',
                                'verticalAlignment' => 'MIDDLE',
                                'wrapStrategy' => 'WRAP',
                                'padding' => [
                                    'top' => 10,
                                    'right' => 10,
                                    'bottom' => 10,
                                    'left' => 10
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,wrapStrategy,padding)'
                    ]
                ]),

                // Basic cell formatting for all data cells - Centered with padding
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
                                ],
                                'padding' => [
                                    'top' => 5,
                                    'right' => 8,
                                    'bottom' => 8,
                                    'left' => 5
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(wrapStrategy,horizontalAlignment,verticalAlignment,textFormat,padding)'
                    ]
                ]),
                // Special formatting for column A (Name) - Larger and bolder text
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
                                    'fontSize' => 13,  // Slightly larger than other cells
                                    'foregroundColor' => $hexToRgb($colors['textDark']['rgb'])
                                ],
                                'padding' => [
                                    'top' => 8,
                                    'right' => 8,
                                    'bottom' => 8,
                                    'left' => 8
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(textFormat,padding)'
                    ]
                ]),
                // Freeze first column and two header rows
                new \Google\Service\Sheets\Request([
                    'updateSheetProperties' => [
                        'properties' => [
                            'sheetId' => $sheetId,
                            'gridProperties' => [
                                'frozenRowCount' => 2,
                                'frozenColumnCount' => 1
                            ]
                        ],
                        'fields' => 'gridProperties.frozenRowCount,gridProperties.frozenColumnCount'
                    ]
                ])
            ];

            // Add client-specific borders (only under last row of each client group)
            foreach ($clientRowGroups as $hostingId => $rows) {
                if (!empty($rows)) {
                    $lastRowIndex = max($rows) + 2; // +2 to account for header rows (1-based)

                    $requests[] = new \Google\Service\Sheets\Request([
                        'updateBorders' => [
                            'range' => [
                                'sheetId' => $sheetId,
                                'startRowIndex' => $lastRowIndex,
                                'endRowIndex' => $lastRowIndex + 1,
                                'startColumnIndex' => 0,
                                'endColumnIndex' => 17
                            ],
                            'bottom' => [
                                'style' => 'SOLID_MEDIUM',
                                'width' => 2,
                                'color' => ['red' => 0.3, 'green' => 0.3, 'blue' => 0.3]
                            ]
                        ]
                    ]);
                }
            }

            // Add column width requests
            foreach ($columnWidths as $col => $width) {
                $colIndex = ord(strtoupper($col)) - ord('A');
                $requests[] = new \Google\Service\Sheets\Request([
                    'updateDimensionProperties' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'COLUMNS',
                            'startIndex' => $colIndex,
                            'endIndex' => $colIndex + 1
                        ],
                        'properties' => ['pixelSize' => $width],
                        'fields' => 'pixelSize'
                    ]
                ]);
            }

            // Apply formatting
            $service->spreadsheets->batchUpdate(
                $settings['sheet_id'],
                new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest(['requests' => $requests])
            );

            return [
                'exported' => count($websiteData),
                'updated' => 0,
                'errors' => 0
            ];
        } catch (Exception $e) {
            error_log("Google Sheets export error: " . $e->getMessage());
            return [
                'exported' => 0,
                'updated' => 0,
                'errors' => 1,
                'message' => $e->getMessage()
            ];
        }
    }





    private function importFromGoogleSheets()
    {
        require_once APP_PATH . '/vendor/autoload.php';
        $settings = $this->settingsModel->getGoogleSheetsSettings();

        // Initialize Google Client
        $client = $this->getGoogleClient();
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
    }

    public function syncWithGoogleSheets()
    {
        require_once APP_PATH . '/vendor/autoload.php';
        $settings = $this->settingsModel->getGoogleSheetsSettings();

        // 1. Get data from both sources
        $dbData = $this->websiteModel->getAllWebsites();
        $client = $this->getGoogleClient();
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

    private function getGoogleClient()
    {
        $settings = $this->settingsModel->getGoogleSheetsSettings();

        if (empty($settings['credentials'])) {
            throw new Exception("Google Sheets credentials not configured");
        }

        $client = new Google\Client();
        $client->setApplicationName('Your Application Name');
        $client->setScopes([Google\Service\Sheets::SPREADSHEETS]);

        try {
            $credentials = json_decode($settings['credentials'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Credenziali Google non valide JSON");
            }

            $client->setAuthConfig($credentials);
        } catch (Exception $e) {
            throw new Exception("Impossibile inizializzare Google Client: " . $e->getMessage());
        }

        return $client;
    }
}
