<?php 
/**
 * Dashboard 2.0 - Modern Layout
 */
include APP_PATH . '/includes/header.php';
include APP_PATH . '/includes/sidebar-v2.php';

$lang = $_SESSION['lang'] ?? 'it';
$langParam = '&lang=' . $lang;

// Safe defaults
$totalWebsites         = $totalWebsites         ?? 0;
$expiringWebsitesCount = $expiringWebsitesCount ?? 0;
$expiredWebsitesCount  = $expiredWebsitesCount  ?? 0;
$buggyWebsitesCount    = $buggyWebsitesCount    ?? 0;
$totalHosting          = $totalHosting          ?? 0;
$expiringWebsites      = $expiringWebsites      ?? [];
$expiredWebsites       = $expiredWebsites       ?? [];
$expiringHosting       = $expiringHosting       ?? [];
$expiredHosting        = $expiredHosting        ?? [];
$criticalServices      = $criticalServices      ?? [];
$portfolioScore        = $portfolioScore        ?? null;
$hasWpSites            = $hasWpSites            ?? false;

// How many critical services to show inline before "See N more"
$criticalShowMax = 6;
$criticalTotal   = count($criticalServices);
$criticalShown   = array_slice($criticalServices, 0, $criticalShowMax);
$criticalHidden  = $criticalTotal - count($criticalShown);
$cronEnabled     = !empty($cronStatus);
$cronLastRunText = !empty($cronLastRun) ? date('d-m-Y H:i', strtotime($cronLastRun)) : __('cron.never');

// Combined expiring+expired for modal
// Map hosting plans to a modal-compatible shape
$mapHosting = fn(array $h, string $type): array => [
    'id'           => $h['id'],
    'domain'       => $h['name'],
    'client_name'  => $h['name'],
    'service_type' => 'hosting_plan',
    'expiry_date'  => $h['expiry_date'],
    '_modal_type'  => $type,
];
$allExpiringForModal = array_merge(
    array_map(fn($w) => array_merge($w, ['_modal_type' => 'expiring']), $expiringWebsites),
    array_map(fn($w) => array_merge($w, ['_modal_type' => 'expired']),  $expiredWebsites),
    array_map(fn($h) => $mapHosting($h, 'expiring'), $expiringHosting),
    array_map(fn($h) => $mapHosting($h, 'expired'),  $expiredHosting)
);
usort($allExpiringForModal, fn($a, $b) => strtotime($a['expiry_date'] ?? '9999-12-31') <=> strtotime($b['expiry_date'] ?? '9999-12-31'));
?>

