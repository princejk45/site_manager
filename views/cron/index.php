<?php
/**
 * Cron Scheduler — Index View
 * Status, diagnostics, cPanel config, manual trigger, log viewer
 */

$pageTitle = __('cron.title');
include APP_PATH . '/includes/header.php';
include APP_PATH . '/includes/sidebar-v2.php';

$cronStatus = $cronStatus ?? false;
$lastRun = $lastRun ?? null;
$cpanelSettings = $cpanelSettings ?? [];
$nextExec = $nextExec ?? [];
$diagnostics = $diagnostics ?? [];
$isLocalhost = $isLocalhost ?? in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true);

$flashSuccess = $_GET['success'] ?? null;
$successMsg = match ($flashSuccess) {
    'toggled'      => __('cron.success_toggled'),
    'cpanel_saved' => __('cron.success_cpanel_saved'),
    'ran'          => __('cron.success_ran'),
    'run_error'    => __('cron.success_run_error'),
    default        => null,
};

$cpHost     = htmlspecialchars($cpanelSettings['cpanel_host'] ?? '');
$cpUser     = htmlspecialchars($cpanelSettings['cpanel_username'] ?? '');
$cpToken    = htmlspecialchars($cpanelSettings['cpanel_api_token'] ?? '');
$cpCmd      = htmlspecialchars($cpanelSettings['cpanel_command'] ?? '');
$cpPath     = htmlspecialchars($cpanelSettings['command_path'] ?? '');

$nextMsg    = $nextExec['message'] ?? null;
$nextRun    = $nextExec['next_run'] ?? null;
$cronExpr   = $nextExec['cron_expression'] ?? '0 8 * * *';
$cronCommand = trim((PHP_BINARY ?: 'php') . ' ' . realpath(APP_PATH . '/cron/expiry_notifier.php'));

$totalEmails = ($diagnostics['total_emails'] ?? 0);
$smtpOk      = $diagnostics['smtp_configured'] ?? false;
?>

