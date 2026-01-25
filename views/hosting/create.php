<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">



    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <?= isset($hostingPlan) ? __('hosting.update_details') : __('hosting.manage_clients') ?>
                    </h3>
                </div>

                <div class="card-body">
                    <form method="POST"
                        action="index.php?action=hosting&do=<?= isset($hostingPlan) ? 'edit&id=' . $hostingPlan['id'] : 'create' ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="server_name"><?= __('hosting.client_name') ?></label>
                                <input type="text" class="form-control" id="server_name" name="server_name"
                                    value="<?= isset($hostingPlan) ? htmlspecialchars($hostingPlan['server_name']) : '' ?>"
                                    required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="provider"><?= __('hosting.vat') ?></label>
                                <input type="text" class="form-control" id="provider" name="provider"
                                    value="<?= isset($hostingPlan) ? htmlspecialchars($hostingPlan['provider']) : '' ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="email_address"><?= __('common.email') ?></label>
                                <input type="email" class="form-control" id="email_address" name="email_address"
                                    value="<?= isset($hostingPlan) ? htmlspecialchars($hostingPlan['email_address']) : '' ?>"
                                    required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="ip_address"><?= __('hosting.address') ?></label>
                                <input type="text" class="form-control" id="ip_address" name="ip_address"
                                    value="<?= isset($hostingPlan) ? htmlspecialchars($hostingPlan['ip_address']) : '' ?>">
                            </div>
                        </div>



                        <button type="submit" class="btn btn-primary">
                            <?= isset($hostingPlan) ? __('hosting.update_client') : __('hosting.add_client') ?>
                        </button>
                        <a onclick="window.history.back();" class="btn btn-secondary">
                            <?= __('common.cancel') ?>
                        </a>
                    </form>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>