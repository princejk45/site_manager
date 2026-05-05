<?php
class Website
{
    private PDO $pdo;
    private array $columnExistsCache = [];
    const DEFAULT_SHEET_NAME = 'Sheet1';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Updated method with sorting, filtering and pagination
    public function getWebsites($search = '', $sort = 'domain', $order = 'ASC', $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        $searchTerm = '%' . $search . '%';

        // Validate inputs
        $allowedSorts = ['hosting_server', 'domain', 'service_type', 'status', 'expiry_date', 'created_at'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'domain';
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $hasHostingId = $this->hasColumn('websites', 'hosting_id');
        $hasServiceType = $this->hasColumn('websites', 'service_type');

        if ($sort === 'service_type' && !$hasServiceType) {
            $sort = 'domain';
        }

        if ($sort === 'hosting_server') {
            $orderBy = $hasHostingId
                ? "COALESCE(h.name, '') $order, w.domain ASC"
                : "w.domain $order, w.domain ASC";
        } else {
            $orderBy = "w.$sort $order, w.domain ASC";
        }

        if ($hasHostingId) {
            $sql = "SELECT w.*, h.name AS hosting_server
    FROM websites w
    LEFT JOIN hosting h ON w.hosting_id = h.id
    WHERE w.domain LIKE ? OR w.notes LIKE ? OR h.name LIKE ?
    ORDER BY $orderBy
    LIMIT ? OFFSET ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(2, $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(3, $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(4, (int)$perPage, PDO::PARAM_INT);
            $stmt->bindValue(5, (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $sql = "SELECT w.*, NULL AS hosting_server
    FROM websites w
    WHERE w.domain LIKE ? OR w.notes LIKE ?
    ORDER BY $orderBy
    LIMIT ? OFFSET ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(2, $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(3, (int)$perPage, PDO::PARAM_INT);
            $stmt->bindValue(4, (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
        }

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

        if ($this->hasColumn('websites', 'hosting_id')) {
            $sql = "
        SELECT COUNT(*) 
        FROM websites w
        LEFT JOIN hosting h ON w.hosting_id = h.id
        WHERE w.domain LIKE ? OR 
              w.notes LIKE ? OR
              h.name LIKE ?
    ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        } else {
            $sql = "
        SELECT COUNT(*) 
        FROM websites w
        WHERE w.domain LIKE ? OR 
              w.notes LIKE ?
    ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$searchTerm, $searchTerm]);
        }

        return $stmt->fetchColumn();
    }

    public function getWebsiteById(int $id)
    {
        $hasHostingId = $this->hasColumn('websites', 'hosting_id');
        $hasHostingAccountId = $this->hasColumn('websites', 'hosting_account_id');
        $hasProviderId = $this->hasColumn('websites', 'provider_id');
        $hasHaCpanel = $this->hasColumn('hosting_accounts', 'cpanel_username');
        $hasHaPackage = $this->hasColumn('hosting_accounts', 'package_name');
        $hasHaServer = $this->hasColumn('hosting_accounts', 'server_hostname');

        if ($hasHostingId) {
            $sql = "
                SELECT
                    w.*,
                    h.name AS hosting_server,
                    " . ($hasHostingAccountId && $hasHaCpanel ? "ha.cpanel_username" : "NULL") . " AS hosting_account_username,
                    " . ($hasHostingAccountId && $hasHaPackage ? "ha.package_name" : "NULL") . " AS hosting_account_package,
                    " . ($hasHostingAccountId && $hasHaServer ? "ha.server_hostname" : "NULL") . " AS hosting_account_server,
                    " . ($hasProviderId ? "p.name" : "NULL") . " AS direct_provider_name,
                    " . ($hasProviderId ? "p.type" : "NULL") . " AS direct_provider_type,
                    " . ($hasHostingAccountId ? "p_ha.name" : "NULL") . " AS hosting_account_provider_name
                FROM websites w
                LEFT JOIN hosting h ON w.hosting_id = h.id
                " . ($hasHostingAccountId ? "LEFT JOIN hosting_accounts ha ON ha.id = w.hosting_account_id" : "") . "
                " . ($hasProviderId ? "LEFT JOIN providers p ON p.id = w.provider_id" : "") . "
                " . ($hasHostingAccountId ? "LEFT JOIN providers p_ha ON p_ha.id = ha.provider_id" : "") . "
                WHERE w.id = ?
            ";
            $stmt = $this->pdo->prepare($sql);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT w.*, NULL AS hosting_server
                FROM websites w
                WHERE w.id = ?
            ");
        }
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function createWebsite(array $data)
    {
        $serviceType = $data['service_type'] ?? 'hosting_web';
        $allowed     = ['domain', 'hosting_web', 'hosting_mail'];
        if (!in_array($serviceType, $allowed, true)) {
            $serviceType = 'hosting_web';
        }

        $hostingId        = !empty($data['hosting_id'])         ? (int)$data['hosting_id']         : null;
        $hostingAccountId = !empty($data['hosting_account_id']) ? (int)$data['hosting_account_id'] : null;
        $providerId       = !empty($data['provider_id'])        ? (int)$data['provider_id']        : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO websites
                (hosting_id, hosting_account_id, provider_id, domain, service_type,
                 assigned_email, proprietario, vendita, cpanel, epanel, dns, remark,
                 registrante_import, expiry_date, status, notes, manutenzione)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $hostingId,
            $hostingAccountId,
            $providerId,
            $data['domain']              ?? '',
            $serviceType,
            $data['assigned_email']      ?? null,
            $data['proprietario']        ?? null,
            $data['vendita']             ?? null,
            $data['cpanel']              ?? null,
            $data['epanel']              ?? null,
            $data['dns']                 ?? null,
            $data['remark']              ?? null,
            $data['registrante_import']  ?? null,
            sm_normalize_date($data['expiry_date'] ?? null),
            $data['status']              ?? 'active',
            $data['notes']               ?? '',
            $data['manutenzione']        ?? null,
        ]);
    }

    public function updateWebsite(int $id, array $data)
    {
        $serviceType = $data['service_type'] ?? 'hosting_web';
        $allowed     = ['domain', 'hosting_web', 'hosting_mail'];
        if (!in_array($serviceType, $allowed, true)) {
            $serviceType = 'hosting_web';
        }

        $hostingId        = !empty($data['hosting_id'])         ? (int)$data['hosting_id']         : null;
        $hostingAccountId = !empty($data['hosting_account_id']) ? (int)$data['hosting_account_id'] : null;
        $providerId       = !empty($data['provider_id'])        ? (int)$data['provider_id']        : null;

        $stmt = $this->pdo->prepare("
            UPDATE websites SET
                hosting_id         = ?,
                hosting_account_id = ?,
                provider_id        = ?,
                domain             = ?,
                service_type       = ?,
                assigned_email     = ?,
                proprietario       = ?,
                vendita            = ?,
                cpanel             = ?,
                epanel             = ?,
                dns                = ?,
                remark             = ?,
                registrante_import = ?,
                expiry_date        = ?,
                status             = ?,
                notes              = ?,
                manutenzione       = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $hostingId,
            $hostingAccountId,
            $providerId,
            $data['domain']              ?? '',
            $serviceType,
            $data['assigned_email']      ?? null,
            $data['proprietario']        ?? null,
            $data['vendita']             ?? null,
            $data['cpanel']              ?? null,
            $data['epanel']              ?? null,
            $data['dns']                 ?? null,
            $data['remark']              ?? null,
            $data['registrante_import']  ?? null,
            sm_normalize_date($data['expiry_date'] ?? null),
            $data['status']              ?? 'active',
            $data['notes']               ?? '',
            $data['manutenzione']        ?? null,
            $id,
        ]);
    }

    /**
     * Returns one row per unique domain, with pivoted service columns for
     * domain registration, web hosting, and mail hosting.
     * Joins to hosting_accounts and providers when the new FK columns are set.
     */
    public function getDomainSummaries(string $search = '', int $page = 1, int $perPage = 10, string $expiryFilter = ''): array
    {
        $offset    = ($page - 1) * $perPage;
        $term      = '%' . $search . '%';

        $havingClause = '';
        if ($expiryFilter === 'expiring') {
            $havingClause = "HAVING (
                (MAX(CASE WHEN w.service_type = 'domain' THEN w.expiry_date END) IS NOT NULL
                 AND MAX(CASE WHEN w.service_type = 'domain' THEN w.expiry_date END) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) OR
                (MAX(CASE WHEN w.service_type = 'hosting_web' THEN w.expiry_date END) IS NOT NULL
                 AND MAX(CASE WHEN w.service_type = 'hosting_web' THEN w.expiry_date END) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) OR
                (MAX(CASE WHEN w.service_type = 'hosting_mail' THEN w.expiry_date END) IS NOT NULL
                 AND MAX(CASE WHEN w.service_type = 'hosting_mail' THEN w.expiry_date END) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
            )";
        }

        $sql = "
            SELECT
                w.domain,
                MIN(w.hosting_id)          AS hosting_id,
                h.name                     AS client_name,

                /* ── Domain registration ─────────────────────────── */
                MAX(CASE WHEN w.service_type = 'domain' THEN w.id          END) AS dom_id,
                MAX(CASE WHEN w.service_type = 'domain' THEN w.expiry_date END) AS dom_expiry,
                MAX(CASE WHEN w.service_type = 'domain' THEN w.status      END) AS dom_status,
                MAX(CASE WHEN w.service_type = 'domain' THEN p_d.name      END) AS dom_registrar,
                MAX(CASE WHEN w.service_type = 'domain' THEN w.provider_id END) AS dom_provider_id,

                /* ── Web hosting ──────────────────────────────────── */
                MAX(CASE WHEN w.service_type = 'hosting_web' THEN w.id              END) AS web_id,
                MAX(CASE WHEN w.service_type = 'hosting_web' THEN w.expiry_date     END) AS web_expiry,
                MAX(CASE WHEN w.service_type = 'hosting_web' THEN w.status          END) AS web_status,
                MAX(CASE WHEN w.service_type = 'hosting_web' THEN ha.cpanel_username END) AS web_cpanel,
                MAX(CASE WHEN w.service_type = 'hosting_web' THEN p_whm.name        END) AS web_provider,
                MAX(CASE WHEN w.service_type = 'hosting_web' THEN w.hosting_account_id END) AS web_ha_id,

                /* ── Mail hosting ─────────────────────────────────── */
                MAX(CASE WHEN w.service_type = 'hosting_mail' THEN w.id          END) AS mail_id,
                MAX(CASE WHEN w.service_type = 'hosting_mail' THEN w.expiry_date END) AS mail_expiry,
                MAX(CASE WHEN w.service_type = 'hosting_mail' THEN w.status      END) AS mail_status,
                MAX(CASE WHEN w.service_type = 'hosting_mail' THEN p_m.name      END) AS mail_provider,
                MAX(CASE WHEN w.service_type = 'hosting_mail' THEN w.provider_id END) AS mail_provider_id

            FROM websites w
            LEFT JOIN hosting         h     ON h.id     = w.hosting_id
            LEFT JOIN hosting_accounts ha   ON ha.id    = w.hosting_account_id
                                           AND w.service_type = 'hosting_web'
            LEFT JOIN providers       p_whm ON p_whm.id = ha.provider_id
            LEFT JOIN providers       p_d   ON p_d.id   = w.provider_id
                                           AND w.service_type = 'domain'
            LEFT JOIN providers       p_m   ON p_m.id   = w.provider_id
                                           AND w.service_type = 'hosting_mail'
            WHERE (w.domain LIKE ? OR h.name LIKE ?)
            GROUP BY w.domain, h.name
            {$havingClause}
            ORDER BY w.domain ASC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $term, PDO::PARAM_STR);
        $stmt->bindValue(2, $term, PDO::PARAM_STR);
        $stmt->bindValue(3, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDomainCount(string $search = '', string $expiryFilter = ''): int
    {
        $term = '%' . $search . '%';
        if ($expiryFilter === 'expiring') {
            $sql = "
                SELECT COUNT(*) FROM (
                    SELECT w.domain
                    FROM websites w
                    LEFT JOIN hosting h ON h.id = w.hosting_id
                    WHERE (w.domain LIKE ? OR h.name LIKE ?)
                    GROUP BY w.domain
                    HAVING (
                        (MAX(CASE WHEN w.service_type = 'domain' THEN w.expiry_date END) IS NOT NULL
                         AND MAX(CASE WHEN w.service_type = 'domain' THEN w.expiry_date END) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) OR
                        (MAX(CASE WHEN w.service_type = 'hosting_web' THEN w.expiry_date END) IS NOT NULL
                         AND MAX(CASE WHEN w.service_type = 'hosting_web' THEN w.expiry_date END) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) OR
                        (MAX(CASE WHEN w.service_type = 'hosting_mail' THEN w.expiry_date END) IS NOT NULL
                         AND MAX(CASE WHEN w.service_type = 'hosting_mail' THEN w.expiry_date END) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                    )
                ) AS filtered
            ";
        } else {
            $sql = "
                SELECT COUNT(DISTINCT w.domain)
                FROM websites w
                LEFT JOIN hosting h ON h.id = w.hosting_id
                WHERE (w.domain LIKE ? OR h.name LIKE ?)
            ";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$term, $term]);
        return (int)$stmt->fetchColumn();
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
        SELECT w.*
        FROM websites w
        WHERE w.notes IS NOT NULL 
        AND w.notes != '' 
        AND LOWER(w.notes) NOT IN ('none', 'nessuno')
        ORDER BY w.domain ASC
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
        SELECT w.*, h.name AS client_name
        FROM websites w
        LEFT JOIN hosting h ON h.id = w.hosting_id
        WHERE w.expiry_date < CURDATE()
        ORDER BY w.expiry_date DESC
    ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function deleteWebsite(int $id)
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
        $hostingPlans = $this->pdo->query("SELECT id, LOWER(TRIM(name)) as name_lower, name FROM hosting")->fetchAll(PDO::FETCH_ASSOC);
        $hostingMap = [];
        foreach ($hostingPlans as $plan) {
            $hostingMap[$plan['name_lower']] = $plan['id'];
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
                'service_type' => $this->normalizeServiceType($row[4] ?? ''),
                'domain' => strtolower((string)$safeTrim($row[5] ?? '')),
                'assigned_email' => $safeTrim($row[6] ?? ''),
                'proprietario' => $safeTrim($row[7] ?? null),
                'registrante_import' => $safeTrim($row[8] ?? null),
                'expiry_date' => sm_normalize_date($safeTrim($row[9] ?? ''), date('Y-m-d', strtotime('+1 year'))),
                'status' => $safeTrim($row[10] ?? ''),
                'vendita' => $safeTrim($row[11] ?? ''),
                'dns' => $safeTrim($row[12] ?? null),
                'cpanel' => $safeTrim($row[13] ?? null),
                'epanel' => $safeTrim($row[14] ?? null),
                'notes' => $safeTrim($row[15] ?? null),
                'manutenzione' => $safeTrim($row[16] ?? null),
                'remark' => $safeTrim($row[17] ?? null)
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
                // Check for existing website by domain + service type
                $existing = $this->getWebsiteByDomain($serviceData['domain'], $serviceData['service_type']);

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

    public function getWebsiteByDomain($domain, ?string $serviceType = null)
    {
        $domain = strtolower(trim((string)$domain));
        if ($domain === '') {
            return false;
        }

        if ($serviceType !== null && $this->hasColumn('websites', 'service_type')) {
            $stmt = $this->pdo->prepare("SELECT id FROM websites WHERE LOWER(domain) = ? AND service_type = ? LIMIT 1");
            $stmt->execute([$domain, $serviceType]);
            return $stmt->fetch();
        }

        $stmt = $this->pdo->prepare("SELECT id FROM websites WHERE LOWER(domain) = ? LIMIT 1");
        $stmt->execute([$domain]);
        return $stmt->fetch();
    }

    public function exportToExcel()
    {
        require_once APP_PATH . '/vendor/autoload.php';

        $sql = "
            SELECT w.*,
                h.name        AS server_name,
                NULL          AS ip_address,
                NULL          AS email_address,
                NULL          AS client_piva,
                p.name        AS registrante_name,
                ha.cpanel_username AS cpanel_col
            FROM websites w
            LEFT JOIN hosting         h  ON h.id  = w.hosting_id
            LEFT JOIN providers       p  ON p.id  = w.provider_id
            LEFT JOIN hosting_accounts ha ON ha.id = w.hosting_account_id
            ORDER BY COALESCE(h.name, 'zzz'), w.domain
        ";
        $websites = $this->pdo->query($sql)->fetchAll();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Define header structure
        $headers = [
            'Informazioni per il cliente' => [
                'Nome'      => 'server_name',
                'Indirizzo' => 'ip_address',
                'Email'     => 'email_address',
                'P.IVA'     => 'client_piva'
            ],
            'Informazioni sul servizio' => [
                'Tipologia di Servizi'            => 'w.service_type',
                'Dettaglio Servizi'               => 'w.domain',
                'Email Assegnata'                 => 'w.assigned_email',
                'Propietario'                     => 'w.proprietario',
                'Registrante'                     => 'registrante_name',
                'Scadenza'                        => 'w.expiry_date',
                'Costo Server (iva inclusa)'       => 'w.status',
                'Prezzo di vendita (iva inclusa)'  => 'w.vendita',
                'Direct DNS A'                    => 'w.dns',
                'User Name cpanel'                => 'cpanel_col',
                'Email panel'                     => 'w.epanel',
                'Bug report'                      => 'w.notes',
                'Costo di manutenzione sito'      => 'w.manutenzione',
                'Notes'                           => 'w.remark'
            ]
        ];

        // Define custom column widths
        $columnWidths = [
            'A' => 25, // Nome
            'B' => 20, // Indirizzo
            'C' => 30, // Email
            'D' => 15, // P.IVA
            'E' => 25, // Tipologia di Servizi
            'F' => 30, // Dettaglio Servizi
            'G' => 25, // Email Assegnata
            'H' => 20, // Propietario
            'I' => 25, // Registrante
            'J' => 15, // Scadenza
            'K' => 20, // Costo Server
            'L' => 20, // Prezzo di vendita
            'M' => 20, // Direct DNS A
            'N' => 20, // User Name cpanel
            'O' => 20, // Email panel
            'P' => 40, // Bug report
            'Q' => 30, // Costo di manutenzione sito
            'R' => 40  // Notes
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
                if ($field === 'w.service_type') {
                    $value = $this->sheetServiceTypeLabel((string)$value);
                }
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
                        ->setFormatCode('dd-mm-yyyy');
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
        SELECT w.*, h.name AS client_name
        FROM websites w
        LEFT JOIN hosting h ON h.id = w.hosting_id
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
     * @return string New expiry date
     */

    public function renewWebsite(int $id)
    {
        $website = $this->getWebsiteById($id);
        if (!$website) {
            throw new Exception("Website not found");
        }

        // Calculate new date
        $expiry = new DateTime($website['expiry_date']);
        $expiry->add(new DateInterval('P1Y'));
        $newExpiry = $expiry->format('Y-m-d');

        $sql = "UPDATE websites SET expiry_date = ? WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([$newExpiry, $id]);

        if (!$success) {
            throw new Exception("Database update failed");
        }

        return $newExpiry;
    }




    // New helper methods for sorting validation
    private function validateSortColumn($column)
    {
        $allowed = ['name', 'domain', 'hosting_server', 'expiry_date'];
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
        SELECT w.*,
               h.id            AS hosting_plan_id,
               h.name          AS server_name,
               h.address       AS ip_address,
               h.email_address AS email_address,
               h.vat_number    AS provider,
               p.name          AS registrante_name
        FROM websites w
        LEFT JOIN hosting   h ON h.id = w.hosting_id
        LEFT JOIN providers p ON p.id = w.provider_id
        ORDER BY COALESCE(h.name, 'zzzzzzzz'), w.domain
    ");

        $websites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("DEBUG prepareForGoogleSheets: Fetched " . count($websites) . " websites");
        if (count($websites) === 0) {
            error_log("WARNING: No websites found in database");
        } else {
            error_log("DEBUG: First website: " . print_r($websites[0], true));
        }

        // Prepare the data array
        $data = [];
        $currentClientGroup = '__INIT__';
        $clientRows = []; // Track row indices for each client group
        $unassignedClientName = 'DOMINI LIBERI';
        $unassignedClientEmail = $this->getSmtpCcOrReplyToEmail();

        foreach ($websites as $index => $website) {
            $groupKey = !empty($website['hosting_plan_id'])
                ? 'hosting_' . (string)$website['hosting_plan_id']
                : 'unassigned';
            $isNewClient = ($currentClientGroup !== $groupKey);
            $currentClientGroup = $groupKey;

            $clientName = trim((string)($website['server_name'] ?? ''));
            $clientEmail = trim((string)($website['email_address'] ?? ''));

            if ($groupKey === 'unassigned') {
                $clientName = $unassignedClientName;
                if ($unassignedClientEmail !== '') {
                    $clientEmail = $unassignedClientEmail;
                }
            }

            if ($isNewClient) {
                // Start a new client group
                $clientRows[$groupKey] = [];
            }

            $rowData = [
                // Client info (only show for first service of each client)
                $isNewClient ? $clientName : '',
                $isNewClient ? ($website['ip_address'] ?? '') : '',
                $isNewClient ? $clientEmail : '',
                $isNewClient ? ($website['provider'] ?? '') : '',
                // Service info (always show)
                $this->sheetServiceTypeLabel($website['service_type'] ?? null),
                $website['domain'] ?? '',
                $website['assigned_email'] ?? '',
                $website['proprietario'] ?? '',
                $website['registrante_name'] ?? '',
                $this->formatDate($website['expiry_date'] ?? ''),
                $website['status'] ?? '',
                $website['vendita'] ?? '',
                $website['dns'] ?? '',
                $website['cpanel'] ?? '',
                $website['epanel'] ?? '',
                $website['notes'] ?? '',
                $website['manutenzione'] ?? '',
                $website['remark'] ?? ''
            ];

            $data[] = $rowData;
            $clientRows[$groupKey][] = count($data) - 1; // Store row index (0-based)
        }

        error_log("DEBUG prepareForGoogleSheets: Prepared " . count($data) . " rows for export");

        return [
            'data' => $data,
            'clientRows' => $clientRows // Return the client row groupings
        ];
    }

    private function getSmtpCcOrReplyToEmail(): string
    {
        try {
            $selectColumns = ['cc_email', 'from_email'];

            if ($this->hasColumn('smtp_settings', 'reply_to_email')) {
                $selectColumns[] = 'reply_to_email';
            }
            if ($this->hasColumn('smtp_settings', 'reply_to')) {
                $selectColumns[] = 'reply_to';
            }

            $select = implode(', ', array_unique($selectColumns));
            $stmt = $this->pdo->query("SELECT {$select} FROM smtp_settings ORDER BY id DESC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $candidates = ['cc_email', 'reply_to_email', 'reply_to', 'from_email'];
            foreach ($candidates as $field) {
                $value = trim((string)($row[$field] ?? ''));
                if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $value;
                }
            }
        } catch (Throwable $e) {
            error_log('prepareForGoogleSheets SMTP lookup error: ' . $e->getMessage());
        }

        return '';
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

            // Service info (columns E-R)
            $this->sheetServiceTypeLabel($website['service_type'] ?? null),
            $website['domain'] ?? '',
            $website['assigned_email'] ?? '',
            $website['proprietario'] ?? '',
            $website['registrante_name'] ?? '',
            $this->formatDate($website['expiry_date'] ?? ''),
            $website['status'] ?? '',
            $website['vendita'] ?? '',
            $website['dns'] ?? '',
            $website['cpanel'] ?? '',
            $website['epanel'] ?? '',
            $website['notes'] ?? '',
            $website['manutenzione'] ?? '',
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

                // Treat DOMINI LIBERI as unassigned group (no hosting plan create/update).
                $isDominiLiberi = strtoupper($clientName) === 'DOMINI LIBERI';

                // Process hosting plan if client name exists and is a real client.
                if (!empty($clientName) && !$isDominiLiberi) {
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
                } elseif ($isDominiLiberi) {
                    $currentHostingId = null;
                }

                // Extract service data (columns E-R)
                $serviceData = [
                    'name' => trim($row[4]),
                    'service_type' => $this->normalizeServiceType($row[4]),
                    'domain' => strtolower(trim($row[5])),
                    'assigned_email' => trim($row[6]),
                    'proprietario' => trim($row[7]),
                    'registrante_import' => trim($row[8]),
                    'expiry_date' => $this->parseDate($row[9]),
                    'status' => trim($row[10]),
                    'vendita' => trim($row[11]),
                    'dns' => trim($row[12]),
                    'cpanel' => trim($row[13]),
                    'epanel' => trim($row[14]),
                    'notes' => trim($row[15]),
                    'manutenzione' => trim($row[16] ?? ''),
                    'remark' => trim($row[17] ?? ''),
                    'hosting_id' => $currentHostingId
                ];

                // Skip if domain is empty
                if (empty($serviceData['domain'])) {
                    $results['skipped']++;
                    continue;
                }

                // Check if website exists by domain + service_type
                $existing = $this->getWebsiteByDomain($serviceData['domain'], $serviceData['service_type']);
                $websiteId = $existing['id'] ?? null;

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
            'registrante_import' => trim($row[8] ?? ''),
            'expiry_date' => $this->parseDate($row[9] ?? ''),
            'status' => trim($row[10] ?? ''),
            'vendita' => trim($row[11] ?? ''),
            'dns' => trim($row[12] ?? ''),
            'cpanel' => trim($row[13] ?? ''),
            'epanel' => trim($row[14] ?? ''),
            'notes' => trim($row[15] ?? ''),
            'manutenzione' => trim($row[16] ?? ''),
            'remark' => trim($row[17] ?? '')
        ];
    }


    /**
     * Date handling methods
     */
    private function formatDate(?string $date): string
    {
        return sm_format_date($date, '');
    }

    private function sheetServiceTypeLabel(?string $serviceType): string
    {
        $normalized = $this->normalizeServiceType((string)$serviceType);
        return match ($normalized) {
            'domain' => 'Domain',
            'hosting_mail' => 'Hosting Mail',
            default => 'Hosting Web',
        };
    }

    private function normalizeServiceType(?string $raw): string
    {
        $v = strtolower(trim((string)$raw));
        if ($v === '') {
            return 'hosting_web';
        }

        if (in_array($v, ['domain', 'dominio', 'domain registration', 'registrar', 'dom'], true)) {
            return 'domain';
        }
        if (in_array($v, ['hosting_mail', 'mail hosting', 'hosting mail', 'mail', 'email', 'posta'], true)) {
            return 'hosting_mail';
        }
        if (in_array($v, ['hosting_web', 'web hosting', 'hosting web', 'hosting', 'web'], true)) {
            return 'hosting_web';
        }

        if (str_contains($v, 'mail') || str_contains($v, 'email')) {
            return 'hosting_mail';
        }
        if (str_contains($v, 'domain') || str_contains($v, 'registr')) {
            return 'domain';
        }

        return 'hosting_web';
    }

    private function parseDate(?string $date): string
    {
        return sm_normalize_date($date, date('Y-m-d', strtotime('+1 year'))) ?? date('Y-m-d', strtotime('+1 year'));
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

    /**
     * Get all websites for a specific user
     * @param int $userId User ID
     * @return array Array of websites
     */
    public function getUserWebsites(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT w.*, h.server_name AS hosting_server
            FROM websites w
            LEFT JOIN hosting_plans h ON w.hosting_id = h.id
            WHERE w.user_id = ?
            ORDER BY w.domain ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user owns a website
     * @param int $userId User ID
     * @param int $websiteId Website ID
     * @return bool True if user owns the website
     */
    public function ownsWebsite(int $userId, int $websiteId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM websites
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$websiteId, $userId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Get a website by ID
     * @param int $websiteId Website ID
     * @return array|false Website data or false if not found
     */
    /**
     * Get a website by ID
     * @param int $websiteId Website ID
     * @return array|false Website data or false if not found
     */
    public function getById(int $websiteId)
    {
        return $this->getWebsiteById($websiteId);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (isset($this->columnExistsCache[$cacheKey])) {
            return $this->columnExistsCache[$cacheKey];
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);

        $exists = (int)$stmt->fetchColumn() > 0;
        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    /**
     * Get all websites
     * @return array Array of all websites
     */
    public function getAll(): array
    {
        return $this->getWebsites('', 'domain', 'asc', 1, PHP_INT_MAX);
    }
}