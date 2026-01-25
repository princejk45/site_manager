<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h5><?= __('common.new_user') ?></h5>
                </div>
                <div class="col-sm-6 text-right">
                    <a onclick="window.history.back();" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('common.back') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- Form column -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title mb-0"><?= __('common.modify_details') ?></h3>
                        </div>

                        <div class="card-body">
                            <form method="POST" action="index.php?action=users&do=store&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                <div class="form-group">
                                    <label><?= __('common.email') ?></label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="username"><?= __('common.username') ?></label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>

                                <div class="form-group">
                                    <label for="password"><?= __('common.password') ?></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="form-text text-muted"><?= __('common.password_length_requirement') ?></small>
                                </div>

                                <div class="form-group">
                                    <label for="role"><?= __('common.role') ?></label>
                                    <select class="form-control" id="role" name="role" required>
                                        <option value="viewer"><?= __('common.viewer') ?></option>
                                        <option value="manager"><?= __('common.manager') ?></option>
                                        <option value="super_admin"><?= __('common.super_admin') ?></option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?= __('common.create') ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Image column -->
                <div class="col-md-6 d-flex align-items-center">
                    <div class="text-center w-100">
                        <img src="assets/images/password-security.gif" alt="User Creation" class="img-fluid"
                            style="max-height: 400px;">
                        <h4 class="mt-3"><?= __('common.user_management') ?></h4>
                        <p class="text-muted"><?= __('auth.subtitle') ?? 'Manage application users and permissions' ?></p>
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