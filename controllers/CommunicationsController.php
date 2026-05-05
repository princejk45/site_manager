<?php
/**
 * CommunicationsController
 * CRM client communication log — list, log new entry, delete, AJAX helpers
 */
class CommunicationsController
{
    private CommunicationLog $logModel;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo      = $pdo;
        $this->logModel = new CommunicationLog($pdo);
    }

    // ── Auth guard ──────────────────────────────────────────────────────────
    private function requireAuth(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['LAST_ACTIVITY'])) {
            header('Location: index.php?action=login');
            exit;
        }
    }

    // ── Main list / timeline ────────────────────────────────────────────────
    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'hosting_id' => isset($_GET['client'])    ? (int)$_GET['client']    : 0,
            'comm_type'  => $_GET['type']    ?? '',
            'channel'    => $_GET['channel'] ?? '',
            'date_from'  => sm_normalize_date($_GET['from'] ?? '', '') ?? '',
            'date_to'    => sm_normalize_date($_GET['to'] ?? '', '') ?? '',
            'search'     => trim($_GET['search'] ?? ''),
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;

        $timeline  = $this->logModel->getTimeline($filters, $page, $perPage);
        $clients   = $this->logModel->getClients();
        $kpi       = $this->logModel->getKpiSummary();
        $commTypes = CommunicationLog::TYPES;
        $channels  = CommunicationLog::CHANNELS;
        $userRole  = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';

        // Flash message
        $flash = $_SESSION['comm_flash'] ?? null;
        unset($_SESSION['comm_flash']);

        require APP_PATH . '/views/communications/index.php';
    }

    // ── Log a new manual communication (POST) ───────────────────────────────
    public function store(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=comms');
            exit;
        }

        // CSRF: basic token check
        if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            $_SESSION['comm_flash'] = ['type' => 'danger', 'msg' => 'Invalid request token.'];
            header('Location: index.php?action=comms');
            exit;
        }

        $hostingId = (int)($_POST['hosting_id'] ?? 0);
        $subject   = trim($_POST['subject'] ?? '');

        if ($hostingId <= 0 || $subject === '') {
            $_SESSION['comm_flash'] = ['type' => 'danger', 'msg' => 'Client and subject are required.'];
            header('Location: index.php?action=comms');
            exit;
        }

        $this->logModel->log([
            'hosting_id' => $hostingId,
            'website_id' => !empty($_POST['website_id']) ? (int)$_POST['website_id'] : null,
            'comm_type'  => $_POST['comm_type']  ?? 'general',
            'channel'    => $_POST['channel']    ?? 'email',
            'subject'    => $subject,
            'notes'      => $_POST['notes']      ?? '',
            'sent_at'    => !empty($_POST['sent_at']) ? (sm_parse_date_value($_POST['sent_at'], true)?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s'),
            'sent_by'    => (int)$_SESSION['user_id'],
        ]);

        $_SESSION['comm_flash'] = ['type' => 'success', 'msg' => 'Communication logged successfully.'];

        // Redirect back preserving client filter if set
        $back = $hostingId ? "index.php?action=comms&client={$hostingId}" : 'index.php?action=comms';
        header("Location: {$back}");
        exit;
    }

    // ── Delete a manual entry ───────────────────────────────────────────────
    public function delete(): void
    {
        $this->requireAuth();

        $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';
        if (!in_array($role, ['manager', 'super_admin'], true)) {
            $_SESSION['comm_flash'] = ['type' => 'danger', 'msg' => 'Insufficient permissions.'];
            header('Location: index.php?action=comms');
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $this->logModel->delete($id);
            $_SESSION['comm_flash'] = ['type' => 'success', 'msg' => 'Entry deleted.'];
        }
        header('Location: index.php?action=comms');
        exit;
    }

    // ── AJAX: get websites for a client (for log modal dropdown) ────────────
    public function ajaxWebsites(): void
    {
        $this->requireAuth();
        $hostingId = (int)($_GET['hosting_id'] ?? 0);
        header('Content-Type: application/json');
        if ($hostingId <= 0) {
            echo json_encode([]);
            exit;
        }
        echo json_encode($this->logModel->getWebsitesByClient($hostingId));
        exit;
    }
}
