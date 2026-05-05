<?php
/**
 * Diagnostics Center — Portfolio Health Overview
 * Data provided by DashboardController::diagnostics()
 */
include APP_PATH . '/includes/header.php';
include APP_PATH . '/includes/sidebar-v2.php';

$portfolioScore = isset($portfolioScore) ? $portfolioScore : null;
$portfolioGrade = $portfolioGrade ?? 'N/A';
$stats = $stats ?? [
    'total' => 0,
    'healthy' => 0,
    'warning' => 0,
    'critical' => 0,
    'expired' => 0,
];
$websites    = $websites    ?? [];   // all sites — detailed table
$wpWebsites  = $wpWebsites  ?? [];  // WP-configured only — grid
$hasWpSites  = $hasWpSites  ?? false;

$langParam = (isset($_SESSION['lang']) && $_SESSION['lang'] !== DEFAULT_LANG)
    ? '&lang=' . urlencode($_SESSION['lang'])
    : '';

// Critical/warning WP sites for the grid
$gridSites = array_values(array_filter($wpWebsites, fn($s) => in_array($s['alert'], ['critical', 'warning', 'expired'])));

function diagScoreColor(?float $s): string
{
    if ($s === null) return '#adb5bd';
    if ($s >= 80)   return '#28a745';
    if ($s >= 60)   return '#ffc107';
    if ($s >= 40)   return '#fd7e14';
    return '#dc3545';
}
function diagAlertClass(string $alert): string
{
    return match ($alert) {
        'ok'       => 'success',
        'warning'  => 'warning',
        'critical' => 'danger',
        'expired'  => 'dark',
        default    => 'secondary',
    };
}
function diagGradeBadge(string $grade): string
{
    return match ($grade) {
        'A'      => 'success',
        'B'      => 'info',
        'C'      => 'warning',
        'D', 'F' => 'danger',
        default  => 'secondary',
    };
}
// Format a MySQL date string as dd-mm-yyyy
function fmtDate(?string $d): string
{
    if (!$d) return '—';
    return date('d-m-Y', strtotime($d));
}
?>

