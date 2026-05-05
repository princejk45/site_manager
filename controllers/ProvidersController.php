<?php
class ProvidersController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ------------------------------------------------------------------ //
    //  LIST
    // ------------------------------------------------------------------ //
    public function index(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $type   = $_GET['filter_type'] ?? '';
        $search = trim($_GET['search'] ?? '');

        $where  = ['1=1'];
        $params = [];

        if ($type !== '') {
            $where[]  = 'p.type = :type';
            $params[':type'] = $type;
        }
        if ($search !== '') {
            $where[]  = '(p.name LIKE :search OR p.base_url LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = 'SELECT p.*,
                       COALESCE(NULLIF(ha.hosting_accounts_count, 0), wc.website_accounts_count, 0) AS hosting_accounts_count,
                       COALESCE(wd.domains_count, 0)          AS domains_count,
                       COALESCE(wm.email_services_count, 0)   AS email_services_count
                FROM providers p
                LEFT JOIN (
                    SELECT provider_id, COUNT(*) AS hosting_accounts_count
                    FROM hosting_accounts
                    GROUP BY provider_id
                ) ha ON ha.provider_id = p.id
                LEFT JOIN (
                    SELECT provider_id,
                           COUNT(DISTINCT CASE WHEN hosting_id IS NOT NULL THEN hosting_id ELSE CONCAT("u_", id) END) AS website_accounts_count
                    FROM websites
                    GROUP BY provider_id
                ) wc ON wc.provider_id = p.id
                LEFT JOIN (
                    SELECT provider_id, COUNT(*) AS domains_count
                    FROM websites
                    WHERE service_type = "domain"
                    GROUP BY provider_id
                ) wd ON wd.provider_id = p.id
                LEFT JOIN (
                    SELECT provider_id, COUNT(*) AS email_services_count
                    FROM websites
                    WHERE service_type = "hosting_mail"
                    GROUP BY provider_id
                ) wm ON wm.provider_id = p.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.type, p.name';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';
        include APP_PATH . '/views/providers/index.php';
    }

    // ------------------------------------------------------------------ //
    //  CREATE — GET shows form, POST saves
    // ------------------------------------------------------------------ //
    public function create(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        $this->requireManagerOrAbove();

        $errors   = [];
        $formData = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $this->sanitizeInput($_POST);
            $errors   = $this->validate($formData);

            if (empty($errors)) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO providers (name, type, base_url, username, api_token, is_active, notes)
                     VALUES (:name, :type, :base_url, :username, :api_token, :is_active, :notes)'
                );
                $stmt->execute([
                    ':name'      => $formData['name'],
                    ':type'      => $formData['type'],
                    ':base_url'  => $formData['base_url'] ?: null,
                    ':username'  => $formData['username'] ?: null,
                    ':api_token' => $formData['api_token'] ?: null,
                    ':is_active' => isset($formData['is_active']) ? 1 : 0,
                    ':notes'     => $formData['notes'] ?: null,
                ]);
                $_SESSION['message'] = __('providers.created_ok');
                header('Location: index.php?action=providers&lang=' . ($_SESSION['lang'] ?? 'it'));
                exit;
            }
        }

        $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';
        include APP_PATH . '/views/providers/form.php';
    }

    // ------------------------------------------------------------------ //
    //  EDIT — GET shows pre-filled form, POST updates
    // ------------------------------------------------------------------ //
    public function edit(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        $this->requireManagerOrAbove();

        $provider = $this->findOrFail($id);
        $errors   = [];
        $formData = $provider;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $this->sanitizeInput($_POST);
            $errors   = $this->validate($formData, $id);

            if (empty($errors)) {
                $stmt = $this->pdo->prepare(
                    'UPDATE providers
                     SET name=:name, type=:type, base_url=:base_url, username=:username,
                         api_token=:api_token, is_active=:is_active, notes=:notes
                     WHERE id=:id'
                );
                $stmt->execute([
                    ':name'      => $formData['name'],
                    ':type'      => $formData['type'],
                    ':base_url'  => $formData['base_url'] ?: null,
                    ':username'  => $formData['username'] ?: null,
                    ':api_token' => $formData['api_token'] ?: null,
                    ':is_active' => isset($formData['is_active']) ? 1 : 0,
                    ':notes'     => $formData['notes'] ?: null,
                    ':id'        => $id,
                ]);
                $_SESSION['message'] = __('providers.updated_ok');
                header('Location: index.php?action=providers&lang=' . ($_SESSION['lang'] ?? 'it'));
                exit;
            }
        }

        $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';
        include APP_PATH . '/views/providers/form.php';
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

        // Prevent delete if still referenced
        $inUse = $this->pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM hosting_accounts WHERE provider_id = :id) +
                (SELECT COUNT(*) FROM websites         WHERE provider_id = :id) AS total'
        );
        $inUse->execute([':id' => $id]);
        $total = (int)($inUse->fetchColumn());

        if ($total > 0) {
            $_SESSION['error'] = __('providers.delete_in_use');
        } else {
            $this->pdo->prepare('DELETE FROM providers WHERE id=:id')->execute([':id' => $id]);
            $_SESSION['message'] = __('providers.deleted_ok');
        }
        header('Location: index.php?action=providers&lang=' . ($_SESSION['lang'] ?? 'it'));
        exit;
    }

    // ------------------------------------------------------------------ //
    //  TOGGLE ACTIVE
    // ------------------------------------------------------------------ //
    public function toggleActive(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        $this->requireManagerOrAbove();

        $this->pdo->prepare(
            'UPDATE providers SET is_active = 1 - is_active WHERE id=:id'
        )->execute([':id' => $id]);

        header('Location: index.php?action=providers&lang=' . ($_SESSION['lang'] ?? 'it'));
        exit;
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //
    private function findOrFail(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM providers WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['error'] = __('providers.not_found');
            header('Location: index.php?action=providers');
            exit;
        }
        return $row;
    }

    private function sanitizeInput(array $post): array
    {
        return [
            'name'      => trim($post['name'] ?? ''),
            'type'      => $post['type'] ?? 'whm',
            'base_url'  => trim($post['base_url'] ?? ''),
            'username'  => trim($post['username'] ?? ''),
            'api_token' => trim($post['api_token'] ?? ''),
            'is_active' => $post['is_active'] ?? null,
            'notes'     => trim($post['notes'] ?? ''),
        ];
    }

    private function validate(array $data, int $excludeId = 0): array
    {
        $errors = [];
        $allowedTypes = ['whm', 'registrar', 'email', 'other'];

        if ($data['name'] === '') {
            $errors[] = __('providers.error_name_required');
        }
        if (!in_array($data['type'], $allowedTypes, true)) {
            $errors[] = __('providers.error_type_invalid');
        }
        if ($data['base_url'] !== '' && !filter_var($data['base_url'], FILTER_VALIDATE_URL)) {
            $errors[] = __('providers.error_url_invalid');
        }

        // Unique name+type combo
        if ($data['name'] !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM providers WHERE name=:name AND type=:type AND id != :exclude'
            );
            $stmt->execute([':name' => $data['name'], ':type' => $data['type'], ':exclude' => $excludeId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = __('providers.error_duplicate');
            }
        }

        return $errors;
    }

    private function requireManagerOrAbove(): void
    {
        $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer';
        if (!in_array($role, ['manager', 'super_admin'], true)) {
            $_SESSION['error'] = __('common.forbidden');
            header('Location: index.php?action=providers');
            exit;
        }
    }
}
