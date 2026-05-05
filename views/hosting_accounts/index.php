<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>
<?php
$accounts   = $accounts   ?? [];
$clients    = $clients    ?? [];
$providers  = $providers  ?? [];
$userRole   = $userRole   ?? ($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer');

$statusBadge = [
    'active'    => 'success',
    'suspended' => 'warning',
    'expired'   => 'danger',
    'cancelled' => 'secondary',
];

function daysLeftClass(mixed $days): string {
    if ($days === null) return 'text-muted';
    $d = (int)$days;
    if ($d < 0) return 'text-danger font-weight-bold';
    if ($d <= 30) return 'text-warning font-weight-bold';
    return 'text-success';
}
?>

<div class="content-wrapper">

    <!-- Hero Header -->
    <section class="content-header px-0 pb-0">
        <div style="background:linear-gradient(135deg,#0d6efd 0%,#3b82f6 100%);color:#fff;padding:1.4rem 1.75rem 1.2rem;">
            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem;">
                <div>
                    <h1 class="mb-0" style="font-size:1.45rem;font-weight:700;">
                        <i class="fas fa-hdd mr-2" style="opacity:.85;"></i><?= __('hosting_accounts.title') ?>
                    </h1>
                    <small style="opacity:.75;font-size:.8rem;"><?= __('hosting_accounts.subtitle') ?></small>
                </div>
                <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                    <a href="index.php?action=hosting_accounts&do=create&lang=<?= $_SESSION['lang'] ?? 'it' ?><?= isset($_GET['client_id']) ? '&client_id=' . (int)$_GET['client_id'] : '' ?>"
                        class="btn btn-light btn-sm font-weight-600">
                        <i class="fas fa-plus mr-1"></i><?= __('hosting_accounts.add') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="content pt-3">
        <div class="container-fluid">

            <!-- Alerts -->
            <?php if (!empty($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3">
                    <?= htmlspecialchars($_SESSION['message']) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Filter Bar -->
            <form method="get" action="index.php" class="mt-3 mb-3 d-flex flex-wrap align-items-center" style="gap:.5rem;">
                <input type="hidden" name="action" value="hosting_accounts">
                <input type="hidden" name="lang" value="<?= htmlspecialchars($_SESSION['lang'] ?? 'it') ?>">
                <select name="client_id" class="form-control form-control-sm" style="max-width:200px;">
                    <option value="0"><?= __('hosting_accounts.all_clients') ?></option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (int)($_GET['client_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="provider_id" class="form-control form-control-sm" style="max-width:180px;">
                    <option value="0"><?= __('hosting_accounts.all_providers') ?></option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (int)($_GET['provider_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter mr-1"></i><?= __('common.filter') ?></button>
                <a href="index.php?action=hosting_accounts&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-sm btn-outline-secondary"><?= __('common.reset') ?></a>
            </form>

            <!-- Accounts Table -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size:.88rem;">
                            <thead class="thead-light" style="border-bottom:2px solid #dee2e6;">
                                <tr>
                                    <th><?= __('hosting_accounts.col_client') ?></th>
                                    <th><?= __('hosting_accounts.col_provider') ?></th>
                                    <th><?= __('hosting_accounts.col_cpanel_user') ?></th>
                                    <th><?= __('hosting_accounts.col_package') ?></th>
                                    <th class="text-center"><?= __('hosting_accounts.col_type') ?></th>
                                    <th class="text-center"><?= __('hosting_accounts.col_email_svcs') ?></th>
                                    <th><?= __('hosting_accounts.col_expiry') ?></th>
                                    <th class="text-center"><?= __('hosting_accounts.col_days_left') ?></th>
                                    <th class="text-center"><?= __('hosting_accounts.col_status') ?></th>
                                    <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                        <th><?= __('hosting.actions') ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($accounts)): ?>
                                    <tr><td colspan="10" class="text-center py-4 text-muted"><?= __('hosting_accounts.empty') ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($accounts as $a):
                                        $badge = $statusBadge[$a['status']] ?? 'secondary';
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="index.php?action=hosting&do=view&id=<?= (int)$a['client_id'] ?>">
                                                    <?= htmlspecialchars($a['client_name']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?= htmlspecialchars($a['provider_name']) ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($a['cpanel_username'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($a['package_name'] ?? '—') ?></td>
                                            <td class="text-center">
                                                <span class="badge badge-light border text-uppercase">
                                                    <?= htmlspecialchars((string)($a['provider_type'] ?? 'other')) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ((int)$a['email_service_count'] > 0): ?>
                                                    <span class="badge badge-primary"><?= (int)$a['email_service_count'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= !empty($a['expiry_date']) ? htmlspecialchars($a['expiry_date']) : '—' ?></td>
                                            <td class="text-center">
                                                <span class="<?= daysLeftClass($a['days_left'] ?? null) ?>">
                                                    <?= $a['days_left'] !== null ? (int)$a['days_left'] : '—' ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-<?= $badge ?>"><?= ucfirst($a['status']) ?></span>
                                                <?php if ($a['auto_renew']): ?>
                                                    <i class="fas fa-sync-alt text-success ml-1" title="<?= __('hosting_accounts.auto_renew') ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                                <td>
                                                    <a href="index.php?action=hosting_accounts&do=view&id=<?= $a['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                        class="btn btn-xs btn-success"><i class="fas fa-eye"></i></a>
                                                    <a href="index.php?action=hosting_accounts&do=edit&id=<?= $a['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                        class="btn btn-xs btn-primary"><i class="fas fa-edit"></i></a>
                                                    <form method="post" action="index.php?action=hosting_accounts&do=delete&id=<?= $a['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                        class="d-inline"
                                                        onsubmit="return confirm('<?= addslashes(__('hosting_accounts.confirm_delete')) ?>')">
                                                        <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>
