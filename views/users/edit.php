<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h5><?= __('common.edit_user') ?></h5>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php?action=users&do=list&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('common.back') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?>
                    <?php unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Form column -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title mb-0"><?= __('common.modify_user') ?></h3>
                        </div>

                        <div class="card-body">
                            <form method="POST" action="index.php?action=users&do=update&id=<?= $user['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                <div class="form-group">
                                    <label><?= __('common.email') ?></label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label><?= __('common.username') ?></label>
                                    <input type="text" name="username" class="form-control"
                                        value="<?= htmlspecialchars($user['username']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label><?= __('common.role') ?></label>
                                    <select name="role" class="form-control" required>
                                        <option value="viewer" <?= $user['role'] === 'viewer' ? 'selected' : '' ?>>
                                            <?= __('common.viewer') ?></option>
                                        <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>
                                            <?= __('common.manager') ?></option>
                                        <option value="super_admin"
                                            <?= $user['role'] === 'super_admin' ? 'selected' : '' ?>><?= __('common.super_admin') ?>
                                        </option>
                                    </select>
                                </div>

                                <div class="form-group form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                        <?= $user['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active"><?= __('common.active') ?></label>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?= __('common.save_changes') ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Image column -->
                <div class="col-md-6 d-flex align-items-center">
                    <div class="text-center w-100">
                        <img src="assets/images/password-security.gif" alt="<?= __('common.user_management') ?>" class="img-fluid"
                            style="max-height: 400px;">
                        <h4 class="mt-3"><?= __('common.user_management') ?></h4>
                        <p class="text-muted"><?= __('common.modify_details') ?></p>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>

<style>
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-control {
        border-radius: 0.25rem;
    }

    .card {
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    }

    .btn-primary {
        padding: 0.375rem 1.5rem;
    }
</style>