<?php
class HostingController
{
    private $pdo;
    private $hostingModel;
    private $websiteModel;

    public function __construct($pdo)
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

        // Get service counts separately
        $hostingWithCounts = $this->hostingModel->getHostingPlansWithServiceCounts();

        // Merge the service counts into the main hosting plans array
        foreach ($hostingPlans as &$plan) {
            foreach ($hostingWithCounts as $countPlan) {
                if ($plan['id'] == $countPlan['id']) {
                    $plan['service_count'] = $countPlan['service_count'];
                    break;
                }
            }
            // Ensure service_count is set even if no match found
            $plan['service_count'] = $plan['service_count'] ?? 0;
        }
        unset($plan); // Break the reference

        require APP_PATH . '/views/hosting/index.php';
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

    public function view($id)
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

    public function edit($id)
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

    public function delete($id)
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
                'email_server' => $_POST['email_server'],
                'expiry_date' => $_POST['expiry_date'],
                'status' => $_POST['status'],
                'vendita' => $_POST['vendita'],
                'assigned_email' => $hostingPlan['email_address'],
                'proprietario' => $_POST['proprietario'] ?? null,
                'dns' => $_POST['dns'] ?? null,
                'cpanel' => $_POST['cpanel'] ?? null,
                'epanel' => $_POST['epanel'] ?? null,
                'notes' => $_POST['notes'] ?? null,
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