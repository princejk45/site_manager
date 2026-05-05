<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>

<?php
$notifications = $notifications ?? [];
$filters = $filters ?? [
    'eventType' => '',
    'status' => '',
    'dateFrom' => '',
    'dateTo' => '',
];

// Service type badge helper (local)
function notifSvcBadge(string $type): string
{
    $map = ['domain' => 'secondary', 'hosting_web' => 'info', 'hosting_mail' => 'primary'];
    return $map[$type] ?? 'info';
}
function notifStatusBadge(string $status): string
{
    return ['sent' => 'success', 'failed' => 'danger', 'queued' => 'warning'][$status] ?? 'secondary';
}
function notifSeverityBadge(string $severity): string
{
    return ['info' => 'info', 'warning' => 'warning', 'error' => 'danger', 'critical' => 'dark'][$severity] ?? 'secondary';
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-bell mr-2"></i><?= __('notifications.title') ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <small class="text-muted"><?= count($notifications) ?> <?= __('notifications.records_shown') ?></small>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- Filter Bar -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter mr-1"></i><?= __('notifications.filter') ?></h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="get" action="index.php" class="form-inline flex-wrap" style="gap: 0.5rem;">
                        <input type="hidden" name="action" value="notifications">
                        <input type="hidden" name="lang" value="<?= $_SESSION['lang'] ?? 'it' ?>">

                        <select name="event_type" class="form-control form-control-sm mr-2">
                            <option value=""><?= __('notifications.all_events') ?></option>
                            <option value="expiry_notification"  <?= ($filters['eventType'] === 'expiry_notification')  ? 'selected' : '' ?>><?= __('notifications.event_expiry') ?></option>
                            <option value="status_notification"  <?= ($filters['eventType'] === 'status_notification')  ? 'selected' : '' ?>><?= __('notifications.event_status') ?></option>
                            <option value="renewal_notification" <?= ($filters['eventType'] === 'renewal_notification') ? 'selected' : '' ?>><?= __('notifications.event_renewal') ?></option>
                            <option value="expiry_scaduto"       <?= ($filters['eventType'] === 'expiry_scaduto')       ? 'selected' : '' ?>><?= __('notifications.event_cron_expired') ?></option>
                            <option value="expiry_30-day"        <?= ($filters['eventType'] === 'expiry_30-day')        ? 'selected' : '' ?>><?= __('notifications.event_cron_30d') ?></option>
                            <option value="expiry_15-day"        <?= ($filters['eventType'] === 'expiry_15-day')        ? 'selected' : '' ?>><?= __('notifications.event_cron_15d') ?></option>
                            <option value="expiry_1-day"         <?= ($filters['eventType'] === 'expiry_1-day')         ? 'selected' : '' ?>><?= __('notifications.event_cron_1d') ?></option>
                        </select>

                        <select name="status" class="form-control form-control-sm mr-2">
                            <option value=""><?= __('notifications.all_statuses') ?></option>
                            <option value="sent"   <?= ($filters['status'] === 'sent')   ? 'selected' : '' ?>><?= __('notifications.status_sent') ?></option>
                            <option value="failed" <?= ($filters['status'] === 'failed') ? 'selected' : '' ?>><?= __('notifications.status_failed') ?></option>
                            <option value="queued" <?= ($filters['status'] === 'queued') ? 'selected' : '' ?>><?= __('notifications.status_queued') ?></option>
                        </select>

                           <input type="text" name="date_from" class="form-control form-control-sm mr-2"
                               value="<?= htmlspecialchars(sm_form_date_value($filters['dateFrom'])) ?>"
                               inputmode="numeric"
                               placeholder="<?= __('notifications.from') ?>">
                           <input type="text" name="date_to" class="form-control form-control-sm mr-2"
                               value="<?= htmlspecialchars(sm_form_date_value($filters['dateTo'])) ?>"
                               inputmode="numeric"
                               placeholder="<?= __('notifications.to') ?>">

                        <button type="submit" class="btn btn-primary btn-sm mr-2">
                            <i class="fas fa-search"></i> <?= __('common.search') ?>
                        </button>
                        <a href="index.php?action=notifications&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times"></i> <?= __('common.reset') ?>
                        </a>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i><?= __('notifications.log_title') ?></h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="notificationsTable" class="table table-bordered table-striped table-hover mb-0"
                               style="font-size: 0.875rem;">
                            <thead class="thead-dark">
                                <tr>
                                    <th><?= __('notifications.col_date') ?></th>
                                    <th><?= __('notifications.col_domain') ?></th>
                                    <th><?= __('notifications.col_client') ?></th>
                                    <th><?= __('websites.service_type') ?></th>
                                    <th><?= __('notifications.col_event') ?></th>
                                    <th><?= __('notifications.col_channel') ?></th>
                                    <th><?= __('notifications.col_severity') ?></th>
                                    <th><?= __('notifications.col_status') ?></th>
                                    <th><?= __('notifications.col_sent_at') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($notifications)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        <?= __('notifications.no_records') ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($notifications as $n): ?>
                                <?php
                                    $svcType  = $n['service_type'] ?? 'hosting_web';
                                    $evtLabel = htmlspecialchars(__('notifications.event_' . str_replace(['-', '_notification', 'expiry_'], ['', '', 'cron_'], $n['event_type'])));
                                    // Fallback to raw event_type if key missing
                                    if (str_starts_with($evtLabel, 'notifications.')) {
                                        $evtLabel = htmlspecialchars($n['event_type']);
                                    }
                                ?>
                                <tr>
                                    <td><small class="text-muted"><?= htmlspecialchars(sm_format_date($n['created_at'])) ?></small></td>
                                    <td>
                                        <?php if ($n['domain']): ?>
                                            <a href="index.php?action=websites&do=view&id=<?= (int)$n['website_id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                                <?= htmlspecialchars($n['domain']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($n['client_name'] ?? '—') ?></td>
                                    <td>
                                        <span class="badge badge-<?= notifSvcBadge($svcType) ?>">
                                            <?= htmlspecialchars(__('websites.type_' . $svcType)) ?>
                                        </span>
                                    </td>
                                    <td><?= $evtLabel ?></td>
                                    <td><span class="badge badge-light border"><?= htmlspecialchars(strtoupper($n['channel'] ?? 'email')) ?></span></td>
                                    <td>
                                        <span class="badge badge-<?= notifSeverityBadge($n['severity'] ?? 'info') ?>">
                                            <?= htmlspecialchars(strtoupper($n['severity'] ?? 'info')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= notifStatusBadge($n['status'] ?? 'queued') ?>">
                                            <?= htmlspecialchars(__('notifications.status_' . ($n['status'] ?? 'queued'))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $n['sent_at'] ? htmlspecialchars(date('d M H:i', strtotime($n['sent_at']))) : '—' ?>
                                        </small>
                                    </td>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof $.fn.DataTable !== 'undefined' && document.getElementById('notificationsTable')) {
        $('#notificationsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            columnDefs: [{ orderable: false, targets: [] }],
            language: {
                search: "<?= addslashes(__('common.search')) ?>:",
                lengthMenu: "<?= addslashes(__('common.show')) ?> _MENU_",
                info: "_START_–_END_ / _TOTAL_",
                paginate: {
                    previous: "‹",
                    next: "›"
                }
            }
        });
    }
});
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>
