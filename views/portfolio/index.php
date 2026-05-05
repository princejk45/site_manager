<?php
/**
 * Portfolio Center — Index View
 * Client-level portfolio summary with click-based service inspection.
 */

$pageTitle = __('portfolio.title');
include APP_PATH . '/includes/header.php';
include APP_PATH . '/includes/sidebar-v2.php';

$clientRows = $clientRows ?? [];
$unassignedServices = $unassignedServices ?? [];
$totalClients = (int)($totalClients ?? 0);
$totalSites = (int)($totalSites ?? 0);
$avgHealth = (float)($avgHealth ?? 0);
$atRiskSites = (int)($atRiskSites ?? 0);
$expiredSites = (int)($expiredSites ?? 0);

function portfolioHealthColor(float $score): string
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

  function portfolioServiceTypeLabel(string $type): string
  {
    return match ($type) {
      'domain' => __('portfolio.type_domain'),
      'hosting_mail' => __('portfolio.type_hosting_mail'),
      default => __('portfolio.type_hosting_web'),
    };
  }

$flashMessage = null;
$flashClass = 'success';

if (($flashSuccess ?? null) === 'assigned') {
    $count = (int)($_GET['count'] ?? 0);
    $flashMessage = __('portfolio.services_assigned') . ': ' . $count;
    $flashClass = 'success';
}

if (!empty($flashError)) {
    $errorMessages = [
        'no_selection' => __('portfolio.error_no_selection'),
        'invalid_client' => __('portfolio.error_invalid_client'),
    'forbidden' => __('portfolio.error_forbidden'),
    ];
  $flashMessage = $errorMessages[$flashError] ?? __('portfolio.error_generic');
    $flashClass = 'danger';
}
?>

