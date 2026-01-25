<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h5><?= __('websites.manage_services') ?>: <b><?= htmlspecialchars($website['domain']) ?></b> </h5>
                </div>
                <div class="col-sm-6 text-right">
                    <div class="btn-group">
                        <a href="index.php?action=email&do=expiry&id=<?= $website['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                            class="btn btn-success confirmable" data-type="email"
                            data-name="<?= htmlspecialchars($website['domain']) ?>">
                            <i class="fas fa-envelope"></i> <?= __('common.send_expiry_email') ?>
                        </a>
                        <a href="index.php?action=email&do=status&id=<?= $website['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                            class="btn btn-danger confirmable ml-2" data-type="email"
                            data-name="<?= htmlspecialchars($website['domain']) ?>">
                            <i class="fas fa-bell"></i> <?= __('common.send_status_email') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Confirmation Modal -->
            <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-dark">
                            <h5 class="modal-title"><?= __('websites.confirm_action') ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="<?= __('common.close') ?>">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body" id="confirmationMessage"><?= __('websites.sure') ?></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-dismiss="modal"><?= __('websites.cancel') ?></button>
                            <button type="button" class="btn btn-success" id="confirmActionBtn"><?= __('websites.confirm') ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success"><?= $_SESSION['message'] ?></div>
                        <?php unset($_SESSION['message']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?= __('common.modify_details') ?>
                                <?php
                                $statusMap = [
                                    'attivo' => 'success',
                                    'scade_presto' => 'warning',
                                    'scaduto' => 'danger'
                                ];
                                $statusText = [
                                    'attivo' => __('common.active'),
                                    'scade_presto' => __('common.expiring_soon'),
                                    'scaduto' => __('common.expired')
                                ];
                                $badgeClass = $statusMap[$website['dynamic_status']] ?? 'secondary';
                                ?>
                                <span class="badge badge-<?= $badgeClass ?>">
                                    <?= $statusText[$website['dynamic_status']] ?? ucwords(str_replace('_', ' ', $website['dynamic_status'])) ?>
                                </span>
                            </h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th><?= __('websites.domain_name') ?></th>
                                    <td><?= htmlspecialchars($website['domain']) ?></td>
                                </tr>
                                <tr>
                                    <th style="width: 30%"><?= __('common.add_client') ?></th>
                                    <td><?= htmlspecialchars($website['hosting_server'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('common.type') ?></th>
                                    <td><?= htmlspecialchars($website['name'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('common.registrant') ?></th>
                                    <td><?= htmlspecialchars($website['email_server']) ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('common.expiry_date') ?></th>
                                    <td><?= htmlspecialchars($website['expiry_date']) ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('hosting.server_cost') ?></th>
                                    <td><?= htmlspecialchars($website['status']) ?></td>
                                </tr>

                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6><?= __('common.modify_details') ?></h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th style="width: 40%"><?= __('common.email') ?></th>
                                    <td><?= htmlspecialchars($website['assigned_email'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('common.proprietario') ?></th>
                                    <td><?= htmlspecialchars($website['proprietario'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('common.dns_a') ?></th>
                                    <td><?= htmlspecialchars($website['dns'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('common.cpanel_username') ?></th>
                                    <td><?= htmlspecialchars($website['cpanel'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('common.panel_email') ?></th>
                                    <td><?= htmlspecialchars($website['epanel'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('common.selling_price') ?></th>
                                    <td><?= htmlspecialchars($website['vendita']) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6><?= __('common.bug') ?></h6>
                            <div class="card">
                                <div class="card-body">
                                    <p><?= !empty($website['notes']) ? nl2br(htmlspecialchars($website['notes'])) : __('common.leave_empty') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><?= __('common.notes') ?></h6>
                            <div class="card">
                                <div class="card-body">
                                    <p><?= !empty($website['remark']) ? nl2br(htmlspecialchars($website['remark'])) : __('common.enter_notes') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">

                            <div>
                                <a href="index.php?action=websites&do=edit&id=<?= $website['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                    class="btn btn-primary">
                                    <i class="fas fa-edit"></i> <?= __('common.edit') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let pendingAction = null;

        // Handle confirmable actions (email)
        document.querySelectorAll('.confirmable').forEach(el => {
            if (el.tagName === 'A') {
                // For email links
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    const type = this.dataset.type;
                    const name = this.dataset.name;
                    const action = this.href.includes('expiry') ? '<?= __('common.send_expiry_email') ?>' : '<?= __('common.send_status_email') ?>';
                    const message =
                        `<?= __('websites.sure') ?> ${action} <?= __('websites.domain_name') ?> <strong>${name}</strong>?`;
                    document.getElementById('confirmationMessage').innerHTML = message;
                    $('#confirmationModal').modal('show');

                    pendingAction = () => {
                        window.location.href = this.href;
                    };
                });
            }
        });

        // Handle confirmation button click
        document.getElementById('confirmActionBtn').addEventListener('click', function() {
            if (pendingAction) {
                $('#confirmationModal').modal('hide');
                setTimeout(pendingAction, 300);
            }
        });
    });
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>