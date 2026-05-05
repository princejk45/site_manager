<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>
<?php
$hostingPlans = $hostingPlans ?? [];
$userRole = $userRole ?? ($_SESSION['role'] ?? 'viewer');
$unassignedServices = $unassignedServices ?? [];
$totalClients = (int)($totalClients ?? count($hostingPlans));
$totalSites = (int)($totalSites ?? 0);
$avgHealth = (float)($avgHealth ?? 0);
$atRiskSites = (int)($atRiskSites ?? 0);
$expiredSites = (int)($expiredSites ?? 0);

$flashMessage = null;
$flashClass = 'success';
if (($_GET['success'] ?? null) === 'assigned') {
    $count = (int)($_GET['count'] ?? 0);
    $flashMessage = __('portfolio.services_assigned') . ': ' . $count;
}
if (!empty($_GET['error'])) {
    $errorMessages = [
        'no_selection' => __('portfolio.error_no_selection'),
        'invalid_client' => __('portfolio.error_invalid_client'),
        'forbidden' => __('portfolio.error_forbidden'),
    ];
    $flashMessage = $errorMessages[$_GET['error']] ?? __('portfolio.error_generic');
    $flashClass = 'danger';
}

function hostingHealthColor(float $score): string
{
    if ($score >= 80) {
        return '#28a745';
    }
    if ($score >= 60) {
        return '#ffc107';
    }
    if ($score >= 40) {
        return '#fd7e14';
    }
    return '#dc3545';
}

function hostingServiceTypeLabel(string $type): string
{
    return match ($type) {
        'domain' => __('portfolio.type_domain'),
        'hosting_mail' => __('portfolio.type_hosting_mail'),
        default => __('portfolio.type_hosting_web'),
    };
}
?>

