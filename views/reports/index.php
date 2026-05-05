<?php
/**
 * Reports Center — Index View
 * Generate, list, download and delete reports
 */

$pageTitle = __('reports.title');
include APP_PATH . '/includes/header.php';
include APP_PATH . '/includes/sidebar-v2.php';

$flashSuccess = $_GET['success'] ?? null;
$flashError   = $_GET['error']   ?? null;
$reports = $reports ?? [];
$successMsg = match ($flashSuccess) {
    'generated' => __('reports.success_generated'),
    'deleted'   => __('reports.success_deleted'),
    default     => null,
};
$errorMsg = match ($flashError) {
    'not_found'    => __('reports.error_not_found'),
    'file_missing' => __('reports.error_file_missing'),
    default        => null,
};

$reportTypes = [
    'portfolio_summary' => __('reports.type_portfolio'),
    'expiry_report'     => __('reports.type_expiry'),
    'health_report'     => __('reports.type_health'),
    'automation_report' => __('reports.type_automation'),
];

function reportTypeBadge(string $type): string
{
    return match ($type) {
        'portfolio_summary' => '<span class="badge badge-primary">Portfolio</span>',
        'expiry_report'     => '<span class="badge badge-warning">Expiry</span>',
        'health_report'     => '<span class="badge badge-info">Health</span>',
        'automation_report' => '<span class="badge badge-secondary">Automation</span>',
        default             => '<span class="badge badge-light">' . htmlspecialchars($type) . '</span>',
    };
}

function reportFormatIcon(string $fmt): string
{
    return match ($fmt) {
    'xlsx' => '<i class="fas fa-file-excel text-success mr-1"></i>XLSX',
    'pdf'  => '<i class="fas fa-file-pdf text-danger mr-1"></i>PDF',
    default => '<span class="text-muted">Legacy</span>',
    };
}

function reportStatusBadge(string $status): string
{
    return match ($status) {
        'GENERATED' => '<span class="badge badge-success">Generated</span>',
        'SCHEDULED' => '<span class="badge badge-info">Scheduled</span>',
        'FAILED'    => '<span class="badge badge-danger">Failed</span>',
        'ARCHIVED'  => '<span class="badge badge-secondary">Archived</span>',
        default     => '<span class="badge badge-light">' . htmlspecialchars($status) . '</span>',
    };
}
?>