<div class="content-wrapper" style="background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);">
    
    <!-- Page Header -->
    <section class="content-header" style="background: white; border-bottom: 1px solid var(--border); padding: 20px 0;">
        <div class="container-fluid">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin: 0;">
                        <?= __('dashboard.title') ?>
                    </h1>
                    <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 14px;">
                        <?= __('dashboard.welcome_back') ?>, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Dashboard Content -->
    <section class="content">
        <div class="dashboard-container">

            <!-- ── KPI CARDS ──────────────────────────────────────────── -->
            <div class="kpi-grid">
                
                <!-- All Services -->
                <div class="kpi-card">
                    <div class="kpi-card-content">
                        <div class="kpi-card-value"><?= $totalWebsites ?></div>
                        <div class="kpi-card-label"><?= __('dashboard.active_services') ?></div>
                        <div class="kpi-card-trend up">
                            <i class="fas fa-arrow-up"></i> <?= __('dashboard.kpi_vs_last_month') ?>
                        </div>
                    </div>
                </div>

                <!-- Expiring Soon — opens modal -->
                <div class="kpi-card kpi-card-warning" style="cursor:pointer;" onclick="$('#expiringModal').modal('show')" title="<?= __('dashboard.click_to_view_expiring') ?>">
                    <div class="kpi-card-content">
                        <div class="kpi-card-value"><?= $expiringWebsitesCount + $expiredWebsitesCount ?></div>
                        <div class="kpi-card-label"><?= __('dashboard.expiring_soon_30') ?></div>
                        <div class="kpi-card-trend" style="background: rgba(0,0,0,0.1);">
                            <i class="fas fa-search"></i> <?= __('dashboard.click_to_view') ?>
                        </div>
                    </div>
                </div>

                <!-- Critical Services -->
                <div class="kpi-card kpi-card-danger">
                    <div class="kpi-card-content">
                        <div class="kpi-card-value"><?= $criticalTotal ?></div>
                        <div class="kpi-card-label"><?= __('dashboard.critical_services_label') ?></div>
                        <div class="kpi-card-trend" style="background: rgba(0,0,0,0.1);">
                            <i class="fas fa-stethoscope"></i> <?= __('dashboard.review_diagnostics') ?>
                        </div>
                    </div>
                </div>

                <!-- Health Score — from real WP diagnostics, clickable for breakdown -->
                <div class="kpi-card kpi-card-success" style="cursor:pointer;" onclick="$('#healthModal').modal('show')" title="<?= __('dashboard.click_for_details') ?>">
                    <div class="kpi-card-content">
                        <?php if ($hasWpSites && $portfolioScore !== null): ?>
                            <div class="kpi-card-value"><?= $portfolioScore ?>%</div>
                            <div class="kpi-card-label"><?= __('dashboard.overall_health_score') ?></div>
                            <div class="kpi-card-trend up"><i class="fas fa-info-circle"></i> <?= __('dashboard.view_breakdown') ?></div>
                        <?php else: ?>
                            <div class="kpi-card-value" style="font-size:22px;">—</div>
                            <div class="kpi-card-label"><?= __('dashboard.overall_health_score') ?></div>
                            <div class="kpi-card-trend" style="background:rgba(0,0,0,0.1);"><i class="fas fa-info-circle"></i> <?= __('dashboard.no_wp_configured') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Total Clients -->
                <div class="kpi-card kpi-card-info">
                    <div class="kpi-card-content">
                        <div class="kpi-card-value"><?= $totalHosting ?></div>
                        <div class="kpi-card-label"><?= __('dashboard.total_clients') ?></div>
                        <div class="kpi-card-trend up">
                            <i class="fas fa-arrow-up"></i> <?= __('dashboard.kpi_new_this_month') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── ALERTS & INSIGHTS ──────────────────────────────────── -->
            <div class="dashboard-section">
                <div class="alert-panel">
                    <div class="alert-title">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= __('dashboard.urgent_alerts_insights') ?>
                    </div>
                    <ul class="alert-list">
                        <?php $alertIdx = 1; ?>

                        <?php if ($expiringWebsitesCount + $expiredWebsitesCount > 0): ?>
                            <li class="alert-item">
                                <div class="alert-item-icon"><?= $alertIdx++ ?></div>
                                <div class="alert-item-text">
                                    <strong><?= $expiringWebsitesCount + $expiredWebsitesCount ?> <?= __('dashboard.services_expiring_within_30') ?></strong> — 
                                    <a href="#" onclick="$('#expiringModal').modal('show');return false;" style="color: var(--danger); text-decoration: none;">
                                        <?= __('dashboard.view_expiring_services') ?> →
                                    </a>
                                </div>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($criticalTotal > 0): ?>
                            <li class="alert-item">
                                <div class="alert-item-icon"><?= $alertIdx++ ?></div>
                                <div class="alert-item-text">
                                    <strong><?= $criticalTotal ?> <?= __('dashboard.services_have_health_issues') ?></strong> — 
                                    <a href="index.php?action=diagnostics<?= $langParam ?>" style="color: var(--danger); text-decoration: none;">
                                        <?= __('dashboard.run_diagnostics') ?> →
                                    </a>
                                </div>
                            </li>
                        <?php endif; ?>

                        <li class="alert-item">
                            <div class="alert-item-icon"><?= $alertIdx++ ?></div>
                            <div class="alert-item-text">
                                <strong><?= __('dashboard.cron_jobs_sync') ?>:</strong> <?= $cronEnabled ? $cronLastRunText : __('cron.disabled') ?> — 
                                <a href="index.php?action=cron<?= $langParam ?>" style="color: var(--danger); text-decoration: none;">
                                    <?= __('dashboard.configure_schedule') ?> →
                                </a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- ── TWO-COLUMN LAYOUT ──────────────────────────────────── -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">

                <!-- LEFT: Critical Service Status (real data, WP-configured only) -->
                <div class="dashboard-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle text-danger"></i> <?= __('dashboard.critical_services_title') ?>
                        </h2>
                        <a href="index.php?action=diagnostics<?= $langParam ?>" style="color: var(--primary); text-decoration: none; font-size: 12px; font-weight: 600;">
                            <?= __('dashboard.view_full_report') ?> →
                        </a>
                    </div>

                    <?php if (!$hasWpSites): ?>
                        <div style="text-align:center; padding: 40px 20px; color: var(--text-secondary);">
                            <i class="fab fa-wordpress" style="font-size:40px; opacity:.3; display:block; margin-bottom:12px;"></i>
                            <p style="font-size:13px; margin:0;"><?= __('dashboard.no_wp_sites_configured') ?></p>
                            <a href="index.php?action=settings&do=wordpress<?= $langParam ?>" style="color:var(--primary); font-size:12px; margin-top:8px; display:inline-block;">
                                <?= __('dashboard.configure_wordpress') ?> →
                            </a>
                        </div>
                    <?php elseif (empty($criticalServices)): ?>
                        <div style="text-align:center; padding: 40px 20px; color: var(--text-secondary);">
                            <i class="fas fa-check-circle" style="font-size:40px; color:var(--success); opacity:.6; display:block; margin-bottom:12px;"></i>
                            <p style="font-size:13px; margin:0;"><?= __('dashboard.all_services_healthy') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="service-grid">
                            <?php foreach ($criticalShown as $service):
                                $sc    = $service['effective_score'];
                                $alertColor = ['critical' => '#dc3545', 'warning' => '#ffc107', 'expired' => '#343a40'][$service['alert']] ?? '#6c757d';
                                $alertBadge = ['critical' => 'danger', 'warning' => 'warning', 'expired' => 'dark'][$service['alert']] ?? 'secondary';
                                $alertLabel = ['critical' => __('dashboard.status_critical'), 'warning' => __('dashboard.status_warning'), 'expired' => __('diagnostics.expired_label')][$service['alert']] ?? $service['alert'];
                                $days = (int)$service['days_to_expiry'];
                            ?>
                                <div class="service-card" style="border-left: 4px solid <?= $alertColor ?>;">
                                    <div class="service-card-header">
                                        <h3 class="service-card-title" style="font-size:13px;" title="<?= htmlspecialchars($service['domain']) ?>">
                                            <?= htmlspecialchars($service['domain']) ?>
                                        </h3>
                                        <span class="badge badge-<?= $alertBadge ?>" style="font-size:10px;">
                                            <i class="fas fa-exclamation-circle mr-1"></i><?= $alertLabel ?>
                                        </span>
                                    </div>
                                    <div class="service-info">
                                        <div class="service-info-item">
                                            <span class="service-info-label"><?= __('dashboard.expiry') ?></span>
                                            <span class="service-info-value" style="font-size:11px;">
                                                <?= $service['expiry_date_fmt'] ?? ($service['expiry_date'] ? date('d-m-Y', strtotime($service['expiry_date'])) : '—') ?>
                                                <?php if ($days >= 0): ?>
                                                    <span class="<?= $days <= 14 ? 'text-danger' : 'text-warning' ?>">(<?= $days ?>d)</span>
                                                <?php else: ?>
                                                    <span class="text-danger font-weight-bold">(<?= __('diagnostics.expired_label') ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php if ($sc !== null): ?>
                                        <div class="service-info-item">
                                            <span class="service-info-label"><?= __('dashboard.health_score') ?></span>
                                            <span class="service-info-value" style="font-weight:700;color:<?= $alertColor ?>;"><?= (int)$sc ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                                        <a href="index.php?action=websites&do=edit&id=<?= $service['id'] ?><?= $langParam ?>" class="quick-action-btn" style="flex: 1; padding: 7px; font-size: 11px;">
                                            <i class="fas fa-edit"></i> <?= __('common.edit') ?>
                                        </a>
                                        <a href="index.php?action=diagnostics<?= $langParam ?>" class="quick-action-btn" style="flex: 1; padding: 7px; font-size: 11px;">
                                            <i class="fas fa-stethoscope"></i> <?= __('dashboard.diagnose') ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($criticalHidden > 0): ?>
                            <div style="text-align: center; margin-top: 16px;">
                                <a href="index.php?action=diagnostics<?= $langParam ?>" style="color: var(--danger); text-decoration: none; font-size: 13px; font-weight: 600;">
                                    <?= sprintf(__('dashboard.see_n_more'), $criticalHidden) ?> →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: Health Score Gauge & System Info -->
                <div class="dashboard-section">
                    <h2 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: var(--text-primary);">
                        <i class="fas fa-chart-pie"></i> <?= __('dashboard.system_health') ?>
                    </h2>

                    <!-- Health Gauge -->
                    <div class="health-gauge" style="cursor:<?= $hasWpSites ? 'pointer' : 'default' ?>;" <?= $hasWpSites ? "onclick=\"$('#healthModal').modal('show')\"" : '' ?>>
                        <?php
                        $gaugeScore = ($hasWpSites && $portfolioScore !== null) ? $portfolioScore : null;
                        $gaugeOffset = $gaugeScore !== null ? round(314 * (1 - $gaugeScore / 100)) : 314;
                        $gaugeColor = $gaugeScore === null ? '#adb5bd' : ($gaugeScore >= 80 ? '#28a745' : ($gaugeScore >= 60 ? '#ffc107' : '#dc3545'));
                        ?>
                        <div class="gauge-circle">
                            <svg width="120" height="120" viewBox="0 0 120 120">
                                <circle cx="60" cy="60" r="50" fill="none" stroke="#e9ecef" stroke-width="8"/>
                                <circle cx="60" cy="60" r="50" fill="none" stroke="<?= $gaugeColor ?>" stroke-width="8" 
                                    stroke-dasharray="314" stroke-dashoffset="<?= $gaugeOffset ?>" stroke-linecap="round"
                                    transform="rotate(-90 60 60)"/>
                            </svg>
                            <div class="gauge-percentage" style="color:<?= $gaugeColor ?>;">
                                <?= $gaugeScore !== null ? $gaugeScore . '%' : '—' ?>
                            </div>
                        </div>
                        <div class="gauge-label"><?= __('dashboard.overall_health_score') ?></div>
                        <?php if ($hasWpSites && $gaugeScore !== null): ?>
                            <div style="font-size: 12px; color: <?= $gaugeColor ?>; margin-top: 8px; font-weight: 600;">
                                <?= $gaugeScore >= 80 ? '✓ ' . __('dashboard.excellent') : ($gaugeScore >= 60 ? '⚠ ' . __('dashboard.status_warning') : '✗ ' . __('dashboard.status_critical')) ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">
                                <i class="fas fa-info-circle"></i> <?= __('dashboard.click_for_details') ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 8px;">
                                <?= __('dashboard.no_wp_configured') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Stats -->
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border);">
                        <div style="display: grid; gap: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-secondary); font-size: 13px;"><?= __('dashboard.database_status') ?></span>
                                <span style="color: var(--success); font-weight: 600;"><?= __('dashboard.online') ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-secondary); font-size: 13px;"><?= __('dashboard.cron_jobs') ?></span>
                                <span style="color: <?= $cronEnabled ? 'var(--success)' : 'var(--danger)' ?>; font-weight: 600;">
                                    <?= $cronEnabled ? __('cron.enabled') : __('cron.disabled') ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-secondary); font-size: 13px;"><?= __('dashboard.total_services_managed') ?></span>
                                <span style="font-weight: 600; color: var(--text-primary);"><?= $totalWebsites ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-secondary); font-size: 13px;"><?= __('dashboard.wp_monitored') ?></span>
                                <span style="font-weight: 600; color: var(--text-primary);"><?= count($criticalServices) + ($hasWpSites ? 0 : 0) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── QUICK ACTIONS ──────────────────────────────────────── -->
            <div class="dashboard-section">
                <h2 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: var(--text-primary);">
                    <i class="fas fa-magic"></i> <?= __('dashboard.quick_actions') ?>
                </h2>
                <div class="quick-actions">
                    <a href="index.php?action=diagnostics<?= $langParam ?>" class="quick-action-btn">
                        <i class="fas fa-stethoscope"></i>
                        <?= __('dashboard.run_diagnostics') ?>
                    </a>
                    <a href="index.php?action=websites&filter=expiring<?= $langParam ?>" class="quick-action-btn">
                        <i class="fas fa-calendar-check"></i>
                        <?= __('dashboard.renewals') ?>
                    </a>
                    <a href="index.php?action=messaging<?= $langParam ?>" class="quick-action-btn">
                        <i class="fas fa-envelope"></i>
                        <?= __('dashboard.messages') ?>
                    </a>
                    <a href="index.php?action=automation<?= $langParam ?>" class="quick-action-btn">
                        <i class="fas fa-robot"></i>
                        <?= __('dashboard.automation') ?>
                    </a>
                    <a href="index.php?action=cron<?= $langParam ?>" class="quick-action-btn">
                        <i class="fas fa-clock"></i>
                        <?= __('sidebar.cron_scheduler') ?>
                    </a>
                    <a href="index.php?action=settings&do=site_settings<?= $langParam ?>" class="quick-action-btn">
                        <i class="fas fa-cog"></i>
                        <?= __('menu.settings') ?>
                    </a>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- ── EXPIRING SERVICES MODAL ─────────────────────────────────────────── -->
