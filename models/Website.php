<?php
class Website
{
    private $pdo;
    const DEFAULT_SHEET_NAME = 'Sheet1';

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Updated method with sorting, filtering and pagination
    public function getWebsites($search = '', $sort = 'hosting_server', $order = 'ASC', $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        $searchTerm = '%' . $search . '%';

        // Validate inputs
        $allowedSorts = ['hosting_server', 'domain', 'name', 'email_server', 'expiry_date'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'hosting_server';
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        // Build ORDER BY safely
        if ($sort === 'hosting_server') {
            $orderBy = "COALESCE(h.server_name, 'ZZZZZZZZ') $order, w.domain ASC"; // NULLs last
        } else {
            $orderBy = "w.$sort $order, w.domain ASC";
        }

        $sql = "SELECT 
        w.*,
        h.server_name AS hosting_server
    FROM websites w
    LEFT JOIN hosting_plans h ON w.hosting_id = h.id
    WHERE w.domain LIKE ? OR w.name LIKE ?
    ORDER BY $orderBy
    LIMIT ? OFFSET ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $perPage, $offset]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Keep existing method but make it use the new one with defaults
    public function getAllWebsites()
    {
        return $this->getWebsites('', 'expiry_date', 'asc', 1, PHP_INT_MAX);
    }

    // New method for counting filtered results
    public function getWebsiteCount($search = '')
    {
        $searchTerm = '%' . $search . '%';

        // Simplified query without the complex conditional
        $sql = "
        SELECT COUNT(*) 
        FROM websites w
        LEFT JOIN hosting_plans h ON w.hosting_id = h.id
        WHERE w.name LIKE ? OR 
              w.domain LIKE ? OR 
              w.email_server LIKE ? OR
              h.server_name LIKE ?
    ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);

        return $stmt->fetchColumn();
    }

