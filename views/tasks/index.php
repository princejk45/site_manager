<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>
<?php
$tasks = $tasks ?? [];
$filters = $filters ?? ['status' => '', 'type' => ''];
?>

<div class="content-wrapper">
    <section class="content-header px-0 pb-0">
        <div style="background:linear-gradient(135deg,#0d6efd 0%,#3b82f6 100%);color:#fff;padding:1.4rem 1.75rem 1.2rem;">
            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem;">
                <div>
                    <h1 class="mb-0" style="font-size:1.45rem;font-weight:700;">
                        <i class="fas fa-tasks mr-2" style="opacity:.85;"></i><?= __('tasks.title') ?>
                    </h1>
                    <small style="opacity:.75;font-size:.8rem;"><?= count($tasks) ?> <?= __('tasks.records_shown') ?></small>
                </div>
            </div>
        </div>
    </section>

    <section class="content pt-3">
        <div class="container-fluid">

            <!-- Filter Bar -->
            <div class="card shadow-sm mb-3">
                <div class="card-body py-2">
                    <form method="get" action="index.php" class="form-inline flex-wrap" style="gap:.5rem;">
                        <input type="hidden" name="action" value="tasks">
                        <input type="hidden" name="lang" value="<?= $_SESSION['lang'] ?? 'it' ?>">

                        <select name="status" class="form-control form-control-sm mr-2">
                            <option value=""><?= __('tasks.all_statuses') ?></option>
                            <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>><?= __('tasks.status_completed') ?></option>
                            <option value="failed"    <?= $filters['status'] === 'failed'    ? 'selected' : '' ?>><?= __('tasks.status_failed') ?></option>
                            <option value="running"   <?= $filters['status'] === 'running'   ? 'selected' : '' ?>><?= __('tasks.status_running') ?></option>
                            <option value="pending"   <?= $filters['status'] === 'pending'   ? 'selected' : '' ?>><?= __('tasks.status_pending') ?></option>
                        </select>

                        <select name="type" class="form-control form-control-sm mr-2">
                            <option value=""><?= __('tasks.all_types') ?></option>
                            <option value="export_websites"   <?= $filters['type'] === 'export_websites'   ? 'selected' : '' ?>><?= __('tasks.type_export_websites') ?></option>
                            <option value="import_websites"   <?= $filters['type'] === 'import_websites'   ? 'selected' : '' ?>><?= __('tasks.type_import_websites') ?></option>
                            <option value="export_hosting"    <?= $filters['type'] === 'export_hosting'    ? 'selected' : '' ?>><?= __('tasks.type_export_hosting') ?></option>
                            <option value="report_generation" <?= $filters['type'] === 'report_generation' ? 'selected' : '' ?>><?= __('tasks.type_report_generation') ?></option>
                            <option value="cron_run"          <?= $filters['type'] === 'cron_run'          ? 'selected' : '' ?>><?= __('tasks.type_cron_run') ?></option>
                        </select>

                        <button type="submit" class="btn btn-primary btn-sm mr-2">
                            <i class="fas fa-search"></i> <?= __('common.search') ?>
                        </button>
                        <a href="index.php?action=tasks&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times"></i> <?= __('common.reset') ?>
                        </a>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <?php
            $byStatus = ['completed' => 0, 'failed' => 0, 'running' => 0, 'pending' => 0];
            foreach ($tasks as $t) { $byStatus[$t['status']] = ($byStatus[$t['status']] ?? 0) + 1; }
            $kpiMeta = [
                'completed' => ['#28a745', 'rgba(40,167,69,.12)',    'fas fa-check-circle'],
                'failed'    => ['#dc3545', 'rgba(220,53,69,.12)',    'fas fa-times-circle'],
                'running'   => ['#17a2b8', 'rgba(23,162,184,.12)',   'fas fa-spinner'],
                'pending'   => ['#6c757d', 'rgba(108,117,125,.12)', 'fas fa-clock'],
            ];
            ?>
            <div class="row mb-3">
                <?php foreach ($kpiMeta as $s => [$color, $bg, $icon]): ?>
                <div class="col-6 col-md-3 mb-2">
                    <div class="card shadow-sm mb-0 h-100" style="border-left:4px solid <?= $color ?>;">
                        <div class="card-body py-3 px-3 d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3"
                                style="width:42px;height:42px;background:<?= $bg ?>;flex-shrink:0;">
                                <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:1rem;"></i>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;line-height:1.1;"><?= __('tasks.status_' . $s) ?></div>
                                <div style="font-size:1.65rem;font-weight:700;color:<?= $color ?>;line-height:1.1;"><?= $byStatus[$s] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i><?= __('tasks.log_title') ?></h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tasksTable" class="table table-hover mb-0" style="font-size:.875rem;">
                            <thead class="thead-light" style="border-bottom:2px solid #dee2e6;">
                                <tr>
                                    <th><?= __('tasks.col_date') ?></th>
                                    <th><?= __('tasks.col_label') ?></th>
                                    <th><?= __('tasks.col_type') ?></th>
                                    <th><?= __('tasks.col_status') ?></th>
                                    <th><?= __('tasks.col_duration') ?></th>
                                    <th><?= __('tasks.col_user') ?></th>
                                    <th><?= __('tasks.col_result') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($tasks)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        <?= __('tasks.no_records') ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tasks as $task): ?>
                                <?php
                                $sc = ['completed' => 'success', 'failed' => 'danger',
                                       'running' => 'info', 'pending' => 'secondary'][$task['status']] ?? 'secondary';
                                $duration = '—';
                                if ($task['started_at'] && $task['completed_at']) {
                                    $secs = strtotime($task['completed_at']) - strtotime($task['started_at']);
                                    $duration = $secs < 60 ? $secs . 's' : round($secs / 60, 1) . 'm';
                                }
                                $result = $task['result_json'] ? json_decode($task['result_json'], true) : [];
                                ?>
                                <tr>
                                    <td><small class="text-muted"><?= htmlspecialchars(sm_format_datetime($task['created_at'])) ?></small></td>
                                    <td><strong><?= htmlspecialchars($task['label']) ?></strong></td>
                                    <td><code class="small"><?= htmlspecialchars($task['type']) ?></code></td>
                                    <td>
                                        <span class="badge badge-<?= $sc ?>"><?= htmlspecialchars(strtoupper($task['status'])) ?></span>
                                        <?php if ($task['status'] !== 'pending' && $task['status'] !== 'running' && $task['progress'] < 100): ?>
                                            <small class="text-muted">(<?= (int)$task['progress'] ?>%)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= htmlspecialchars($duration) ?></small></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($task['user_name'] ?? '—') ?></small></td>
                                    <td>
                                        <?php if ($task['error_message']): ?>
                                            <small class="text-danger"><?= htmlspecialchars(substr($task['error_message'], 0, 80)) ?></small>
                                        <?php elseif (!empty($result)): ?>
                                            <small class="text-muted">
                                                <?php foreach (array_slice($result, 0, 3, true) as $k => $v): ?>
                                                    <?= htmlspecialchars($k) ?>: <?= htmlspecialchars($v) ?>&nbsp;
                                                <?php endforeach; ?>
                                            </small>
                                        <?php else: ?>—<?php endif; ?>
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
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#tasksTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language: {
                search: "<?= addslashes(__('common.search')) ?>:",
                lengthMenu: "<?= addslashes(__('common.show')) ?> _MENU_"
            }
        });
    }
});
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>