<?php
function modalTimeLeft(?string $date): string {
    if (empty($date)) return '—';
    try {
        $today  = new DateTimeImmutable('today');
        $expiry = new DateTimeImmutable($date);
        $diff   = $today->diff($expiry);
        $months = ($diff->y * 12) + $diff->m;
        $days   = $diff->d;
        $parts  = [];
        if ($months > 0) $parts[] = $months . 'mo';
        if ($days > 0 || $months === 0) $parts[] = $days . 'd';
        $label = implode(' ', $parts);
        return $diff->invert
            ? '<span class="badge badge-danger">-' . htmlspecialchars($label) . '</span>'
            : htmlspecialchars($label);
    } catch (\Exception $e) { return '—'; }
}
?>
<div class="modal fade" id="expiringModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#e53e3e,#fc8181);color:#fff;">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-exclamation mr-2"></i><?= __('dashboard.expiring_modal_title') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <?php if (empty($allExpiringForModal)): ?>
                    <p class="text-muted text-center py-4">
                        <i class="fas fa-check-circle fa-2x text-success d-block mb-2"></i>
                        <?= __('dashboard.no_expiring_services') ?>
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" style="font-size:13px;">
                            <thead class="thead-light">
                                <tr>
                                    <th><?= __('websites.domain') ?></th>
                                    <th><?= __('dashboard.client') ?></th>
                                        <th><?= __('websites.service_type') ?></th>
                                    <th><?= __('dashboard.expiring_date') ?></th>
                                        <th><?= __('dashboard.time_left_label') ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allExpiringForModal as $w):
                                    $daysLeft = isset($w['expiry_date']) ? (int)((strtotime($w['expiry_date']) - time()) / 86400) : null;
                                    $isExpired = $w['_modal_type'] === 'expired';
                                    $rowClass  = $isExpired ? 'table-danger' : ($daysLeft !== null && $daysLeft <= 7 ? 'table-warning' : '');
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td><strong><?= htmlspecialchars($w['domain'] ?? '—') ?></strong></td>
                                        <td><?= htmlspecialchars($w['client_name'] ?? ($w['company_name'] ?? '—')) ?></td>
                                            <td>
                                                <?php
                                                    $svcType = $w['service_type'] ?? '';
                                                    $svcLabel = match($svcType) {
                                                        'domain'        => __('websites.type_domain'),
                                                        'hosting_web'   => __('websites.type_hosting_web'),
                                                        'hosting_mail'  => __('websites.type_hosting_mail'),
                                                        'hosting_plan'  => __('hosting.title'),
                                                        default         => $svcType ?: '—',
                                                    };
                                                    $svcColor = match($svcType) {
                                                        'domain'       => 'primary',
                                                        'hosting_web'  => 'info',
                                                        'hosting_mail' => 'secondary',
                                                        'hosting_plan' => 'success',
                                                        default        => 'light',
                                                    };
                                                ?>
                                                <span class="badge badge-<?= $svcColor ?>"><?= htmlspecialchars($svcLabel) ?></span>
                                            </td>
                                            <td><?= isset($w['expiry_date']) ? date('d-m-Y', strtotime($w['expiry_date'])) : '—' ?></td>
                                            <td><?= modalTimeLeft($w['expiry_date'] ?? null) ?></td>
                                        <td>
                                            <a href="index.php?action=websites&do=edit&id=<?= $w['id'] ?? '' ?><?= $langParam ?>"
                                               class="btn btn-xs btn-outline-primary"><?= __('common.edit') ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <a href="index.php?action=websites&expiry_filter=expiring<?= $langParam ?>" class="btn btn-danger btn-sm">
                    <i class="fas fa-list mr-1"></i><?= __('dashboard.view_all_renewals') ?>
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><?= __('common.close') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- ── HEALTH SCORE BREAKDOWN MODAL ───────────────────────────────────── -->
<div class="modal fade" id="healthModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#2d6a9f,#1e3a5f);color:#fff;">
                <h5 class="modal-title">
                    <i class="fas fa-chart-pie mr-2"></i><?= __('dashboard.health_breakdown_title') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <?php if (!$hasWpSites): ?>
                    <p class="text-muted text-center py-3">
                        <i class="fab fa-wordpress fa-2x d-block mb-2 text-muted"></i>
                        <?= __('dashboard.no_wp_configured_detail') ?>
                    </p>
                <?php else: ?>
                    <p class="text-muted small mb-3"><?= __('dashboard.health_breakdown_note') ?></p>
                    <div class="text-center mb-4">
                        <div style="display:inline-flex;align-items:center;justify-content:center;width:80px;height:80px;border-radius:50%;background:conic-gradient(<?= $gaugeColor ?> <?= round(3.6 * ($portfolioScore ?? 0)) ?>deg,#e9ecef 0deg);">
                            <div style="width:62px;height:62px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;">
                                <strong style="font-size:18px;color:<?= $gaugeColor ?>;"><?= $portfolioScore !== null ? $portfolioScore : '—' ?></strong>
                            </div>
                        </div>
                        <div class="mt-2 font-weight-bold" style="color:<?= $gaugeColor ?>;">
                            <?= $portfolioScore !== null ? ($portfolioScore >= 80 ? __('dashboard.excellent') : ($portfolioScore >= 60 ? __('dashboard.status_warning') : __('dashboard.status_critical'))) : '—' ?>
                        </div>
                    </div>
                    <table class="table table-sm table-bordered">
                        <tbody>
                            <tr>
                                <th><?= __('dashboard.health_basis_wp_sites') ?></th>
                                <td><?= count($criticalServices) + max(0, count($criticalServices)) ?> <?= __('dashboard.health_basis_monitored') ?></td>
                            </tr>
                            <tr>
                                <th><?= __('dashboard.health_basis_critical') ?></th>
                                <td><span class="badge badge-danger"><?= count(array_filter($criticalServices, fn($s) => $s['alert'] === 'critical')) ?></span></td>
                            </tr>
                            <tr>
                                <th><?= __('dashboard.health_basis_warning') ?></th>
                                <td><span class="badge badge-warning"><?= count(array_filter($criticalServices, fn($s) => $s['alert'] === 'warning')) ?></span></td>
                            </tr>
                            <tr>
                                <th><?= __('dashboard.health_basis_expired') ?></th>
                                <td><span class="badge badge-dark"><?= count(array_filter($criticalServices, fn($s) => $s['alert'] === 'expired')) ?></span></td>
                            </tr>
                            <tr>
                                <th><?= __('dashboard.health_basis_method') ?></th>
                                <td class="text-muted small"><?= __('dashboard.health_basis_method_desc') ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="text-right">
                        <a href="index.php?action=diagnostics<?= $langParam ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-stethoscope mr-1"></i><?= __('dashboard.full_diagnostics') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><?= __('common.close') ?></button>
            </div>
        </div>
    </div>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>
