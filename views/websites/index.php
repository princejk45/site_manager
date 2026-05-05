<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>
<?php
$search          = $search          ?? '';
$page            = $page            ?? 1;
$perPage         = $perPage         ?? 10;
$totalPages      = $totalPages      ?? 1;
$totalDomains    = isset($totalDomains) ? $totalDomains : count($domainSummaries ?? []);
$domainSummaries = $domainSummaries ?? [];
$userRole        = $userRole        ?? ($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer');
$lang            = $_SESSION['lang'] ?? 'en';
$canManage       = ($userRole !== 'viewer');

function wsDomStatusBadgeClass(string $s): string {
    return match($s) {
        'expired'       => 'danger',
        'expiring_soon' => 'warning',
        'active'        => 'success',
        default         => 'secondary',
    };
}
function wsDomStatusLabel(string $s): string {
    return match($s) {
        'expired'       => __('common.expired'),
        'expiring_soon' => __('common.expiring_soon'),
        'active'        => __('common.active'),
        default         => ucfirst(str_replace('_',' ',$s)),
    };
}
function wsFormatDate(?string $date): string {
    if (empty($date)) {
        return '-';
    }
    try {
        return (new DateTimeImmutable($date))->format('d-m-Y');
    } catch (Exception $e) {
        return htmlspecialchars((string)$date, ENT_QUOTES, 'UTF-8');
    }
}
function wsRemainingLabel(?string $date): string {
    if (empty($date)) {
        return '-';
    }
    try {
        $today = new DateTimeImmutable('today');
        $expiry = new DateTimeImmutable($date);
        $interval = $today->diff($expiry);

        $months = ($interval->y * 12) + $interval->m;
        $days = $interval->d;
        $monthLabel = $months === 1 ? __('websites.month_singular') : __('websites.month_plural');
        $dayLabel = $days === 1 ? __('websites.day_singular') : __('websites.day_plural');
        $durationParts = [];
        if ($months > 0) {
            $durationParts[] = $months . ' ' . $monthLabel;
        }
        if ($days > 0 || $months === 0) {
            $durationParts[] = $days . ' ' . $dayLabel;
        }
        $duration = implode(' ', $durationParts);

        return $interval->invert
            ? __('websites.remaining_overdue_by', ['duration' => $duration])
            : __('websites.remaining_in', ['duration' => $duration]);
    } catch (Exception $e) {
        return '-';
    }
}
?>
<div class="content-wrapper">

    <!-- Gradient hero header -->
    <section class="content-header" style="background:linear-gradient(135deg,#1a6fc4 0%,#0d47a1 100%);padding:24px 28px 20px;margin-bottom:0;">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:12px;">
                <div>
                    <h1 class="mb-0 text-white" style="font-size:1.6rem;font-weight:700;letter-spacing:-.3px;">
                        <i class="fas fa-globe mr-2"></i><?= __('websites.manage_services') ?>
                    </h1>
                    <p class="mb-0 text-white-50" style="font-size:.85rem;margin-top:4px;">
                        <?= (int)$totalDomains ?> domain(s) &mdash; domain &bull; web hosting &bull; mail &mdash; one row per domain
                    </p>
                </div>
                <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                <a href="index.php?action=websites&do=create&lang=<?= $lang ?>" class="btn btn-light btn-sm">
                    <i class="fas fa-plus mr-1"></i> <?= __('websites.add_service') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="content" style="padding-top:18px;">
        <div class="container-fluid">

            <!-- Flash messages -->
            <?php if (!empty($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['message']) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Search + per-page + export bar -->
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:10px;">
                <form method="get" action="index.php" class="d-flex" style="gap:6px;">
                    <input type="hidden" name="action" value="websites">
                    <input type="hidden" name="lang"   value="<?= $lang ?>">
                    <div class="input-group input-group-sm" style="min-width:240px;">
                        <input type="text" class="form-control" name="search"
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="<?= __('common.search') ?>...">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
                <div class="d-flex align-items-center" style="gap:10px;">
                    <form method="get" action="index.php" class="d-flex align-items-center" style="gap:6px;">
                        <input type="hidden" name="action" value="websites">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="lang"   value="<?= $lang ?>">
                        <label class="mb-0"><?= __('common.show') ?>:</label>
                        <select name="per_page" class="form-control form-control-sm" onchange="this.form.submit()">
                            <?php foreach ([10,30,50] as $n): ?>
                                <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <form method="post" action="index.php?action=websites&do=export&lang=<?= $lang ?>" class="d-inline">
                        <button class="btn btn-success btn-sm">
                            <i class="fas fa-file-export mr-1"></i><?= __('websites.export_excel') ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Domain-centric service table -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <style>
                        .ws-svc { border-radius:8px; padding:8px 12px; }
                        .ws-svc .ws-prov { font-weight:700; font-size:.82rem; }
                        .ws-svc .ws-det  { font-size:.78rem; color:#6c757d; }
                        .ws-svc .ws-exp  { font-size:.78rem; }
                        .ws-svc.ws-none  { background:#f8f9fa; border:1px dashed #ced4da; color:#adb5bd; }
                        .ws-svc.ws-ok    { background:#f0fff4; border:1px solid #28a745; }
                        .ws-svc.ws-warn  { background:#fffbf0; border:1px solid #ffc107; }
                        .ws-svc.ws-bad   { background:#fff5f5; border:1px solid #dc3545; }
                        .ws-days { font-size:.7rem; font-weight:700; padding:2px 6px; border-radius:20px; display:inline-block; }
                    </style>
                    <div class="table-responsive">
                        <form id="ws-bulk-form" method="post" action="index.php?action=websites&do=bulk_delete&lang=<?= $lang ?>">
                        <input type="hidden" name="ids" id="ws-bulk-ids" value="">
                        <?php if ($canManage): ?>
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom" style="gap:10px;">
                            <div class="d-flex align-items-center" style="gap:10px;">
                                <small class="text-muted"><span id="ws-selected-count">0</span> <?= __('websites.selected') ?></small>
                            </div>
                            <button type="button" id="ws-bulk-delete-btn" class="btn btn-danger btn-xs" style="display:none;">
                                <i class="fas fa-trash mr-1"></i><?= __('websites.delete_selected') ?>
                            </button>
                        </div>
                        <?php endif; ?>
                        <table class="table table-bordered table-hover mb-0" style="font-size:.85rem;">
                            <thead>
                                <tr style="background:#343a40;color:#fff;">
                                    <?php if ($canManage): ?>
                                    <th style="width:36px;" class="text-center">
                                        <input type="checkbox" id="ws-select-all" aria-label="<?= __('websites.select_all') ?>">
                                    </th>
                                    <?php endif; ?>
                                    <th style="width:160px;">Domain</th>
                                    <th style="width:110px;">Client</th>
                                    <th style="min-width:175px; border-left:3px solid #6c757d;">
                                        <i class="fas fa-at mr-1"></i><?= __('websites.registrar') ?>
                                    </th>
                                    <th style="min-width:175px; border-left:3px solid #17a2b8;">
                                        <i class="fas fa-server mr-1"></i><?= __('websites.type_hosting_web') ?>
                                    </th>
                                    <th style="min-width:175px; border-left:3px solid #007bff;">
                                        <i class="fas fa-envelope mr-1"></i><?= __('websites.mail_provider') ?>
                                    </th>
                                    <th style="width:130px;"><?= __('websites.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($domainSummaries)): ?>
                                <tr>
                                    <td colspan="<?= $canManage ? 7 : 6 ?>" class="text-center text-muted py-5">
                                        <i class="fas fa-globe fa-2x mb-2 d-block"></i>
                                        <?= __('websites.no_domains_found') ?> <a href="index.php?action=websites&do=create&lang=<?= $lang ?>"><?= __('websites.add_first_domain') ?></a>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($domainSummaries as $row):
                                    $domain = htmlspecialchars($row['domain']);
                                    $viewId = $row['web_id'] ?? $row['dom_id'] ?? $row['mail_id'];
                                ?>
                                <tr>
                                    <?php if ($canManage): ?>
                                    <td class="align-middle text-center">
                                        <?php if ($viewId): ?>
                                        <input type="checkbox" class="ws-select-item" value="<?= (int)$viewId ?>" aria-label="Select service">
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <!-- Domain name -->
                                    <td class="align-middle" style="font-weight:600;">
                                        <i class="fas fa-globe text-muted mr-1" style="font-size:.75rem;"></i>
                                        <?= $domain ?>
                                    </td>

                                    <!-- Client -->
                                    <td class="align-middle">
                                        <?php if (!empty($row['client_name'])): ?>
                                            <span class="badge badge-light border"><?= htmlspecialchars($row['client_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:.78rem;">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Domain Registration -->
                                    <td class="align-middle" style="border-left:3px solid #6c757d;">
                                    <?php if (!empty($row['dom_id'])): ?>
                                        <?php
                                        $cls = match($row['dom_computed_status'] ?? '') {
                                            'expired'       => 'ws-bad',
                                            'expiring_soon' => 'ws-warn',
                                            default         => 'ws-ok',
                                        };
                                        $d = (int)($row['dom_days_left'] ?? 0);
                                        $bc = $d < 0 ? 'danger' : ($d <= 30 ? 'warning' : 'success');
                                        ?>
                                        <div class="ws-svc <?= $cls ?>">
                                            <div class="ws-prov">
                                                <i class="fas fa-building mr-1"></i>
                                                <?= !empty($row['dom_registrar']) ? htmlspecialchars($row['dom_registrar']) : '<em class="text-muted fw-normal">' . __('websites.no_registrar') . '</em>' ?>
                                            </div>
                                            <?php if (!empty($row['dom_expiry'])): ?>
                                            <div class="ws-exp mt-1">
                                                <i class="fas fa-calendar-alt mr-1"></i><?= htmlspecialchars(wsFormatDate($row['dom_expiry']), ENT_QUOTES, 'UTF-8') ?>
                                                <span class="badge badge-<?= $bc ?> ws-days ml-1"><?= htmlspecialchars(wsRemainingLabel($row['dom_expiry']), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <span class="badge badge-<?= wsDomStatusBadgeClass($row['dom_computed_status'] ?? '') ?>" style="font-size:.65rem;">
                                                    <?= wsDomStatusLabel($row['dom_computed_status'] ?? '') ?>
                                                </span>
                                                <?php if ($userRole !== 'viewer'): ?>
                                                <a href="index.php?action=websites&do=edit&id=<?= (int)$row['dom_id'] ?>&lang=<?= $lang ?>"
                                                   class="btn btn-xs btn-outline-secondary ml-1" style="font-size:.65rem;padding:1px 5px;">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="index.php?action=websites&do=delete&id=<?= (int)$row['dom_id'] ?>&lang=<?= $lang ?>"
                                                   class="btn btn-xs btn-outline-danger ml-1" style="font-size:.65rem;padding:1px 5px;"
                                                   onclick="return confirm('<?= addslashes(__('websites.delete_registrar_service_confirm')) ?>');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="ws-svc ws-none d-flex align-items-center justify-content-between">
                                            <span style="font-size:.78rem;"><i class="fas fa-plus-circle mr-1"></i><?= __('websites.not_configured') ?></span>
                                            <?php if ($userRole !== 'viewer'): ?>
                                            <a href="index.php?action=websites&do=create&domain=<?= urlencode($row['domain']) ?>&service_type=domain&lang=<?= $lang ?>"
                                               class="btn btn-xs btn-outline-secondary" style="font-size:.65rem;padding:1px 6px;"><?= __('websites.add_short') ?></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    </td>

                                    <!-- Web Hosting -->
                                    <td class="align-middle" style="border-left:3px solid #17a2b8;">
                                    <?php if (!empty($row['web_id'])): ?>
                                        <?php
                                        $cls = match($row['web_computed_status'] ?? '') {
                                            'expired'       => 'ws-bad',
                                            'expiring_soon' => 'ws-warn',
                                            default         => 'ws-ok',
                                        };
                                        $d = (int)($row['web_days_left'] ?? 0);
                                        $bc = $d < 0 ? 'danger' : ($d <= 30 ? 'warning' : 'success');
                                        ?>
                                        <div class="ws-svc <?= $cls ?>">
                                            <div class="ws-prov">
                                                <i class="fas fa-server mr-1"></i>
                                                <?= !empty($row['web_provider']) ? htmlspecialchars($row['web_provider']) : '<em class="text-muted fw-normal">' . __('websites.no_server_linked') . '</em>' ?>
                                            </div>
                                            <?php if (!empty($row['web_cpanel'])): ?>
                                            <div class="ws-det mt-1">
                                                <i class="fas fa-terminal mr-1"></i>cPanel: <?= htmlspecialchars($row['web_cpanel']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($row['web_expiry'])): ?>
                                            <div class="ws-exp mt-1">
                                                <i class="fas fa-calendar-alt mr-1"></i><?= htmlspecialchars(wsFormatDate($row['web_expiry']), ENT_QUOTES, 'UTF-8') ?>
                                                <span class="badge badge-<?= $bc ?> ws-days ml-1"><?= htmlspecialchars(wsRemainingLabel($row['web_expiry']), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <span class="badge badge-<?= wsDomStatusBadgeClass($row['web_computed_status'] ?? '') ?>" style="font-size:.65rem;">
                                                    <?= wsDomStatusLabel($row['web_computed_status'] ?? '') ?>
                                                </span>
                                                <?php if ($userRole !== 'viewer'): ?>
                                                <a href="index.php?action=websites&do=edit&id=<?= (int)$row['web_id'] ?>&lang=<?= $lang ?>"
                                                   class="btn btn-xs btn-outline-secondary ml-1" style="font-size:.65rem;padding:1px 5px;">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="index.php?action=websites&do=delete&id=<?= (int)$row['web_id'] ?>&lang=<?= $lang ?>"
                                                   class="btn btn-xs btn-outline-danger ml-1" style="font-size:.65rem;padding:1px 5px;"
                                                   onclick="return confirm('<?= addslashes(__('websites.delete_web_service_confirm')) ?>');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="ws-svc ws-none d-flex align-items-center justify-content-between">
                                            <span style="font-size:.78rem;"><i class="fas fa-plus-circle mr-1"></i><?= __('websites.not_configured') ?></span>
                                            <?php if ($userRole !== 'viewer'): ?>
                                            <a href="index.php?action=websites&do=create&domain=<?= urlencode($row['domain']) ?>&service_type=hosting_web&lang=<?= $lang ?>"
                                               class="btn btn-xs btn-outline-secondary" style="font-size:.65rem;padding:1px 6px;"><?= __('websites.add_short') ?></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    </td>

                                    <!-- Mail -->
                                    <td class="align-middle" style="border-left:3px solid #007bff;">
                                    <?php if (!empty($row['mail_id'])): ?>
                                        <?php
                                        $cls = match($row['mail_computed_status'] ?? '') {
                                            'expired'       => 'ws-bad',
                                            'expiring_soon' => 'ws-warn',
                                            default         => 'ws-ok',
                                        };
                                        $d = (int)($row['mail_days_left'] ?? 0);
                                        $bc = $d < 0 ? 'danger' : ($d <= 30 ? 'warning' : 'success');
                                        ?>
                                        <div class="ws-svc <?= $cls ?>">
                                            <div class="ws-prov">
                                                <i class="fas fa-envelope mr-1"></i>
                                                <?= !empty($row['mail_provider']) ? htmlspecialchars($row['mail_provider']) : '<em class="text-muted fw-normal">' . __('websites.no_provider') . '</em>' ?>
                                            </div>
                                            <?php if (!empty($row['mail_expiry'])): ?>
                                            <div class="ws-exp mt-1">
                                                <i class="fas fa-calendar-alt mr-1"></i><?= htmlspecialchars(wsFormatDate($row['mail_expiry']), ENT_QUOTES, 'UTF-8') ?>
                                                <span class="badge badge-<?= $bc ?> ws-days ml-1"><?= htmlspecialchars(wsRemainingLabel($row['mail_expiry']), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <span class="badge badge-<?= wsDomStatusBadgeClass($row['mail_computed_status'] ?? '') ?>" style="font-size:.65rem;">
                                                    <?= wsDomStatusLabel($row['mail_computed_status'] ?? '') ?>
                                                </span>
                                                <?php if ($userRole !== 'viewer'): ?>
                                                <a href="index.php?action=websites&do=edit&id=<?= (int)$row['mail_id'] ?>&lang=<?= $lang ?>"
                                                   class="btn btn-xs btn-outline-secondary ml-1" style="font-size:.65rem;padding:1px 5px;">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="index.php?action=websites&do=delete&id=<?= (int)$row['mail_id'] ?>&lang=<?= $lang ?>"
                                                   class="btn btn-xs btn-outline-danger ml-1" style="font-size:.65rem;padding:1px 5px;"
                                                   onclick="return confirm('<?= addslashes(__('websites.delete_mail_service_confirm')) ?>');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="ws-svc ws-none d-flex align-items-center justify-content-between">
                                            <span style="font-size:.78rem;"><i class="fas fa-plus-circle mr-1"></i><?= __('websites.not_configured') ?></span>
                                            <?php if ($userRole !== 'viewer'): ?>
                                            <a href="index.php?action=websites&do=create&domain=<?= urlencode($row['domain']) ?>&service_type=hosting_mail&lang=<?= $lang ?>"
                                               class="btn btn-xs btn-outline-secondary" style="font-size:.65rem;padding:1px 6px;"><?= __('websites.add_short') ?></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="align-middle text-center">
                                        <?php if ($viewId): ?>
                                        <a href="index.php?action=websites&do=view&id=<?= (int)$viewId ?>&lang=<?= $lang ?>"
                                           class="btn btn-sm btn-success d-block mb-1" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($userRole !== 'viewer'): ?>
                                        <a href="index.php?action=email&do=expiry&id=<?= (int)$viewId ?>"
                                           class="btn btn-sm btn-info d-block" title="Send expiry email">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                        <a href="index.php?action=websites&do=delete&id=<?= (int)$viewId ?>&lang=<?= $lang ?>"
                                           class="btn btn-sm btn-danger d-block mt-1"
                                           title="<?= __('websites.delete') ?>"
                                           onclick="return confirm('<?= addslashes(__('websites.delete_service_confirm')) ?>');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        </form>
                    </div>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="card-footer py-2">
                    <nav>
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?action=websites&page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>">&laquo;</a>
                                </li>
                            <?php endif; ?>
                            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                                <li class="page-item <?= $i==$page ? 'active' : '' ?>">
                                    <a class="page-link" href="?action=websites&page=<?= $i ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?action=websites&page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>">&raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </section>
</div>
<!-- /.content-wrapper -->

<?php if ($canManage): ?>
<script>
(function () {
    var selectAll = document.getElementById('ws-select-all');
    var items = Array.prototype.slice.call(document.querySelectorAll('.ws-select-item'));
    var bulkBtn = document.getElementById('ws-bulk-delete-btn');
    var idsInput = document.getElementById('ws-bulk-ids');
    var form = document.getElementById('ws-bulk-form');
    var countEl = document.getElementById('ws-selected-count');

    if (!selectAll || !bulkBtn || !idsInput || !form || !countEl) {
        return;
    }

    function syncState() {
        var checked = items.filter(function (cb) { return cb.checked; });
        countEl.textContent = String(checked.length);
        bulkBtn.style.display = checked.length > 0 ? 'inline-block' : 'none';
        if (items.length > 0) {
            selectAll.checked = checked.length === items.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < items.length;
        }
    }

    selectAll.addEventListener('change', function () {
        items.forEach(function (cb) { cb.checked = selectAll.checked; });
        syncState();
    });

    items.forEach(function (cb) {
        cb.addEventListener('change', syncState);
    });

    bulkBtn.addEventListener('click', function () {
        var checked = items.filter(function (cb) { return cb.checked; });
        if (checked.length === 0) {
            return;
        }
        if (!window.confirm('<?= addslashes(__('websites.delete_selected_confirm')) ?>')) {
            return;
        }
        idsInput.value = checked.map(function (cb) { return cb.value; }).join(',');
        form.submit();
    });

    syncState();
})();
</script>
<?php endif; ?>

<?php include APP_PATH . '/includes/footer.php'; ?>