<div class="content-wrapper" style="min-height:100vh;">

  <!-- Page Header -->
  <div class="content-header" style="background:linear-gradient(135deg,#20c997 0%,#12b886 100%);color:#fff;padding:1.5rem 1.5rem 1rem;">
    <div class="container-fluid">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h1 class="m-0 font-weight-bold" style="font-size:1.6rem;">
            <i class="fas fa-chart-bar mr-2"></i><?= __('reports.title') ?>
          </h1>
          <p class="mb-0 mt-1" style="opacity:.85;font-size:.9rem;"><?= __('reports.subtitle') ?></p>
        </div>
        <button class="btn btn-light btn-sm font-weight-bold px-3"
                data-toggle="modal" data-target="#generateModal">
          <i class="fas fa-plus mr-1"></i><?= __('reports.generate_new') ?>
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

      <!-- Report Type Quick-Generate Cards -->
      <div class="row mb-3">
        <?php foreach ($reportTypes as $typeKey => $typeLabel): ?>
        <?php
          [$icon, $color, $bg] = match($typeKey) {
              'portfolio_summary' => ['fa-layer-group', '#0d6efd', 'rgba(13,110,253,.1)'],
              'expiry_report'     => ['fa-calendar-alt', '#fd7e14', 'rgba(253,126,20,.1)'],
              'health_report'     => ['fa-heartbeat',    '#17a2b8', 'rgba(23,162,184,.1)'],
              'automation_report' => ['fa-robot',        '#6f42c1', 'rgba(111,66,193,.1)'],
              default             => ['fa-file-alt',     '#6c757d', 'rgba(108,117,125,.1)'],
          };
        ?>
        <div class="col-lg-3 col-sm-6 mb-2">
          <div class="card shadow-sm h-100 border-0 text-center"
               style="cursor:pointer;transition:box-shadow .15s;"
               onclick="quickGenerate('<?= $typeKey ?>', '<?= addslashes($typeLabel) ?>')"
               onmouseenter="this.style.boxShadow='0 4px 14px rgba(0,0,0,.12)'"
               onmouseleave="this.style.boxShadow=''"
          >
            <div class="card-body py-3 px-2">
              <div style="width:44px;height:44px;border-radius:12px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;margin:0 auto .6rem;">
                <i class="fas <?= $icon ?>" style="font-size:1.1rem;color:<?= $color ?>;"></i>
              </div>
              <div class="font-weight-bold text-dark" style="font-size:.82rem;line-height:1.3;"><?= $typeLabel ?></div>
              <small class="text-muted" style="font-size:.72rem;"><?= __('reports.click_to_generate') ?></small>
            </div>
            <div style="height:3px;background:<?= $color ?>;border-radius:0 0 .25rem .25rem;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Stats row -->
      <?php
        $totalReports    = count($reports);
        $totalDownloads  = array_sum(array_column($reports, 'download_count'));
        $completedCount  = count(array_filter($reports, fn($r) => $r['status'] === 'GENERATED'));
      ?>
      <div class="row mb-3">
        <div class="col-md-4 col-sm-4 col-6 mb-2">
          <div class="card shadow-sm mb-0 h-100">
            <div class="card-body py-3 px-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;letter-spacing:.04em;"><?= __('reports.total_reports') ?></div>
                  <div style="font-size:1.6rem;font-weight:700;line-height:1.1;color:#212529;"><?= $totalReports ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(40,167,69,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="fas fa-file-alt" style="font-size:1rem;color:#28a745;"></i>
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
                  <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;letter-spacing:.04em;"><?= __('reports.completed') ?></div>
                  <div style="font-size:1.6rem;font-weight:700;line-height:1.1;color:#17a2b8;"><?= $completedCount ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(23,162,184,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="fas fa-check-circle" style="font-size:1rem;color:#17a2b8;"></i>
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
                  <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:600;letter-spacing:.04em;"><?= __('reports.total_downloads') ?></div>
                  <div style="font-size:1.6rem;font-weight:700;line-height:1.1;color:#fd7e14;"><?= number_format($totalDownloads) ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(253,126,20,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="fas fa-download" style="font-size:1rem;color:#fd7e14;"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Reports Table -->
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
          <h6 class="mb-0 font-weight-bold">
            <i class="fas fa-history mr-2 text-success"></i><?= __('reports.reports_list') ?>
          </h6>
          <span class="badge badge-secondary"><?= $totalReports ?> <?= __('reports.reports') ?></span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($reports)): ?>
          <div class="text-center py-5 text-muted">
            <i class="fas fa-chart-bar fa-3x mb-3 d-block" style="opacity:.15;"></i>
            <p><?= __('reports.no_reports') ?></p>
            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#generateModal">
              <i class="fas fa-plus mr-1"></i><?= __('reports.generate_first') ?>
            </button>
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0" id="reportsTable">
              <thead class="thead-light">
                <tr>
                  <th><?= __('reports.title_col') ?></th>
                  <th><?= __('reports.type') ?></th>
                  <th><?= __('reports.format') ?></th>
                  <th class="text-center"><?= __('reports.status') ?></th>
                  <th class="text-center"><?= __('reports.downloads') ?></th>
                  <th><?= __('reports.generated_at') ?></th>
                  <th><?= __('reports.generated_by') ?></th>
                  <th class="text-right"><?= __('reports.actions') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reports as $rpt): ?>
                <tr>
                  <td>
                    <div class="font-weight-bold"><?= htmlspecialchars($rpt['title']) ?></div>
                    <?php if ($rpt['description']): ?>
                    <small class="text-muted"><?= htmlspecialchars(mb_strimwidth($rpt['description'], 0, 55, '…')) ?></small>
                    <?php endif; ?>
                    <?php if ($rpt['date_from'] || $rpt['date_to']): ?>
                    <small class="text-muted d-block">
                      <?= $rpt['date_from'] ? sm_format_date($rpt['date_from'], '?') : '?' ?>
                      → <?= $rpt['date_to'] ? sm_format_date($rpt['date_to'], '?') : '?' ?>
                    </small>
                    <?php endif; ?>
                  </td>
                  <td><?= reportTypeBadge($rpt['report_type']) ?></td>
                  <td><?= reportFormatIcon($rpt['report_format']) ?></td>
                  <td class="text-center"><?= reportStatusBadge($rpt['status']) ?></td>
                  <td class="text-center">
                    <span class="badge badge-light border"><?= (int)$rpt['download_count'] ?></span>
                  </td>
                  <td class="small text-muted">
                    <?= sm_format_datetime($rpt['generated_at']) ?>
                  </td>
                  <td class="small text-muted">
                    <?= htmlspecialchars($rpt['generator_name'] ?? '—') ?>
                  </td>
                  <td class="text-right text-nowrap">
                      <?php if ($rpt['status'] === 'GENERATED'): ?>
                    <a href="index.php?action=reports&do=download&id=<?= $rpt['id'] ?>"
                       class="btn btn-xs btn-outline-success mr-1"
                       title="<?= __('reports.download') ?>">
                      <i class="fas fa-download"></i>
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-xs btn-outline-danger"
                            onclick="deleteReport(<?= $rpt['id'] ?>, '<?= addslashes(htmlspecialchars($rpt['title'])) ?>')"
                            title="<?= __('reports.delete') ?>">
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

    </div><!-- /.container-fluid -->
  </div><!-- /.content -->
</div><!-- /.content-wrapper -->

<!-- Generate Report Modal -->
<div class="modal fade" id="generateModal" tabindex="-1" role="dialog" aria-labelledby="generateModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="index.php?action=reports&do=generate" id="generateForm">
        <div class="modal-header" style="background:linear-gradient(135deg,#20c997,#12b886);color:#fff;">
          <h5 class="modal-title" id="generateModalLabel">
            <i class="fas fa-chart-bar mr-2"></i><?= __('reports.generate_new') ?>
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="font-weight-bold"><?= __('reports.report_type') ?> <span class="text-danger">*</span></label>
            <select name="report_type" id="reportTypeSelect" class="form-control" required onchange="onTypeChange()">
              <?php foreach ($reportTypes as $key => $label): ?>
              <option value="<?= $key ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="font-weight-bold"><?= __('reports.service_filter') ?></label>
            <select name="service_type_filter" class="form-control">
              <option value="all"><?= __('reports.all_services') ?></option>
              <option value="domain"><?= __('reports.type_domain') ?></option>
              <option value="hosting_web"><?= __('reports.type_hosting_web') ?></option>
              <option value="hosting_mail"><?= __('reports.type_hosting_mail') ?></option>
            </select>
          </div>

          <!-- Per-site filter: shown only for health_report -->
          <div id="siteFilterGroup" style="display:none;">
            <div class="form-group">
              <label class="font-weight-bold">Site <small class="text-muted font-weight-normal">(optional — leave blank for all WP sites)</small></label>
              <select name="website_id" id="websiteIdSelect" class="form-control">
                <option value="">— All WP-configured sites —</option>
                <?php foreach ($wpSitesForReport ?? [] as $ws): ?>
                <option value="<?= (int)$ws['id'] ?>">
                  <?= htmlspecialchars($ws['domain']) ?>
                  <?php if ($ws['client_name']): ?> — <?= htmlspecialchars($ws['client_name']) ?><?php endif; ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="font-weight-bold"><?= __('reports.format') ?> <span class="text-danger">*</span></label>
            <div class="d-flex gap-3">
              <div class="custom-control custom-radio mr-3">
                <input type="radio" name="report_format" id="fmtXlsx" value="xlsx" class="custom-control-input" checked>
                <label class="custom-control-label" for="fmtXlsx">
                  <i class="fas fa-file-excel text-success mr-1"></i>XLSX
                </label>
              </div>
              <div class="custom-control custom-radio">
                <input type="radio" name="report_format" id="fmtPdf" value="pdf" class="custom-control-input">
                <label class="custom-control-label" for="fmtPdf">
                  <i class="fas fa-file-pdf text-danger mr-1"></i>PDF
                </label>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label><?= __('reports.title_col') ?></label>
            <input type="text" name="title" id="reportTitle" class="form-control"
                   placeholder="<?= __('reports.title_placeholder') ?>">
            <small class="text-muted"><?= __('reports.title_hint') ?></small>
          </div>

          <div class="form-group">
            <label><?= __('reports.description') ?></label>
            <textarea name="description" class="form-control" rows="2"
                      placeholder="<?= __('reports.description_placeholder') ?>"></textarea>
          </div>

          <!-- Date range (shown for expiry_report) -->
          <div id="dateRangeGroup" style="display:none;">
            <div class="form-row">
              <div class="col">
                <div class="form-group">
                  <label><?= __('reports.date_from') ?></label>
                  <input type="text" name="date_from" class="form-control" placeholder="dd-mm-yyyy" inputmode="numeric">
                </div>
              </div>
              <div class="col">
                <div class="form-group">
                  <label><?= __('reports.date_to') ?></label>
                  <input type="text" name="date_to" class="form-control" placeholder="dd-mm-yyyy" inputmode="numeric">
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('reports.cancel') ?></button>
          <button type="submit" class="btn btn-success" id="generateBtn">
            <i class="fas fa-cog mr-1"></i><?= __('reports.generate') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete form -->
<form method="POST" id="deleteForm" style="display:none;"></form>

<?php include APP_PATH . '/includes/footer.php'; ?>

<script>
const reportTypeLabels = <?= json_encode(array_map(fn($l) => $l, $reportTypes)) ?>;

function onTypeChange() {
  const type = document.getElementById('reportTypeSelect').value;
  document.getElementById('dateRangeGroup').style.display =
    (type === 'expiry_report') ? '' : 'none';
  document.getElementById('siteFilterGroup').style.display =
    (type === 'health_report') ? '' : 'none';
  if (type !== 'health_report') {
    document.getElementById('websiteIdSelect').value = '';
  }
  // Auto-suggest title
  const label = reportTypeLabels[type] || type;
  const today = new Date().toLocaleDateString('en-GB', {day:'2-digit',month:'2-digit',year:'numeric'}).replace(/\//g, '-');
  document.getElementById('reportTitle').placeholder = label + ' — ' + today;
}

function quickGenerate(type, label) {
  document.getElementById('reportTypeSelect').value = type;
  onTypeChange();
  $('#generateModal').modal('show');
}

function deleteReport(id, title) {
  if (!confirm(<?= json_encode(__('reports.confirm_delete')) ?> + '\n\n' + title)) return;
  const form = document.getElementById('deleteForm');
  form.action = 'index.php?action=reports&do=delete&id=' + id;
  form.submit();
}

// Show spinner on generate
document.getElementById('generateForm').addEventListener('submit', function() {
  const btn = document.getElementById('generateBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i><?= __('reports.generating') ?>';
});

// Initialize DataTables if reports exist
<?php if (!empty($reports)): ?>
$(document).ready(function() {
  $('#reportsTable').DataTable({
    order: [[5, 'desc']],
    pageLength: 25,
    columnDefs: [{ orderable: false, targets: [7] }]
  });
});
<?php endif; ?>
</script>
