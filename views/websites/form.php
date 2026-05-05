<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>

<?php
// Initialize default website array with all required keys
$website = $website ?? [];
// Change this part:
$defaultWebsite = [
    'id' => null,
    'domain' => '',
    'service_type' => 'hosting_web',
    'name' => '',
    'hosting_id' => null,
    'registrante_import' => '',
    'expiry_date' => '',
    'status' => '',
    'vendita' => '',
    'assigned_email' => '',
    'proprietario' => '',
    'dns' => '',
    'cpanel' => '',
    'epanel' => '',
    'notes' => '',
    'manutenzione' => '',
    'dynamic_status' => 'attivo'
];
$website = array_merge($defaultWebsite, $website);
$hostingPlans = $hostingPlans ?? [];
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

                        <?php
                        // Pre-fill domain / service_type from query string (when clicking "Add" from the index)
                        $prefillDomain = htmlspecialchars($_GET['domain'] ?? ($website['domain'] ?? ''));
                        $prefillSvcType = $_GET['service_type'] ?? ($website['service_type'] ?? 'hosting_web');
                        $currentHaId = $website['hosting_account_id'] ?? '';
                        $currentProviderId = $website['provider_id'] ?? '';
                        ?>

                        <!-- Row 1: Client + Domain + Service Type -->
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label><?= __('common.add_client') ?></label>
                                <select class="form-control" name="hosting_id">
                                    <option value=""><?= __('common.no_client_selected') ?></option>
                                    <?php foreach ($hostingPlans as $plan): ?>
                                        <option value="<?= $plan['id'] ?>"
                                            <?= (isset($website['hosting_id']) && $website['hosting_id'] == $plan['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($plan['name'] ?? ('Client #'.(int)$plan['id'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label><?= __('websites.domain_name') ?></label>
                                <input type="text" class="form-control" name="domain"
                                    value="<?= $prefillDomain ?>"
                                    placeholder="e.g. example.com" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label><?= __('websites.service_type') ?></label>
                                <select class="form-control" id="svc_service_type" name="service_type" onchange="svcTypeChanged(this.value)">
                                    <option value="domain"       <?= $prefillSvcType === 'domain'       ? 'selected' : '' ?>><?= __('websites.type_domain') ?></option>
                                    <option value="hosting_web"  <?= $prefillSvcType === 'hosting_web'  ? 'selected' : '' ?>><?= __('websites.type_hosting_web') ?></option>
                                    <option value="hosting_mail" <?= $prefillSvcType === 'hosting_mail' ? 'selected' : '' ?>><?= __('websites.type_hosting_mail') ?></option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 2: Service-specific provider/account (shown/hidden by JS) -->

                        <!-- Domain Registration: registrar provider -->
                        <div id="svc_section_domain" class="row" style="display:none;">
                            <div class="col-12 mb-2">
                                <div class="alert alert-secondary py-2 mb-0" style="font-size:.85rem;">
                                    <i class="fas fa-at mr-1"></i><strong>Domain Registration</strong>
                                    &mdash; seleziona il registrante di questo dominio.
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Registrante (Provider)</label>
                                <select class="form-control" name="provider_id" id="svc_dom_provider">
                                    <option value="">&mdash; Seleziona registrante &mdash;</option>
                                    <?php foreach ($registrars ?? [] as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= $currentProviderId == $r['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($r['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Solo registranti attivi. <a href="index.php?action=providers&do=create">Aggiungi registrante</a>.</small>
                            </div>
                        </div>

                        <!-- Web Hosting: hosting account (cPanel/WHM) -->
                        <div id="svc_section_hosting_web" class="row" style="display:none;">
                            <div class="col-12 mb-2">
                                <div class="alert alert-info py-2 mb-0" style="font-size:.85rem;">
                                    <i class="fas fa-server mr-1"></i><strong>Web Hosting</strong>
                                    &mdash; link this domain to a cPanel/WHM hosting account.
                                </div>
                            </div>
                            <div class="form-group col-md-8">
                                <label>Hosting Account (cPanel / WHM)</label>
                                <select class="form-control" name="hosting_account_id" id="svc_web_ha">
                                    <option value="">— Select hosting account —</option>
                                    <?php foreach ($hostingAccounts ?? [] as $ha): ?>
                                        <option value="<?= $ha['id'] ?>" <?= $currentHaId == $ha['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ha['cpanel_username']) ?>
                                            <?= $ha['provider_name'] ? ' @ '.htmlspecialchars($ha['provider_name']) : '' ?>
                                            <?= $ha['client_name']   ? ' ('.htmlspecialchars($ha['client_name']).')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Only active hosting accounts are listed. <a href="index.php?action=hosting_accounts&do=create">Add account</a>.</small>
                            </div>
                        </div>

                        <!-- Mail Hosting: mail provider -->
                        <div id="svc_section_hosting_mail" class="row" style="display:none;">
                            <div class="col-12 mb-2">
                                <div class="alert alert-primary py-2 mb-0" style="font-size:.85rem;">
                                    <i class="fas fa-envelope mr-1"></i><strong>Mail Hosting</strong>
                                    &mdash; select the mail provider for this domain (cPanel mail, Google Workspace, Microsoft 365, etc.).
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Mail Provider</label>
                                <select class="form-control" name="provider_id" id="svc_mail_provider">
                                    <option value="">— Select mail provider —</option>
                                    <?php foreach ($mailProviders ?? [] as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $currentProviderId == $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Only active email providers are listed. <a href="index.php?action=providers&do=create">Add provider</a>.</small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="form-group col-md-4">
                                <label><?= __('common.expiry') ?></label>
                                <input type="text" class="form-control" name="expiry_date"
                                    value="<?= htmlspecialchars(sm_form_date_value($website['expiry_date'] ?? '')) ?>"
                                    placeholder="dd-mm-yyyy" inputmode="numeric">
                            </div>
                            <div class="form-group col-md-4">
                                <label><?= __('common.manutenzione_cost') ?></label>
                                <input type="text" class="form-control" name="manutenzione"
                                    value="<?= htmlspecialchars($website['manutenzione'] ?? '') ?>"
                                    placeholder="es. 50.00">
                            </div>
                            <div class="form-group col-md-4">
                                <label><?= __('common.notes') ?></label>
                                <textarea class="form-control" name="notes" rows="2"
                                    placeholder="<?= __('common.leave_empty') ?>"><?= htmlspecialchars($website['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i>
                                <?= $website['id'] ? __('common.update_service') ?? 'Update' : __('common.add_service') ?? 'Save' ?>
                            </button>
                            <a onclick="window.history.back();" class="btn btn-secondary">
                                <?= __('common.cancel') ?>
                            </a>
                        </div>
                    </form>

                    <script>
                    function svcTypeChanged(val) {
                        document.getElementById('svc_section_domain').style.display      = (val === 'domain')       ? '' : 'none';
                        document.getElementById('svc_section_hosting_web').style.display  = (val === 'hosting_web')  ? '' : 'none';
                        document.getElementById('svc_section_hosting_mail').style.display = (val === 'hosting_mail') ? '' : 'none';
                        // reset non-active provider selects so they don't submit stale values
                        if (val !== 'domain')       document.getElementById('svc_dom_provider').value  = '';
                        if (val !== 'hosting_web')  document.getElementById('svc_web_ha').value        = '';
                        if (val !== 'hosting_mail') document.getElementById('svc_mail_provider').value = '';
                    }
                    document.addEventListener('DOMContentLoaded', function() {
                        svcTypeChanged(document.getElementById('svc_service_type').value);
                    });
                    </script>
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