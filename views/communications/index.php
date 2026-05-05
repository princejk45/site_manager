<?php
/**
 * Client Communications — CRM communication history
 */
include APP_PATH . '/includes/header.php';
include APP_PATH . '/includes/sidebar-v2.php';

// Safe defaults
$timeline  = isset($timeline) ? $timeline : ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'last_page' => 1];
$clients   = isset($clients) ? $clients : [];
$kpi       = isset($kpi) ? $kpi : [];
$commTypes = isset($commTypes) ? $commTypes : [];
$channels  = isset($channels) ? $channels : [];
$filters   = isset($filters) ? $filters : [];
$flash     = isset($flash) ? $flash : null;
$userRole  = isset($userRole) ? $userRole : 'viewer';

$canDelete = in_array($userRole, ['manager', 'super_admin'], true);
$langParam = (isset($_SESSION['lang']) ? '&lang=' . urlencode($_SESSION['lang']) : '');

// Refresh CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Type badge colors
$typeBadge = [
    'invoice'          => 'primary',
    'domain_renewal'   => 'info',
    'hosting_renewal'  => 'info',
    'email_hosting'    => 'info',
    'email_space'      => 'secondary',
    'website_changes'  => 'warning',
    'health_report'    => 'success',
    'maintenance'      => 'warning',
    'general'          => 'secondary',
    'other'            => 'dark',
];
$typeLabels = [
    'invoice'          => __('comms.type_invoice'),
    'domain_renewal'   => __('comms.type_domain_renewal'),
    'hosting_renewal'  => __('comms.type_hosting_renewal'),
    'email_hosting'    => __('comms.type_email_hosting'),
    'email_space'      => __('comms.type_email_space'),
    'website_changes'  => __('comms.type_website_changes'),
    'health_report'    => __('comms.type_health_report'),
    'maintenance'      => __('comms.type_maintenance'),
    'general'          => __('comms.type_general'),
    'other'            => __('comms.type_other'),
];
$channelIcon = [
    'email'     => 'fas fa-envelope',
    'phone'     => 'fas fa-phone',
    'whatsapp'  => 'fab fa-whatsapp',
    'in_person' => 'fas fa-handshake',
    'portal'    => 'fas fa-globe',
    'other'     => 'fas fa-comment',
];
?>