<div class="content-wrapper" style="background:linear-gradient(135deg,#f7fafc 0%,#edf2f7 100%);">

    <!-- ── Page Header ────────────────────────────────────────────────────── -->
    <section class="content-header" style="background:#fff;border-bottom:1px solid #dee2e6;padding:20px 0;">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                <div>
                    <h1 style="font-size:26px;font-weight:700;color:#2d3748;margin:0;">
                        <i class="fas fa-heartbeat text-danger mr-2"></i><?= __('diagnostics.title') ?>
                    </h1>
                    <p class="text-muted mb-0 small"><?= __('diagnostics.subtitle') ?></p>
                </div>
                <div class="d-flex align-items-center" style="gap:8px;">
                    <div class="custom-control custom-switch mr-2">
                        <input type="checkbox" class="custom-control-input" id="autoRefreshToggle" checked>
                        <label class="custom-control-label small" for="autoRefreshToggle"><?= __('diagnostics.auto_refresh') ?></label>
                    </div>
                    <a href="index.php?action=diagnostics&do=export<?= $langParam ?>"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-file-csv mr-1"></i><?= __('diagnostics.export_csv') ?>
                    </a>
                    <button id="refreshNowBtn" class="btn btn-primary btn-sm" onclick="refreshDiagnostics()">
                        <i class="fas fa-sync-alt mr-1"></i><?= __('diagnostics.refresh_now') ?>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content pt-3">
        <div class="container-fluid">

            <!-- ── KPI Cards ──────────────────────────────────────────────── -->
            <div class="row mb-3" id="kpiRow">

                <!-- Portfolio Score -->
                <div class="col-6 col-md-4 col-lg-2 mb-3">
                    <div class="card h-100 text-center shadow-sm border-0">
                        <div class="card-body p-3">
                            <?php $scoreColor = diagScoreColor($portfolioScore); $deg = round(($portfolioScore ?? 0) * 3.6); ?>
                            <div style="width:64px;height:64px;border-radius:50%;
                                 background:conic-gradient(<?= $scoreColor ?> <?= $deg ?>deg,#e9ecef 0deg);
                                 display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                                <div style="width:50px;height:50px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;">
                                    <strong style="font-size:13px;color:<?= $scoreColor ?>;">
                                        <?= $portfolioScore !== null ? $portfolioScore : '—' ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="small font-weight-bold text-muted"><?= __('diagnostics.portfolio_score') ?></div>
                            <span class="badge badge-<?= diagGradeBadge($portfolioGrade) ?> mt-1">
                                <?= __('diagnostics.grade') ?>: <?= $portfolioGrade ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div class="col-6 col-md-4 col-lg-2 mb-3">
                    <div class="card h-100 text-center shadow-sm border-0" style="border-left:4px solid #007bff!important;">
                        <div class="card-body p-3">
                            <i class="fas fa-globe fa-2x text-primary mb-2"></i>
                            <div class="h3 mb-0 font-weight-bold" id="kpiTotal"><?= $stats['total'] ?></div>
                            <div class="small text-muted"><?= __('diagnostics.total_sites') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Healthy -->
                <div class="col-6 col-md-4 col-lg-2 mb-3">
                    <div class="card h-100 text-center shadow-sm border-0" style="border-left:4px solid #28a745!important;">
                        <div class="card-body p-3">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <div class="h3 mb-0 font-weight-bold text-success" id="kpiHealthy"><?= $stats['healthy'] ?></div>
                            <div class="small text-muted"><?= __('diagnostics.healthy') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Warning -->
                <div class="col-6 col-md-4 col-lg-2 mb-3">
                    <div class="card h-100 text-center shadow-sm border-0" style="border-left:4px solid #ffc107!important;">
                        <div class="card-body p-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                            <div class="h3 mb-0 font-weight-bold text-warning" id="kpiWarning"><?= $stats['warning'] ?></div>
                            <div class="small text-muted"><?= __('diagnostics.warning') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Critical -->
                <div class="col-6 col-md-4 col-lg-2 mb-3">
                    <div class="card h-100 text-center shadow-sm border-0" style="border-left:4px solid #dc3545!important;">
                        <div class="card-body p-3">
                            <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                            <div class="h3 mb-0 font-weight-bold text-danger" id="kpiCritical"><?= $stats['critical'] ?></div>
                            <div class="small text-muted"><?= __('diagnostics.critical') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Expired -->
                <div class="col-6 col-md-4 col-lg-2 mb-3">
                    <div class="card h-100 text-center shadow-sm border-0" style="border-left:4px solid #6c757d!important;">
                        <div class="card-body p-3">
                            <i class="fas fa-calendar-times fa-2x text-secondary mb-2"></i>
                            <div class="h3 mb-0 font-weight-bold text-secondary" id="kpiExpired"><?= $stats['expired'] ?></div>
                            <div class="small text-muted"><?= __('diagnostics.expired') ?></div>
                        </div>
                    </div>
                </div>

            </div><!-- /KPI row -->

            <!-- ── Service Status Grid ────────────────────────────────────── -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center" style="background:#fff;border-bottom:1px solid #dee2e6;">
                    <h6 class="mb-0 font-weight-bold">
                        <i class="fas fa-exclamation-triangle mr-2 text-danger"></i><?= __('diagnostics.service_grid') ?>
                        <small class="text-muted font-weight-normal ml-2"><?= __('diagnostics.grid_critical_only_note') ?></small>
                    </h6>
                    <span class="badge badge-danger"><?= count($gridSites) ?> <?= __('diagnostics.critical_warning_sites') ?></span>
                </div>
                <div class="card-body">
                    <?php if (!$hasWpSites): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fab fa-wordpress fa-3x mb-3 d-block" style="opacity:.3;"></i>
                            <p class="mb-1"><?= __('diagnostics.no_wp_sites_notice') ?></p>
                            <a href="index.php?action=settings&do=wordpress<?= $langParam ?>" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="fab fa-wordpress mr-1"></i><?= __('diagnostics.configure_wordpress_sites') ?>
                            </a>
                        </div>
                    <?php elseif (empty($gridSites)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-check-circle fa-3x mb-3 d-block text-success" style="opacity:.6;"></i>
                            <p class="mb-0"><?= __('diagnostics.all_wp_healthy') ?></p>
                        </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($gridSites as $site):
                            $sc    = $site['effective_score'];
                            $col   = diagScoreColor($sc);
                            $alcls = diagAlertClass($site['alert']);
                            $sdeg  = $sc !== null ? round($sc * 3.6) : 0;
                            $days  = (int)$site['days_to_expiry'];
                        ?>
                        <div class="col-12 col-sm-6 col-lg-4 col-xl-3 mb-3">
                            <div class="card h-100 shadow-sm site-card"
                                 style="border-left:4px solid <?= $col ?>!important;cursor:pointer;"
                                 onclick="openSiteDetail(<?= htmlspecialchars(json_encode($site), ENT_QUOTES) ?>)">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div style="flex:1;min-width:0;">
                                            <div class="font-weight-bold text-truncate small"
                                                 title="<?= htmlspecialchars($site['domain']) ?>">
                                                <?= htmlspecialchars($site['domain']) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:11px;">
                                                <?= htmlspecialchars($site['client_name'] ?? '—') ?>
                                            </div>
                                        </div>
                                        <!-- Score ring -->
                                        <div style="width:44px;height:44px;border-radius:50%;
                                             background:conic-gradient(<?= $col ?> <?= $sdeg ?>deg,#e9ecef 0deg);
                                             display:flex;align-items:center;justify-content:center;
                                             flex-shrink:0;margin-left:8px;">
                                            <div style="width:34px;height:34px;border-radius:50%;background:#fff;
                                                 display:flex;align-items:center;justify-content:center;">
                                                <span style="font-size:10px;font-weight:700;color:<?= $col ?>;">
                                                    <?= $sc !== null ? (int)$sc : '?' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap" style="gap:4px;">
                                        <span class="badge badge-<?= $alcls ?>" style="font-size:10px;">
                                            <?= htmlspecialchars(ucfirst($site['status'])) ?>
                                        </span>
                                        <?php if ($site['ssl_valid'] !== null): ?>
                                        <span class="badge badge-<?= $site['ssl_valid'] ? 'success' : 'danger' ?>"
                                              style="font-size:10px;">
                                            SSL <?= $site['ssl_valid'] ? '✓' : '✗' ?>
                                        </span>
                                        <?php endif; ?>
                                        <span class="badge badge-info" style="font-size:10px;">WP</span>
                                    </div>

                                    <?php if ($site['expiry_date']): ?>
                                    <div class="mt-2" style="font-size:10px;color:#6c757d;">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        <?= fmtDate($site['expiry_date']) ?>
                                        <?php if ($days >= 0): ?>
                                            <span class="<?= $days <= 14 ? 'text-danger font-weight-bold' : ($days <= 30 ? 'text-warning' : 'text-success') ?>">
                                                (<?= $days ?>d)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-dark font-weight-bold">(<?= __('diagnostics.expired_label') ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Detailed Table ─────────────────────────────────────────── -->
            <div class="card shadow-sm mb-4">
                <div class="card-header" style="background:#fff;border-bottom:1px solid #dee2e6;">
                    <h6 class="mb-0 font-weight-bold">
                        <i class="fas fa-table mr-2 text-primary"></i><?= __('diagnostics.detailed_table') ?>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($wpWebsites)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fab fa-wordpress fa-3x mb-3 d-block" style="opacity:.3;"></i>
                            <p class="mb-1"><?= __('diagnostics.no_wp_sites_notice') ?></p>
                            <a href="index.php?action=settings&do=wordpress<?= $langParam ?>" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="fab fa-wordpress mr-1"></i><?= __('diagnostics.configure_wordpress_sites') ?>
                            </a>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table id="diagTable" class="table table-sm table-hover mb-0" style="font-size:13px;">
                            <thead class="thead-light">
                                <tr>
                                    <th><?= __('diagnostics.domain') ?></th>
                                    <th><?= __('diagnostics.client') ?></th>
                                    <th><?= __('diagnostics.status') ?></th>
                                    <th><?= __('diagnostics.health_score') ?></th>
                                    <th><?= __('diagnostics.grade') ?></th>
                                    <th><?= __('diagnostics.expiry') ?></th>
                                    <th><?= __('diagnostics.days_left') ?></th>
                                    <th>SSL</th>
                                    <th>WP</th>
                                    <th><?= __('diagnostics.last_check') ?></th>
                                    <th><?= __('diagnostics.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($wpWebsites as $site):
                                $sc    = $site['effective_score'];
                                $col   = diagScoreColor($sc);
                                $alcls = diagAlertClass($site['alert']);
                                $days  = (int)$site['days_to_expiry'];
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($site['domain']) ?></strong>
                                        <?php if ($site['notes']): ?>
                                        <i class="fas fa-sticky-note text-muted ml-1"
                                           title="<?= htmlspecialchars($site['notes']) ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($site['client_name'] ?? '—') ?></td>
                                    <td>
                                        <span class="badge badge-<?= $alcls ?>">
                                            <?= htmlspecialchars(ucfirst($site['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($sc !== null): ?>
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <div style="width:60px;height:6px;background:#e9ecef;border-radius:3px;">
                                                <div style="width:<?= (int)$sc ?>%;height:6px;background:<?= $col ?>;border-radius:3px;"></div>
                                            </div>
                                            <span style="font-weight:600;color:<?= $col ?>;"><?= (int)$sc ?></span>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= diagGradeBadge($site['grade']) ?>">
                                            <?= $site['grade'] ?>
                                        </span>
                                    </td>
                                    <td><?= fmtDate($site['expiry_date'] ?? null) ?></td>
                                    <td>
                                        <?php if ($site['expiry_date']): ?>
                                        <span class="<?= $days < 0 ? 'text-dark font-weight-bold' : ($days <= 14 ? 'text-danger font-weight-bold' : ($days <= 30 ? 'text-warning' : 'text-success')) ?>">
                                            <?= $days < 0 ? __('diagnostics.expired_label') : $days ?>
                                        </span>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($site['ssl_valid'] !== null): ?>
                                            <span class="<?= $site['ssl_valid'] ? 'text-success' : 'text-danger' ?>">
                                                <i class="fas fa-<?= $site['ssl_valid'] ? 'lock' : 'lock-open' ?>"></i>
                                            </span>
                                            <?php if ($site['ssl_expiry_days'] !== null): ?>
                                            <small class="text-muted"><?= (int)$site['ssl_expiry_days'] ?>d</small>
                                            <?php endif; ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($site['wordpress_url']): ?>
                                        <span class="badge badge-info"
                                              title="<?= htmlspecialchars($site['wordpress_url']) ?>">WP</span>
                                        <?php if ($site['wordpress_version']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($site['wordpress_version']) ?></small>
                                        <?php endif; ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $site['last_metric_at']
                                                ? date('d-m-Y H:i', strtotime($site['last_metric_at']))
                                                : ($site['last_check']
                                                    ? date('d-m-Y H:i', strtotime($site['last_check']))
                                                    : __('diagnostics.never')) ?>
                                        </small>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <button class="btn btn-xs btn-outline-primary"
                                                onclick="openSiteDetail(<?= htmlspecialchars(json_encode($site), ENT_QUOTES) ?>)"
                                                title="<?= __('diagnostics.view_detail') ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="index.php?action=websites&do=edit&id=<?= $site['id'] ?><?= $langParam ?>"
                                           class="btn btn-xs btn-outline-secondary"
                                           title="<?= __('common.edit') ?>">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /container-fluid -->
    </section>
</div><!-- /content-wrapper -->

<!-- ── Site Detail Modal ──────────────────────────────────────────────────── -->
<div class="modal fade" id="siteDetailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-heartbeat text-danger mr-2"></i><span id="modalDomain"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="siteDetailBody"></div>
            <div class="modal-footer">
                <a id="modalEditLink" href="#" class="btn btn-primary btn-sm">
                    <i class="fas fa-edit mr-1"></i><?= __('common.edit') ?>
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <?= __('common.close') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- DataTables CSS -->
<link rel="stylesheet" href="<?= WEB_PATH ?>/assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">

<?php include APP_PATH . '/includes/footer.php'; ?>

<!-- DataTables JS -->
<script src="<?= WEB_PATH ?>/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= WEB_PATH ?>/assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>

<style>
.site-card { transition: transform .15s ease, box-shadow .15s ease; }
.site-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.12)!important; }
.btn-xs { padding: 2px 8px; font-size: 11px; }
#diagTable thead th { white-space: nowrap; font-size: 12px; }
</style>

<script>
// ── DataTable init ─────────────────────────────────────────────────────────
$(function () {
    $('#diagTable').DataTable({
        pageLength: 25,
        order: [[3, 'asc']],
        columnDefs: [{ orderable: false, targets: -1 }],
        language: {
            search: '',
            searchPlaceholder: '<?= addslashes(__('common.search')) ?>'
        }
    });
});

// ── Auto-refresh ───────────────────────────────────────────────────────────
let _refreshInterval = null;

function startAutoRefresh() {
    if (_refreshInterval) return;
    _refreshInterval = setInterval(refreshDiagnostics, 60000);
}
function stopAutoRefresh() {
    clearInterval(_refreshInterval);
    _refreshInterval = null;
}

document.getElementById('autoRefreshToggle').addEventListener('change', function () {
    this.checked ? startAutoRefresh() : stopAutoRefresh();
});
startAutoRefresh();

function refreshDiagnostics() {
    const btn = document.getElementById('refreshNowBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i><?= addslashes(__('diagnostics.refreshing')) ?>';

    fetch('index.php?action=diagnostics&do=data')
        .then(r => r.json())
        .then(data => {
            const s = data.stats || {};
            document.getElementById('kpiTotal').textContent    = s.total    ?? '?';
            document.getElementById('kpiHealthy').textContent  = s.healthy  ?? '?';
            document.getElementById('kpiWarning').textContent  = s.warning  ?? '?';
            document.getElementById('kpiCritical').textContent = s.critical ?? '?';
            document.getElementById('kpiExpired').textContent  = s.expired  ?? '?';
        })
        .catch(e => console.warn('Diagnostics refresh error:', e))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i><?= addslashes(__('diagnostics.refresh_now')) ?>';
        });
}

// ── Site Detail Modal ──────────────────────────────────────────────────────
function openSiteDetail(site) {
    document.getElementById('modalDomain').textContent = site.domain;
    document.getElementById('modalEditLink').href =
        'index.php?action=websites&do=edit&id=' + site.id;

    const score  = site.effective_score;
    const color  = scoreColor(score);
    const alcls  = alertCls(site.alert);
    const days   = parseInt(site.days_to_expiry, 10);
    const deg    = score !== null ? Math.round(parseFloat(score) * 3.6) : 0;

    let html = '<div class="row">';

    // Left: health metrics
    html += '<div class="col-md-6">';
    html += '<h6 class="font-weight-bold mb-3"><i class="fas fa-chart-bar mr-1 text-primary"></i><?= addslashes(__('diagnostics.health_breakdown')) ?></h6>';

    html += `<div class="text-center mb-3">
        <div style="width:80px;height:80px;border-radius:50%;
             background:conic-gradient(${color} ${deg}deg,#e9ecef 0deg);
             display:flex;align-items:center;justify-content:center;margin:0 auto 6px;">
            <div style="width:62px;height:62px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;">
                <strong style="font-size:18px;color:${color};">${score !== null ? Math.round(parseFloat(score)) : '?'}</strong>
            </div>
        </div>
        <span class="badge badge-${alcls}">${ucFirst(site.status)}</span>
    </div>`;

    const metrics = [
        ['<?= addslashes(__('diagnostics.uptime')) ?>',     site.uptime_percent,   '%'],
        ['<?= addslashes(__('diagnostics.security')) ?>',   site.security_score,   ''],
        ['<?= addslashes(__('diagnostics.performance')) ?>', site.performance_score,''],
    ];
    metrics.forEach(([label, val, suffix]) => {
        if (val !== null && val !== undefined && val !== '') {
            const pct = parseFloat(val);
            const c   = scoreColor(pct);
            html += `<div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span>${label}</span><strong style="color:${c};">${Math.round(pct)}${suffix}</strong>
                </div>
                <div style="height:6px;background:#e9ecef;border-radius:3px;">
                    <div style="width:${Math.min(pct,100)}%;height:6px;background:${c};border-radius:3px;"></div>
                </div>
            </div>`;
        }
    });

    if (site.ssl_valid !== null && site.ssl_valid !== undefined) {
        const ok = (site.ssl_valid == 1 || site.ssl_valid === true);
        html += `<p class="small mb-1"><strong>SSL:</strong>
            <span class="${ok ? 'text-success' : 'text-danger'}">
                ${ok ? '✓ <?= addslashes(__('diagnostics.ssl_valid')) ?>' : '✗ <?= addslashes(__('diagnostics.ssl_invalid')) ?>'}
            </span>
            ${site.ssl_expiry_days ? `<small class="text-muted">(${site.ssl_expiry_days}d)</small>` : ''}
        </p>`;
    }
    if (site.average_response_time_ms) {
        html += `<p class="small mb-1"><strong><?= addslashes(__('diagnostics.response_time')) ?>:</strong> ${site.average_response_time_ms} ms</p>`;
    }
    if (parseInt(site.security_issues_count) > 0) {
        html += `<p class="small mb-0"><span class="badge badge-danger">${site.security_issues_count} <?= addslashes(__('diagnostics.security_issues')) ?></span></p>`;
    }
    html += '</div>';

    // Right: general info + WP
    html += '<div class="col-md-6">';
    html += '<h6 class="font-weight-bold mb-3"><i class="fas fa-info-circle mr-1 text-primary"></i><?= addslashes(__('diagnostics.site_info')) ?></h6>';

    const infoRows = [
        ['<?= addslashes(__('diagnostics.client')) ?>',    site.client_name],
        ['<?= addslashes(__('diagnostics.expiry')) ?>',    site.expiry_date],
        ['<?= addslashes(__('diagnostics.days_left')) ?>', days >= 0 ? days + ' days' : '<?= addslashes(__('diagnostics.expired_label')) ?>'],
        ['<?= addslashes(__('diagnostics.grade')) ?>',     site.grade],
        ['<?= addslashes(__('diagnostics.last_check')) ?>', site.last_metric_at || site.last_check || '<?= addslashes(__('diagnostics.never')) ?>'],
    ];
    html += '<table class="table table-sm table-bordered" style="font-size:12px;"><tbody>';
    infoRows.forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') {
            html += `<tr><th style="width:45%">${k}</th><td>${v}</td></tr>`;
        }
    });
    html += '</tbody></table>';

        if (site.wordpress_url) {
        html += '<h6 class="font-weight-bold mt-3 mb-2"><i class="fab fa-wordpress mr-1 text-info"></i>WordPress</h6>';
        const wpRows = [
            ['Version',  site.wordpress_version ? site.wordpress_version + (site.wp_version_outdated == 1 ? ' <span class="badge badge-warning ml-1">Outdated</span>' : '') : null],
            ['PHP',      site.php_version],
            ['MySQL',    site.mysql_version],
            ['Theme',    site.theme_name],
            ['Memory',   site.memory_limit],
            ['Debug mode', site.debug_mode == 1 ? '<span class="badge badge-warning">ON</span>' : (site.debug_mode == 0 ? '<span class="badge badge-success">OFF</span>' : null)],
            ['<?= addslashes(__('diagnostics.active_plugins')) ?>', site.active_plugin_count],
            ['Wordfence', (site.wordfence_installed == 1)
                ? '✓ <?= addslashes(__('diagnostics.installed')) ?>'
                : '✗ <?= addslashes(__('diagnostics.not_installed')) ?>'],
            ['<?= addslashes(__('diagnostics.wp_health')) ?>', site.wp_health_status],
        ];
        html += '<table class="table table-sm table-bordered" style="font-size:12px;"><tbody>';
        wpRows.forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') {
                html += `<tr><th style="width:45%">${k}</th><td>${v}</td></tr>`;
            }
        });
        html += '</tbody></table>';

        // Performance & backup block — uses wd.* columns (fallback to hm.* where wd.* is null)
        const perfRows = [
            ['Page load',   site.page_load_time_ms   ? site.page_load_time_ms + ' ms'   : null],
            ['Avg response',site.wd_avg_response_ms  ? site.wd_avg_response_ms + ' ms'  :
                            (site.average_response_time_ms ? site.average_response_time_ms + ' ms' : null)],
            ['Uptime',      (site.wd_uptime_percent !== null && site.wd_uptime_percent !== undefined && site.wd_uptime_percent !== '')
                                ? parseFloat(site.wd_uptime_percent).toFixed(1) + '%'
                                : (site.uptime_percent ? parseFloat(site.uptime_percent).toFixed(1) + '%' : null)],
            ['Backups',     site.backup_enabled == 1 ? '<span class="text-success">✓ Enabled</span>'
                          : site.backup_enabled == 0 ? '<span class="text-danger">✗ Disabled</span>' : null],
        ];
        const perfFiltered = perfRows.filter(([, v]) => v !== null && v !== undefined && v !== '');
        if (perfFiltered.length > 0) {
            html += '<h6 class="font-weight-bold mt-2 mb-2"><i class="fas fa-tachometer-alt mr-1 text-warning"></i>Performance &amp; Backup</h6>';
            html += '<table class="table table-sm table-bordered" style="font-size:12px;"><tbody>';
            perfFiltered.forEach(([k, v]) => {
                html += `<tr><th style="width:45%">${k}</th><td>${v}</td></tr>`;
            });
            html += '</tbody></table>';
        }
    }

    html += '</div></div>';

    document.getElementById('siteDetailBody').innerHTML = html;
    $('#siteDetailModal').modal('show');
}

function scoreColor(s) {
    if (s === null || s === undefined || s === '') return '#adb5bd';
    const n = parseFloat(s);
    if (n >= 80) return '#28a745';
    if (n >= 60) return '#ffc107';
    if (n >= 40) return '#fd7e14';
    return '#dc3545';
}
function alertCls(a) {
    return {ok:'success',warning:'warning',critical:'danger',expired:'dark'}[a] || 'secondary';
}
function ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
</script>
