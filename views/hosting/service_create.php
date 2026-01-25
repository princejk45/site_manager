<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= __('hosting.add_service') ?> <?= htmlspecialchars($hostingPlan['server_name']) ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php?action=hosting&do=services&id=<?= $hostingPlan['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                        class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('common.back') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST"
                        action="index.php?action=hosting&do=service_create&id=<?= $hostingPlan['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                        <input type="hidden" name="hosting_id" value="<?= $hostingPlan['id'] ?>">
                        <input type="hidden" name="assigned_email"
                            value="<?= htmlspecialchars($hostingPlan['email_address'] ?? '') ?>">

                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="domain"><?= __('websites.manage_services') ?></label>
                                <input type="text" class="form-control" id="domain" name="domain"
                                    value="<?= htmlspecialchars($website['domain'] ?? '') ?>"
                                    placeholder="e.g. fullmidia.it" required>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="name"><?= __('common.type') ?></label>
                                <input type="text" class="form-control" id="name" name="name"
                                    value="<?= htmlspecialchars($website['name'] ?? '') ?>"
                                    placeholder="e.g. domain, server, email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="proprietario"><?= __('common.owner') ?></label>
                                <input type="text" class="form-control" id="proprietario" name="proprietario"
                                    value="<?= htmlspecialchars($website['proprietario'] ?? '') ?>"
                                    placeholder="e.g. Company Name">
                            </div>

                            <div class="form-group col-md-3">
                                <label for="email_server"><?= __('common.registrant') ?></label>
                                <input type="text" class="form-control" id="email_server" name="email_server"
                                    value="<?= htmlspecialchars($website['email_server'] ?? '') ?>"
                                    placeholder="e.g. Serverplan, Vhosting">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="expiry_date"><?= __('common.expiry_date') ?></label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date"
                                    value="<?= htmlspecialchars($website['expiry_date'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-2">
                                <label for="status"><?= __('hosting.server_cost') ?></label>
                                <input type="text" class="form-control" id="status" name="status"
                                    value="<?= htmlspecialchars($website['status'] ?? '') ?>"
                                    placeholder="ex. 50.00">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="vendita"><?= __('hosting.selling_price') ?></label>
                                <input type="text" class="form-control" id="vendita" name="vendita"
                                    value="<?= htmlspecialchars($website['vendita'] ?? '') ?>"
                                    placeholder="ex. 75.00">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="dns"><?= __('hosting.dns_record') ?></label>
                                <input type="text" class="form-control" id="dns" name="dns"
                                    value="<?= htmlspecialchars($website['dns'] ?? '') ?>" placeholder="IP Address">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="cpanel"><?= __('hosting.cpanel_username') ?></label>
                                <input type="text" class="form-control" id="cpanel" name="cpanel"
                                    value="<?= htmlspecialchars($website['cpanel'] ?? '') ?>"
                                    placeholder="Username">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="epanel"><?= __('hosting.panel_email') ?></label>
                                <input type="text" class="form-control" id="epanel" name="epanel"
                                    value="<?= htmlspecialchars($website['epanel'] ?? '') ?>" placeholder="Email">
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="notes"><?= __('common.bug') ?></label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"
                                    placeholder="<?= __('common.leave_empty') ?>"><?= htmlspecialchars($website['notes'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="remark"><?= __('common.notes') ?></label>
                                <textarea class="form-control" id="remark" name="remark" rows="2"
                                    placeholder="<?= __('common.enter_notes') ?>"><?= htmlspecialchars($website['remark'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <?= __('common.add') ?> <?= __('hosting.create_service') ?>
                            </button>
                            <a href="index.php?action=hosting&do=services&id=<?= $hostingPlan['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                class="btn btn-secondary">
                                <?= __('common.cancel') ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>