    // Existing methods (unchanged)
    public function getWebsiteById($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT w.*, h.server_name as hosting_server 
            FROM websites w 
            LEFT JOIN hosting_plans h ON w.hosting_id = h.id
            WHERE w.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function createWebsite($data)
    {
        $stmt = $this->pdo->prepare("
        INSERT INTO websites 
        (name, domain, hosting_id, email_server, expiry_date, status, vendita, assigned_email, proprietario, dns, cpanel, epanel, notes, remark) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
        return $stmt->execute([
            $data['name'],
            $data['domain'],
            $data['hosting_id'] ?: null,
            $data['email_server'],
            $data['expiry_date'],
            $data['status'],
            $data['vendita'],
            $data['assigned_email'],
            $data['proprietario'] ?? null,
            $data['dns'] ?? null,
            $data['cpanel'] ?? null,
            $data['epanel'] ?? null,
            $data['notes'] ?? null,
            $data['remark'] ?? null
        ]);
    }

    public function updateWebsite($id, $data)
    {
        $stmt = $this->pdo->prepare("
        UPDATE websites SET 
        name = ?, 
        domain = ?, 
        hosting_id = ?, 
        email_server = ?, 
        expiry_date = ?, 
        status = ?, 
        vendita = ?, 
        assigned_email = ?,
        proprietario = ?,
        dns = ?,
        cpanel = ?,
        epanel = ?,
        notes = ?,
        remark = ? 
        WHERE id = ?
    ");
        return $stmt->execute([
            $data['name'],
            $data['domain'],
            $data['hosting_id'] ?: null,
            $data['email_server'],
            $data['expiry_date'],
            $data['status'],
            $data['vendita'],
            $data['assigned_email'],
            $data['proprietario'] ?? null,
            $data['dns'] ?? null,
            $data['cpanel'] ?? null,
            $data['epanel'] ?? null,
            $data['notes'] ?? null,
            $data['remark'] ?? null,
            $id
        ]);
    }

    public function getBuggyWebsitesCount()
    {
        $stmt = $this->pdo->prepare("
        SELECT COUNT(*) 
        FROM websites 
        WHERE notes IS NOT NULL 
        AND notes != '' 
        AND LOWER(notes) NOT IN ('none', 'nessuno')
    ");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getBuggyWebsites()
    {
        $stmt = $this->pdo->prepare("
        SELECT w.*, h.server_name AS hosting_server
        FROM websites w
        LEFT JOIN hosting_plans h ON w.hosting_id = h.id
        WHERE w.notes IS NOT NULL 
        AND w.notes != '' 
        AND LOWER(w.notes) NOT IN ('none', 'nessuno')
        ORDER BY h.server_name ASC
    ");
        $stmt->execute();
        return $stmt->fetchAll();
    }


    public function getExpiredWebsitesCount()
    {
        $stmt = $this->pdo->prepare("
        SELECT COUNT(*) 
        FROM websites 
        WHERE expiry_date < CURDATE()
    ");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getExpiredWebsites()
    {
        $stmt = $this->pdo->prepare("
        SELECT w.*, h.server_name as hosting_server 
        FROM websites w
        LEFT JOIN hosting_plans h ON w.hosting_id = h.id
        WHERE w.expiry_date < CURDATE()
        ORDER BY w.expiry_date DESC
    ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function deleteWebsite($id)
    {
        // First delete all related notifications
        $this->pdo->prepare("DELETE FROM website_notifications WHERE website_id = ?")
            ->execute([$id]);

        // Then delete the website
        $stmt = $this->pdo->prepare("DELETE FROM websites WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function importFromExcel($filePath)
    {
        require_once APP_PATH . '/vendor/autoload.php';

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        // Skip headers (first 2 rows)
        $rows = array_slice($rows, 2);

        // Get all existing hosting plans for mapping
        $hostingPlans = $this->pdo->query("SELECT id, LOWER(TRIM(server_name)) as server_name_lower, server_name FROM hosting_plans")->fetchAll(PDO::FETCH_ASSOC);
        $hostingMap = [];
        foreach ($hostingPlans as $plan) {
            $hostingMap[$plan['server_name_lower']] = $plan['id'];
        }

        $results = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'hosting_created' => 0
        ];

        $currentHostingId = null;
        $currentHostingName = null;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 3; // Account for header rows

            // Skip completely empty rows
            if (empty(array_filter($row, function ($value) {
                return $value !== null && $value !== '';
            }))) {
                continue;
            }

            // Helper function to safely trim values
            $safeTrim = function ($value) {
                return $value === null ? null : trim($value);
            };

            // Process Informazioni per il cliente (columns A-D)
            $clientName = $safeTrim($row[0] ?? null);
            $clientAddress = $safeTrim($row[1] ?? null);
            $clientEmail = $safeTrim($row[2] ?? null);
            $clientPiva = $safeTrim($row[3] ?? null);

            // Process Informazioni sul servizio (columns E-N)
            $serviceData = [
                'name' => $safeTrim($row[4] ?? ''),
                'domain' => $safeTrim($row[5] ?? ''),
                'assigned_email' => $safeTrim($row[6] ?? ''),
                'proprietario' => $safeTrim($row[7] ?? null),
                'email_server' => $safeTrim($row[8] ?? null),
                'expiry_date' => $safeTrim($row[9] ?? date('Y-m-d', strtotime('+1 year'))),
                'status' => $safeTrim($row[10] ?? ''),
                'vendita' => $safeTrim($row[11] ?? ''),
                'dns' => $safeTrim($row[12] ?? null),
                'cpanel' => $safeTrim($row[13] ?? null),
                'epanel' => $safeTrim($row[14] ?? null),
                'notes' => $safeTrim($row[15] ?? null),
                'remark' => $safeTrim($row[16] ?? null)
            ];

            // Validate required service fields
            if (empty($serviceData['domain'])) {
                $results['skipped']++;
                $results['errors'][] = [
                    'row' => $rowNumber,
                    'domain' => '',
                    'message' => 'DETTAGLIO SERVIZI (Domain) is required'
                ];
                continue;
            }

            // Only process new hosting client if name is provided (not null or empty string)
            if ($clientName !== null && $clientName !== '') {
                $serverNameKey = strtolower($clientName);
                $hostingId = $hostingMap[$serverNameKey] ?? null;

                if (!$hostingId) {
                    // Create new hosting client
                    $hostingData = [
                        'server_name' => $clientName,
                        'provider' => $clientPiva,
                        'email_address' => $clientEmail,
                        'ip_address' => $clientAddress
                    ];

                    try {
                        $stmt = $this->pdo->prepare("
                        INSERT INTO hosting_plans 
                        (server_name, provider, email_address, ip_address) 
                        VALUES (?, ?, ?, ?)
                    ");
                        $stmt->execute([
                            $hostingData['server_name'],
                            $hostingData['provider'],
                            $hostingData['email_address'],
                            $hostingData['ip_address']
                        ]);

                        $hostingId = $this->pdo->lastInsertId();
                        $hostingMap[$serverNameKey] = $hostingId;
                        $results['hosting_created']++;
                    } catch (PDOException $e) {
                        $results['skipped']++;
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'domain' => $serviceData['domain'],
                            'message' => 'Failed to create hosting client: ' . $e->getMessage()
                        ];
                        continue;
                    }
                }

                // Update current hosting reference
                $currentHostingId = $hostingId;
                $currentHostingName = $clientName;
            }

            // If we have a current hosting client but no new client specified in this row,
            // maintain the existing association
            $serviceData['hosting_id'] = $currentHostingId;

            try {
                // Check for existing website by domain
                $existing = $this->getWebsiteByDomain($serviceData['domain']);

                if ($existing) {
                    $this->updateWebsite($existing['id'], $serviceData);
                    $results['updated']++;
                } else {
                    $this->createWebsite($serviceData);
                    $results['imported']++;
                }
            } catch (PDOException $e) {
                $results['skipped']++;
                $results['errors'][] = [
                    'row' => $rowNumber,
                    'domain' => $serviceData['domain'],
                    'message' => $e->getMessage()
                ];
                error_log("Import failed for {$serviceData['domain']}: " . $e->getMessage());
            }
        }

        return $results;
    }

    public function getWebsiteByDomain($domain)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM websites WHERE domain = ?");
        $stmt->execute([$domain]);
        return $stmt->fetch();
    }

    public function exportToExcel()
    {
        require_once APP_PATH . '/vendor/autoload.php';

        // Get all websites with full hosting information
        $sql = "SELECT w.*, h.server_name, h.provider, h.email_address, h.ip_address 
        FROM websites w
        LEFT JOIN hosting_plans h ON w.hosting_id = h.id
        ORDER BY h.server_name, w.domain";
        $websites = $this->pdo->query($sql)->fetchAll();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Define header structure
        $headers = [
            'Informazioni per il cliente' => [
                'Name' => 'h.server_name',
                'Address' => 'h.ip_address',
                'Email' => 'h.email_address',
                'P.IVA' => 'h.provider'
            ],
            'Informazioni sul servizio' => [
                'TIPOLOGIA DI SERVIZI' => 'w.name',
                'DETTAGLIO SERVIZI' => 'w.domain',
                'EMAIL ASSEGNATA' => 'w.assigned_email',
                'PROPRIETARIO' => 'w.proprietario',
                'REGISTRANTE' => 'w.email_server',
                'SCADENZA' => 'w.expiry_date',
                'COSTO SERVER (iva inclusa)' => 'w.status',
                'Prezzo di vendita' => 'w.vendita',
                'Direct DNS A' => 'w.dns',
                'User Name cpanel' => 'w.cpanel',
                'Email panel' => 'w.epanel',
                'Bug report' => 'w.notes',
                'Notes' => 'w.remark'
            ]
        ];

        // Define custom column widths
        $columnWidths = [
            'A' => 25, // Name
            'B' => 20, // Address
            'C' => 30, // Email
            'D' => 15, // P.IVA
            'E' => 25, // TIPOLOGIA DI SERVIZI
            'F' => 30, // DETTAGLIO SERVIZI
            'G' => 25, // EMAIL ASSEGNATA
            'H' => 20, // PROPRIETARIO
            'I' => 25, // REGISTRANTE
            'J' => 15, // SCADENZA
            'K' => 20, // COSTO SERVER
            'L' => 20, // PREZZO DI VENDITA
            'M' => 20, // Direct DNS A
            'N' => 20, // User Name cpanel
            'O' => 20, // Email panel
            'P' => 40, // Bug report
            'Q' => 40  // Notes
        ];

        // Professional color scheme (dark blue gradient with accent colors)
        $colors = [
            'groupHeader' => ['rgb' => '1B5E20'],  // Dark blue
            'columnHeader' => ['rgb' => '2E7D32'], // Medium dark blue
            'clientNameHighlight' => ['rgb' => 'ccc'], // Red accent for client names
            'textLight' => ['rgb' => 'FFFFFF'],    // White text
            'textDark' => ['rgb' => '333333'],     // Dark text
            'rowBg' => ['rgb' => 'F8F9FA']        // Light gray for alternate rows
        ];

        // Set headers with styling
        $col = 'A';
        foreach ($headers as $group => $columns) {
            // Group header
            $sheet->setCellValue($col . '1', $group);
            $sheet->mergeCells($col . '1:' . chr(ord($col) + count($columns) - 1) . '1');
            $sheet->getStyle($col . '1')->applyFromArray([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => $colors['groupHeader']
                ],
                'font' => [
                    'bold' => true,
                    'color' => $colors['textLight'],
                    'size' => 13
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    'wrapText' => true
                ]
            ]);

            // Column headers
            $row = 2;
            foreach ($columns as $header => $field) {
                $sheet->setCellValue($col . $row, $header);
                $sheet->getStyle($col . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => $colors['columnHeader']
                    ],
                    'font' => [
                        'bold' => true,
                        'color' => $colors['textLight']
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        'wrapText' => true
                    ]
                ]);

                // Set custom column width if defined
                if (isset($columnWidths[$col])) {
                    $sheet->getColumnDimension($col)->setWidth($columnWidths[$col]);
                }

                $col++;
            }
        }

        // Add data
        $currentClient = null;
        $row = 3;
        $alternateRow = false;

        foreach ($websites as $website) {
            $col = 'A';

            // Alternate row background color
            $rowBg = $alternateRow ? $colors['rowBg'] : ['rgb' => 'FFFFFF'];
            $alternateRow = !$alternateRow;

            // Only show client info when it changes
            if ($currentClient !== $website['hosting_id']) {
                $currentClient = $website['hosting_id'];

                foreach ($headers['Informazioni per il cliente'] as $field) {
                    $value = $this->getNestedValue($website, $field);
                    $sheet->setCellValue($col . $row, $value);

                    // Highlight client names in column A
                    if ($col === 'A') {
                        $sheet->getStyle($col . $row)->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'color' => $colors['clientNameHighlight']
                            ],
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'color' => ['rgb' => 'FDF2F1'] // Light red background
                            ]
                        ]);
                    } else {
                        $sheet->getStyle($col . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'color' => $rowBg
                            ]
                        ]);
                    }

                    $sheet->getStyle($col . $row)->getAlignment()->setWrapText(true);
                    $sheet->getStyle($col . $row)->getAlignment()->setVertical(
                        \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    );
                    $col++;
                }
            } else {
                $col = chr(ord('A') + count($headers['Informazioni per il cliente']));
            }

            // Always show service info
            foreach ($headers['Informazioni sul servizio'] as $field) {
                $value = $this->getNestedValue($website, $field);
                $sheet->setCellValue($col . $row, $value);

                // Apply styling to service info cells
                $sheet->getStyle($col . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => $rowBg
                    ],
                    'font' => [
                        'color' => $colors['textDark']
                    ],
                    'alignment' => [
                        'wrapText' => true,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ]
                ]);

                // Special formatting for dates
                if ($field === 'w.expiry_date') {
                    $sheet->getStyle($col . $row)
                        ->getNumberFormat()
                        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD);
                }

