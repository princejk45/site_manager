<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<?php
// Initialize default website array with all required keys
$website = $website ?? [];
// Change this part:
$defaultWebsite = [
    'id' => null,
    'domain' => '',
    'name' => '',
    'hosting_id' => null,
    'email_server' => '',
    'expiry_date' => '',
    'status' => '',
    'vendita' => '',
    'assigned_email' => '',
    'proprietario' => '',
    'dns' => '',
    'cpanel' => '',
    'epanel' => '',
    'notes' => '',
    'dynamic_status' => 'attivo'
];
$website = array_merge($defaultWebsite, $website);
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h6 class="card-title mb-0">
                        <?php if ($website['id']) : ?>
                            <b><?= htmlspecialchars($website['domain']) ?> </b>
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
                        <?php else : ?>
                            <?= __('websites.manage_services') ?>
                        <?php endif; ?>
                    </h6>

                </div>
                <div class="col-sm-6 text-right">
                    <?php if ($website['id'] && in_array($website['dynamic_status'], ['scade_presto', 'scaduto'])): ?>

                        <form method="POST" action="index.php?action=websites&do=renew&id=<?= $website['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                            class="d-inline">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-sync-alt"></i> <?= __('common.renew') ?>
                            </button>
                        </form>
                    <?php endif; ?>
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

            <div class="card">

                <div class="card-body">
                    <form method="POST"
                        action="index.php?action=websites&do=<?= $website['id'] ? 'edit&id=' . $website['id'] : 'create' ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="hosting_id"><?= __('common.add_client') ?></label>
                                <select class="form-control" id="hosting_id" name="hosting_id">
                                    <option value=""><?= __('common.no_client_selected') ?></option>
                                    <?php foreach ($hostingPlans as $plan): ?>
                                        <option value="<?= $plan['id'] ?>"
                                            <?= (isset($website['hosting_id']) && $website['hosting_id'] == $plan['id']) ? 'selected' : '' ?>
                                            data-email="<?= htmlspecialchars($plan['email_address'] ?? '') ?>">
                                            <?= htmlspecialchars($plan['server_name']) ?>
                                            (<?= htmlspecialchars($plan['provider'] ?? 'N/A') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="assigned_email"><?= __('common.email') ?></label>
                                <input type="email" class="form-control" id="assigned_email" name="assigned_email"
                                    value="<?= htmlspecialchars($website['assigned_email'] ?? '') ?>" readonly>

                            </div>
                        </div>

                        <div class="row">

                            <div class="form-group col-md-6">
                                <label for="domain"><?= __('websites.domain_name') ?></label>
                                <input type="text" class="form-control" id="domain" name="domain"
                                    value="<?= htmlspecialchars($website['domain']) ?>"
                                    placeholder="e.g. example.com" required>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="name"><?= __('common.type') ?></label>
                                <input type="text" class="form-control" id="name" name="name"
                                    value="<?= htmlspecialchars($website['name']) ?>"
                                    placeholder="e.g. domain, server, email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="proprietario"><?= __('common.proprietario') ?></label>
                                <input type="text" class="form-control" id="proprietario" name="proprietario"
                                    value="<?= htmlspecialchars($website['proprietario'] ?? '') ?>"
                                    placeholder="<?= __('common.proprietario') ?>">
                            </div>

                            <div class="form-group col-md-3">
                                <label for="email_server"><?= __('common.registrant') ?></label>
                                <input type="text" class="form-control" id="email_server" name="email_server"
                                    value="<?= htmlspecialchars($website['email_server']) ?>"
                                    placeholder="<?= __('common.registrant') ?>">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="expiry_date"><?= __('common.expiry') ?></label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date"
                                    value="<?= htmlspecialchars($website['expiry_date']) ?>" required>
                            </div>
                        </div>
                        <div class="row">

                            <div class="form-group col-md-2">
                                <label for="status"><?= __('common.server_cost') ?></label>
                                <input type="text" class="form-control" id="status" name="status"
                                    value="<?= htmlspecialchars($website['status']) ?>"
                                    placeholder="<?= __('common.server_cost_vat') ?>">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="vendita"><?= __('common.selling_price') ?></label>
                                <input type="text" class="form-control" id="vendita" name="vendita"
                                    value="<?= htmlspecialchars($website['vendita']) ?>"
                                    placeholder="<?= __('common.selling_price') ?>">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="dns"><?= __('common.dns_a') ?></label>
                                <input type="text" class="form-control" id="dns" name="dns"
                                    value="<?= htmlspecialchars($website['dns'] ?? '') ?>" placeholder="<?= __('common.dns_a') ?>">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="cpanel"><?= __('common.cpanel_username') ?></label>
                                <input type="text" class="form-control" id="cpanel" name="cpanel"
                                    value="<?= htmlspecialchars($website['cpanel'] ?? '') ?>"
                                    placeholder="<?= __('common.cpanel_username') ?>">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="epanel"><?= __('common.panel_email') ?></label>
                                <input type="text" class="form-control" id="epanel" name="epanel"
                                    value="<?= htmlspecialchars($website['epanel'] ?? '') ?>" placeholder="<?= __('common.panel_email') ?>">
                            </div>


                        </div>

                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="notes"><?= __('common.bug') ?> <?= __('common.add_report') ?> </label>
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
                                <?= $website['id'] ? __('common.update_service') : __('common.add_service') ?>
                            </button>
                            <a onclick="window.history.back();" class="btn btn-secondary">
                                <?= __('common.cancel') ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </section>
</div>
<script>
    document.getElementById('hosting_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const emailField = document.getElementById('assigned_email');

        // Get email from data-attribute first (faster, no API call needed)
        if (selectedOption && selectedOption.dataset.email) {
            emailField.value = selectedOption.dataset.email;
        } else {
            emailField.value = '';
        }

        // Optional: Still make the API call to verify
        const hostingId = this.value;
        if (hostingId) {
            fetch(`index.php?action=websites&do=getHostingEmail&id=${hostingId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.email && data.email !== emailField.value) {
                        emailField.value = data.email;
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        const hostingSelect = document.getElementById('hosting_id');
        if (hostingSelect.value) {
            hostingSelect.dispatchEvent(new Event('change'));
        }

        // Fallback: Check if email exists but field is empty
        const emailField = document.getElementById('assigned_email');
        if (!emailField.value && hostingSelect.value) {
            const selectedOption = hostingSelect.options[hostingSelect.selectedIndex];
            if (selectedOption && selectedOption.dataset.email) {
                emailField.value = selectedOption.dataset.email;
            }
        }
    });
</script>
<?php include APP_PATH . '/includes/footer.php'; ?>