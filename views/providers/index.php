<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>
<?php
$providers = $providers ?? [];
$userRole  = $userRole  ?? ($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer');

$typeBadge = [
    'whm'       => ['label' => __('providers.type_whm'),       'color' => '#0d6efd'],
    'registrar' => ['label' => __('providers.type_registrar'), 'color' => '#198754'],
    'email'     => ['label' => __('providers.type_email'),     'color' => '#6f42c1'],
    'other'     => ['label' => __('providers.type_other'),     'color' => '#6c757d'],
];
?>

<div class="content-wrapper">

    <!-- Hero Header -->
    <section class="content-header px-0 pb-0">
        <div style="background:linear-gradient(135deg,#198754 0%,#20c997 100%);color:#fff;padding:1.4rem 1.75rem 1.2rem;">
            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem;">
                <div>
                    <h1 class="mb-0" style="font-size:1.45rem;font-weight:700;">
                        <i class="fas fa-network-wired mr-2" style="opacity:.85;"></i><?= __('providers.title') ?>
                    </h1>
                    <small style="opacity:.75;font-size:.8rem;"><?= __('providers.subtitle') ?></small>
                </div>
                <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                    <a href="index.php?action=providers&do=create&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                        class="btn btn-light btn-sm font-weight-600">
                        <i class="fas fa-plus mr-1"></i><?= __('providers.add') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="content">
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

            <!-- KPI Summary -->
            <?php
            $byType = ['whm' => 0, 'registrar' => 0, 'email' => 0, 'other' => 0];
            foreach ($providers as $p) {
                $byType[$p['type'] ?? 'other'] = ($byType[$p['type'] ?? 'other'] ?? 0) + 1;
            }
            ?>
            <div class="row mt-3 mb-3">
                <?php foreach ($byType as $t => $cnt): ?>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="card shadow-sm h-100" style="border-left:4px solid <?= $typeBadge[$t]['color'] ?>;">
                            <div class="card-body py-3 px-3 d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mr-3"
                                    style="width:40px;height:40px;background:<?= $typeBadge[$t]['color'] ?>22;flex-shrink:0;">
                                    <i class="fas <?= $t === 'whm' ? 'fa-server' : ($t === 'registrar' ? 'fa-globe' : ($t === 'email' ? 'fa-envelope' : 'fa-plug')) ?>"
                                        style="color:<?= $typeBadge[$t]['color'] ?>;"></i>
                                </div>
                                <div>
                                    <div style="font-size:.68rem;text-transform:uppercase;color:#6c757d;font-weight:600;"><?= $typeBadge[$t]['label'] ?></div>
                                    <div style="font-size:1.5rem;font-weight:700;color:<?= $typeBadge[$t]['color'] ?>;line-height:1.1;"><?= $cnt ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Filter Bar -->
            <form method="get" action="index.php" class="mb-3 d-flex flex-wrap align-items-center" style="gap:.5rem;">
                <input type="hidden" name="action" value="providers">
                <input type="hidden" name="lang" value="<?= htmlspecialchars($_SESSION['lang'] ?? 'it') ?>">
                <select name="filter_type" class="form-control form-control-sm" style="max-width:160px;">
                    <option value=""><?= __('providers.all_types') ?></option>
                    <?php foreach ($typeBadge as $t => $info): ?>
                        <option value="<?= $t ?>" <?= ($_GET['filter_type'] ?? '') === $t ? 'selected' : '' ?>><?= $info['label'] ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                    class="form-control form-control-sm" style="max-width:220px;" placeholder="<?= __('common.search') ?>...">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search mr-1"></i><?= __('common.search') ?></button>
                <a href="index.php?action=providers&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-sm btn-outline-secondary"><?= __('common.reset') ?></a>
            </form>

            <!-- Providers Table -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm mb-0" style="font-size:.88rem;">
                            <thead class="thead-dark">
                                <tr>
                                    <th><?= __('providers.col_name') ?></th>
                                    <th><?= __('providers.col_type') ?></th>
                                    <th><?= __('providers.col_url') ?></th>
                                    <th class="text-center"><?= __('providers.col_accounts') ?></th>
                                    <th class="text-center"><?= __('providers.col_domains') ?></th>
                                    <th class="text-center"><?= __('providers.col_email_svcs') ?></th>
                                    <th class="text-center"><?= __('providers.col_status') ?></th>
                                    <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                        <th><?= __('hosting.actions') ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($providers)): ?>
                                    <tr><td colspan="8" class="text-center py-4 text-muted"><?= __('providers.empty') ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($providers as $p):
                                        $tb = $typeBadge[$p['type']] ?? $typeBadge['other'];
                                    ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                            <td>
                                                <span class="badge" style="background:<?= $tb['color'] ?>;color:#fff;">
                                                    <?= $tb['label'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($p['base_url'])): ?>
                                                    <a href="<?= htmlspecialchars($p['base_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-truncate d-inline-block" style="max-width:200px;">
                                                        <?= htmlspecialchars($p['base_url']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ((int)$p['hosting_accounts_count'] > 0): ?>
                                                    <a href="index.php?action=hosting_accounts&provider_id=<?= $p['id'] ?>" class="badge badge-info">
                                                        <?= (int)$p['hosting_accounts_count'] ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ((int)$p['domains_count'] > 0): ?>
                                                    <span class="badge badge-secondary"><?= (int)$p['domains_count'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ((int)$p['email_services_count'] > 0): ?>
                                                    <span class="badge badge-primary"><?= (int)$p['email_services_count'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-<?= $p['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $p['is_active'] ? __('common.active') : __('common.inactive') ?>
                                                </span>
                                            </td>
                                            <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                                <td>
                                                    <a href="index.php?action=providers&do=edit&id=<?= $p['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                        class="btn btn-xs btn-primary"><i class="fas fa-edit"></i></a>
                                                    <form method="post" action="index.php?action=providers&do=toggle_active&id=<?= $p['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="d-inline">
                                                        <button type="submit" class="btn btn-xs btn-<?= $p['is_active'] ? 'warning' : 'success' ?>"
                                                            title="<?= $p['is_active'] ? __('common.deactivate') : __('common.activate') ?>">
                                                            <i class="fas fa-<?= $p['is_active'] ? 'pause' : 'play' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" action="index.php?action=providers&do=delete&id=<?= $p['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="d-inline"
                                                        onsubmit="return confirm('<?= addslashes(__('providers.confirm_delete')) ?>')">
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
