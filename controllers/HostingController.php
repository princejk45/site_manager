<?php
class HostingController
{
    private PDO $pdo;
    private Hosting $hostingModel;
    private Website $websiteModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->hostingModel = new Hosting($pdo);
        $this->websiteModel = new Website($pdo); // Initialize website model here
    }

    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // Get all hosting plans with their complete data
        $hostingPlans = $this->hostingModel->getAllHostingPlans();

        // Enrich with service count and health/risk metrics per client.
        $metricsRows = $this->pdo->query(
            "SELECT h.id,
                    COUNT(w.id) AS service_count,
                    ROUND(AVG(CASE WHEN w.id IS NOT NULL THEN COALESCE(m.health_score, w.health_score, 0) END), 1) AS avg_health,
                    SUM(CASE WHEN w.id IS NOT NULL AND COALESCE(m.health_score, w.health_score, 0) < 60 THEN 1 ELSE 0 END) AS at_risk_sites,
                    SUM(CASE WHEN w.expiry_date IS NOT NULL AND w.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_sites
             FROM hosting h
             LEFT JOIN websites w ON w.hosting_id = h.id
             LEFT JOIN (
                 SELECT website_id, health_score, recorded_at
                 FROM health_metrics
                 WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
             ) m ON m.website_id = w.id
             GROUP BY h.id"
        )->fetchAll(PDO::FETCH_ASSOC);

        $metricsById = [];
        foreach ($metricsRows as $row) {
            $metricsById[(int)$row['id']] = $row;
        }

        foreach ($hostingPlans as &$plan) {
            $m = $metricsById[(int)$plan['id']] ?? null;
            $plan['service_count'] = (int)($m['service_count'] ?? 0);
            $plan['avg_health'] = isset($m['avg_health']) ? (float)$m['avg_health'] : 0.0;
            $plan['at_risk_sites'] = (int)($m['at_risk_sites'] ?? 0);
            $plan['expired_sites'] = (int)($m['expired_sites'] ?? 0);
        }
        unset($plan); // Break the reference

        $portfolioTotals = $this->pdo->query(
            "SELECT COUNT(w.id) AS total_sites,
                    ROUND(AVG(COALESCE(m.health_score, w.health_score, 0)), 1) AS avg_health,
                    SUM(CASE WHEN COALESCE(m.health_score, w.health_score, 0) < 60 THEN 1 ELSE 0 END) AS at_risk_sites,
                    SUM(CASE WHEN w.expiry_date IS NOT NULL AND w.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_sites
             FROM websites w
             LEFT JOIN (
                 SELECT website_id, health_score, recorded_at
                 FROM health_metrics
                 WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
             ) m ON m.website_id = w.id"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $unassignedServices = $this->pdo->query(
            "SELECT w.id,
                    w.domain,
                    w.status,
                    w.expiry_date,
                    DATEDIFF(w.expiry_date, CURDATE()) AS days_left,
                    COALESCE(m.health_score, w.health_score, 0) AS health_score,
                    m.recorded_at AS last_check,
                    " . ($this->hasTableColumn('websites', 'service_type') ? 'w.service_type' : "'hosting_web' AS service_type") . "
             FROM websites w
             LEFT JOIN (
                 SELECT website_id, health_score, recorded_at
                 FROM health_metrics
                 WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
             ) m ON m.website_id = w.id
             WHERE w.hosting_id IS NULL
             ORDER BY w.domain ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $totalClients = count($hostingPlans);
        $totalSites = (int)($portfolioTotals['total_sites'] ?? 0);
        $avgHealth = (float)($portfolioTotals['avg_health'] ?? 0);
        $atRiskSites = (int)($portfolioTotals['at_risk_sites'] ?? 0);
        $expiredSites = (int)($portfolioTotals['expired_sites'] ?? 0);

        require APP_PATH . '/views/hosting/index.php';
    }

    public function clientServices(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $clientId = max(0, $id);
        $serviceTypeSelect = $this->hasTableColumn('websites', 'service_type')
            ? 'w.service_type'
            : "'hosting_web' AS service_type";

        $sql = "SELECT w.id,
                       w.domain,
                       $serviceTypeSelect,
                       w.status,
                       w.expiry_date,
                       DATEDIFF(w.expiry_date, CURDATE()) AS days_left,
                       COALESCE(m.health_score, w.health_score, 0) AS health_score,
                       m.recorded_at AS last_check
                FROM websites w
                LEFT JOIN (
                    SELECT website_id, health_score, recorded_at
                    FROM health_metrics
                    WHERE id IN (SELECT MAX(id) FROM health_metrics GROUP BY website_id)
                ) m ON m.website_id = w.id
                WHERE w.hosting_id = :client_id
                ORDER BY w.domain ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':client_id' => $clientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'rows' => $rows,
            'client_id' => $clientId,
        ]);
        exit;
    }

    public function assignServices(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=hosting');
            exit;
        }

        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, ['super_admin', 'manager'], true)) {
            header('Location: index.php?action=hosting&error=forbidden');
            exit;
        }

        $clientId = (int)($_POST['client_id'] ?? 0);
        $websiteIds = $_POST['website_ids'] ?? [];

        if ($clientId <= 0 || !is_array($websiteIds) || $websiteIds === []) {
            header('Location: index.php?action=hosting&error=no_selection');
            exit;
        }

        $checkClient = $this->pdo->prepare('SELECT id FROM hosting WHERE id = :id LIMIT 1');
        $checkClient->execute([':id' => $clientId]);
        if (!$checkClient->fetch(PDO::FETCH_ASSOC)) {
            header('Location: index.php?action=hosting&error=invalid_client');
            exit;
        }

        $cleanIds = array_values(array_filter(array_map(static fn($id) => (int)$id, $websiteIds), static fn($id) => $id > 0));
        if ($cleanIds === []) {
            header('Location: index.php?action=hosting&error=no_selection');
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $params = array_merge([$clientId], $cleanIds);
        $stmt = $this->pdo->prepare(
            "UPDATE websites
             SET hosting_id = ?
             WHERE id IN ($placeholders)
               AND (hosting_id IS NULL OR hosting_id = 0)"
        );
        $stmt->execute($params);

        $updated = (int)$stmt->rowCount();
        if ($updated <= 0) {
            header('Location: index.php?action=hosting&error=no_selection');
            exit;
        }

        header('Location: index.php?action=hosting&success=assigned&count=' . $updated);
        exit;
    }

    private function hasTableColumn(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'server_name' => $_POST['server_name'],
                'provider' => $_POST['provider'] ?? null,
                'email_address' => $_POST['email_address'],
                'ip_address' => $_POST['ip_address'] ?? null,
            ];

            try {
                $this->hostingModel->createHostingPlan($data);
                $_SESSION['message'] = "Cliente creato con successo";
                header('Location: index.php?action=hosting');
                exit;
            } catch (InvalidArgumentException $e) {
                $_SESSION['error'] = $e->getMessage();
                $_SESSION['form_data'] = $_POST;
                header('Location: index.php?action=hosting&do=create');
                exit;
            } catch (PDOException $e) {
                $_SESSION['error'] = "Errore durante la creazione del cliente: " . $e->getMessage();
                $_SESSION['form_data'] = $_POST;
                header('Location: index.php?action=hosting&do=create');
                exit;
            }
        }

        $formData = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_data']);

        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);

        require APP_PATH . '/views/hosting/create.php';
    }

    public function view(int $id)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $hostingPlan = $this->hostingModel->getHostingPlanById($id);
        if (!$hostingPlan) {
            header('Location: index.php?action=hosting');
            exit;
        }



        require APP_PATH . '/views/hosting/view.php';
    }

    public function edit(int $id)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $hostingPlan = $this->hostingModel->getHostingPlanById($id);
        if (!$hostingPlan) {
            header('Location: index.php?action=hosting');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'server_name' => $_POST['server_name'],
                'provider' => $_POST['provider'] ?? null,
                'email_address' => $_POST['email_address'] ?? null,
                'ip_address' => $_POST['ip_address'] ?? null,
            ];

            try {
                $this->hostingModel->updateHostingPlan($id, $data);
                $_SESSION['message'] = "Client aggiornato con successo";
                header('Location: index.php?action=hosting');
                exit;
            } catch (PDOException $e) {
                $error = "Errore durante l'aggiornamento del client: " . $e->getMessage();
            }
        }

        require APP_PATH . '/views/hosting/create.php';
    }

    public function delete(int $id)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $this->hostingModel->deleteHostingPlan($id);
        $_SESSION['message'] = __('hosting.deleted_success');
        header('Location: index.php?action=hosting');
        exit;
    }

    public function bulk_delete()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ids'])) {
            header('Location: index.php?action=hosting');
            exit;
        }

        // Get and validate IDs
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'])));
        
        if (empty($ids)) {
            $_SESSION['error'] = "No items selected";
            header('Location: index.php?action=hosting');
            exit;
        }

        try {
            $deleted = 0;
            foreach ($ids as $id) {
                $this->hostingModel->deleteHostingPlan($id);
                $deleted++;
            }
            $_SESSION['message'] = "$deleted " . __('hosting.bulk_deleted_success');
        } catch (Exception $e) {
            $_SESSION['error'] = "Errore durante l'eliminazione: " . $e->getMessage();
        }

        header('Location: index.php?action=hosting');
        exit;
    }

    public function services($hostingId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        try {
            // Get hosting plan details
            $hostingPlan = $this->hostingModel->getHostingPlanById($hostingId);

            if (!$hostingPlan) {
                throw new Exception("Cliente non trovato");
            }

            // Get associated services
            $services = $this->websiteModel->getServicesByHostingId($hostingId);

            // Add service count to hosting plan data
            $hostingPlan['service_count'] = count($services);

            // Calculate dynamic status for each service
            foreach ($services as &$service) {
                $service['dynamic_status'] = $this->websiteModel->calculateDynamicStatus($service['expiry_date']);
            }
            unset($service); // Break the reference

            require APP_PATH . '/views/hosting/services.php';
        } catch (Exception $e) {
            $_SESSION['error'] = "Errore: " . $e->getMessage();
            header('Location: index.php?action=hosting');
            exit;
        }
    }

    // Add this method to your HostingController class
    public function serviceCreate($hostingId)
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $hostingPlan = $this->hostingModel->getHostingPlanById($hostingId);
        if (!$hostingPlan) {
            $_SESSION['error'] = "Cliente non trovato";
            header('Location: index.php?action=hosting');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'],
                'domain' => $_POST['domain'],
                'hosting_id' => $hostingId,
                'registrante_import' => $_POST['registrante_import'] ?? null,
                'expiry_date' => sm_normalize_date($_POST['expiry_date'] ?? null),
                'status' => $_POST['status'],
                'vendita' => $_POST['vendita'],
                'assigned_email' => $hostingPlan['email_address'],
                'proprietario' => $_POST['proprietario'] ?? null,
                'dns' => $_POST['dns'] ?? null,
                'cpanel' => $_POST['cpanel'] ?? null,
                'epanel' => $_POST['epanel'] ?? null,
                'notes' => $_POST['notes'] ?? null,
                'manutenzione' => $_POST['manutenzione'] ?? null,
                'remark' => $_POST['remark'] ?? null
            ];

            try {
                $this->websiteModel->createWebsite($data);
                $_SESSION['message'] = "Servizio ('{$data['domain']}') creato con successo per {$hostingPlan['server_name']}";
                header("Location: index.php?action=hosting&do=services&id=$hostingId");
                exit;
            } catch (PDOException $e) {
                $error = "Errore durante la creazione del servizio: " . $e->getMessage();
                $website = $data; // Preserve form input
            }
        }

        require APP_PATH . '/views/hosting/service_create.php';
    }
}