<div class="content-wrapper">

    <style>
        @media (min-width: 992px) {
            .comm-kpi-col {
                flex: 0 0 20%;
                max-width: 20%;
            }
        }
    </style>

    <!-- ── Page Header ───────────────────────────────────────────────────── -->
    <section class="content-header" style="background:#fff;border-bottom:1px solid #dee2e6;padding:18px 0;">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 style="font-size:24px;font-weight:700;margin:0;">
                        <i class="fas fa-history text-primary mr-2"></i><?= __('comms.title') ?>
                    </h1>
                    <p style="color:#6c757d;margin:4px 0 0;font-size:13px;"><?= __('comms.subtitle') ?></p>
                </div>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#logCommModal">
                    <i class="fas fa-plus mr-1"></i><?= __('comms.log_communication') ?>
                </button>
            </div>
        </div>
    </section>

    <section class="content pt-3">
        <div class="container-fluid">

            <!-- ── Flash ─────────────────────────────────────────────────── -->
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
                    <?= htmlspecialchars($flash['msg']) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>

            <!-- ── KPI Strip ──────────────────────────────────────────────── -->
            <div class="row mb-3">
                <?php
                $kpiItems = [
                    ['val' => (isset($kpi['total']) ? $kpi['total'] : 0), 'label' => __('comms.kpi_total'),    'icon' => 'fas fa-comments',      'color' => '#4361ee'],
                    ['val' => (isset($kpi['today']) ? $kpi['today'] : 0), 'label' => __('comms.kpi_today'),    'icon' => 'fas fa-calendar-day',  'color' => '#2ec4b6'],
                    ['val' => (isset($kpi['invoices']) ? $kpi['invoices'] : 0), 'label' => __('comms.kpi_invoices'), 'icon' => 'fas fa-file-invoice',  'color' => '#e76f51'],
                    ['val' => (isset($kpi['renewals']) ? $kpi['renewals'] : 0), 'label' => __('comms.kpi_renewals'), 'icon' => 'fas fa-redo-alt',      'color' => '#f77f00'],
                    ['val' => (isset($kpi['month']) ? $kpi['month'] : 0), 'label' => __('comms.kpi_month'),    'icon' => 'fas fa-calendar-alt',  'color' => '#6c757d'],
                ];
                foreach ($kpiItems as $k):
                ?>
                <div class="col-6 col-sm-4 mb-2 comm-kpi-col">
                    <div class="card shadow-sm h-100" style="border-left:4px solid <?= $k['color'] ?>;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div style="font-size:22px;font-weight:700;color:<?= $k['color'] ?>;"><?= $k['val'] ?></div>
                                    <div style="font-size:11px;color:#6c757d;"><?= $k['label'] ?></div>
                                </div>
                                <i class="<?= $k['icon'] ?>" style="font-size:20px;color:<?= $k['color'] ?>;opacity:.4;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Filters ────────────────────────────────────────────────── -->
            <div class="card shadow-sm mb-3">
                <div class="card-body py-2">
                    <form method="GET" action="index.php" class="form-inline flex-wrap" style="gap:8px;">
                        <input type="hidden" name="action" value="comms">
                        <?php if (!empty($_SESSION['lang'])): ?>
                            <input type="hidden" name="lang" value="<?= htmlspecialchars($_SESSION['lang']) ?>">
                        <?php endif; ?>

                        <!-- Client filter -->
                        <select name="client" class="form-control form-control-sm" style="min-width:160px;" onchange="this.form.submit()">
                            <option value=""><?= __('comms.all_clients') ?></option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ((isset($filters['hosting_id']) ? $filters['hosting_id'] : 0) == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Type filter -->
                        <select name="type" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value=""><?= __('comms.all_types') ?></option>
                            <?php foreach ($typeLabels as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ((isset($filters['comm_type']) ? $filters['comm_type'] : '') === $k) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Channel filter -->
                        <select name="channel" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value=""><?= __('comms.all_channels') ?></option>
                            <?php foreach ($channels as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ((isset($filters['channel']) ? $filters['channel'] : '') === $k) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Date range -->
                           <input type="text" name="from" class="form-control form-control-sm"
                               value="<?= htmlspecialchars(sm_form_date_value(isset($filters['date_from']) ? $filters['date_from'] : '')) ?>"
                               inputmode="numeric"
                               placeholder="<?= __('comms.date_from') ?>">
                           <input type="text" name="to" class="form-control form-control-sm"
                               value="<?= htmlspecialchars(sm_form_date_value(isset($filters['date_to']) ? $filters['date_to'] : '')) ?>"
                               inputmode="numeric"
                               placeholder="<?= __('comms.date_to') ?>">

                        <!-- Search -->
                        <div class="input-group input-group-sm" style="width:200px;">
                            <input type="text" name="search" class="form-control"
                                   value="<?= htmlspecialchars(isset($filters['search']) ? $filters['search'] : '') ?>"
                                   placeholder="<?= __('common.search') ?>…">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <?php if (!empty(array_filter($filters))): ?>
                            <a href="index.php?action=comms<?= $langParam ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times mr-1"></i><?= __('common.clear') ?>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- ── Timeline Table ─────────────────────────────────────────── -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center" style="background:#fff;">
                    <span class="font-weight-bold">
                        <i class="fas fa-list-ul mr-1 text-primary"></i>
                        <?= __('comms.timeline') ?>
                        <small class="text-muted font-weight-normal ml-1">(<?= $timeline['total'] ?> <?= __('comms.entries') ?>)</small>
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($timeline['rows'])): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 d-block" style="opacity:.3;"></i>
                            <p class="mb-1"><?= __('comms.no_entries') ?></p>
                            <button type="button" class="btn btn-sm btn-primary mt-2" data-toggle="modal" data-target="#logCommModal">
                                <i class="fas fa-plus mr-1"></i><?= __('comms.log_first') ?>
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" style="font-size:13px;">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:130px;"><?= __('comms.col_date') ?></th>
                                        <th><?= __('comms.col_client') ?></th>
                                        <th><?= __('comms.col_type') ?></th>
                                        <th><?= __('comms.col_channel') ?></th>
                                        <th><?= __('comms.col_subject') ?></th>
                                        <th><?= __('comms.col_service') ?></th>
                                        <th><?= __('comms.col_logged_by') ?></th>
                                        <th style="width:80px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeline['rows'] as $row):
                                        $badge   = isset($typeBadge[$row['comm_type']]) ? $typeBadge[$row['comm_type']] : 'secondary';
                                        $typeStr = isset($typeLabels[$row['comm_type']]) ? $typeLabels[$row['comm_type']] : ucfirst($row['comm_type']);
                                        $icon    = isset($channelIcon[$row['channel']]) ? $channelIcon[$row['channel']] : 'fas fa-comment';
                                        $isManual = $row['source'] === 'manual';
                                    ?>
                                        <tr>
                                            <td class="text-nowrap">
                                                <div><?= date('d-m-Y', strtotime($row['sent_at'])) ?></div>
                                                <small class="text-muted"><?= date('H:i', strtotime($row['sent_at'])) ?></small>
                                            </td>
                                            <td>
                                                <a href="index.php?action=comms&client=<?= $row['hosting_id'] ?><?= $langParam ?>"
                                                   style="color:inherit;font-weight:600;text-decoration:none;">
                                                    <?= htmlspecialchars(isset($row['client_name']) ? $row['client_name'] : '—') ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $badge ?>"><?= htmlspecialchars($typeStr) ?></span>
                                            </td>
                                            <td class="text-nowrap">
                                                <i class="<?= $icon ?> mr-1" title="<?= htmlspecialchars(ucfirst($row['channel'])) ?>"></i>
                                                <small><?= htmlspecialchars(ucfirst($row['channel'])) ?></small>
                                            </td>
                                            <td>
                                                <span title="<?= htmlspecialchars(isset($row['notes']) ? $row['notes'] : '') ?>">
                                                    <?= htmlspecialchars(mb_strimwidth($row['subject'], 0, 70, '…')) ?>
                                                </span>
                                                <?php if (!empty($row['notes'])): ?>
                                                    <i class="fas fa-sticky-note text-muted ml-1" style="font-size:10px;"
                                                       title="<?= htmlspecialchars(mb_strimwidth($row['notes'], 0, 200, '…')) ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= $row['website_domain'] ? htmlspecialchars($row['website_domain']) : '—' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php if ($isManual): ?>
                                                        <?= htmlspecialchars(isset($row['sent_by_name']) ? $row['sent_by_name'] : '—') ?>
                                                    <?php else: ?>
                                                        <span class="badge badge-light border" style="font-size:9px;">
                                                            <i class="fas fa-robot mr-1"></i>System
                                                        </span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td class="text-right text-nowrap">
                                                <!-- Detail toggle -->
                                                <button class="btn btn-xs btn-outline-secondary comm-detail-btn"
                                                        data-notes="<?= htmlspecialchars(isset($row['notes']) ? $row['notes'] : '') ?>"
                                                        data-subject="<?= htmlspecialchars($row['subject']) ?>"
                                                        data-client="<?= htmlspecialchars(isset($row['client_name']) ? $row['client_name'] : '') ?>"
                                                        data-type="<?= htmlspecialchars($typeStr) ?>"
                                                        data-date="<?= date('d-m-Y H:i', strtotime($row['sent_at'])) ?>"
                                                        data-toggle="modal" data-target="#detailModal">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($canDelete && $isManual): ?>
                                                    <a href="index.php?action=comms&do=delete&id=<?= $row['id'] ?><?= $langParam ?>"
                                                       class="btn btn-xs btn-outline-danger"
                                                       onclick="return confirm('<?= __('comms.confirm_delete') ?>');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($timeline['last_page'] > 1): ?>
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" style="font-size:13px;">
                                <span class="text-muted">
                                    <?= __('comms.page') ?> <?= $timeline['page'] ?> / <?= $timeline['last_page'] ?>
                                </span>
                                <div>
                                    <?php
                                    $qBase = http_build_query(array_filter([
                                        'action'  => 'comms',
                                        'client'  => $filters['hosting_id'] ?: '',
                                        'type'    => $filters['comm_type'],
                                        'channel' => $filters['channel'],
                                        'from'    => $filters['date_from'],
                                        'to'      => $filters['date_to'],
                                        'search'  => $filters['search'],
                                        'lang'    => isset($_SESSION['lang']) ? $_SESSION['lang'] : '',
                                    ]));
                                    $prev = $timeline['page'] - 1;
                                    $next = $timeline['page'] + 1;
                                    ?>
                                    <?php if ($prev >= 1): ?>
                                        <a href="index.php?<?= $qBase ?>&page=<?= $prev ?>" class="btn btn-sm btn-outline-secondary">‹ <?= __('common.prev') ?></a>
                                    <?php endif; ?>
                                    <?php if ($next <= $timeline['last_page']): ?>
                                        <a href="index.php?<?= $qBase ?>&page=<?= $next ?>" class="btn btn-sm btn-outline-secondary"><?= __('common.next') ?> ›</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- ── Log Communication Modal ───────────────────────────────────────────── -->
<div class="modal fade" id="logCommModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="index.php?action=comms&do=store<?= $langParam ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="modal-header" style="background:linear-gradient(135deg,#4361ee,#3a0ca3);color:#fff;">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle mr-2"></i><?= __('comms.log_communication') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;"><span>&times;</span></button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <!-- Client -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold"><?= __('comms.col_client') ?> <span class="text-danger">*</span></label>
                            <select name="hosting_id" id="modal-client" class="form-control" required>
                                <option value=""><?= __('comms.select_client') ?>…</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ((isset($filters['hosting_id']) ? $filters['hosting_id'] : 0) == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Related service/site -->
                        <div class="col-md-6 form-group">
                            <label><?= __('comms.related_service') ?> <small class="text-muted">(<?= __('common.optional') ?>)</small></label>
                            <select name="website_id" id="modal-website" class="form-control">
                                <option value=""><?= __('comms.select_service') ?>…</option>
                            </select>
                        </div>

                        <!-- Type -->
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold"><?= __('comms.col_type') ?></label>
                            <select name="comm_type" class="form-control">
                                <?php foreach ($typeLabels as $k => $v): ?>
                                    <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Channel -->
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold"><?= __('comms.col_channel') ?></label>
                            <select name="channel" class="form-control">
                                <?php foreach ($channels as $k => $v): ?>
                                    <option value="<?= $k ?>">
                                        <?= htmlspecialchars($v) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date -->
                        <div class="col-md-4 form-group">
                            <label><?= __('comms.sent_at') ?></label>
                            <input type="text" name="sent_at" class="form-control"
                                value="<?= htmlspecialchars(sm_format_datetime(date('Y-m-d H:i'), false)) ?>"
                                placeholder="dd-mm-yyyy hh:mm" inputmode="numeric">
                        </div>

                        <!-- Subject -->
                        <div class="col-12 form-group">
                            <label class="font-weight-bold"><?= __('comms.col_subject') ?> <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control"
                                   placeholder="<?= __('comms.subject_placeholder') ?>"
                                   maxlength="255" required>
                        </div>

                        <!-- Notes -->
                        <div class="col-12 form-group mb-0">
                            <label><?= __('comms.notes') ?> <small class="text-muted">(<?= __('common.optional') ?>)</small></label>
                            <textarea name="notes" class="form-control" rows="4"
                                      placeholder="<?= __('comms.notes_placeholder') ?>"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i><?= __('comms.save_entry') ?>
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('common.cancel') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Detail Modal ────────────────────────────────────────────────────────── -->
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i><span id="detail-subject"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?= __('comms.col_client') ?></dt><dd class="col-sm-8" id="detail-client"></dd>
                    <dt class="col-sm-4"><?= __('comms.col_type') ?></dt><dd class="col-sm-8" id="detail-type"></dd>
                    <dt class="col-sm-4"><?= __('comms.col_date') ?></dt><dd class="col-sm-8" id="detail-date"></dd>
                    <dt class="col-sm-4"><?= __('comms.notes') ?></dt>
                    <dd class="col-sm-8"><pre id="detail-notes" style="font-size:12px;white-space:pre-wrap;background:#f8f9fa;padding:10px;border-radius:4px;max-height:200px;overflow-y:auto;"></pre></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><?= __('common.close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
// Populate detail modal
document.querySelectorAll('.comm-detail-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('detail-subject').textContent = this.dataset.subject;
        document.getElementById('detail-client').textContent  = this.dataset.client;
        document.getElementById('detail-type').textContent    = this.dataset.type;
        document.getElementById('detail-date').textContent    = this.dataset.date;
        document.getElementById('detail-notes').textContent   = this.dataset.notes || '—';
    });
});

// Dynamic website dropdown based on selected client
document.getElementById('modal-client').addEventListener('change', function() {
    var hid = this.value;
    var sel = document.getElementById('modal-website');
    sel.innerHTML = '<option value=""><?= __('comms.select_service') ?>…</option>';
    if (!hid) return;
    fetch('index.php?action=comms&do=websites&hosting_id=' + hid)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            data.forEach(function(site) {
                var opt = document.createElement('option');
                opt.value = site.id;
                opt.textContent = site.domain + ' (' + (site.service_type || '') + ')';
                sel.appendChild(opt);
            });
        })
        .catch(function() {});
});
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>
