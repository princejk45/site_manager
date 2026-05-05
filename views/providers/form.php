<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>
<?php
$provider = $provider ?? null;
$formData = $formData ?? [];
$errors   = $errors   ?? [];
$userRole = $userRole ?? ($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer');
$isEdit   = isset($formData['id']) && (int)$formData['id'] > 0;

$f = fn(string $k, string $default = '') => htmlspecialchars((string)($formData[$k] ?? $default));
?>

<div class="content-wrapper">
    <section class="content-header px-0 pb-0">
        <div style="background:linear-gradient(135deg,#198754 0%,#20c997 100%);color:#fff;padding:1.4rem 1.75rem 1.2rem;">
            <h1 class="mb-0" style="font-size:1.45rem;font-weight:700;">
                <i class="fas fa-network-wired mr-2" style="opacity:.85;"></i>
                <?= $isEdit ? __('providers.edit') : __('providers.add') ?>
            </h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row justify-content-center mt-4">
                <div class="col-lg-7">

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
                                action="index.php?action=providers&do=<?= $isEdit ? 'edit&id=' . (int)$formData['id'] : 'create' ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">

                                <div class="form-row">
                                    <div class="form-group col-md-7">
                                        <label for="name"><?= __('providers.col_name') ?> <span class="text-danger">*</span></label>
                                        <input type="text" id="name" name="name" class="form-control"
                                            value="<?= $f('name') ?>" required maxlength="100"
                                            placeholder="e.g. vhosting, aruba, serverplan">
                                    </div>
                                    <div class="form-group col-md-5">
                                        <label for="type"><?= __('providers.col_type') ?> <span class="text-danger">*</span></label>
                                        <select id="type" name="type" class="form-control" required>
                                            <option value="whm"       <?= ($formData['type'] ?? '') === 'whm'       ? 'selected' : '' ?>><?= __('providers.type_whm') ?></option>
                                            <option value="registrar" <?= ($formData['type'] ?? '') === 'registrar' ? 'selected' : '' ?>><?= __('providers.type_registrar') ?></option>
                                            <option value="email"     <?= ($formData['type'] ?? '') === 'email'     ? 'selected' : '' ?>><?= __('providers.type_email') ?></option>
                                            <option value="other"     <?= ($formData['type'] ?? '') === 'other'     ? 'selected' : '' ?>><?= __('providers.type_other') ?></option>
                                        </select>
                                        <small class="text-muted"><?= __('providers.type_hint') ?></small>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="base_url"><?= __('providers.col_url') ?></label>
                                    <input type="url" id="base_url" name="base_url" class="form-control"
                                        value="<?= $f('base_url') ?>" placeholder="https://...">
                                    <small class="text-muted"><?= __('providers.url_hint') ?></small>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="username"><?= __('providers.col_username') ?></label>
                                        <input type="text" id="username" name="username" class="form-control"
                                            value="<?= $f('username') ?>" autocomplete="off">
                                        <small class="text-muted"><?= __('providers.username_hint') ?></small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="api_token"><?= __('providers.col_api_token') ?></label>
                                        <input type="password" id="api_token" name="api_token" class="form-control"
                                            value="<?= $f('api_token') ?>" autocomplete="new-password"
                                            placeholder="<?= $isEdit ? __('providers.token_placeholder_edit') : '' ?>">
                                        <small class="text-muted"><?= __('providers.token_hint') ?></small>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="notes"><?= __('common.notes') ?></label>
                                    <textarea id="notes" name="notes" class="form-control" rows="3"><?= $f('notes') ?></textarea>
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1"
                                            <?= ($formData['is_active'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="is_active"><?= __('providers.active_label') ?></label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="index.php?action=providers&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                        class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left mr-1"></i><?= __('common.back') ?>
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save mr-1"></i><?= $isEdit ? __('common.save_changes') : __('providers.add') ?>
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
