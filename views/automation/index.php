<?php
/**
 * Automation Center — Index View
 * Lists automation rules, execution history, create/edit modal
 */

$pageTitle = __('automation.title');
include APP_PATH . '/includes/header.php';
include APP_PATH . '/includes/sidebar-v2.php';

$rules = $rules ?? [];
$websites = $websites ?? [];

$flashSuccess = $_GET['success'] ?? null;
$flashError   = $_GET['error']   ?? null;
$flashTriggered = (int)($_GET['triggered'] ?? 0);

$successMsg = match ($flashSuccess) {
    'created' => __('automation.success_created'),
    'updated' => __('automation.success_updated'),
    'deleted' => __('automation.success_deleted'),
    'ran'     => __('automation.success_ran') . " ($flashTriggered sites triggered)",
  'google_sync_saved' => 'Google Sheets sync automation settings updated.',
  'google_sync_ran' => 'Google Sheets sync executed successfully.',
  'google_sync_run_error' => 'Google Sheets sync run failed. Check output/logs.',
    default   => null,
};
$errorMsg = match ($flashError) {
    'name_required' => __('automation.error_name_required'),
    'not_found'     => __('automation.error_not_found'),
  'google_sync_not_configured' => 'Google Sheets is not configured yet. Configure it in Import/Export first.',
    default         => null,
};

$googleSync = $googleSync ?? ['configured' => false, 'sync_config' => null, 'sheet_settings' => []];
$googleSyncConfig = $googleSync['sync_config'] ?? null;
$googleSheetSettings = $googleSync['sheet_settings'] ?? [];
$googleSyncConfigured = (bool)($googleSync['configured'] ?? false);
$googleSyncEnabled = $googleSyncConfigured && (($googleSyncConfig['status'] ?? 'ACTIVE') !== 'PAUSED');
$googleSyncDirection = $googleSyncConfig['sync_direction'] ?? 'BIDIRECTIONAL';
$googleSyncInterval = (int)($googleSyncConfig['sync_interval_minutes'] ?? 60);
$googleSyncNextRun = $googleSyncConfig['next_sync_at'] ?? null;
$googleSyncLastRun = $googleSyncConfig['last_sync_at'] ?? null;
$googleSyncCommand = trim((PHP_BINARY ?: 'php') . ' ' . realpath(APP_PATH . '/cron/google_sheets_sync.php'));

function autoTriggerLabel(string $type, int $threshold, string $unit): string
{
    return match ($type) {
        'expiry_approaching' => __('automation.trigger_expiry') . " (&lt; $threshold $unit)",
        'health_score_below' => __('automation.trigger_health') . " $threshold",
        default              => htmlspecialchars($type),
    };
}

function autoActionLabel(string $type, array $params): string
{
    return match ($type) {
        'send_email' => __('automation.action_send_email') . (isset($params['email']) && $params['email'] ? ' → ' . htmlspecialchars($params['email']) : ''),
        'log'        => __('automation.action_log'),
        default      => htmlspecialchars($type),
    };
}
?>

