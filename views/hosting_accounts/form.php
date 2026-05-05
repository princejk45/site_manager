<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>
<?php
$formData  = $formData  ?? [];
$errors    = $errors    ?? [];
$clients   = $clients   ?? [];
$providers = $providers ?? [];
$userRole  = $userRole  ?? ($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer');
$isEdit    = isset($formData['id']) && (int)$formData['id'] > 0;

$f = fn(string $k, string $default = '') => htmlspecialchars((string)($formData[$k] ?? $default));
$fd = fn(string $k) => htmlspecialchars(sm_form_date_value($formData[$k] ?? ''));
?>

<div class="content-wrapper">
    <section class="content-header px-0 pb-0">
        <div style="background:linear-gradient(135deg,#0d6efd 0%,#3b82f6 100%);color:#fff;padding:1.4rem 1.75rem 1.2rem;">
            <h1 class="mb-0" style="font-size:1.45rem;font-weight:700;">
                <i class="fas fa-hdd mr-2" style="opacity:.85;"></i>
                <?= $isEdit ? __('hosting_accounts.edit') : __('hosting_accounts.add') ?>
            </h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row justify-content-center mt-4">
                <div class="col-lg-8">

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 pl-3">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= htmlspecialchars($e) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form method="post"
                                action="index.php?action=hosting_accounts&do=<?= $isEdit ? 'edit&id=' . (int)$formData['id'] : 'create' ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">

                                <!-- Client + Provider -->
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="client_id"><?= __('hosting_accounts.col_client') ?> <span class="text-danger">*</span></label>
                                        <select id="client_id" name="client_id" class="form-control" required>
                                            <option value="0">— <?= __('hosting_accounts.select_client') ?> —</option>
                                            <?php foreach ($clients as $c): ?>
                                                <option value="<?= $c['id'] ?>" <?= (int)($formData['client_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="provider_id"><?= __('hosting_accounts.col_provider') ?> <span class="text-danger">*</span></label>
                                        <select id="provider_id" name="provider_id" class="form-control" required>
                                            <option value="0">— <?= __('hosting_accounts.select_provider') ?> —</option>
                                            <?php foreach ($providers as $p): ?>
                                                <option value="<?= $p['id'] ?>" <?= (int)($formData['provider_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($p['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted"><?= __('hosting_accounts.provider_hint') ?></small>
                                    </div>
                                </div>

                                <!-- cPanel username + Package -->
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="cpanel_username"><?= __('hosting_accounts.col_cpanel_user') ?></label>
                                        <input type="text" id="cpanel_username" name="cpanel_username" class="form-control"
                                            value="<?= $f('cpanel_username') ?>" maxlength="100"
                                            placeholder="e.g. clientabc">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="package_name"><?= __('hosting_accounts.col_package') ?></label>
                                        <input type="text" id="package_name" name="package_name" class="form-control"
                                            value="<?= $f('package_name') ?>" maxlength="100"
                                            placeholder="e.g. Professional, Business">
                                    </div>
                                </div>

                                <!-- Disk + Bandwidth + IP -->
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="disk_quota_mb"><?= __('hosting_accounts.col_disk_mb') ?></label>
                                        <div class="input-group">
                                            <input type="number" id="disk_quota_mb" name="disk_quota_mb" class="form-control"
                                                value="<?= $f('disk_quota_mb') ?>" min="0" placeholder="e.g. 10240">
                                            <div class="input-group-append"><span class="input-group-text">MB</span></div>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="bandwidth_mb"><?= __('hosting_accounts.col_bandwidth_mb') ?></label>
                                        <div class="input-group">
                                            <input type="number" id="bandwidth_mb" name="bandwidth_mb" class="form-control"
                                                value="<?= $f('bandwidth_mb') ?>" min="0" placeholder="e.g. 102400">
                                            <div class="input-group-append"><span class="input-group-text">MB</span></div>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="ip_address"><?= __('hosting_accounts.col_ip') ?></label>
                                        <input type="text" id="ip_address" name="ip_address" class="form-control"
                                            value="<?= $f('ip_address') ?>" placeholder="e.g. 1.2.3.4">
                                    </div>
                                </div>

                                <!-- Expiry + Status + Auto-renew -->
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="expiry_date"><?= __('hosting_accounts.col_expiry') ?></label>
                                        <input type="text" id="expiry_date" name="expiry_date" class="form-control"
                                            value="<?= $fd('expiry_date') ?>" placeholder="dd-mm-yyyy" inputmode="numeric">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="status"><?= __('hosting_accounts.col_status') ?></label>
                                        <select id="status" name="status" class="form-control">
                                            <?php foreach (['active','suspended','expired','cancelled'] as $s): ?>
                                                <option value="<?= $s ?>" <?= ($formData['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4 d-flex align-items-end">
                                        <div class="custom-control custom-switch mb-2">
                                            <input type="checkbox" class="custom-control-input" id="auto_renew" name="auto_renew" value="1"
                                                <?= ($formData['auto_renew'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="auto_renew"><?= __('hosting_accounts.auto_renew') ?></label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="notes"><?= __('common.notes') ?></label>
                                    <textarea id="notes" name="notes" class="form-control" rows="3"><?= $f('notes') ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="index.php?action=hosting_accounts&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                        class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left mr-1"></i><?= __('common.back') ?>
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save mr-1"></i><?= $isEdit ? __('common.save_changes') : __('hosting_accounts.add') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>
