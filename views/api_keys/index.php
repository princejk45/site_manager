<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>
<?php
$keys = $keys ?? [];
$newKey = $newKey ?? null;
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-key mr-2"></i><?= __('api_keys.title') ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createKeyModal">
                        <i class="fas fa-plus mr-1"></i><?= __('api_keys.create') ?>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if ($newKey): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    <strong><i class="fas fa-check-circle mr-1"></i><?= __('api_keys.key_created') ?></strong>
                    <p class="mb-1 mt-2"><?= __('api_keys.copy_warning') ?></p>
                    <div class="input-group input-group-sm mt-2" style="max-width: 620px;">
                        <input type="text" id="newKeyDisplay" class="form-control font-monospace"
                               value="<?= htmlspecialchars($newKey['key']) ?>" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-light border" onclick="copyKey()" type="button">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted mt-1 d-block"><?= __('api_keys.name_label') ?>: <strong><?= htmlspecialchars($newKey['name']) ?></strong></small>
                </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="callout callout-info mb-3">
                <h5><?= __('api_keys.how_to_use') ?></h5>
                <p class="mb-1 small"><?= __('api_keys.usage_hint') ?></p>
                <code class="small">Authorization: Bearer fm_xxxxxxxx_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code>
            </div>

            <!-- Keys Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i><?= __('api_keys.active_keys') ?></h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0" style="font-size:.875rem;">
                            <thead class="thead-dark">
                                <tr>
                                    <th><?= __('api_keys.col_name') ?></th>
                                    <th><?= __('api_keys.col_prefix') ?></th>
                                    <th><?= __('api_keys.col_scopes') ?></th>
                                    <th><?= __('api_keys.col_status') ?></th>
                                    <th><?= __('api_keys.col_expires') ?></th>
                                    <th><?= __('api_keys.col_last_used') ?></th>
                                    <th><?= __('api_keys.col_created') ?></th>
                                    <th><?= __('common.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($keys)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-key fa-2x mb-2 d-block"></i>
                                        <?= __('api_keys.no_keys') ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($keys as $k): ?>
                                <?php
                                $scopes  = $k['scopes_json'] ? json_decode($k['scopes_json'], true) : [];
                                $expired = $k['expires_at'] && strtotime($k['expires_at']) < time();
                                $active  = (bool)$k['is_active'] && !$expired;
                                ?>
                                <tr class="<?= !$active ? 'table-secondary' : '' ?>">
                                    <td><strong><?= htmlspecialchars($k['name']) ?></strong></td>
                                    <td><code>fm_<?= htmlspecialchars($k['key_prefix']) ?>_…</code></td>
                                    <td>
                                        <?php if (empty($scopes)): ?>
                                            <span class="text-muted small"><?= __('api_keys.all_scopes') ?></span>
                                        <?php else: ?>
                                            <?php foreach ($scopes as $scope): ?>
                                                <span class="badge badge-light border small"><?= htmlspecialchars($scope) ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($expired): ?>
                                            <span class="badge badge-dark"><?= __('api_keys.status_expired') ?></span>
                                        <?php elseif ($k['is_active']): ?>
                                            <span class="badge badge-success"><?= __('api_keys.status_active') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><?= __('api_keys.status_revoked') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= $k['expires_at'] ? htmlspecialchars(sm_format_date($k['expires_at'])) : '—' ?></small></td>
                                    <td><small class="text-muted"><?= $k['last_used_at'] ? htmlspecialchars(sm_format_datetime($k['last_used_at'])) : '—' ?></small></td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars(sm_format_date($k['created_at'])) ?></small>
                                        <?php if ($k['created_by_name']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($k['created_by_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php if ($k['is_active'] && !$expired): ?>
                                            <a href="index.php?action=api_keys&do=revoke&id=<?= (int)$k['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                               class="btn btn-sm btn-warning confirmable"
                                               data-confirm="<?= addslashes(__('api_keys.confirm_revoke')) ?>">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="index.php?action=api_keys&do=delete&id=<?= (int)$k['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                           class="btn btn-sm btn-danger confirmable"
                                           data-confirm="<?= addslashes(__('api_keys.confirm_delete')) ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
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

<!-- Create Key Modal -->
<div class="modal fade" id="createKeyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post" action="index.php?action=api_keys&do=create&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-key mr-1"></i><?= __('api_keys.create') ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= __('api_keys.form_name') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               placeholder="<?= __('api_keys.form_name_placeholder') ?>" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label><?= __('api_keys.form_scopes') ?></label>
                        <div class="row">
                            <?php foreach ([
                                'read_websites'      => __('api_keys.scope_read_websites'),
                                'export_data'        => __('api_keys.scope_export_data'),
                                'read_reports'       => __('api_keys.scope_read_reports'),
                                'read_notifications' => __('api_keys.scope_read_notifications'),
                            ] as $scope => $label): ?>
                            <div class="col-6">
                                <div class="custom-control custom-checkbox mb-1">
                                    <input type="checkbox" class="custom-control-input"
                                           id="scope_<?= $scope ?>" name="scopes[]" value="<?= $scope ?>">
                                    <label class="custom-control-label small" for="scope_<?= $scope ?>"><?= htmlspecialchars($label) ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted"><?= __('api_keys.scopes_hint') ?></small>
                    </div>
                    <div class="form-group">
                        <label><?= __('api_keys.form_expires') ?></label>
                           <input type="text" name="expires_at" class="form-control"
                               placeholder="dd-mm-yyyy" inputmode="numeric">
                        <small class="text-muted"><?= __('api_keys.expires_hint') ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('common.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus mr-1"></i><?= __('api_keys.generate') ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function copyKey() {
    const el = document.getElementById('newKeyDisplay');
    el.select();
    document.execCommand('copy');
}

document.querySelectorAll('.confirmable').forEach(el => {
    el.addEventListener('click', function(e) {
        const msg = this.dataset.confirm || '<?= addslashes(__('websites.sure')) ?>';
        if (!confirm(msg)) e.preventDefault();
    });
});
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>