<div class="content-wrapper" style="min-height:100vh;">
  <!-- Page Header -->
  <div class="content-header" style="background:linear-gradient(135deg,#17a2b8 0%,#138496 100%);color:#fff;padding:1.5rem 1.5rem 1rem;">
    <div class="container-fluid">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h1 class="m-0 font-weight-bold" style="font-size:1.6rem;">
            <i class="fas fa-robot mr-2"></i><?= __('automation.title') ?>
          </h1>
          <p class="mb-0 mt-1" style="opacity:.85;font-size:.9rem;"><?= __('automation.subtitle') ?></p>
        </div>
        <button class="btn btn-light btn-sm font-weight-bold px-3" data-toggle="modal" data-target="#ruleModal" onclick="openCreateModal()">
          <i class="fas fa-plus mr-1"></i><?= __('automation.new_rule') ?>
        </button>
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

      <?php if ($errorMsg): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
      <?php endif; ?>

      <div class="card shadow-sm mb-3">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
          <h6 class="mb-0 font-weight-bold">
            <i class="fas fa-sync-alt mr-2 text-primary"></i>Google Sheets Sync Automation
          </h6>
        </div>
        <div class="card-body">
          <?php if (!$googleSyncConfigured): ?>
            <div class="alert alert-warning mb-2 py-2 small">
              Configure Google Sheets first in Import/Export, then return here to automate sync.
            </div>
          <?php else: ?>
            <form method="POST" action="index.php?action=automation&do=save_google_sync" class="mb-2">
              <div class="row">
                <div class="col-md-4">
                  <div class="custom-control custom-switch mb-3">
                    <input type="checkbox" class="custom-control-input" id="googleSyncEnabled" name="google_sync_enabled" <?= $googleSyncEnabled ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="googleSyncEnabled">Enable automated sync</label>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group mb-2">
                    <label class="small font-weight-bold">Interval (minutes)</label>
                    <input type="number" name="google_sync_interval_minutes" class="form-control form-control-sm" min="5" max="1440" value="<?= $googleSyncInterval ?>">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group mb-2">
                    <label class="small font-weight-bold">Direction</label>
                    <select name="google_sync_direction" class="form-control form-control-sm">
                      <option value="BIDIRECTIONAL" <?= $googleSyncDirection === 'BIDIRECTIONAL' ? 'selected' : '' ?>>Bidirectional</option>
                      <option value="IMPORT" <?= $googleSyncDirection === 'IMPORT' ? 'selected' : '' ?>>Google to Database</option>
                      <option value="EXPORT" <?= $googleSyncDirection === 'EXPORT' ? 'selected' : '' ?>>Database to Google</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary btn-sm btn-block mb-2">
                    <i class="fas fa-save mr-1"></i>Save
                  </button>
                </div>
              </div>
            </form>

            <div class="d-flex flex-wrap align-items-center mb-2" style="gap:.5rem;">
              <span class="badge badge-light border">Sheet ID: <?= htmlspecialchars($googleSheetSettings['sheet_id'] ?? '—') ?></span>
              <span class="badge badge-light border">Next: <?= $googleSyncNextRun ? sm_format_datetime($googleSyncNextRun) : '—' ?></span>
              <span class="badge badge-light border">Last: <?= $googleSyncLastRun ? sm_format_datetime($googleSyncLastRun) : '—' ?></span>
              <button class="btn btn-outline-primary btn-sm ml-auto" id="runGoogleSyncBtn" onclick="runGoogleSyncNow()">
                <i class="fas fa-play mr-1"></i>Run Google Sync Now
              </button>
            </div>

            <div class="text-muted small">
              <strong>Cron command:</strong>
              <code class="d-block bg-light p-2 mt-1 rounded" style="font-size:.75rem;word-break:break-all;"><?= htmlspecialchars($googleSyncCommand) ?></code>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Summary KPI row -->
      <?php
        $totalRules  = count($rules);
        $activeRules = count(array_filter($rules, fn($r) => $r['is_active']));
        $totalExecs  = array_sum(array_column($rules, 'exec_count'));
      ?>
      <div class="row mb-3">
        <div class="col-md-4 col-sm-4 col-6 mb-2">
          <div class="card shadow-sm mb-0 h-100">
            <div class="card-body py-3 px-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;letter-spacing:.04em;"><?= __('automation.rules_list') ?></div>
                  <div style="font-size:1.6rem;font-weight:700;line-height:1.1;color:#212529;"><?= $totalRules ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(23,162,184,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="fas fa-th-list" style="font-size:1rem;color:#17a2b8;"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4 col-sm-4 col-6 mb-2">
          <div class="card shadow-sm mb-0 h-100">
            <div class="card-body py-3 px-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;letter-spacing:.04em;"><?= __('automation.active') ?></div>
                  <div style="font-size:1.6rem;font-weight:700;line-height:1.1;color:#28a745;"><?= $activeRules ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(40,167,69,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="fas fa-check-circle" style="font-size:1rem;color:#28a745;"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4 col-sm-4 col-6 mb-2">
          <div class="card shadow-sm mb-0 h-100">
            <div class="card-body py-3 px-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;letter-spacing:.04em;"><?= __('automation.exec_count') ?></div>
                  <div style="font-size:1.6rem;font-weight:700;line-height:1.1;color:#fd7e14;"><?= number_format($totalExecs) ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(253,126,20,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="fas fa-bolt" style="font-size:1rem;color:#fd7e14;"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Rules List -->
      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
          <h6 class="mb-0 font-weight-bold">
            <i class="fas fa-cogs mr-2 text-info"></i><?= __('automation.rules_list') ?>
          </h6>
          <span class="badge badge-secondary"><?= $totalRules ?> <?= __('automation.rule') ?></span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($rules)): ?>
          <div class="text-center py-5 text-muted">
            <i class="fas fa-robot fa-3x mb-3 d-block" style="opacity:.2;"></i>
            <?= __('automation.no_rules') ?>
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0" id="rulesTable">
              <thead class="thead-light">
                <tr>
                  <th><?= __('automation.rule_name') ?></th>
                  <th><?= __('automation.trigger') ?></th>
                  <th><?= __('automation.action') ?></th>
                  <th class="text-center"><?= __('automation.status') ?></th>
                  <th class="text-center"><?= __('automation.exec_count') ?></th>
                  <th><?= __('automation.last_run') ?></th>
                  <th class="text-right"><?= __('automation.actions') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rules as $rule):
                  $params = json_decode($rule['action_params'] ?? '{}', true);
                ?>
                <tr id="rule-row-<?= $rule['id'] ?>">
                  <td>
                    <div class="font-weight-bold"><?= htmlspecialchars($rule['name']) ?></div>
                    <?php if ($rule['description']): ?>
                    <small class="text-muted"><?= htmlspecialchars(mb_strimwidth($rule['description'], 0, 60, '…')) ?></small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge badge-light border">
                      <i class="fas fa-bolt text-warning mr-1"></i>
                      <?= autoTriggerLabel($rule['trigger_type'], (int)$rule['trigger_threshold'], $rule['trigger_threshold_unit']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge badge-light border">
                      <i class="fas fa-paper-plane text-info mr-1"></i>
                      <?= autoActionLabel($rule['action_type'], $params) ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <div class="custom-control custom-switch d-inline-block">
                      <input type="checkbox" class="custom-control-input rule-toggle"
                             id="toggle-<?= $rule['id'] ?>"
                             data-rule-id="<?= $rule['id'] ?>"
                             <?= $rule['is_active'] ? 'checked' : '' ?>>
                      <label class="custom-control-label" for="toggle-<?= $rule['id'] ?>">
                        <span class="rule-status-label-<?= $rule['id'] ?> <?= $rule['is_active'] ? 'text-success' : 'text-muted' ?> small">
                          <?= $rule['is_active'] ? __('automation.active') : __('automation.inactive') ?>
                        </span>
                      </label>
                    </div>
                  </td>
                  <td class="text-center">
                    <span class="badge badge-info"><?= number_format((int)$rule['exec_count']) ?></span>
                  </td>
                  <td class="small text-muted">
                    <?= $rule['last_executed_at']
                      ? sm_format_datetime($rule['last_executed_at'])
                        : __('automation.never') ?>
                  </td>
                  <td class="text-right text-nowrap">
                    <button class="btn btn-xs btn-outline-success mr-1"
                            onclick="runRule(<?= $rule['id'] ?>, this)"
                            title="<?= __('automation.run_now') ?>">
                      <i class="fas fa-play"></i>
                    </button>
                    <button class="btn btn-xs btn-outline-primary mr-1"
                            onclick='openEditModal(<?= htmlspecialchars(json_encode($rule), ENT_QUOTES) ?>)'
                            title="<?= __('automation.edit') ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-xs btn-outline-danger"
                            onclick="deleteRule(<?= $rule['id'] ?>)"
                            title="<?= __('automation.delete') ?>">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Execution History -->
      <div class="card shadow-sm">
        <div class="card-header py-2">
          <h6 class="mb-0 font-weight-bold">
            <i class="fas fa-history mr-2 text-secondary"></i><?= __('automation.execution_history') ?>
          </h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($history)): ?>
          <div class="text-center py-4 text-muted small">
            <i class="fas fa-history fa-2x mb-2 d-block" style="opacity:.2;"></i>
            <?= __('automation.no_history') ?>
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead class="thead-light">
                <tr>
                  <th><?= __('automation.rule') ?></th>
                  <th><?= __('automation.website') ?></th>
                  <th><?= __('automation.trigger_value') ?></th>
                  <th><?= __('automation.result') ?></th>
                  <th><?= __('automation.executed_at') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $exec): ?>
                <tr>
                  <td><?= htmlspecialchars($exec['rule_name'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($exec['domain'] ?? '—') ?></td>
                  <td><code><?= htmlspecialchars($exec['trigger_value'] ?? '') ?></code></td>
                  <td>
                    <?php
                    $res = $exec['action_result'] ?? '';
                    $cls = match($res) { 'logged' => 'secondary', 'sent' => 'success', 'error' => 'danger', default => 'light' };
                    ?>
                    <span class="badge badge-<?= $cls ?>"><?= htmlspecialchars($res) ?></span>
                    <?php if ($exec['error_message']): ?>
                      <small class="text-danger ml-1"><?= htmlspecialchars(mb_strimwidth($exec['error_message'], 0, 40, '…')) ?></small>
                    <?php endif; ?>
                  </td>
                  <td class="small text-muted"><?= sm_format_datetime($exec['executed_at']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /.container-fluid -->
  </div><!-- /.content -->
</div><!-- /.content-wrapper -->

<!-- Rule Create / Edit Modal -->
<div class="modal fade" id="ruleModal" tabindex="-1" role="dialog" aria-labelledby="ruleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" id="ruleForm" action="index.php?action=automation&do=create">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title" id="ruleModalLabel">
            <i class="fas fa-robot mr-2"></i><span id="modalTitleText"><?= __('automation.create_rule') ?></span>
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="rule_id" id="ruleId">

          <div class="form-group">
            <label class="font-weight-bold"><?= __('automation.rule_name') ?> <span class="text-danger">*</span></label>
            <input type="text" name="name" id="fieldName" class="form-control" required
                   placeholder="e.g. Expiry Alert — 30 days">
          </div>

          <div class="form-group">
            <label><?= __('automation.description') ?></label>
            <textarea name="description" id="fieldDesc" class="form-control" rows="2"
                      placeholder="Optional description…"></textarea>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="card border mb-0 h-100">
                <div class="card-header py-2 bg-light">
                  <strong><i class="fas fa-bolt text-warning mr-1"></i><?= __('automation.trigger') ?></strong>
                </div>
                <div class="card-body">
                  <div class="form-group">
                    <select name="trigger_type" id="fieldTriggerType" class="form-control form-control-sm" onchange="onTriggerChange()">
                      <option value="expiry_approaching"><?= __('automation.trigger_expiry') ?></option>
                      <option value="health_score_below"><?= __('automation.trigger_health') ?></option>
                    </select>
                  </div>
                  <div class="form-row">
                    <div class="col-7">
                      <label class="small"><?= __('automation.trigger_threshold') ?></label>
                      <input type="number" name="trigger_threshold" id="fieldThreshold"
                             class="form-control form-control-sm" value="30" min="1" max="999">
                    </div>
                    <div class="col-5" id="unitGroup">
                      <label class="small"><?= __('automation.trigger_unit_days') ?></label>
                      <select name="trigger_threshold_unit" id="fieldThresholdUnit" class="form-control form-control-sm">
                        <option value="days"><?= __('automation.trigger_unit_days') ?></option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card border mb-0 h-100">
                <div class="card-header py-2 bg-light">
                  <strong><i class="fas fa-paper-plane text-info mr-1"></i><?= __('automation.action') ?></strong>
                </div>
                <div class="card-body">
                  <div class="form-group">
                    <select name="action_type" id="fieldActionType" class="form-control form-control-sm" onchange="onActionChange()">
                      <option value="send_email"><?= __('automation.action_send_email') ?></option>
                      <option value="log"><?= __('automation.action_log') ?></option>
                    </select>
                  </div>
                  <div id="emailFields">
                    <div class="form-group mb-2">
                      <label class="small"><?= __('automation.action_email_label') ?></label>
                      <input type="email" name="action_email" id="fieldEmail" class="form-control form-control-sm"
                             placeholder="notify@example.com">
                    </div>
                  </div>
                  <div class="form-group mb-0">
                    <label class="small"><?= __('automation.action_target') ?></label>
                    <select name="action_target" id="fieldTarget" class="form-control form-control-sm" onchange="onTargetChange()">
                      <option value="all"><?= __('automation.target_all') ?></option>
                      <option value="specific"><?= __('automation.target_specific') ?></option>
                    </select>
                  </div>
                  <div id="websiteSelectGroup" class="mt-2" style="display:none;">
                    <select name="action_website_id" id="fieldWebsite" class="form-control form-control-sm">
                      <option value="">— select site —</option>
                      <?php foreach ($websites as $ws): ?>
                      <option value="<?= $ws['id'] ?>"><?= htmlspecialchars($ws['domain']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('automation.cancel') ?></button>
          <button type="submit" class="btn btn-info" id="saveRuleBtn">
            <i class="fas fa-save mr-1"></i><span id="saveBtnText"><?= __('automation.create_rule') ?></span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete form (hidden) -->
<form method="POST" id="deleteForm" style="display:none;">
  <input type="hidden" name="_confirm" value="1">
</form>

<?php include APP_PATH . '/includes/footer.php'; ?>

<script>
// ── Modal helpers ──────────────────────────────────────────────────
function openCreateModal() {
  document.getElementById('ruleModalLabel').querySelector('span').textContent = <?= json_encode(__('automation.create_rule')) ?>;
  document.getElementById('saveBtnText').textContent = <?= json_encode(__('automation.create_rule')) ?>;
  document.getElementById('ruleForm').action = 'index.php?action=automation&do=create';
  document.getElementById('ruleForm').reset();
  document.getElementById('emailFields').style.display = '';
  document.getElementById('websiteSelectGroup').style.display = 'none';
}

function openEditModal(rule) {
  document.getElementById('ruleModalLabel').querySelector('span').textContent = <?= json_encode(__('automation.update_rule')) ?>;
  document.getElementById('saveBtnText').textContent = <?= json_encode(__('automation.update_rule')) ?>;
  document.getElementById('ruleForm').action = 'index.php?action=automation&do=edit&id=' + rule.id;

  document.getElementById('ruleId').value          = rule.id;
  document.getElementById('fieldName').value        = rule.name || '';
  document.getElementById('fieldDesc').value        = rule.description || '';
  document.getElementById('fieldTriggerType').value = rule.trigger_type || 'expiry_approaching';
  document.getElementById('fieldThreshold').value   = rule.trigger_threshold || 30;
  document.getElementById('fieldThresholdUnit').value = rule.trigger_threshold_unit || 'days';

  let params = {};
  try { params = JSON.parse(rule.action_params || '{}'); } catch(e) {}

  document.getElementById('fieldActionType').value = rule.action_type || 'send_email';
  document.getElementById('fieldEmail').value      = params.email || '';

  const target = params.target || 'all';
  document.getElementById('fieldTarget').value = target;
  document.getElementById('websiteSelectGroup').style.display = (target === 'specific') ? '' : 'none';
  if (params.website_id) {
    document.getElementById('fieldWebsite').value = params.website_id;
  }

  onTriggerChange();
  onActionChange();

  $('#ruleModal').modal('show');
}

function onTriggerChange() {
  const t = document.getElementById('fieldTriggerType').value;
  const unitGroup = document.getElementById('unitGroup');
  unitGroup.style.display = (t === 'expiry_approaching') ? '' : 'none';
}

function onActionChange() {
  const t = document.getElementById('fieldActionType').value;
  document.getElementById('emailFields').style.display = (t === 'send_email') ? '' : 'none';
}

function onTargetChange() {
  const t = document.getElementById('fieldTarget').value;
  document.getElementById('websiteSelectGroup').style.display = (t === 'specific') ? '' : 'none';
}

// ── Toggle ────────────────────────────────────────────────────────
document.querySelectorAll('.rule-toggle').forEach(function(cb) {
  cb.addEventListener('change', function() {
    const ruleId = this.dataset.ruleId;
    const checked = this.checked;
    fetch('index.php?action=automation&do=toggle&id=' + ruleId, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      const label = document.querySelector('.rule-status-label-' + ruleId);
      if (data.is_active) {
        label.textContent = <?= json_encode(__('automation.active')) ?>;
        label.className = 'rule-status-label-' + ruleId + ' text-success small';
      } else {
        label.textContent = <?= json_encode(__('automation.inactive')) ?>;
        label.className = 'rule-status-label-' + ruleId + ' text-muted small';
      }
    })
    .catch(() => { this.checked = !checked; }); // revert on error
  });
});

// ── Run Now ───────────────────────────────────────────────────────
function runRule(id, btn) {
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  fetch('index.php?action=automation&do=run&id=' + id, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play"></i>';
    if (data.success) {
      toastr.success('Rule triggered ' + data.triggered + ' site(s).');
      setTimeout(() => location.reload(), 1500);
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play"></i>';
  });
}

function runGoogleSyncNow() {
  const btn = document.getElementById('runGoogleSyncBtn');
  if (!btn) {
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Running...';

  fetch('index.php?action=automation&do=run_google_sync', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play mr-1"></i>Run Google Sync Now';

    if (typeof toastr !== 'undefined') {
      if (data.success) {
        toastr.success('Google Sheets sync executed successfully.');
      } else {
        toastr.error('Google Sheets sync failed (exit ' + data.exit_code + ').');
      }
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play mr-1"></i>Run Google Sync Now';
  });
}

// ── Delete ────────────────────────────────────────────────────────
function deleteRule(id) {
  if (!confirm(<?= json_encode(__('automation.confirm_delete')) ?>)) return;
  const form = document.getElementById('deleteForm');
  form.action = 'index.php?action=automation&do=delete&id=' + id;
  form.submit();
}

// Toastr fallback if not loaded
if (typeof toastr === 'undefined') {
  window.toastr = { success: (m) => alert(m), error: (m) => alert(m) };
}

<?php if (!empty($editRule)): ?>
// Auto-open edit modal when coming from GET edit route
document.addEventListener('DOMContentLoaded', function() {
  openEditModal(<?= json_encode($editRule) ?>);
});
<?php endif; ?>
</script>