<div class="content-wrapper" style="min-height:100vh;">
  <div class="content-header" style="background:linear-gradient(135deg,#0d6efd 0%,#3b82f6 100%);color:#fff;padding:1.5rem 1.5rem 1rem;">
    <div class="container-fluid">
      <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
        <div>
          <h1 class="m-0 font-weight-bold" style="font-size:1.6rem;">
            <i class="fas fa-chart-line mr-2"></i><?= __('portfolio.title') ?>
          </h1>
          <p class="mb-0 mt-1" style="opacity:.85;font-size:.9rem;"><?= __('portfolio.subtitle') ?></p>
        </div>
      </div>
    </div>
  </div>

  <div class="content pt-3">
    <div class="container-fluid">

      <?php if ($flashMessage): ?>
      <div class="alert alert-<?= $flashClass ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashMessage) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <?php endif; ?>

      <div class="row mb-3">
        <div class="col-md-3 col-sm-6 mb-2">
          <div class="card shadow-sm mb-0 h-100">
            <div class="card-body py-3 px-3 text-center">
              <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:600;"><?= __('portfolio.total_clients') ?></div>
              <div style="font-size:1.7rem;font-weight:700;color:#0d6efd;"><?= (int)$totalClients ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <div class="card shadow-sm mb-0 h-100">
            <div class="card-body py-3 px-3 text-center">
              <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:600;"><?= __('portfolio.total_sites') ?></div>
              <div style="font-size:1.7rem;font-weight:700;color:#17a2b8;"><?= (int)$totalSites ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <div class="card shadow-sm mb-0 h-100">
            <div class="card-body py-3 px-3 text-center">
              <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:600;"><?= __('portfolio.avg_health') ?></div>
              <div style="font-size:1.7rem;font-weight:700;color:<?= portfolioHealthColor((float)$avgHealth) ?>;"><?= number_format((float)$avgHealth, 1) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <div class="card shadow-sm mb-0 h-100">
            <div class="card-body py-3 px-3 text-center">
              <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:600;"><?= __('portfolio.at_risk') ?></div>
              <div style="font-size:1.7rem;font-weight:700;color:#dc3545;"><?= (int)$atRiskSites ?></div>
              <small class="text-muted d-block"><?= __('portfolio.expired') ?>: <?= (int)$expiredSites ?></small>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-3">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
          <h6 class="mb-0 font-weight-bold">
            <i class="fas fa-users mr-2 text-primary"></i><?= __('portfolio.clients_overview') ?>
          </h6>
          <span class="badge badge-secondary"><?= (int)$totalClients ?></span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($clientRows)): ?>
          <div class="text-center py-4 text-muted">
            <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.2;"></i>
            <?= __('portfolio.no_clients') ?>
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="portfolioClientsTable">
              <thead class="thead-light">
                <tr>
                  <th><?= __('portfolio.client') ?></th>
                  <th><?= __('portfolio.status') ?></th>
                  <th class="text-center"><?= __('portfolio.sites') ?></th>
                  <th class="text-center"><?= __('portfolio.avg_health_col') ?></th>
                  <th class="text-center"><?= __('portfolio.at_risk_col') ?></th>
                  <th class="text-center"><?= __('portfolio.expired_col') ?></th>
                  <th class="text-right"><?= __('portfolio.actions') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($clientRows as $row): ?>
                <?php
                  $clientId = (int)$row['id'];
                  $avg = (float)($row['avg_health'] ?? 0);
                  $status = strtolower((string)($row['client_status'] ?? 'active'));
                  $statusClass = $status === 'active' ? 'success' : ($status === 'suspended' ? 'warning' : 'secondary');
                  $clientName = (string)$row['client_name'];
                  $sitesCount = (int)$row['sites_count'];
                ?>
                <tr>
                  <td>
                    <?php if ($clientId > 0): ?>
                    <a href="index.php?action=hosting&do=view&id=<?= $clientId ?>" class="font-weight-bold">
                      <?= htmlspecialchars($clientName) ?>
                    </a>
                    <?php else: ?>
                    <span class="font-weight-bold"><?= htmlspecialchars($clientName) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['client_expiry_date'])): ?>
                    <small class="text-muted d-block"><?= htmlspecialchars((string)$row['client_expiry_date']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge badge-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                  <td class="text-center"><span class="badge badge-light border"><?= $sitesCount ?></span></td>
                  <td class="text-center" style="color:<?= portfolioHealthColor($avg) ?>;font-weight:700;"><?= $sitesCount ? number_format($avg, 1) : '—' ?></td>
                  <td class="text-center"><span class="badge badge-warning"><?= (int)$row['at_risk_sites'] ?></span></td>
                  <td class="text-center"><span class="badge badge-danger"><?= (int)$row['expired_sites'] ?></span></td>
                  <td class="text-right">
                    <button type="button" class="btn btn-xs btn-outline-primary"
                            onclick="openServicesModal(<?= $clientId ?>, <?= htmlspecialchars(json_encode($clientName), ENT_QUOTES) ?>)">
                      <i class="fas fa-list-ul mr-1"></i><?= __('portfolio.view_services') ?>
                    </button>
                    <?php if ($clientId > 0 && $sitesCount === 0 && !empty($unassignedServices)): ?>
                    <button type="button" class="btn btn-xs btn-outline-success ml-1"
                            onclick="openAssignModal(<?= $clientId ?>, <?= htmlspecialchars(json_encode($clientName), ENT_QUOTES) ?>)">
                      <i class="fas fa-link mr-1"></i><?= __('portfolio.assign_services') ?>
                    </button>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
          <h6 class="mb-0 font-weight-bold">
            <i class="fas fa-unlink mr-2 text-warning"></i><?= __('portfolio.unassigned_services') ?>
          </h6>
          <span class="badge badge-secondary"><?= count($unassignedServices) ?></span>
        </div>
        <div class="card-body">
          <?php if (empty($unassignedServices)): ?>
          <p class="text-muted mb-0"><?= __('portfolio.no_unassigned_services') ?></p>
          <?php else: ?>
          <p class="mb-3 text-muted"><?= __('portfolio.select_services_to_assign') ?></p>
          <button type="button" class="btn btn-sm btn-success" onclick="openAssignModal(0, '')">
            <i class="fas fa-link mr-1"></i><?= __('portfolio.assign_services') ?>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="servicesModal" tabindex="-1" role="dialog" aria-labelledby="servicesModalLabel" aria-hidden="true">
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

<div class="modal fade" id="assignModal" tabindex="-1" role="dialog" aria-labelledby="assignModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form class="modal-content" method="post" action="index.php?action=portfolio&do=assign_services">
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
            <?php foreach ($clientRows as $row): ?>
            <?php if ((int)$row['id'] > 0): ?>
            <option value="<?= (int)$row['id'] ?>"><?= htmlspecialchars((string)$row['client_name']) ?></option>
            <?php endif; ?>
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
                <td><?= htmlspecialchars(portfolioServiceTypeLabel((string)($service['service_type'] ?? 'hosting_web'))) ?></td>
                <td><?= htmlspecialchars(ucfirst((string)$service['status'])) ?></td>
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

<?php include APP_PATH . '/includes/footer.php'; ?>

<script>
const serviceTypeLabels = {
  domain: '<?= addslashes(__('portfolio.type_domain')) ?>',
  hosting_web: '<?= addslashes(__('portfolio.type_hosting_web')) ?>',
  hosting_mail: '<?= addslashes(__('portfolio.type_hosting_mail')) ?>'
};

function serviceStatusBadge(status) {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'active') {
    return 'success';
  }
  if (normalized === 'warning') {
    return 'warning';
  }
  if (normalized === 'expired') {
    return 'danger';
  }
  return 'secondary';
}

function openServicesModal(clientId, clientName) {
  $('#servicesModalLabel').text('<?= addslashes(__('portfolio.service_list')) ?>: ' + clientName);
  $('#servicesModalBody').html('<tr><td colspan="7" class="text-center py-4 text-muted"><?= addslashes(__('portfolio.loading')) ?></td></tr>');
  $('#servicesModal').modal('show');

  const lang = encodeURIComponent('<?= addslashes($_SESSION['lang'] ?? 'it') ?>');
  fetch('index.php?action=portfolio&do=client_services&id=' + encodeURIComponent(clientId) + '&lang=' + lang, {
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
    console.error('Portfolio client_services fetch failed:', err);
    const msg = String((err && err.message) || '');
    const isSessionIssue = msg.toLowerCase().indexOf('non-json response') !== -1;
    const display = isSessionIssue
      ? '<?= addslashes(__('portfolio.error_generic')) ?>. Please refresh the page and sign in again if needed.'
      : '<?= addslashes(__('portfolio.error_generic')) ?>';
    $('#servicesModalBody').html('<tr><td colspan="7" class="text-center py-4 text-danger">' + display + '</td></tr>');
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

$('#assignClientSelector').on('change', function() {
  $('#assignClientId').val($(this).val());
});

$(document).ready(function() {
  $('#portfolioClientsTable').DataTable({
    order: [[2, 'desc']],
    pageLength: 25
  });
});
</script>
