<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>
<?php
$account        = $account        ?? [];
$assignedDomains = $assignedDomains ?? [];
$emailServices  = $emailServices  ?? [];
$userRole       = $userRole       ?? ($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer');

$statusBadge = ['active'=>'success','suspended'=>'warning','expired'=>'danger','cancelled'=>'secondary'];

function haViewDaysClass(mixed $days): string {
    if ($days === null) return 'text-muted';
    $d = (int)$days;
    if ($d < 0) return 'text-danger font-weight-bold';
    if ($d <= 30) return 'text-warning font-weight-bold';
    return 'text-success';
}
?>

<div class="content-wrapper">
    <section class="content-header px-0 pb-0">
        <div style="background:linear-gradient(135deg,#0d6efd 0%,#3b82f6 100%);color:#fff;padding:1.4rem 1.75rem 1.2rem;">
            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem;">
                <div>
                    <h1 class="mb-0" style="font-size:1.45rem;font-weight:700;">
                        <i class="fas fa-hdd mr-2" style="opacity:.85;"></i>
                        <?= htmlspecialchars($account['provider_name'] ?? '') ?>
                        <small style="font-weight:400;font-size:.85rem;opacity:.8;">/ <?= htmlspecialchars($account['cpanel_username'] ?? '—') ?></small>
                    </h1>
                    <small style="opacity:.75;font-size:.8rem;">
                        <?= __('hosting_accounts.client') ?>: <strong><?= htmlspecialchars($account['client_name'] ?? '') ?></strong>
                    </small>
                </div>
                <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                    <a href="index.php?action=hosting_accounts&do=edit&id=<?= (int)$account['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                        class="btn btn-light btn-sm">
                        <i class="fas fa-edit mr-1"></i><?= __('common.edit') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid mt-3">

            <!-- Summary Cards -->
            <div class="row mb-3">
                <?php
                $daysLeft  = $account['days_left'] ?? null;
                $daysClass = haViewDaysClass($daysLeft);
                $badge     = $statusBadge[$account['status'] ?? 'active'] ?? 'secondary';
                ?>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="card shadow-sm h-100" style="border-left:4px solid #0d6efd;">
                        <div class="card-body py-3 px-3">
                            <div style="font-size:.68rem;text-transform:uppercase;color:#6c757d;font-weight:600;"><?= __('hosting_accounts.col_provider') ?></div>
                            <div style="font-size:1.1rem;font-weight:700;"><?= htmlspecialchars($account['provider_name'] ?? '—') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="card shadow-sm h-100" style="border-left:4px solid #198754;">
                        <div class="card-body py-3 px-3">
                            <div style="font-size:.68rem;text-transform:uppercase;color:#6c757d;font-weight:600;"><?= __('hosting_accounts.col_package') ?></div>
                            <div style="font-size:1.1rem;font-weight:700;"><?= htmlspecialchars($account['package_name'] ?? '—') ?></div>
                            <?php if (!empty($account['cpanel_username'])): ?>
                                <small class="text-muted"><?= __('hosting_accounts.col_cpanel_user') ?>: <code><?= htmlspecialchars($account['cpanel_username']) ?></code></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="card shadow-sm h-100" style="border-left:4px solid <?= $daysLeft !== null && $daysLeft < 30 ? '#dc3545' : '#17a2b8' ?>;">
                        <div class="card-body py-3 px-3">
                            <div style="font-size:.68rem;text-transform:uppercase;color:#6c757d;font-weight:600;"><?= __('hosting_accounts.col_expiry') ?></div>
                            <div style="font-size:1.1rem;font-weight:700;"><?= !empty($account['expiry_date']) ? htmlspecialchars($account['expiry_date']) : '—' ?></div>
                            <small class="<?= $daysClass ?>"><?= $daysLeft !== null ? (int)$daysLeft . ' ' . __('common.days_left') : '' ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="card shadow-sm h-100" style="border-left:4px solid #6c757d;">
                        <div class="card-body py-3 px-3">
                            <div style="font-size:.68rem;text-transform:uppercase;color:#6c757d;font-weight:600;"><?= __('hosting_accounts.col_status') ?></div>
                            <div class="mt-1">
                                <span class="badge badge-<?= $badge ?>" style="font-size:.85rem;"><?= ucfirst($account['status'] ?? '') ?></span>
                                <?php if ($account['auto_renew'] ?? false): ?>
                                    <span class="badge badge-info ml-1"><i class="fas fa-sync-alt mr-1"></i><?= __('hosting_accounts.auto_renew') ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($account['ip_address'])): ?>
                                <small class="text-muted d-block mt-1">IP: <code><?= htmlspecialchars($account['ip_address']) ?></code></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Assigned Domains -->
                <div class="col-lg-7 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex align-items-center justify-content-between py-2">
                            <h6 class="mb-0 font-weight-bold">
                                <i class="fas fa-globe mr-2 text-secondary"></i><?= __('hosting_accounts.assigned_domains') ?>
                                <span class="badge badge-secondary ml-1"><?= count($assignedDomains) ?></span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($assignedDomains)): ?>
                                <div class="px-3 py-3 text-muted"><i class="fas fa-info-circle mr-1"></i><?= __('hosting_accounts.no_domains') ?></div>
                            <?php else: ?>
                                <table class="table table-sm table-hover mb-0" style="font-size:.85rem;">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><?= __('providers.col_name') ?></th>
                                            <th><?= __('domains.col_registrar') ?></th>
                                            <th><?= __('hosting_accounts.col_expiry') ?></th>
                                            <th class="text-center"><?= __('hosting_accounts.col_days_left') ?></th>
                                            <th class="text-center"><?= __('domains.col_primary') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignedDomains as $d): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($d['domain_name']) ?></strong></td>
                                                <td><span class="badge badge-light border"><?= htmlspecialchars($d['registrar_name']) ?></span></td>
                                                <td><?= !empty($d['expiry_date']) ? htmlspecialchars($d['expiry_date']) : '—' ?></td>
                                                <td class="text-center">
                                                    <span class="<?= haViewDaysClass($d['days_left'] ?? null) ?>">
                                                        <?= $d['days_left'] !== null ? (int)$d['days_left'] : '—' ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($d['is_primary']): ?>
                                                        <span class="badge badge-primary"><?= __('domains.primary') ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-light border"><?= __('domains.addon') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Email Services -->
                <div class="col-lg-5 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex align-items-center justify-content-between py-2">
                            <h6 class="mb-0 font-weight-bold">
                                <i class="fas fa-envelope mr-2 text-primary"></i><?= __('hosting_accounts.email_services') ?>
                                <span class="badge badge-primary ml-1"><?= count($emailServices) ?></span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($emailServices)): ?>
                                <div class="px-3 py-3 text-muted"><i class="fas fa-info-circle mr-1"></i><?= __('hosting_accounts.no_email_services') ?></div>
                            <?php else: ?>
                                <table class="table table-sm table-hover mb-0" style="font-size:.85rem;">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><?= __('email_services.col_domain') ?></th>
                                            <th><?= __('email_services.col_type') ?></th>
                                            <th><?= __('hosting_accounts.col_expiry') ?></th>
                                            <th class="text-center"><?= __('hosting_accounts.col_days_left') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($emailServices as $es): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($es['domain_name'] ?? '—') ?></td>
                                                <td><span class="badge badge-light border"><?= htmlspecialchars($es['provider_name']) ?></span></td>
                                                <td>
                                                    <?= !empty($es['effective_expiry']) ? htmlspecialchars($es['effective_expiry']) : '—' ?>
                                                    <?php if ($es['expiry_date'] === null): ?>
                                                        <small class="text-muted d-block" style="font-size:.72rem;"><?= __('email_services.inherited') ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="<?= haViewDaysClass($es['days_left'] ?? null) ?>">
                                                        <?= $es['days_left'] !== null ? (int)$es['days_left'] : '—' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($account['notes'])): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header py-2"><h6 class="mb-0"><?= __('common.notes') ?></h6></div>
                    <div class="card-body"><p class="mb-0"><?= nl2br(htmlspecialchars($account['notes'])) ?></p></div>
                </div>
            <?php endif; ?>

            <a href="index.php?action=hosting_accounts&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i><?= __('common.back') ?>
            </a>

        </div>
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>