<div class="content-wrapper" style="min-height:100vh;">

  <!-- Page Header -->
  <div class="content-header" style="background:linear-gradient(135deg,#6f42c1 0%,#563d7c 100%);color:#fff;padding:1.5rem 1.5rem 1rem;">
    <div class="container-fluid">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h1 class="m-0 font-weight-bold" style="font-size:1.6rem;">
            <i class="fas fa-clock mr-2"></i><?= __('cron.title') ?>
          </h1>
          <p class="mb-0 mt-1" style="opacity:.85;font-size:.9rem;"><?= __('cron.subtitle') ?></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <!-- Enable/Disable toggle -->
          <div class="custom-control custom-switch mr-3">
            <input type="checkbox" class="custom-control-input" id="cronMasterToggle"
                   <?= $cronStatus ? 'checked' : '' ?>>
            <label class="custom-control-label text-white font-weight-bold" for="cronMasterToggle">
              <span id="cronToggleLabel">
                <?= $cronStatus ? __('cron.enabled') : __('cron.disabled') ?>
              </span>
            </label>
          </div>
          <!-- Run Now -->
          <button class="btn btn-light btn-sm font-weight-bold px-3" id="runNowBtn" onclick="runCronNow()">
            <i class="fas fa-play mr-1"></i><?= __('cron.run_now') ?>
          </button>
          <button class="btn btn-outline-light btn-sm font-weight-bold px-3 ml-2" id="cronDiagnosticsBtn" onclick="openCronDiagnostics()">
            <i class="fas fa-stethoscope mr-1"></i><?= __('settings.cron_diagnostics_button') ?>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="content pt-3">
    <div class="container-fluid">

      <?php if ($successMsg): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($successMsg) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
      <?php endif; ?>

      <!-- Status Row -->
      <div class="row mb-3">

        <!-- Cron Status Card -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body text-center py-4">
              <?php if ($cronStatus): ?>
                <div style="font-size:3rem;color:#28a745;"><i class="fas fa-check-circle"></i></div>
                <h5 class="mt-2 text-success font-weight-bold"><?= __('cron.enabled') ?></h5>
              <?php else: ?>
                <div style="font-size:3rem;color:#dc3545;"><i class="fas fa-pause-circle"></i></div>
                <h5 class="mt-2 text-danger font-weight-bold"><?= __('cron.disabled') ?></h5>
              <?php endif; ?>
              <p class="text-muted small mb-0"><?= __('cron.status_label') ?></p>
            </div>
          </div>
        </div>

        <!-- Last Run -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body text-center py-4">
              <div style="font-size:2.5rem;color:#17a2b8;"><i class="fas fa-history"></i></div>
              <h5 class="mt-2 font-weight-bold">
                <?= $lastRun ? sm_format_date($lastRun) : __('cron.never') ?>
              </h5>
              <p class="text-muted small mb-0"><?= __('cron.last_run') ?></p>
              <?php if ($lastRun): ?>
                <small class="text-muted"><?= sm_format_datetime($lastRun, true, '') !== '' ? substr(sm_format_datetime($lastRun, true), 11) : '' ?></small>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Next Run -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body text-center py-4">
              <div style="font-size:2.5rem;color:#fd7e14;"><i class="fas fa-forward"></i></div>
              <h5 class="mt-2 font-weight-bold">
                <?= $nextRun ? sm_format_datetime($nextRun) : '—' ?>
              </h5>
              <p class="text-muted small mb-1"><?= __('cron.next_run') ?></p>
              <?php if ($nextMsg): ?>
                <small class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($nextMsg) ?></small>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Pending Emails -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body text-center py-4">
              <div style="font-size:2.5rem;color:<?= $totalEmails > 0 ? '#dc3545' : '#28a745' ?>;">
                <i class="fas fa-envelope<?= $totalEmails > 0 ? '-open' : '' ?>"></i>
              </div>
              <h5 class="mt-2 font-weight-bold <?= $totalEmails > 0 ? 'text-danger' : 'text-success' ?>">
                <?= $totalEmails ?>
              </h5>
              <p class="text-muted small mb-0"><?= __('cron.pending_emails') ?></p>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-3 border-left border-warning" style="border-left-width:4px !important;">
        <div class="card-header bg-white">
          <h3 class="card-title mb-0">
            <i class="fas fa-info-circle mr-2 text-warning"></i><?= __('cron.setup_title') ?>
          </h3>
        </div>
        <div class="card-body">
          <p class="mb-3 text-muted"><?= __('cron.setup_intro') ?></p>
          <ol class="mb-3 pl-3">
            <li class="mb-2"><?= __('cron.setup_step_1') ?></li>
            <li class="mb-2"><?= __('cron.setup_step_2') ?></li>
            <li class="mb-2"><?= __('cron.setup_step_3') ?></li>
            <li class="mb-2"><?= __('cron.setup_step_4') ?></li>
            <li><?= __('cron.setup_step_5') ?></li>
          </ol>

          <div class="row">
            <div class="col-lg-8 mb-3 mb-lg-0">
              <div class="small text-muted mb-1"><strong><?= __('cron.cron_command') ?>:</strong></div>
              <code class="d-block bg-light p-3 rounded" style="font-size:.8rem;word-break:break-all;"><?= htmlspecialchars($cronCommand) ?></code>
            </div>
            <div class="col-lg-4">
              <div class="small text-muted mb-1"><strong><?= __('cron.setup_schedule_example') ?>:</strong></div>
              <code class="d-block bg-light p-3 rounded" style="font-size:.8rem;"><?= htmlspecialchars($cronExpr) ?></code>
            </div>
          </div>

          <div class="alert alert-warning mt-3 mb-2 py-2 small">
            <i class="fas fa-exclamation-triangle mr-2"></i><?= __('cron.setup_cpanel_note') ?>
          </div>

          <?php if ($isLocalhost): ?>
            <div class="alert alert-info mb-0 py-2 small">
              <i class="fas fa-laptop mr-2"></i><?= __('cron.setup_local_note') ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="row">
        <!-- Left column: Expiry breakdown + SMTP status -->
        <div class="col-lg-7 mb-3">

          <!-- Expiry Overview -->
          <div class="card shadow-sm mb-3">
            <div class="card-header">
              <h3 class="card-title mb-0">
                <i class="fas fa-calendar-times mr-2 text-warning"></i><?= __('cron.expiry_overview') ?>
              </h3>
            </div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <tbody>
                  <tr>
                    <td><?= __('cron.total_sites') ?></td>
                    <td class="text-right font-weight-bold"><?= $diagnostics['total_websites'] ?? 0 ?></td>
                  </tr>
                  <tr class="table-danger">
                    <td><i class="fas fa-exclamation-circle text-danger mr-1"></i><?= __('cron.expired') ?></td>
                    <td class="text-right font-weight-bold text-danger"><?= $diagnostics['expired_count'] ?? 0 ?></td>
                  </tr>
                  <tr class="table-warning">
                    <td><i class="fas fa-clock text-warning mr-1"></i><?= __('cron.expires_1_day') ?></td>
                    <td class="text-right font-weight-bold text-warning"><?= $diagnostics['expires_1_day_count'] ?? 0 ?></td>
                  </tr>
                  <tr>
                    <td><?= __('cron.expires_15_days') ?></td>
                    <td class="text-right"><?= $diagnostics['expires_15_days_count'] ?? 0 ?></td>
                  </tr>
                  <tr>
                    <td><?= __('cron.expires_30_days') ?></td>
                    <td class="text-right"><?= $diagnostics['expires_30_days_count'] ?? 0 ?></td>
                  </tr>
                  <tr class="<?= $totalEmails > 0 ? 'table-warning' : 'table-success' ?>">
                    <td><strong><?= __('cron.total_pending') ?></strong></td>
                    <td class="text-right font-weight-bold"><?= $totalEmails ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- SMTP Status -->
          <div class="card shadow-sm">
            <div class="card-header">
              <h3 class="card-title mb-0">
                <i class="fas fa-envelope mr-2 text-info"></i><?= __('cron.smtp_status') ?>
              </h3>
            </div>
            <div class="card-body">
              <?php if ($smtpOk): ?>
                <div class="d-flex align-items-center">
                  <i class="fas fa-check-circle text-success fa-2x mr-3"></i>
                  <div>
                    <div class="font-weight-bold text-success"><?= __('cron.smtp_ok') ?></div>
                    <small class="text-muted"><?= __('cron.smtp_from') ?>: <?= htmlspecialchars($diagnostics['smtp_from_email'] ?? '') ?></small>
                  </div>
                </div>
              <?php else: ?>
                <div class="d-flex align-items-center">
                  <i class="fas fa-times-circle text-danger fa-2x mr-3"></i>
                  <div>
                    <div class="font-weight-bold text-danger"><?= __('cron.smtp_not_configured') ?></div>
                    <small><?= __('cron.smtp_hint') ?> <a href="index.php?action=settings&do=smtp"><?= __('cron.smtp_settings_link') ?></a></small>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Right column: Environment + cPanel config -->
        <div class="col-lg-5 mb-3">

          <!-- Environment Info -->
          <div class="card shadow-sm mb-3">
            <div class="card-header">
              <h3 class="card-title mb-0">
                <i class="fas fa-server mr-2 text-secondary"></i><?= __('cron.environment') ?>
              </h3>
            </div>
            <div class="card-body">
              <?php if ($isLocalhost): ?>
                <div class="alert alert-info mb-2 py-2">
                  <i class="fas fa-laptop mr-2"></i><?= __('cron.localhost_notice') ?>
                </div>
                <div class="text-muted small">
                  <strong><?= __('cron.cron_command') ?>:</strong><br>
                  <code class="d-block bg-light p-2 mt-1 rounded" style="font-size:.75rem;word-break:break-all;">
                    <?= htmlspecialchars(PHP_BINARY) ?> <?= htmlspecialchars(realpath(APP_PATH . '/cron/expiry_notifier.php')) ?>
                  </code>
                  <strong class="mt-2 d-block">WP Diagnostics Refresh:</strong>
                  <code class="d-block bg-light p-2 mt-1 rounded" style="font-size:.75rem;word-break:break-all;">
                    <?= htmlspecialchars(PHP_BINARY) ?> <?= htmlspecialchars(realpath(APP_PATH . '/cron/wp_diagnostics_refresh.php')) ?>
                  </code>
                </div>
              <?php else: ?>
                <div class="alert alert-secondary mb-2 py-2 small">
                  <i class="fas fa-cloud mr-2"></i><?= __('cron.production_notice') ?>
                </div>
                <div class="text-muted small">
                  <strong><?= __('cron.suggested_expression') ?>:</strong>
                  <code class="d-block bg-light p-2 mt-1 rounded"><?= htmlspecialchars($cronExpr) ?></code>
                  <strong class="mt-2 d-block">WP Diagnostics (every 6 hours):</strong>
                  <code class="d-block bg-light p-2 mt-1 rounded">0 */6 * * * <?= htmlspecialchars(PHP_BINARY) ?> <?= htmlspecialchars(realpath(APP_PATH . '/cron/wp_diagnostics_refresh.php')) ?></code>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- cPanel Settings (only on non-localhost) -->
          <?php if (!$isLocalhost): ?>
          <div class="card shadow-sm">
            <div class="card-header">
              <h3 class="card-title mb-0">
                <i class="fas fa-cog mr-2 text-warning"></i><?= __('cron.cpanel_settings') ?>
              </h3>
            </div>
            <div class="card-body">
              <form method="POST" action="index.php?action=cron&do=save_cpanel">
                <div class="form-group mb-2">
                  <label class="small font-weight-bold"><?= __('cron.cpanel_host') ?></label>
                  <input type="text" name="cpanel_host" class="form-control form-control-sm"
                         value="<?= $cpHost ?>" placeholder="https://yourhost.com:2087">
                </div>
                <div class="form-group mb-2">
                  <label class="small font-weight-bold"><?= __('cron.cpanel_user') ?></label>
                  <input type="text" name="cpanel_username" class="form-control form-control-sm"
                         value="<?= $cpUser ?>" placeholder="username">
                </div>
                <div class="form-group mb-2">
                  <label class="small font-weight-bold"><?= __('cron.cpanel_token') ?></label>
                  <input type="password" name="cpanel_api_token" class="form-control form-control-sm"
                         value="<?= $cpToken ?>" placeholder="API Token">
                </div>
                <div class="form-group mb-2">
                  <label class="small font-weight-bold"><?= __('cron.cpanel_command') ?></label>
                  <input type="text" name="cpanel_command" class="form-control form-control-sm"
                         value="<?= $cpCmd ?>" placeholder="php /path/to/cron/expiry_notifier.php">
                </div>
                <button type="submit" class="btn btn-warning btn-sm btn-block">
                  <i class="fas fa-save mr-1"></i><?= __('cron.save_cpanel') ?>
                </button>
              </form>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- Run Output Panel (hidden, shown after AJAX run) -->
      <div class="card shadow-sm mb-3" id="runOutputCard" style="display:none;">
        <div class="card-header">
          <h3 class="card-title mb-0">
            <i class="fas fa-terminal mr-2"></i><?= __('cron.run_output') ?>
          </h3>
        </div>
        <div class="card-body p-0">
          <pre id="runOutputContent" class="m-0 p-3 bg-dark text-light" style="max-height:200px;overflow-y:auto;font-size:.78rem;"></pre>
        </div>
      </div>

      <!-- Cron Diagnostics Modal -->
      <div class="modal fade" id="cronDiagnosticsModal" tabindex="-1" role="dialog" aria-labelledby="cronDiagnosticsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="cronDiagnosticsModalLabel"><?= __('settings.cron_diagnostics') ?></h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div id="cronDiagnosticsContent">
                <div class="text-center">
                  <div class="spinner-border" role="status">
                    <span class="sr-only">Loading...</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('common.close') ?></button>
            </div>
          </div>
        </div>
      </div>

      <!-- Log Viewer -->
      <?php if (!empty($logLines)): ?>
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">
            <i class="fas fa-file-alt mr-2 text-secondary"></i><?= __('cron.log_title') ?>
          </h3>
          <span class="badge badge-secondary"><?= count($logLines) ?> <?= __('cron.log_entries') ?></span>
        </div>
        <div class="card-body p-0">
          <div style="max-height:300px;overflow-y:auto;background:#1e1e1e;">
            <pre class="m-0 p-3 text-light" style="font-size:.72rem;line-height:1.6;"><?php
            foreach ($logLines as $line) {
                $escaped = htmlspecialchars($line);
                // Colorize log levels
                $colored = preg_replace('/\[ERROR\]/', '<span style="color:#ff6b6b;">[ERROR]</span>', $escaped);
                $colored = preg_replace('/\[WARN\]/',  '<span style="color:#ffd43b;">[WARN]</span>',  $colored);
                $colored = preg_replace('/\[INFO\]/',  '<span style="color:#69db7c;">[INFO]</span>',  $colored);
                echo $colored . "\n";
            }
            ?></pre>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-4">
          <i class="fas fa-file-alt fa-2x mb-2 d-block" style="opacity:.2;"></i>
          <?= __('cron.no_log') ?>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /.container-fluid -->
  </div><!-- /.content -->