                // Special formatting for prices
                if ($field === 'w.status') {
                    $numericValue = $this->parseEuroValue($value);
                    if (is_numeric($numericValue)) {
                        $sheet->getStyle($col . $row)
                            ->getNumberFormat()
                            ->setFormatCode('€#,##0.00');
                    }
                }

                $col++;
            }

            // Set fixed row height for data rows
            //$sheet->getRowDimension($row)->setRowHeight(22);
            $row++;
        }

        // Set fixed height for header rows
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(30);

        // Add borders
        $lastCol = chr(ord('A') + array_sum(array_map('count', $headers)) - 1);
        $sheet->getStyle('A1:' . $lastCol . ($row - 1))
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Freeze column A (left) and header rows (top)
        $sheet->freezePane('B3'); // Freezes columns left of B (column A) and rows above 3 (rows 1-2)

        $filename = 'fullmidia_export_' . date('Ymd_His') . '.xlsx';
        $filepath = EXPORT_PATH . '/' . $filename;

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);

        return $filename;
    }

    // Helper function to get nested values
    private function getNestedValue($array, $key)
    {
        if (strpos($key, '.') !== false) {
            list($prefix, $field) = explode('.', $key);
            return $array[$field] ?? '';
        }
        return $array[$key] ?? '';
    }
    private function parseEuroValue($value)
    {
        // Remove all non-numeric characters except decimal point
        $numericValue = preg_replace('/[^0-9.]/', '', $value);

        // Return as float if we got a valid number, otherwise return original string
        return is_numeric($numericValue) ? (float)$numericValue : $value;
    }

    /**
     * Bulk update website statuses based on expiry dates
     * @return int Number of websites updated
     */
    public function updateStatusBasedOnExpiry()
    {
        $today = date('Y-m-d');
        $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
        $fifteenDaysLater = date('Y-m-d', strtotime('+15 days'));

        // Initialize counter
        $count = 0;

        // Update websites expiring in 15-30 days
        $stmt = $this->pdo->prepare("
        UPDATE websites 
        SET dynamic_status = 'scade_presto' 
        WHERE expiry_date BETWEEN ? AND ?
        AND dynamic_status != 'scade_presto'
    ");
        $stmt->execute([$today, $thirtyDaysLater]);
        $count += $stmt->rowCount();

        // Update websites expiring in 1-14 days
        $stmt = $this->pdo->prepare("
        UPDATE websites 
        SET dynamic_status = 'scade_presto' 
        WHERE expiry_date BETWEEN ? AND ?
        AND dynamic_status != 'scade_presto'
    ");
        $stmt->execute([$today, $fifteenDaysLater]);
        $count += $stmt->rowCount();

        // Update expired websites
        $stmt = $this->pdo->prepare("
        UPDATE websites 
        SET dynamic_status = 'scaduto' 
        WHERE expiry_date < ? 
        AND dynamic_status != 'scaduto'
    ");
        $stmt->execute([$today]);
        $count += $stmt->rowCount();

        return $count;
    }
    public function calculateDynamicStatus($expiryDate)
    {
        $today = new DateTime();
        $expiry = new DateTime($expiryDate);

        if ($expiry < $today) {
            return 'scaduto';
        }

        $interval = $today->diff($expiry);
        if ($interval->days <= 30) {
            return 'scade_presto';
        }

        return 'attivo';
    }

    /**
     * Modified version of getExpiringWebsites that uses dynamic calculation
     * while maintaining backward compatibility
     */

    public function getTotalWebsites()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM websites");
        return $stmt->fetchColumn();
    }
    // Replace ALL your expiring websites methods with just these two:

    /**
     * Count expiring websites (simple count - no join needed)
     */
    public function getExpiringWebsitesCount($days = 30)
    {
        $stmt = $this->pdo->prepare("
        SELECT COUNT(*) 
        FROM websites 
        WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ");
        $stmt->execute([$days]);
        return $stmt->fetchColumn();
    }

    /**
     * Get full expiring websites data (with join to hosting_plans)
     */
    public function getExpiringWebsites($days = 30)
    {
        $stmt = $this->pdo->prepare("
        SELECT w.*, h.server_name as hosting_server 
        FROM websites w
        LEFT JOIN hosting_plans h ON w.hosting_id = h.id
        WHERE w.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY w.expiry_date ASC
    ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public function getExpiringWebsite($days = 60)
    {
        $stmt = $this->pdo->prepare("
        SELECT * FROM websites 
        WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        AND status != 'expired'
        ORDER BY expiry_date ASC
    ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    /**
     * Renew website and update expiry date
     * @param int $id Website ID
     * @param string $renewalCost Renewal cost to store in status field
     * @return string New expiry date
     */

    public function renewWebsite($id)
    {
        // Debug start
        file_put_contents(
            'renew_debug.log',
            "[" . date('Y-m-d H:i:s') . "] METHOD STARTED\n",
            FILE_APPEND | FILE_APPEND
        );

        $website = $this->getWebsiteById($id);
        if (!$website) {
            file_put_contents(
                'renew_debug.log',
                "[" . date('Y-m-d H:i:s') . "] WEBSITE NOT FOUND\n",
                FILE_APPEND
            );
            throw new Exception("Website not found");
        }

        file_put_contents(
            'renew_debug.log',
            "[" . date('Y-m-d H:i:s') . "] CURRENT DATE: " . $website['expiry_date'] . "\n",
            FILE_APPEND
        );

        // Calculate new date
        $expiry = new DateTime($website['expiry_date']);
        $expiry->add(new DateInterval('P1Y'));
        $newExpiry = $expiry->format('Y-m-d');

        file_put_contents(
            'renew_debug.log',
            "[" . date('Y-m-d H:i:s') . "] NEW DATE WILL BE: $newExpiry\n",
            FILE_APPEND
        );

        // DEBUG THE QUERY
        $sql = "UPDATE websites SET expiry_date = ? WHERE id = ?";
        file_put_contents(
            'renew_debug.log',
            "[" . date('Y-m-d H:i:s') . "] EXECUTING QUERY: $sql\n",
            FILE_APPEND
        );

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([$newExpiry, $id]);

        file_put_contents(
            'renew_debug.log',
            "[" . date('Y-m-d H:i:s') . "] QUERY " . ($success ? "SUCCEEDED" : "FAILED") . "\n",
            FILE_APPEND
        );

        if (!$success) {
            file_put_contents(
                'renew_debug.log',
                "[" . date('Y-m-d H:i:s') . "] ERROR INFO: " . print_r($stmt->errorInfo(), true) . "\n",
                FILE_APPEND
            );
            throw new Exception("Database update failed");
        }

        // Verify update
        $updatedWebsite = $this->getWebsiteById($id);
        file_put_contents(
            'renew_debug.log',
            "[" . date('Y-m-d H:i:s') . "] ACTUAL NEW DATE IN DB: " . $updatedWebsite['expiry_date'] . "\n",
            FILE_APPEND
        );

        return $newExpiry;
    }




    // New helper methods for sorting validation
    private function validateSortColumn($column)
    {
        $allowed = ['name', 'domain', 'hosting_server', 'expiry_date', 'email_server'];
        return in_array($column, $allowed) ? $column : 'name';
    }

    private function validateSortOrder($order)
    {
        return strtolower($order) === 'desc' ? 'DESC' : 'ASC';
    }


    public function getServicesByHostingId($hostingId)
    {
        $stmt = $this->pdo->prepare("
        SELECT w.*, h.server_name AS hosting_server 
        FROM websites w
        LEFT JOIN hosting_plans h ON w.hosting_id = h.id
        WHERE w.hosting_id = ?
        ORDER BY w.domain ASC
    ");
        $stmt->execute([$hostingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Google Sheets Integration Methods
     */
    public function prepareForGoogleSheets(): array
    {
        // Get all websites with their hosting information
        $stmt = $this->pdo->query("
        SELECT w.*, h.server_name, h.ip_address, h.email_address, h.provider, h.id as hosting_id
        FROM websites w
        LEFT JOIN hosting_plans h ON w.hosting_id = h.id
        ORDER BY COALESCE(h.server_name, 'zzzzzzzz'), w.domain
    ");

        $websites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare the data array
        $data = [];
        $currentHostingId = null;
        $clientRows = []; // Track row indices for each client

        foreach ($websites as $index => $website) {
            $isNewClient = ($currentHostingId !== $website['hosting_id']);
            $currentHostingId = $website['hosting_id'];

            if ($isNewClient) {
                // Start a new client group
                $clientRows[$currentHostingId] = [];
            }

            $rowData = [
                // Client info (only show for first service)
                $isNewClient ? ($website['server_name'] ?? '') : '',
                $isNewClient ? ($website['ip_address'] ?? '') : '',
                $isNewClient ? ($website['email_address'] ?? '') : '',
                $isNewClient ? ($website['provider'] ?? '') : '',
                // Service info (always show)
                $website['name'] ?? '',
                $website['domain'] ?? '',
                $website['assigned_email'] ?? '',
                $website['proprietario'] ?? '',
                $website['email_server'] ?? '',
                $this->formatDate($website['expiry_date'] ?? ''),
                $website['status'] ?? '',
                $website['vendita'] ?? '',
                $website['dns'] ?? '',
                $website['cpanel'] ?? '',
                $website['epanel'] ?? '',
                $website['notes'] ?? '',
                $website['remark'] ?? ''
            ];

            $data[] = $rowData;
            $clientRows[$currentHostingId][] = count($data) - 1; // Store row index (0-based)
        }

        return [
            'data' => $data,
            'clientRows' => $clientRows // Return the client row groupings
        ];
    }

    /**
     * Map single website to Google Sheets row format
     */
    private function mapToSheetFormat(array $website): array
    {
        $hosting = $this->getHostingInfo($website['hosting_id'] ?? null);

        return [
            // Client info (columns A-D)
            $hosting['server_name'] ?? '',
            $hosting['ip_address'] ?? '',
            $hosting['email_address'] ?? '',
            $hosting['provider'] ?? '',

            // Service info (columns E-P)
            $website['name'] ?? '',
            $website['domain'] ?? '',
            $website['assigned_email'] ?? '',
            $website['proprietario'] ?? '',
            $website['email_server'] ?? '',
            $this->formatDate($website['expiry_date'] ?? ''),
            $website['status'] ?? '',
            $website['vendita'] ?? '',
            $website['dns'] ?? '',
            $website['cpanel'] ?? '',
            $website['epanel'] ?? '',
            $website['notes'] ?? '',
            $website['remark'] ?? ''
        ];
    }

    /**
     * Import data from Google Sheets format
     */
    public function importFromSheets(array $sheetRows): array
    {
        $results = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'hosting_created' => 0,
            'hosting_updated' => 0
        ];

        $currentHostingId = null;

        foreach ($sheetRows as $index => $row) {
            $rowNumber = $index + 1;

            // Skip header row (first row only)
            if ($rowNumber <= 1) {
                continue;
            }

            // Skip empty rows
            if (empty(array_filter($row))) {
                $results['skipped']++;
                continue;
            }

            // Ensure we have all columns
            $row = array_pad($row, 17, '');

            try {
                // Extract client data (columns A-D)
                $clientName = trim($row[0]);
                $clientAddress = trim($row[1]);
                $clientEmail = trim($row[2]);
                $clientPiva = trim($row[3]);

                // Process hosting plan if client name exists
                if (!empty($clientName)) {
                    $stmt = $this->pdo->prepare("SELECT id FROM hosting_plans WHERE server_name = ?");
                    $stmt->execute([$clientName]);
                    $currentHostingId = $stmt->fetchColumn();

                    if ($currentHostingId) {
                        // Update existing hosting plan
                        $updateStmt = $this->pdo->prepare("
                        UPDATE hosting_plans 
                        SET email_address = ?, ip_address = ?, provider = ?
                        WHERE id = ?
                    ");
                        $updateStmt->execute([$clientEmail, $clientAddress, $clientPiva, $currentHostingId]);
                        $results['hosting_updated']++;
                    } else {
                        // Create new hosting plan
                        $stmt = $this->pdo->prepare("
                        INSERT INTO hosting_plans 
                        (server_name, ip_address, email_address, provider) 
                        VALUES (?, ?, ?, ?)
                    ");
                        $stmt->execute([$clientName, $clientAddress, $clientEmail, $clientPiva]);
                        $currentHostingId = $this->pdo->lastInsertId();
                        $results['hosting_created']++;
                    }
                }

                // Extract service data (columns E-P)
                $serviceData = [
                    'name' => trim($row[4]),
                    'domain' => strtolower(trim($row[5])), // Normalize domain to lowercase
                    'assigned_email' => trim($row[6]),
                    'proprietario' => trim($row[7]),
                    'email_server' => trim($row[8]),
                    'expiry_date' => $this->parseDate($row[9]),
                    'status' => trim($row[10]),
                    'vendita' => trim($row[11]),
                    'dns' => trim($row[12]),
                    'cpanel' => trim($row[13]),
                    'epanel' => trim($row[14]),
                    'notes' => trim($row[15]),
                    'remark' => trim($row[16]),
                    'hosting_id' => $currentHostingId
                ];

                // Skip if domain is empty
                if (empty($serviceData['domain'])) {
                    $results['skipped']++;
                    continue;
                }

                // Check if website exists (case-insensitive comparison)
                $stmt = $this->pdo->prepare("SELECT id FROM websites WHERE LOWER(domain) = ?");
                $stmt->execute([$serviceData['domain']]);
                $websiteId = $stmt->fetchColumn();

                if ($websiteId) {
                    // Update existing website
                    $updated = $this->updateWebsite($websiteId, $serviceData);
                    $results['updated'] += $updated ? 1 : 0;
                } else {
                    // Create new website
                    $createdId = $this->createWebsite($serviceData);
                    if ($createdId) {
                        $results['imported']++;
                    } else {
                        throw new Exception("Failed to create website");
                    }
                }
            } catch (Exception $e) {
                $domain = $serviceData['domain'] ?? 'unknown';
                $results['errors'][] = "Row $rowNumber (Domain: $domain): " . $e->getMessage();
                $results['skipped']++;
            }
        }

        return $results;
    }

    private function getHostingIdByName($name): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM hosting_plans WHERE LOWER(TRIM(server_name)) = LOWER(TRIM(?))");
        $stmt->execute([$name]);
        return $stmt->fetchColumn() ?: null;
    }

    private function createHostingPlan(array $data): int
    {
        $stmt = $this->pdo->prepare("
        INSERT INTO hosting_plans (server_name, provider, email_address, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
        $stmt->execute([
            $data['server_name'],
            $data['provider'],
            $data['email_address'],
            $data['ip_address']
        ]);
        return $this->pdo->lastInsertId();
    }

    /*hey */
    private function extractServiceData(array $row): array
    {
        return [
            'name' => trim($row[4] ?? ''),
            'domain' => trim($row[5] ?? ''),
            'assigned_email' => trim($row[6] ?? ''),
            'proprietario' => trim($row[7] ?? ''),
            'email_server' => trim($row[8] ?? ''),
            'expiry_date' => $this->parseDate($row[9] ?? ''),
            'status' => trim($row[10] ?? ''),
            'vendita' => trim($row[11] ?? ''),
            'dns' => trim($row[12] ?? ''),
            'cpanel' => trim($row[13] ?? ''),
            'epanel' => trim($row[14] ?? ''),
            'notes' => trim($row[15] ?? ''),
            'remark' => trim($row[16] ?? '')
        ];
    }


    /**
     * Date handling methods
     */
    private function formatDate(?string $date): string
    {
        try {
            return $date ? (new DateTime($date))->format('Y-m-d') : '';
        } catch (Exception $e) {
            return '';
        }
    }

    private function parseDate(?string $date): string
    {
        try {
            return $date ? (new DateTime($date))->format('Y-m-d') : date('Y-m-d', strtotime('+1 year'));
        } catch (Exception $e) {
            return date('Y-m-d', strtotime('+1 year'));
        }
    }

    private function getHostingInfo($hostingId): array
    {
        if (!$hostingId) return [];

        $stmt = $this->pdo->prepare("
            SELECT server_name, ip_address, email_address, provider 
            FROM hosting_plans 
            WHERE id = ?
        ");
        $stmt->execute([$hostingId]);
        return $stmt->fetch() ?: [];
    }
}