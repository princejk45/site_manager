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
                        <?php if ($hasWordPressConfig ?? false): ?>
                            <button type="button" class="btn btn-info" id="openDiagnosticsBtn" data-website-id="<?= $website['id'] ?>">
                                <i class="fas fa-stethoscope"></i> <?= __('common.diagnostics') ?? 'Diagnostics' ?>
                            </button>
                        <?php endif; ?>
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
                                    <p><?= !empty($website['notes']) ? nl2br(htmlspecialchars($website['notes'])) : '-' ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><?= __('common.notes') ?></h6>
                            <div class="card">
                                <div class="card-body">
                                    <p><?= !empty($website['remark']) ? nl2br(htmlspecialchars($website['remark'])) : '-' ?>
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

<!-- WordPress Diagnostics Modal -->
<?php if ($hasWordPressConfig ?? false): ?>
<div class="modal fade" id="diagnosticsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-stethoscope mr-2"></i>WordPress Diagnostics</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="diagnosticsModalBody">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x text-info"></i>
                    <p class="mt-3">Loading diagnostics...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="refreshDiagnosticsBtn">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const openDiagnosticsBtn = document.getElementById('openDiagnosticsBtn');
        const refreshDiagnosticsBtn = document.getElementById('refreshDiagnosticsBtn');
        
        if (openDiagnosticsBtn) {
            openDiagnosticsBtn.addEventListener('click', function() {
                fetchDiagnostics();
                $('#diagnosticsModal').modal('show');
            });
        }
        
        if (refreshDiagnosticsBtn) {
            refreshDiagnosticsBtn.addEventListener('click', function() {
                fetchDiagnostics();
            });
        }
        
        function fetchDiagnostics() {
            const websiteId = openDiagnosticsBtn?.dataset.websiteId || <?= $website['id'] ?>;
            const modalBody = document.getElementById('diagnosticsModalBody');
            
            // Show loading
            modalBody.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x text-info"></i><p class="mt-3">Loading diagnostics...</p></div>';
            
            fetch(`index.php?action=websites&do=fetch_diagnostics&id=${websiteId}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    displayDiagnostics(result.data);
                } else {
                    showError(result.error || 'Failed to fetch diagnostics');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Network error: ' + error.message);
            });
        }
        
        function displayDiagnostics(data) {
            const modalBody = document.getElementById('diagnosticsModalBody');
            
            const statusBadgeClass = {
                'healthy': 'success',
                'degraded': 'warning',
                'critical': 'danger',
                'unknown': 'secondary'
            };
            
            const healthStatus = data.health?.status || 'unknown';
            const healthScore = data.health?.score || 0;
            const badgeClass = statusBadgeClass[healthStatus] || 'secondary';
            
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Site Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr><th style="width: 50%">Site Name:</th><td>${escapeHtml(data.site_name || '')}</td></tr>
                            <tr><th>Site URL:</th><td><a href="${escapeHtml(data.site_url)}" target="_blank">${escapeHtml(data.site_url)}</a></td></tr>
                            <tr><th>WordPress:</th><td>${escapeHtml(data.wordpress_version || 'Unknown')}</td></tr>
                            <tr><th>PHP:</th><td>${escapeHtml(data.php_version || 'Unknown')}</td></tr>
                            <tr><th>MySQL:</th><td>${escapeHtml(data.mysql_version || 'Unknown')}</td></tr>
                            <tr><th>Theme:</th><td>${escapeHtml(data.theme_name || 'Unknown')}</td></tr>
                            <tr><th>Memory Limit:</th><td>${escapeHtml(data.memory_limit || 'Unknown')}</td></tr>
                            <tr><th>Debug Mode:</th><td><span class="badge badge-${data.debug_mode ? 'danger' : 'success'}">${data.debug_mode ? 'Enabled' : 'Disabled'}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Health Status</h6>
                        <div class="text-center mb-3">
                            <div class="mb-2">
                                <span class="badge badge-${badgeClass}" style="font-size: 16px; padding: 10px 20px;">
                                    ${healthStatus.toUpperCase()}
                                </span>
                            </div>
                            <div style="font-size: 36px; font-weight: bold; color: #007bff;">${healthScore}/100</div>
                            <small class="text-muted">Health Score</small>
                        </div>
                        <h6 class="mt-4">Wordfence</h6>
                        <p><span class="badge badge-${data.wordfence?.installed ? 'success' : 'warning'}">${data.wordfence?.installed ? 'Installed' : 'Not Installed'}</span></p>
                        <h6 class="mt-4">Active Plugins</h6>
                        <p>${data.active_plugins?.length || 0} plugin${(data.active_plugins?.length || 0) !== 1 ? 's' : ''} active</p>
                    </div>
                </div>
                
                <hr>
                
                <h6>Security Check</h6>
                <div class="row">
            `;
            
            const securityItems = [
                { key: 'wp_config_writable', label: 'wp-config.php Writable', critical: true },
                { key: 'xmlrpc_enabled', label: 'XML-RPC Enabled', critical: false },
                { key: 'debug_mode', label: 'Debug Mode', critical: false },
                { key: 'directory_listing', label: 'Directory Listing', critical: false },
                { key: 'default_admin_user', label: 'Default Admin User', critical: false }
            ];
            
            securityItems.forEach(item => {
                const isIssue = data.security?.[item.key] || false;
                const badgeClass = isIssue ? (item.critical ? 'danger' : 'warning') : 'success';
                html += `
                    <div class="col-md-6 mb-2">
                        <span class="badge badge-${badgeClass}">${item.label}: ${isIssue ? 'YES' : 'NO'}</span>
                    </div>
                `;
            });
            
            html += `
                </div>
                
                <hr>
                
                <h6>Top Plugins</h6>
                <ul class="list-group" style="max-height: 200px; overflow-y: auto;">
            `;
            
            if (data.active_plugins && data.active_plugins.length > 0) {
                data.active_plugins.slice(0, 10).forEach(plugin => {
                    html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        ${escapeHtml(plugin.name || '')}
                        <span class="badge badge-secondary">${escapeHtml(plugin.version || '')}</span>
                    </li>`;
                });
            } else {
                html += '<li class="list-group-item">No plugins active</li>';
            }
            
            html += `
                </ul>
                <small class="text-muted d-block mt-2">Last updated: ${new Date().toLocaleString()}</small>
            `;
            
            modalBody.innerHTML = html;
        }
        
        function showError(message) {
            const modalBody = document.getElementById('diagnosticsModalBody');
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    ${escapeHtml(message)}
                </div>
            `;
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });
</script>
<?php endif; ?>

<?php include APP_PATH . '/includes/footer.php'; ?>