</div><!-- /.content-wrapper -->

<?php include APP_PATH . '/includes/footer.php'; ?>

<script>
// ── Master toggle ─────────────────────────────────────────────────
document.getElementById('cronMasterToggle').addEventListener('change', function() {
  const checked = this.checked;
  fetch('index.php?action=cron&do=toggle', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    const label = document.getElementById('cronToggleLabel');
    const statusCard = document.querySelector('.card-body .fa-check-circle, .card-body .fa-pause-circle');
    if (data.is_active) {
      label.textContent = <?= json_encode(__('cron.enabled')) ?>;
    } else {
      label.textContent = <?= json_encode(__('cron.disabled')) ?>;
    }
    if (typeof toastr !== 'undefined') {
      toastr.success(data.is_active ? <?= json_encode(__('cron.enabled')) ?> : <?= json_encode(__('cron.disabled')) ?>);
    }
  })
  .catch(() => { this.checked = !checked; });
});

// ── Run Now ───────────────────────────────────────────────────────
function runCronNow() {
  const btn = document.getElementById('runNowBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i><?= __('cron.running') ?>';

  fetch('index.php?action=cron&do=run', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play mr-1"></i><?= __('cron.run_now') ?>';

    const card = document.getElementById('runOutputCard');
    const pre  = document.getElementById('runOutputContent');
    pre.textContent = data.output || '(no output)';
    card.style.display = '';

    if (typeof toastr !== 'undefined') {
      if (data.success) {
        toastr.success(<?= json_encode(__('cron.success_ran')) ?>);
      } else {
        toastr.error(<?= json_encode(__('cron.success_run_error')) ?> + ' (exit ' + data.exit_code + ')');
      }
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play mr-1"></i><?= __('cron.run_now') ?>';
    console.error(err);
  });
}

// ── Diagnostics ───────────────────────────────────────────────────
function openCronDiagnostics() {
  const btn = document.getElementById('cronDiagnosticsBtn');
  const originalText = btn.innerHTML;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <?= __('settings.test_in_progress') ?>';

  fetch('index.php?action=cron&do=diagnostics', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(response => response.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = originalText;

    if (!data.success) {
      document.getElementById('cronDiagnosticsContent').innerHTML =
        '<div class="alert alert-danger"><?= __('settings.diagnostic_error') ?><br>' +
        (data.error || '') + '</div>';
    } else {
      let html = '<div class="row">';

      html += '<div class="col-md-12 mb-3">';
      html += '<h6><?= __('settings.cron_management') ?></h6>';
      html += '<p><strong><?= __('common.status') ?>:</strong> ';
      html += data.cron_enabled ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>';
      html += '</p></div>';

      html += '<div class="col-md-12 mb-3">';
      if (data.is_localhost) {
        html += '<p><strong><?= __('settings.next_execution') ?>:</strong><br>';
        html += '<span class="text-info"><?= __('settings.local_system') ?> - <?= __('settings.system_scheduler_configured') ?></span></p>';
      } else if (data.next_execution) {
        html += '<p><strong><?= __('settings.next_execution') ?>:</strong> ' + data.next_execution;
        if (data.cron_expression) {
          html += '<br><small class="text-muted"><?= __('settings.cron_expression') ?>: ' + data.cron_expression + '</small>';
        }
        html += '</p>';
      } else {
        html += '<p><strong><?= __('settings.next_execution') ?>:</strong><br>';
        html += '<span class="text-warning">' + (data.next_execution_message || '') + '</span></p>';
      }
      html += '</div>';

      html += '<div class="col-md-12 mb-3">';
      html += '<p><strong><?= __('settings.last_execution') ?>:</strong> ';
      html += data.last_run ? data.last_run : '<?= __('settings.never_run') ?>';
      html += '</p></div>';

      html += '<div class="col-md-12 mb-3"><hr></div>';
      html += '<div class="col-md-6">';
      html += '<p><strong><?= __('settings.total_websites') ?>:</strong> <span class="badge badge-primary">' + data.total_websites + '</span></p>';
      html += '</div>';
      html += '<div class="col-md-6">';
      html += '<p><strong><?= __('settings.total_emails_to_send') ?>:</strong> <span class="badge badge-warning">' + data.total_emails + '</span></p>';
      html += '</div>';

      html += '<div class="col-md-3 text-center">';
      html += '<p><small><?= __('settings.websites_expired') ?></small><br><span class="badge badge-danger">' + data.expired_count + '</span></p>';
      html += '</div>';
      html += '<div class="col-md-3 text-center">';
      html += '<p><small><?= __('settings.websites_expiring_1') ?></small><br><span class="badge badge-danger">' + data.expires_1_day_count + '</span></p>';
      html += '</div>';
      html += '<div class="col-md-3 text-center">';
      html += '<p><small><?= __('settings.websites_expiring_15') ?></small><br><span class="badge badge-warning">' + data.expires_15_days_count + '</span></p>';
      html += '</div>';
      html += '<div class="col-md-3 text-center">';
      html += '<p><small><?= __('settings.websites_expiring_30') ?></small><br><span class="badge badge-info">' + data.expires_30_days_count + '</span></p>';
      html += '</div>';

      html += '<div class="col-md-12 mb-3" style="margin-top: 1rem;"><hr></div>';
      html += '<div class="col-md-12">';
      html += '<p><strong><?= __('settings.smtp_status') ?>:</strong> ';
      html += data.smtp_configured
        ? '<span class="badge badge-success"><?= __('settings.smtp_configured_yes') ?></span>'
        : '<span class="badge badge-danger"><?= __('settings.smtp_configured_no') ?></span>';
      if (data.smtp_from_email) {
        html += '<br><small><?= __('settings.sending_from') ?>: ' + data.smtp_from_email + '</small>';
      }
      html += '</p></div>';

      html += '</div>';
      document.getElementById('cronDiagnosticsContent').innerHTML = html;
    }

    $('#cronDiagnosticsModal').modal('show');
  })
  .catch(error => {
    btn.disabled = false;
    btn.innerHTML = originalText;
    document.getElementById('cronDiagnosticsContent').innerHTML =
      '<div class="alert alert-danger">Error: ' + error + '</div>';
    $('#cronDiagnosticsModal').modal('show');
  });
}

// Toastr fallback
if (typeof toastr === 'undefined') {
  window.toastr = { success: (m) => console.log(m), error: (m) => console.error(m) };
}
</script>
