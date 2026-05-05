<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<?php
$error = $error ?? null;
$success = $success ?? null;
$status = $status ?? ['valid' => false, 'reason' => 'no_license', 'mode' => 'offline', 'payload' => []];
$stored_key = $stored_key ?? '';
$verify_url = $verify_url ?? '';
?>

<style>
  .license-hero {
    border: 1px solid #e9ecef;
    border-radius: .5rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
    padding: 1rem 1.25rem;
    margin-bottom: 1rem;
  }
  .license-status-badge {
    font-size: .82rem;
    padding: .45rem .65rem;
    border-radius: .35rem;
  }
  .license-kv th {
    width: 36%;
    color: #6c757d;
    font-weight: 500;
  }
  .license-mono {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: .82rem;
  }
  .license-muted-note {
    font-size: .84rem;
  }
  .license-page-title {
    font-size: 1.55rem;
    font-weight: 600;
    margin-bottom: 0;
    line-height: 1.2;
  }
  @media (max-width: 767.98px) {
    .license-page-title {
      font-size: 1.3rem;
    }
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="license-page-title"><i class="fas fa-key mr-2"></i><?= __('license_page.title') ?></h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="index.php?action=dashboard"><?= __('menu.dashboard') ?></a></li>
            <li class="breadcrumb-item active"><?= __('license_page.title') ?></li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <div class="license-hero d-flex align-items-center justify-content-between flex-wrap">
        <div class="pr-2">
          <h5 class="mb-1"><i class="fas fa-shield-alt text-primary mr-1"></i><?= __('license_page.hero_title') ?></h5>
          <p class="text-muted mb-0 license-muted-note"><?= __('license_page.hero_subtitle') ?></p>
        </div>
        <span class="badge badge-light border license-status-badge mt-2 mt-sm-0">
          <i class="fas fa-clock mr-1"></i><?= __('license_page.cache_notice') ?>
        </span>
      </div>

      <div class="row">
        <div class="col-lg-6">
          <div class="card card-outline card-primary h-100">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i><b><?= __('license_page.status_title') ?></b></h3>
            </div>
            <div class="card-body">
          <?php
            $reason     = $status['reason'] ?? 'no_license';
            $expires_at = $status['expires_at'] ?? null;
            $payload    = $status['payload'] ?? [];
            $mode       = $status['mode'] ?? 'offline';

            $reasonLabels = [
              'expired'                           => __('license_page.reason_expired'),
              'revoked'                           => __('license_page.reason_revoked'),
              'no_license'                        => __('license_page.reason_not_configured'),
              'invalid_signature'                 => __('license_page.reason_invalid_key'),
              'server_unreachable_grace_expired' => __('license_page.reason_server_unreachable'),
            ];
          ?>

              <?php if ($status['valid']): ?>
                <div class="d-flex align-items-center mb-3">
                  <span class="badge badge-success license-status-badge mr-2"><i class="fas fa-check-circle mr-1"></i><?= __('license_page.valid') ?></span>
                  <span class="text-muted small"><?= __('license_page.valid_description') ?></span>
                </div>

                <?php if ($mode === 'online'): ?>
                  <span class="badge badge-info mb-2"><i class="fas fa-cloud mr-1"></i><?= __('license_page.mode_online') ?></span>
                <?php elseif ($mode === 'online_fallback'): ?>
                  <span class="badge badge-warning mb-2"><i class="fas fa-exclamation-triangle mr-1"></i><?= __('license_page.mode_online_fallback') ?></span>
                <?php else: ?>
                  <span class="badge badge-secondary mb-2"><i class="fas fa-lock mr-1"></i><?= __('license_page.mode_offline') ?></span>
                <?php endif; ?>

                <table class="table table-sm table-borderless license-kv mb-0">
                  <?php if (!empty($payload['client'])): ?>
                    <tr><th><?= __('license_page.client') ?></th><td><?= htmlspecialchars($payload['client']) ?></td></tr>
                  <?php endif; ?>
                  <?php if (!empty($payload['plan'])): ?>
                    <tr><th><?= __('license_page.plan') ?></th><td><span class="badge badge-light border"><?= htmlspecialchars($payload['plan']) ?></span></td></tr>
                  <?php endif; ?>
                  <?php if ($expires_at): ?>
                    <tr><th><?= __('license_page.expires') ?></th><td><?= htmlspecialchars(sm_format_date($expires_at)) ?></td></tr>
                  <?php endif; ?>
                  <?php if (!empty($payload['domain'])): ?>
                    <tr><th><?= __('license_page.domain_lock') ?></th><td><span class="license-mono"><?= htmlspecialchars($payload['domain']) ?></span></td></tr>
                  <?php endif; ?>
                </table>

              <?php elseif ($reason === 'no_enforcement'): ?>
                <div class="alert alert-info mb-2">
                  <i class="fas fa-info-circle mr-1"></i>
                  <?= __('license_page.enforcement_not_configured') ?>
                </div>
              <?php else: ?>
                <?php
                  $badge_map = ['expired' => 'warning', 'revoked' => 'danger', 'no_license' => 'secondary'];
                  $badge_cls = $badge_map[$reason] ?? 'danger';
                  $label     = $reasonLabels[$reason] ?? __('license_page.reason_invalid');
                ?>
                <div class="d-flex align-items-center mb-2">
                  <span class="badge badge-<?= $badge_cls ?> license-status-badge mr-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i><?= $label ?>
                  </span>
                  <?php if ($expires_at && $reason === 'expired'): ?>
                    <span class="text-muted small">
                      <?= __('license_page.expired_on', ['date' => htmlspecialchars(sm_format_date($expires_at))]) ?>
                    </span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php $preview = $stored_key ? (substr($stored_key, 0, 28) . '...') : ''; ?>
              <?php if ($preview): ?>
                <p class="text-muted small mt-3 mb-1"><?= __('license_page.current_key') ?>: <span class="license-mono"><?= htmlspecialchars($preview) ?></span></p>
              <?php endif; ?>

              <?php if ($mode === 'online' || $mode === 'online_fallback'): ?>
                <?php $last_online = $status['last_online_at'] ?? null; ?>
                <?php if ($last_online): ?>
                  <p class="text-muted small mb-0"><?= __('license_page.last_confirmed_online') ?>: <strong><?= sm_format_datetime($last_online) ?></strong></p>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card card-outline card-secondary h-100">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-edit mr-1"></i><b><?= __('license_page.update_title') ?></b></h3>
            </div>
            <div class="card-body">
              <form method="POST" action="index.php?action=settings&do=license">
                <div class="form-group">
                  <label class="font-weight-semibold"><?= __('license_page.license_key_label') ?></label>
                  <textarea name="license_key" class="form-control license-mono" rows="4"
                            placeholder="FM-eyJ..."><?= htmlspecialchars($stored_key) ?></textarea>
                  <small class="text-muted"><?= __('license_page.license_key_help') ?></small>
                </div>

                <div class="form-group mt-3">
                  <label class="font-weight-semibold"><?= __('license_page.verify_url_label') ?>
                    <small class="text-muted font-weight-normal">(<?= __('license_page.optional') ?>)</small>
                  </label>
                  <input type="url" name="verify_url" class="form-control"
                         placeholder="https://your-license-server.com/index.php?action=api_verify"
                         value="<?= htmlspecialchars($verify_url) ?>">
                  <small class="text-muted d-block"><?= __('license_page.verify_url_help_1') ?></small>
                  <small class="text-muted d-block"><?= __('license_page.verify_url_help_2') ?></small>
                </div>

                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save mr-1"></i><?= __('license_page.save_and_verify') ?>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>