<div class="content-wrapper">

    <!-- Page Header -->
    <section class="content-header px-0 pb-0">
        <div style="background:linear-gradient(135deg,#0d6efd 0%,#3b82f6 100%);color:#fff;padding:1.4rem 1.75rem 1.2rem;margin-bottom:0;">
            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem;">
                <div>
                    <h1 class="mb-0" style="font-size:1.45rem;font-weight:700;letter-spacing:.01em;">
                        <i class="fas fa-server mr-2" style="opacity:.85;"></i><?= __('hosting.manage_clients') ?>
                    </h1>
                    <small style="opacity:.75;font-size:.8rem;"><?= __('dashboard.all_clients') ?> &mdash; <?= $totalClients ?> <?= __('portfolio.total_clients') ?></small>
                </div>
                <div class="d-flex" style="gap:.5rem;">
                    <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                        <a href="index.php?action=hosting&do=create&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                            class="btn btn-light btn-sm font-weight-600">
                            <i class="fas fa-plus mr-1"></i><?= __('common.add_client') ?>
                        </a>
                        <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-sm" style="display:none;">
                            <i class="fas fa-trash mr-1"></i><span id="selectedCount">0</span> <?= __('common.delete') ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Alerts -->
    <section class="content pt-3">
        <div class="container-fluid">

            <!-- Confirmation Modal -->
            <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-dark">
                            <h5 class="modal-title"><?= __('common.confirm') ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="<?= __('common.close') ?>">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body" id="confirmationMessage">Are you sure?</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-dismiss="modal"><?= __('common.cancel') ?></button>
                            <button type="button" class="btn btn-success" id="confirmActionBtn"><?= __('common.confirm') ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if ($flashMessage): ?>
                <div class="alert alert-<?= $flashClass ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($flashMessage) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- KPI Summary Row -->
            <div class="row mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="card shadow-sm mb-0 h-100" style="border-left:4px solid #0d6efd;">
                        <div class="card-body py-3 px-3 d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3"
                                style="width:42px;height:42px;background:rgba(13,110,253,.12);flex-shrink:0;">
                                <i class="fas fa-users" style="color:#0d6efd;font-size:1rem;"></i>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;line-height:1.1;"><?= __('portfolio.total_clients') ?></div>
                                <div style="font-size:1.65rem;font-weight:700;color:#0d6efd;line-height:1.1;"><?= $totalClients ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="card shadow-sm mb-0 h-100" style="border-left:4px solid #17a2b8;">
                        <div class="card-body py-3 px-3 d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3"
                                style="width:42px;height:42px;background:rgba(23,162,184,.12);flex-shrink:0;">
                                <i class="fas fa-globe" style="color:#17a2b8;font-size:1rem;"></i>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;line-height:1.1;"><?= __('portfolio.total_sites') ?></div>
                                <div style="font-size:1.65rem;font-weight:700;color:#17a2b8;line-height:1.1;"><?= $totalSites ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <?php $hColor = hostingHealthColor($avgHealth); ?>
                    <div class="card shadow-sm mb-0 h-100" style="border-left:4px solid <?= $hColor ?>;">
                        <div class="card-body py-3 px-3 d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3"
                                style="width:42px;height:42px;background:<?= $hColor ?>22;flex-shrink:0;">
                                <i class="fas fa-heartbeat" style="color:<?= $hColor ?>;font-size:1rem;"></i>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;line-height:1.1;"><?= __('portfolio.avg_health') ?></div>
                                <div style="font-size:1.65rem;font-weight:700;color:<?= $hColor ?>;line-height:1.1;"><?= number_format($avgHealth, 1) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="card shadow-sm mb-0 h-100" style="border-left:4px solid #dc3545;">
                        <div class="card-body py-3 px-3 d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3"
                                style="width:42px;height:42px;background:rgba(220,53,69,.12);flex-shrink:0;">
                                <i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:1rem;"></i>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;line-height:1.1;"><?= __('portfolio.at_risk') ?></div>
                                <div style="font-size:1.65rem;font-weight:700;color:#dc3545;line-height:1.1;"><?= $atRiskSites ?></div>
                                <small class="text-muted" style="font-size:.7rem;"><?= __('portfolio.expired') ?>: <?= $expiredSites ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                /* Ensure the table header and body columns align */
                .table-responsive {
                    overflow-x: auto;
                }

                /* Make sure the action buttons stay together */
                .btn-group-actions {
                    display: inline-flex;
                    flex-wrap: nowrap;
                }

                /* Optional: Add horizontal scroll for small screens */
                @media (max-width: 768px) {
                    .table-responsive {
                        -webkit-overflow-scrolling: touch;
                    }
                }

                .no-wrap {
                    white-space: nowrap;
                    width: 17%;
                }

                .site-wrap {
                    white-space: nowrap;
                    width: 20%;
                }

                .date-wrap {
                    white-space: nowrap;
                    width: 10%;
                }

                .table-sm-text {
                    font-size: 0.90rem;
                }

                .table-sm-text th,
                .table-sm-text td {
                    padding: 0.5rem 0.8rem;
                }

                .table td,
                .table th {
                    white-space: normal !important;
                    /* word-break: break-word;*/
                    max-width: 200px;

                    vertical-align: top;
                }


                /* Custom Tooltip Styles */
                [data-custom-tooltip] {
                    position: relative;
                    cursor: pointer;
                }

                [data-custom-tooltip]::after {
                    content: attr(data-custom-tooltip);
                    position: absolute;
                    bottom: 100%;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #333;
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 14px;
                    font-weight: bold;
                    white-space: nowrap;
                    opacity: 0;
                    visibility: hidden;
                    transition: opacity 0.2s, visibility 0.2s;
                    z-index: 1000;
                    pointer-events: none;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                }

                [data-custom-tooltip]:hover::after {
                    opacity: 1;
                    visibility: visible;
                }

                /* Remove default tooltips */
                [title] {
                    position: relative;
                }

                [title]:hover::before,
                [title]:hover::after {
                    display: none !important;
                }

                /* Action buttons styling - keep horizontal with good spacing */
                td a,
                td button,
                td form {
                    margin-right: 0.3rem;
                    margin-bottom: 0.3rem;
                }

                td form {
                    display: inline-block;
                    margin: 0 0.3rem 0.3rem 0;
                }

                td .d-inline {
                    display: inline-block !important;
                    margin-right: 0.3rem;
                }

                /* Ensure buttons wrap nicely on smaller screens */
                @media (max-width: 768px) {
                    td a,
                    td button {
                        margin-right: 0.25rem;
                        margin-bottom: 0.25rem;
                        padding: 0.25rem 0.5rem !important;
                    }
                }
            </style>

            <!-- Hosting Plans Table Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0"><?= __('dashboard.all_clients') ?></h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">

                        <table class="table table-hover mb-0 table-sm-text">
                            <thead class="thead-light" style="border-bottom:2px solid #dee2e6;">
                                <tr>
                                    <!-- Checkbox Column -->
                                    <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                        <th style="width: 30px;">
                                            <input type="checkbox" id="selectAllCheckbox" />
                                        </th>
                                    <?php endif; ?>
                                    <th class="site-wrap"><?= __('hosting.client_name') ?></th>
                                    <th class="site-wrap"><?= __('hosting.vat') ?></th>
                                    <th class="site-wrap"><?= __('hosting.email') ?></th>
                                    <th class="site-wrap"><?= __('hosting.address') ?></th>
                                    <th class=""><?= __('hosting.services') ?></th>
                                    <th class="text-center\"><?= __('portfolio.avg_health_col') ?></th>
                                    <th class="text-center\"><?= __('portfolio.at_risk_col') ?></th>
                                    <th class="text-center\"><?= __('portfolio.expired_col') ?></th>
                                    <th class=""><?= __('hosting.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hostingPlans as $plan): ?>
                                    <tr>
                                        <!-- Checkbox Column -->
                                        <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                            <td style="width: 30px;">
                                                <input type="checkbox" class="hosting-checkbox" value="<?= $plan['id'] ?>" data-name="<?= htmlspecialchars($plan['name'] ?? '') ?>" />
                                            </td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($plan['name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($plan['provider'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($plan['email_address'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($plan['ip_address'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php
                                            $serviceCount = $plan['service_count'] ?? 0;
                                            if ($serviceCount > 0): ?>
                                                <a href="index.php?action=hosting&do=services&id=<?= $plan['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                    class="btn btn-sm btn-info">
                                                    <?= __('hosting.see') ?> <?= $serviceCount ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted"><?= __('hosting.no_services') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" style="color:<?= hostingHealthColor((float)($plan['avg_health'] ?? 0)) ?>;font-weight:700;">
                                            <?= (int)($plan['service_count'] ?? 0) > 0 ? number_format((float)($plan['avg_health'] ?? 0), 1) : '—' ?>
                                        </td>
                                        <td class="text-center"><span class="badge badge-warning"><?= (int)($plan['at_risk_sites'] ?? 0) ?></span></td>
                                        <td class="text-center"><span class="badge badge-danger"><?= (int)($plan['expired_sites'] ?? 0) ?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="openServicesModal(<?= (int)$plan['id'] ?>, <?= htmlspecialchars(json_encode((string)($plan['name'] ?? '')), ENT_QUOTES) ?>)">
                                                <i class="fas fa-list-ul"></i>
                                            </button>
                                            <a href="index.php?action=hosting&do=view&id=<?= $plan['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                class="btn btn-sm btn-success" data-custom-tooltip="<?= __('hosting.view') ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ((int)($plan['service_count'] ?? 0) === 0 && !empty($unassignedServices) && ($userRole === 'manager' || $userRole === 'super_admin')): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info"
                                                    onclick="openAssignModal(<?= (int)$plan['id'] ?>, <?= htmlspecialchars(json_encode((string)($plan['name'] ?? '')), ENT_QUOTES) ?>)">
                                                    <i class="fas fa-link"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                                <!--<a href="index.php?action=hosting&do=edit&id=<?= $plan['id'] ?>"
                                                    class="btn btn-sm btn-primary" data-custom-tooltip="<?= __('hosting.edit') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>-->
                                                <form method="post"
                                                    action="index.php?action=hosting&do=delete&id=<?= $plan['id'] ?>"
                                                    class="d-inline"
                                                    onsubmit="return confirm('<?= __('common.sure_delete_client') ?>')">
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                        data-custom-tooltip="<?= __('hosting.delete') ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Unassigned Services Inline Section -->
            <div class="card shadow-sm mt-3" id="unassignedServicesCard">
                <div class="card-header d-flex align-items-center justify-content-between py-2"
                    style="background:linear-gradient(90deg,#fff3cd 0%,#fff 100%);border-left:4px solid #ffc107;">
                    <h6 class="mb-0 font-weight-bold">
                        <i class="fas fa-unlink mr-2 text-warning"></i><?= __('portfolio.unassigned_services') ?>
                        <span class="badge badge-warning ml-1"><?= count($unassignedServices) ?></span>
                    </h6>
                    <?php if (!empty($unassignedServices) && ($userRole === 'manager' || $userRole === 'super_admin')): ?>
                        <div class="d-flex align-items-center" style="gap:.5rem;">
                            <small class="text-muted"><?= __('portfolio.selected_count') ?>: <strong id="inlineSelectedCount">0</strong></small>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleAllInline(true)"><?= __('portfolio.select_all') ?></button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleAllInline(false)"><?= __('common.cancel') ?></button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($unassignedServices)): ?>
                        <div class="px-3 py-3 text-muted">
                            <i class="fas fa-check-circle text-success mr-1"></i><?= __('portfolio.no_unassigned_services') ?>
                        </div>
                    <?php else: ?>
                        <form method="post" action="index.php?action=hosting&do=assign_services&lang=<?= $_SESSION['lang'] ?? 'it' ?>" id="inlineAssignForm">
                            <input type="hidden" name="client_id" id="inlineClientId" value="0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0" style="font-size:.88rem;">
                                    <thead class="thead-light">
                                        <tr>
                                            <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                                <th style="width:34px;"></th>
                                            <?php endif; ?>
                                            <th><?= __('portfolio.domain') ?></th>
                                            <th><?= __('portfolio.service_type') ?></th>
                                            <th><?= __('portfolio.status') ?></th>
                                            <th><?= __('portfolio.expiry') ?></th>
                                            <th class="text-center"><?= __('portfolio.days_left') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($unassignedServices as $service):
                                            $sType = (string)($service['service_type'] ?? 'hosting_web');
                                            $sTypeBadge = match($sType) { 'domain' => 'secondary', 'hosting_mail' => 'primary', default => 'info' };
                                            $sStatus = strtolower((string)($service['status'] ?? ''));
                                            $sStatusBadge = match($sStatus) { 'active' => 'success', 'warning' => 'warning', 'expired' => 'danger', default => 'secondary' };
                                            $daysLeft = $service['days_left'] ?? null;
                                            $daysClass = $daysLeft === null ? '' : ($daysLeft < 0 ? 'text-danger font-weight-bold' : ($daysLeft <= 30 ? 'text-warning font-weight-bold' : 'text-success'));
                                        ?>
                                            <tr>
                                                <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                                    <td><input type="checkbox" name="website_ids[]" value="<?= (int)$service['id'] ?>" class="inline-service-select" onchange="updateInlineCount()"></td>
                                                <?php endif; ?>
                                                <td>
                                                    <a href="index.php?action=websites&do=view&id=<?= (int)$service['id'] ?>">
                                                        <strong><?= htmlspecialchars((string)$service['domain']) ?></strong>
                                                    </a>
                                                </td>
                                                <td><span class="badge badge-<?= $sTypeBadge ?>"><?= htmlspecialchars(hostingServiceTypeLabel($sType)) ?></span></td>
                                                <td><span class="badge badge-<?= $sStatusBadge ?>"><?= htmlspecialchars(ucfirst($sStatus)) ?></span></td>
                                                <td><?= !empty($service['expiry_date']) ? htmlspecialchars((string)$service['expiry_date']) : '—' ?></td>
                                                <td class="text-center"><span class="<?= $daysClass ?>"><?= $daysLeft !== null ? (int)$daysLeft : '—' ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                <div class="px-3 py-2 border-top bg-light d-flex align-items-center flex-wrap" style="gap:.5rem;" id="inlineAssignBar">
                                    <label for="inlineClientSelector" class="mb-0 mr-1 font-weight-600" style="font-size:.85rem;"><?= __('portfolio.assign_to_client') ?>:</label>
                                    <select id="inlineClientSelector" name="client_id_select" class="form-control form-control-sm" style="max-width:220px;">
                                        <option value="0">— <?= __('portfolio.select_client') ?> —</option>
                                        <?php foreach ($hostingPlans as $plan): ?>
                                            <option value="<?= (int)$plan['id'] ?>"><?= htmlspecialchars((string)($plan['name'] ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-success" id="inlineAssignBtn" disabled>
                                        <i class="fas fa-link mr-1"></i><?= __('portfolio.assign_services') ?>
                                    </button>
                                    <small class="text-muted ml-1" id="inlineAssignHint" style="font-size:.75rem;"><?= __('portfolio.select_services_to_assign') ?></small>
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal fade" id="servicesModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="servicesModalLabel"><?= __('portfolio.service_list') ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><?= __('portfolio.domain') ?></th>
                                            <th><?= __('portfolio.service_type') ?></th>
                                            <th><?= __('portfolio.status') ?></th>
                                            <th class="text-center"><?= __('portfolio.health_score') ?></th>
                                            <th><?= __('portfolio.expiry') ?></th>
                                            <th class="text-center"><?= __('portfolio.days_left') ?></th>
                                            <th><?= __('portfolio.last_check') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="servicesModalBody">
                                        <tr><td colspan="7" class="text-center py-4 text-muted"><?= __('portfolio.loading') ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="assignModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <form class="modal-content" method="post" action="index.php?action=hosting&do=assign_services&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                        <div class="modal-header">
                            <h5 class="modal-title" id="assignModalLabel"><?= __('portfolio.assign_services') ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="client_id" id="assignClientId" value="0">

                            <div class="form-group" id="assignClientSelectorWrap" style="display:none;">
                                <label for="assignClientSelector"><?= __('portfolio.client') ?></label>
                                <select id="assignClientSelector" class="form-control">
                                    <option value="0">—</option>
                                    <?php foreach ($hostingPlans as $plan): ?>
                                        <option value="<?= (int)$plan['id'] ?>"><?= htmlspecialchars((string)($plan['name'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if (empty($unassignedServices)): ?>
                                <p class="text-muted mb-0"><?= __('portfolio.no_unassigned_services') ?></p>
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <small class="text-muted"><?= __('portfolio.selected_count') ?>: <span id="selectedServicesCount">0</span></small>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleAllServices(true)"><?= __('portfolio.select_all') ?></button>
                                </div>
                                <div class="table-responsive" style="max-height:360px;">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width:34px;"></th>
                                                <th><?= __('portfolio.domain') ?></th>
                                                <th><?= __('portfolio.service_type') ?></th>
                                                <th><?= __('portfolio.status') ?></th>
                                                <th><?= __('portfolio.expiry') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($unassignedServices as $service): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="website_ids[]" value="<?= (int)$service['id'] ?>" class="service-select" onchange="updateSelectedCount()"></td>
                                                    <td>
                                                        <a href="index.php?action=websites&do=view&id=<?= (int)$service['id'] ?>">
                                                            <?= htmlspecialchars((string)$service['domain']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars(hostingServiceTypeLabel((string)($service['service_type'] ?? 'hosting_web'))) ?></td>
                                                    <td><?= htmlspecialchars(ucfirst((string)($service['status'] ?? ''))) ?></td>
                                                    <td><?= !empty($service['expiry_date']) ? htmlspecialchars((string)$service['expiry_date']) : '—' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('portfolio.cancel') ?></button>
                            <?php if (!empty($unassignedServices)): ?>
                                <button type="submit" class="btn btn-success"><?= __('portfolio.assign_services') ?></button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </section>
</div>

<script>
    // Translation strings
    const transMessages = {
        warning: '<?= __('common.bulk_delete_warning') ?>',
        clients: '<?= __('common.bulk_delete_clients') ?>',
        cannotUndo: '<?= __('common.cannot_be_undone') ?>'
    };

    const serviceTypeLabels = {
        domain: '<?= addslashes(__('portfolio.type_domain')) ?>',
        hosting_web: '<?= addslashes(__('portfolio.type_hosting_web')) ?>',
        hosting_mail: '<?= addslashes(__('portfolio.type_hosting_mail')) ?>'
    };

    function serviceStatusBadge(status) {
        const normalized = String(status || '').toLowerCase();
        if (normalized === 'active') return 'success';
        if (normalized === 'warning') return 'warning';
        if (normalized === 'expired') return 'danger';
        return 'secondary';
    }

    function openServicesModal(clientId, clientName) {
        $('#servicesModalLabel').text('<?= addslashes(__('portfolio.service_list')) ?>: ' + clientName);
        $('#servicesModalBody').html('<tr><td colspan="7" class="text-center py-4 text-muted"><?= addslashes(__('portfolio.loading')) ?></td></tr>');
        $('#servicesModal').modal('show');

        const lang = encodeURIComponent('<?= addslashes($_SESSION['lang'] ?? 'it') ?>');
        fetch('index.php?action=hosting&do=client_services&id=' + encodeURIComponent(clientId) + '&lang=' + lang, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(resp) {
            const contentType = (resp.headers.get('content-type') || '').toLowerCase();
            if (!resp.ok) {
                throw new Error('HTTP ' + resp.status);
            }
            if (contentType.indexOf('application/json') === -1) {
                return resp.text().then(function(body) {
                    throw new Error('Non-JSON response: ' + body.slice(0, 120));
                });
            }
            return resp.json();
        })
        .then(function(payload) {
            if (!payload.success || !Array.isArray(payload.rows) || payload.rows.length === 0) {
                $('#servicesModalBody').html('<tr><td colspan="7" class="text-center py-4 text-muted"><?= addslashes(__('portfolio.no_services_for_client')) ?></td></tr>');
                return;
            }

            const rows = payload.rows.map(function(row) {
                const badge = serviceStatusBadge(row.status);
                const serviceType = serviceTypeLabels[String(row.service_type || 'hosting_web')] || String(row.service_type || 'hosting_web');
                const score = Number(row.health_score || 0).toFixed(1);
                const daysLeft = row.days_left === null ? '—' : Number(row.days_left);
                const daysClass = row.days_left === null
                    ? ''
                    : (Number(row.days_left) < 0 ? 'text-danger font-weight-bold' : (Number(row.days_left) <= 30 ? 'text-warning font-weight-bold' : 'text-success'));
                const expiry = row.expiry_date ? row.expiry_date : '—';
                const lastCheck = row.last_check ? row.last_check : '—';

                return '' +
                    '<tr>' +
                        '<td><a href="index.php?action=websites&do=view&id=' + Number(row.id) + '">' + String(row.domain || '') + '</a></td>' +
                        '<td>' + serviceType + '</td>' +
                        '<td><span class="badge badge-' + badge + '">' + String(row.status || '') + '</span></td>' +
                        '<td class="text-center" style="font-weight:700;">' + score + '</td>' +
                        '<td>' + expiry + '</td>' +
                        '<td class="text-center"><span class="' + daysClass + '">' + daysLeft + '</span></td>' +
                        '<td>' + lastCheck + '</td>' +
                    '</tr>';
            }).join('');

            $('#servicesModalBody').html(rows);
        })
        .catch(function(err) {
            console.error('Hosting client_services fetch failed:', err);
            $('#servicesModalBody').html('<tr><td colspan="7" class="text-center py-4 text-danger"><?= addslashes(__('portfolio.error_generic')) ?></td></tr>');
        });
    }

    function openAssignModal(clientId, clientName) {
        $('#assignClientId').val(clientId);
        if (clientId > 0) {
            $('#assignClientSelectorWrap').hide();
            $('#assignModalLabel').text('<?= addslashes(__('portfolio.assign_services')) ?>: ' + clientName);
        } else {
            $('#assignClientSelectorWrap').show();
            $('#assignClientSelector').val('0');
            $('#assignModalLabel').text('<?= addslashes(__('portfolio.assign_services')) ?>');
        }

        $('.service-select').prop('checked', false);
        updateSelectedCount();
        $('#assignModal').modal('show');
    }

    function toggleAllServices(checked) {
        $('.service-select').prop('checked', checked);
        updateSelectedCount();
    }

    function updateSelectedCount() {
        $('#selectedServicesCount').text($('.service-select:checked').length);
    }

    // Inline unassigned services table helpers
    function toggleAllInline(checked) {
        $('.inline-service-select').prop('checked', checked);
        updateInlineCount();
    }

    function updateInlineCount() {
        const n = $('.inline-service-select:checked').length;
        $('#inlineSelectedCount').text(n);
        const clientOk = parseInt($('#inlineClientSelector').val() || 0) > 0;
        $('#inlineAssignBtn').prop('disabled', !(n > 0 && clientOk));
        $('#inlineAssignHint').toggle(n === 0 || !clientOk);
    }

    document.addEventListener('DOMContentLoaded', function() {
        let pendingAction = null;

        // Wire inline client selector → client_id hidden + button enable
        const inlineSelector = document.getElementById('inlineClientSelector');
        if (inlineSelector) {
            inlineSelector.addEventListener('change', function() {
                document.getElementById('inlineClientId').value = this.value;
                updateInlineCount();
            });
        }

        // ===== BULK DELETE FUNCTIONALITY =====
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const hostingCheckboxes = document.querySelectorAll('.hosting-checkbox');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const selectedCount = document.getElementById('selectedCount');

        function updateBulkDeleteButton() {
            const checkedCount = document.querySelectorAll('.hosting-checkbox:checked').length;
            if (checkedCount > 0) {
                bulkDeleteBtn.style.display = 'inline-block';
                selectedCount.textContent = checkedCount;
            } else {
                bulkDeleteBtn.style.display = 'none';
                selectAllCheckbox.checked = false;
            }
        }

        // Select all checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                hostingCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkDeleteButton();
            });
        }

        // Individual checkboxes
        hostingCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateBulkDeleteButton();
                // Update select all checkbox state
                if (selectAllCheckbox) {
                    const allChecked = Array.from(hostingCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(hostingCheckboxes).some(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;
                }
            });
        });

        // Bulk delete button
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.hosting-checkbox:checked');
                const selectedNames = Array.from(checkedBoxes).map(cb => cb.dataset.name).join(', ');
                const message = `<strong>${transMessages.warning}</strong> ${checkedBoxes.length} ${transMessages.clients}:<br/><br/><strong>${selectedNames}</strong><br/><br/>${transMessages.cannotUndo}.`;
                document.getElementById('confirmationMessage').innerHTML = message;
                $('#confirmationModal').modal('show');

                pendingAction = () => {
                    const ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'index.php?action=hosting&do=bulk_delete';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids';
                    input.value = ids;
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                };
            });
        }

        // Handle confirmation button click
        document.getElementById('confirmActionBtn').addEventListener('click', function() {
            if (pendingAction) {
                $('#confirmationModal').modal('hide');
                setTimeout(pendingAction, 300);
            }
        });
    });
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>