<?php
class HostingAccountsController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ------------------------------------------------------------------ //
    //  LIST (optionally scoped to a client)
    // ------------------------------------------------------------------ //
    public function index(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $clientId  = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
        $providerId = isset($_GET['provider_id']) ? (int)$_GET['provider_id'] : 0;

        $where  = ['1=1'];
        $params = [];

        if ($clientId > 0) {
            $where[]  = 'ha.client_id = :client_id';
            $params[':client_id'] = $clientId;
        }
        if ($providerId > 0) {
            $where[]  = 'ha.provider_id = :provider_id';
            $params[':provider_id'] = $providerId;
        }

        $sql = "SELECT ha.*,
                       h.name AS client_name,
                       p.name AS provider_name,
                       p.type AS provider_type,
                       COALESCE(wd.domain_count, 0)        AS domain_count,
                       COALESCE(wm.email_service_count, 0) AS email_service_count,
                       DATEDIFF(ha.expiry_date, CURDATE()) AS days_left
                FROM hosting_accounts ha
                JOIN hosting h    ON h.id = ha.client_id
                JOIN providers p  ON p.id = ha.provider_id
                LEFT JOIN (
                    SELECT hosting_account_id, COUNT(*) AS domain_count
                    FROM websites
                    WHERE service_type = 'domain' AND hosting_account_id IS NOT NULL
                    GROUP BY hosting_account_id
                ) wd ON wd.hosting_account_id = ha.id
                LEFT JOIN (
                    SELECT hosting_account_id, COUNT(*) AS email_service_count
                    FROM websites
                    WHERE service_type = 'hosting_mail' AND hosting_account_id IS NOT NULL
                    GROUP BY hosting_account_id
                ) wm ON wm.hosting_account_id = ha.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY h.name, p.name, ha.expiry_date";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dropdowns for filters/forms
        $clients   = $this->pdo->query('SELECT id, name FROM hosting ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $providers = $this->pdo->query("SELECT id, name, type FROM providers WHERE type='whm' AND is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';
        include APP_PATH . '/views/hosting_accounts/index.php';
    }

    // ------------------------------------------------------------------ //
    //  CREATE
    // ------------------------------------------------------------------ //
    public function create(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        $this->requireManagerOrAbove();

        $errors   = [];
        $formData = ['is_active' => 1, 'status' => 'active'];
        $clients   = $this->pdo->query('SELECT id, name FROM hosting ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $providers = $this->pdo->query("SELECT id, name FROM providers WHERE type='whm' AND is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $this->sanitizeInput($_POST);
            $errors   = $this->validate($formData);

            if (empty($errors)) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO hosting_accounts
                     (client_id, provider_id, cpanel_username, package_name, disk_quota_mb, bandwidth_mb,
                      ip_address, expiry_date, auto_renew, status, notes)
                     VALUES
                     (:client_id, :provider_id, :cpanel_username, :package_name, :disk_quota_mb, :bandwidth_mb,
                      :ip_address, :expiry_date, :auto_renew, :status, :notes)'
                );
                $stmt->execute([
                    ':client_id'       => $formData['client_id'],
                    ':provider_id'     => $formData['provider_id'],
                    ':cpanel_username' => $formData['cpanel_username'] ?: null,
                    ':package_name'    => $formData['package_name']    ?: null,
                    ':disk_quota_mb'   => $formData['disk_quota_mb']   !== '' ? (int)$formData['disk_quota_mb'] : null,
                    ':bandwidth_mb'    => $formData['bandwidth_mb']    !== '' ? (int)$formData['bandwidth_mb']  : null,
                    ':ip_address'      => $formData['ip_address']      ?: null,
                    ':expiry_date'     => $formData['expiry_date']     ?: null,
                    ':auto_renew'      => isset($formData['auto_renew']) ? 1 : 0,
                    ':status'          => $formData['status'],
                    ':notes'           => $formData['notes']           ?: null,
                ]);
                $_SESSION['message'] = __('hosting_accounts.created_ok');
                $back = isset($_POST['client_id']) ? '&client_id=' . (int)$_POST['client_id'] : '';
                header('Location: index.php?action=hosting_accounts' . $back . '&lang=' . ($_SESSION['lang'] ?? 'it'));
                exit;
            }
        }

        // Pre-select client from querystring
        if ($clientId = (int)($_GET['client_id'] ?? 0)) {
            $formData['client_id'] = $clientId;
        }

        $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';
        include APP_PATH . '/views/hosting_accounts/form.php';
    }

    // ------------------------------------------------------------------ //
    //  EDIT
    // ------------------------------------------------------------------ //
    public function edit(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        $this->requireManagerOrAbove();

        $account  = $this->findOrFail($id);
        $errors   = [];
        $formData = $account;
        $clients   = $this->pdo->query('SELECT id, name FROM hosting ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $providers = $this->pdo->query("SELECT id, name FROM providers WHERE type='whm' AND is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $this->sanitizeInput($_POST);
            $errors   = $this->validate($formData);

            if (empty($errors)) {
                $stmt = $this->pdo->prepare(
                    'UPDATE hosting_accounts
                     SET client_id=:client_id, provider_id=:provider_id, cpanel_username=:cpanel_username,
                         package_name=:package_name, disk_quota_mb=:disk_quota_mb, bandwidth_mb=:bandwidth_mb,
                         ip_address=:ip_address, expiry_date=:expiry_date, auto_renew=:auto_renew,
                         status=:status, notes=:notes
                     WHERE id=:id'
                );
                $stmt->execute([
                    ':client_id'       => $formData['client_id'],
                    ':provider_id'     => $formData['provider_id'],
                    ':cpanel_username' => $formData['cpanel_username'] ?: null,
                    ':package_name'    => $formData['package_name']    ?: null,
                    ':disk_quota_mb'   => $formData['disk_quota_mb']   !== '' ? (int)$formData['disk_quota_mb'] : null,
                    ':bandwidth_mb'    => $formData['bandwidth_mb']    !== '' ? (int)$formData['bandwidth_mb']  : null,
                    ':ip_address'      => $formData['ip_address']      ?: null,
                    ':expiry_date'     => $formData['expiry_date']     ?: null,
                    ':auto_renew'      => isset($formData['auto_renew']) ? 1 : 0,
                    ':status'          => $formData['status'],
                    ':notes'           => $formData['notes']           ?: null,
                    ':id'              => $id,
                ]);
                $_SESSION['message'] = __('hosting_accounts.updated_ok');
                header('Location: index.php?action=hosting_accounts&lang=' . ($_SESSION['lang'] ?? 'it'));
                exit;
            }
        }

        $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';
        include APP_PATH . '/views/hosting_accounts/form.php';
    }

    // ------------------------------------------------------------------ //
    //  DELETE
    // ------------------------------------------------------------------ //
    public function delete(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        $this->requireManagerOrAbove();

        // Cascades handle domain_assignments and email_services (ON DELETE CASCADE / SET NULL)
        $this->pdo->prepare('DELETE FROM hosting_accounts WHERE id=:id')->execute([':id' => $id]);
        $_SESSION['message'] = __('hosting_accounts.deleted_ok');
        header('Location: index.php?action=hosting_accounts&lang=' . ($_SESSION['lang'] ?? 'it'));
        exit;
    }

    // ------------------------------------------------------------------ //
    //  VIEW — detail for a single hosting account (domains + email)
    // ------------------------------------------------------------------ //
    public function view(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $account = $this->findOrFail($id);

        // Active domain assignments
        $domains = $this->pdo->prepare(
            "SELECT d.*, p.name AS registrar_name,
                    da.is_primary, da.assigned_at,
                    DATEDIFF(d.expiry_date, CURDATE()) AS days_left
             FROM domain_assignments da
             JOIN domains d  ON d.id  = da.domain_id
             JOIN providers p ON p.id = d.registrar_id
             WHERE da.hosting_account_id = :id AND da.unassigned_at IS NULL
             ORDER BY da.is_primary DESC, d.domain_name"
        );
        $domains->execute([':id' => $id]);
        $assignedDomains = $domains->fetchAll(PDO::FETCH_ASSOC);

        // Email services linked to this hosting account
        $emails = $this->pdo->prepare(
            "SELECT es.*,
                    COALESCE(p.name, 'cPanel') AS provider_name,
                    d.domain_name,
                    DATEDIFF(COALESCE(es.expiry_date, ha.expiry_date), CURDATE()) AS days_left,
                    COALESCE(es.expiry_date, ha.expiry_date) AS effective_expiry
             FROM email_services es
             LEFT JOIN providers p ON p.id = es.provider_id
             LEFT JOIN domains d   ON d.id = es.domain_id
             LEFT JOIN hosting_accounts ha ON ha.id = es.hosting_account_id
             WHERE es.hosting_account_id = :id
             ORDER BY es.service_type, d.domain_name"
        );
        $emails->execute([':id' => $id]);
        $emailServices = $emails->fetchAll(PDO::FETCH_ASSOC);

        // Provider + client info
        $meta = $this->pdo->prepare(
            'SELECT ha.*, h.name AS client_name, p.name AS provider_name, p.type AS provider_type,
                    DATEDIFF(ha.expiry_date, CURDATE()) AS days_left
             FROM hosting_accounts ha
             JOIN hosting h   ON h.id = ha.client_id
             JOIN providers p ON p.id = ha.provider_id
             WHERE ha.id = :id'
        );
        $meta->execute([':id' => $id]);
        $account = $meta->fetch(PDO::FETCH_ASSOC);

        $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';
        include APP_PATH . '/views/hosting_accounts/view.php';
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //
    private function findOrFail(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM hosting_accounts WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['error'] = __('hosting_accounts.not_found');
            header('Location: index.php?action=hosting_accounts');
            exit;
        }
        return $row;
    }

    private function sanitizeInput(array $post): array
    {
        return [
            'client_id'       => (int)($post['client_id'] ?? 0),
            'provider_id'     => (int)($post['provider_id'] ?? 0),
            'cpanel_username' => trim($post['cpanel_username'] ?? ''),
            'package_name'    => trim($post['package_name'] ?? ''),
            'disk_quota_mb'   => trim($post['disk_quota_mb'] ?? ''),
            'bandwidth_mb'    => trim($post['bandwidth_mb'] ?? ''),
            'ip_address'      => trim($post['ip_address'] ?? ''),
            'expiry_date'     => sm_normalize_date(trim($post['expiry_date'] ?? ''), '') ?? '',
            'auto_renew'      => $post['auto_renew'] ?? null,
            'status'          => $post['status'] ?? 'active',
            'notes'           => trim($post['notes'] ?? ''),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        $allowedStatuses = ['active', 'suspended', 'expired', 'cancelled'];

        if ((int)$data['client_id'] <= 0) {
            $errors[] = __('hosting_accounts.error_client_required');
        }
        if ((int)$data['provider_id'] <= 0) {
            $errors[] = __('hosting_accounts.error_provider_required');
        }
        if (!in_array($data['status'], $allowedStatuses, true)) {
            $errors[] = __('hosting_accounts.error_status_invalid');
        }
        if ($data['expiry_date'] !== '' && !sm_normalize_date($data['expiry_date'])) {
            $errors[] = __('hosting_accounts.error_expiry_invalid');
        }
        if ($data['ip_address'] !== '' && !filter_var($data['ip_address'], FILTER_VALIDATE_IP)) {
            $errors[] = __('hosting_accounts.error_ip_invalid');
        }

        return $errors;
    }

    private function requireManagerOrAbove(): void
    {
        $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';
        if (!in_array($role, ['manager', 'super_admin'], true)) {
            $_SESSION['error'] = __('common.forbidden');
            header('Location: index.php?action=hosting_accounts');
            exit;
        }
    }
}
