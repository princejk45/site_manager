<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= __('settings.advanced_title') ?></h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" style="white-space: pre-wrap; margin-bottom: 1rem;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <?= htmlspecialchars($_SESSION['message']) ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" style="margin-bottom: 1rem;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['google_sync_result'])): ?>
                <div class="mt-4 p-3 border rounded <?= !empty($_SESSION['google_sync_result']['errors']) ? 'alert-danger' : 'alert-primary' ?>">
                    <h5><?= __('settings.google_sync_results') ?></h5>
                    <ul>
                        <li><?= __('settings.exported') ?>: <?= $_SESSION['google_sync_result']['exported'] ?? 0 ?></li>
                        <li><?= __('settings.imported') ?>: <?= $_SESSION['google_sync_result']['imported'] ?? 0 ?></li>
                        <li><?= __('settings.updated') ?>: <?= $_SESSION['google_sync_result']['updated'] ?? 0 ?></li>
                        <li><?= __('settings.conflicts_resolved') ?>: <?= $_SESSION['google_sync_result']['conflicts'] ?? 0 ?></li>
                        <?php if (!empty($_SESSION['google_sync_result']['errors']) && is_array($_SESSION['google_sync_result']['errors'])): ?>
                            <li class="text-danger"><strong><?= __('common.error') ?>:</strong>
                                <ul>
                                    <?php foreach ($_SESSION['google_sync_result']['errors'] as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php unset($_SESSION['google_sync_result']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['comparison_result'])): ?>
                <?php $comparison = $_SESSION['comparison_result']; ?>
                <div class="mt-4">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="m-0"><i class="fas fa-balance-scale"></i> <?= __('settings.comparison_results') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h6><?= __('settings.database_total') ?></h6>
                                        <p class="display-4 text-primary"><?= $comparison['summary']['db_total'] ?></p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h6><?= __('settings.google_sheets_total') ?></h6>
                                        <p class="display-4 text-success"><?= $comparison['summary']['google_total'] ?></p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h6><?= __('settings.matching_records') ?></h6>
                                        <p class="display-4 text-info"><?= $comparison['summary']['matches'] ?></p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h6><?= __('common.conflicts') ?></h6>
                                        <p class="display-4 text-warning"><?= $comparison['summary']['conflicts'] ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Comparison Table -->
                    <?php if (!empty($comparison['different_values']) || !empty($comparison['only_in_db']) || !empty($comparison['only_in_google'])): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="m-0"><?= __('settings.detailed_disparities') ?></h5>
                                    <small class="text-muted">
                                        <?php 
                                        $fieldDiffCount = 0;
                                        foreach ($comparison['different_values'] as $conflict) {
                                            $fieldDiffCount += count($conflict['differences']);
                                        }
                                        $totalDisparity = $fieldDiffCount + count($comparison['only_in_db']) + count($comparison['only_in_google']);
                                        ?>
                                        <?= $comparison['summary']['conflicts'] ?> <?= __('common.conflicts') ?> (<?= $fieldDiffCount ?> <?= $fieldDiffCount === 1 ? __('settings.field_difference') : __('settings.field_differences') ?>)
                                    </small>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th style="width: 15%;"><?= __('settings.domain') ?></th>
                                                <th style="width: 15%;"><?= __('settings.field') ?></th>
                                                <th style="width: 35%;"><?= __('settings.database_value') ?></th>
                                                <th style="width: 35%;"><?= __('settings.google_sheets_value') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalRows = 0;
                                            foreach ($comparison['different_values'] as $conflict):
                                                foreach ($conflict['differences'] as $field => $diff):
                                                    $totalRows++;
                                                    ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($conflict['domain']) ?></strong></td>
                                                        <td><span class="badge badge-warning"><?= htmlspecialchars($field) ?></span></td>
                                                        <td>
                                                            <code class="bg-light p-2 d-block" style="max-height: 60px; overflow: auto;">
                                                                <?= htmlspecialchars($diff['db_value']) ?>
                                                            </code>
                                                        </td>
                                                        <td>
                                                            <code class="bg-light p-2 d-block" style="max-height: 60px; overflow: auto;">
                                                                <?= htmlspecialchars($diff['google_value']) ?>
                                                            </code>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                endforeach;
                                            endforeach;
                                            
                                            // Add rows for items only in database
                                            foreach ($comparison['only_in_db'] as $item):
                                                $totalRows++;
                                                ?>
                                                <tr class="table-warning">
                                                    <td><strong><?= htmlspecialchars($item['domain']) ?></strong></td>
                                                    <td><span class="badge badge-info"><?= __('settings.only_in_db') ?></span></td>
                                                    <td><em><?= __('settings.exists_in_database') ?></em></td>
                                                    <td><em class="text-danger"><?= __('settings.not_in_google_sheets') ?></em></td>
                                                </tr>
                                                <?php
                                            endforeach;
                                            
                                            // Add rows for items only in Google Sheets
                                            foreach ($comparison['only_in_google'] as $item):
                                                $totalRows++;
                                                ?>
                                                <tr class="table-info">
                                                    <td><strong><?= htmlspecialchars($item['domain'] ?? 'N/A') ?></strong></td>
                                                    <td><span class="badge badge-success"><?= __('settings.only_in_google') ?></span></td>
                                                    <td><em class="text-danger"><?= __('settings.not_in_database') ?></em></td>
                                                    <td><em><?= __('settings.exists_in_google_sheets') ?></em></td>
                                                </tr>
                                                <?php
                                            endforeach;
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ($totalRows === 0): ?>
                                    <div class="p-3 text-center text-muted">
                                        <i class="fas fa-check-circle"></i> <?= __('settings.all_records_match') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-3 text-right">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#mergeModal">
                                <i class="fas fa-sync"></i> <?= __('settings.proceed_to_merge') ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php unset($_SESSION['comparison_result']); ?>
            <?php endif; ?>

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
                <div class="card-header">
                    <h3 class="card-title text-primary">
                        <i class="fas fa-clock text-primary mr-1"></i>
                        <b><?= __('settings.cron_management') ?></b>
                    </h3>
                </div>
                <form method="post" action="index.php?action=settings&do=advanced&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                    <div class="card-body">
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="cronActive" name="cron_active"
                                    value="1" <?= $cronStatus ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="cronActive"><?= __('settings.activate_cron') ?></label>
                            </div>
                            <small class="form-text text-muted"><?= __('settings.cron_description') ?></small>
                        </div>

                        <?php if ($lastRun): ?>
                            <div class="form-group">
                                <label><?= __('settings.last_run') ?></label>
                                <p><?= htmlspecialchars($lastRun) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary"><?= __('settings.save_settings') ?></button>
                        <button type="button" id="cronDiagnosticsBtn" class="btn btn-info"><?= __('settings.cron_diagnostics_button') ?></button>
                    </div>
                </form>
            </div>

            <!-- Cron Diagnostics Modal -->
            <div class="modal fade" id="cronDiagnosticsModal" tabindex="-1" role="dialog" aria-labelledby="cronDiagnosticsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="cronDiagnosticsModalLabel"><?= __('settings.cron_diagnostics') ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div id="cronDiagnosticsContent">
                                <div class="text-center">
                                    <div class="spinner-border" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('common.close') ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                document.getElementById('cronDiagnosticsBtn').addEventListener('click', function() {
                    const btn = this;
                    const originalText = btn.innerHTML;
                    
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <?= __('settings.test_in_progress') ?>';

                    fetch('index.php?action=settings&do=cron_diagnostics', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;

                        if (!data.success) {
                            document.getElementById('cronDiagnosticsContent').innerHTML = 
                                '<div class="alert alert-danger"><?= __('settings.diagnostic_error') ?><br>' + 
                                (data.error || '') + '</div>';
                        } else {
                            let html = '<div class="row">';
                            
                            // Cron Status
                            html += '<div class="col-md-12 mb-3">';
                            html += '<h6><?= __('settings.cron_management') ?></h6>';
                            html += '<p><strong><?= __('common.status') ?>:</strong> ';
                            html += data.cron_enabled ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>';
                            html += '</p></div>';

                            // Next Execution
                            html += '<div class="col-md-12 mb-3">';
                            if (data.is_localhost) {
                                html += '<p><strong><?= __('settings.next_execution') ?>:</strong><br>';
                                html += '<span class="text-info"><?= __('settings.local_system') ?> - <?= __('settings.system_scheduler_configured') ?></span></p>';
                            } else if (data.next_execution) {
                                html += '<p><strong><?= __('settings.next_execution') ?>:</strong> ' + data.next_execution;
                                if (data.cron_expression) {
                                    html += '<br><small class="text-muted"><?= __('settings.cron_expression') ?>: ' + data.cron_expression + '</small>';
                                }
                                html += '</p>';
                            } else {
                                html += '<p><strong><?= __('settings.next_execution') ?>:</strong><br>';
                                html += '<span class="text-warning">' + (data.next_execution_message || '') + '</span></p>';
                            }
                            html += '</div>';

                            // Last Execution
                            html += '<div class="col-md-12 mb-3">';
                            html += '<p><strong><?= __('settings.last_execution') ?>:</strong> ';
                            html += data.last_run ? data.last_run : '<?= __('settings.never_run') ?>';
                            html += '</p></div>';

                            // Websites Summary
                            html += '<div class="col-md-12 mb-3"><hr></div>';
                            html += '<div class="col-md-6">';
                            html += '<p><strong><?= __('settings.total_websites') ?>:</strong> <span class="badge badge-primary">' + data.total_websites + '</span></p>';
                            html += '</div>';
                            html += '<div class="col-md-6">';
                            html += '<p><strong><?= __('settings.total_emails_to_send') ?>:</strong> <span class="badge badge-warning">' + data.total_emails + '</span></p>';
                            html += '</div>';

                            // Websites by Status
                            html += '<div class="col-md-3 text-center">';
                            html += '<p><small><?= __('settings.websites_expired') ?></small><br><span class="badge badge-danger">' + data.expired_count + '</span></p>';
                            html += '</div>';
                            html += '<div class="col-md-3 text-center">';
                            html += '<p><small><?= __('settings.websites_expiring_1') ?></small><br><span class="badge badge-danger">' + data.expires_1_day_count + '</span></p>';
                            html += '</div>';
                            html += '<div class="col-md-3 text-center">';
                            html += '<p><small><?= __('settings.websites_expiring_15') ?></small><br><span class="badge badge-warning">' + data.expires_15_days_count + '</span></p>';
                            html += '</div>';
                            html += '<div class="col-md-3 text-center">';
                            html += '<p><small><?= __('settings.websites_expiring_30') ?></small><br><span class="badge badge-info">' + data.expires_30_days_count + '</span></p>';
                            html += '</div>';

                            // SMTP Status
                            html += '<div class="col-md-12 mb-3" style="margin-top: 1rem;"><hr></div>';
                            html += '<div class="col-md-12">';
                            html += '<p><strong><?= __('settings.smtp_status') ?>:</strong> ';
                            html += data.smtp_configured ? 
                                '<span class="badge badge-success"><?= __('settings.smtp_configured_yes') ?></span>' : 
                                '<span class="badge badge-danger"><?= __('settings.smtp_configured_no') ?></span>';
                            if (data.smtp_from_email) {
                                html += '<br><small><?= __('settings.sending_from') ?>: ' + data.smtp_from_email + '</small>';
                            }
                            html += '</p></div>';

                            html += '</div>';
                            document.getElementById('cronDiagnosticsContent').innerHTML = html;
                        }

                        $('#cronDiagnosticsModal').modal('show');
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        document.getElementById('cronDiagnosticsContent').innerHTML = 
                            '<div class="alert alert-danger">Error: ' + error + '</div>';
                        $('#cronDiagnosticsModal').modal('show');
                    });
                });
            </script>

            <?php if (isset($_SESSION['google_error'])): ?>
                <div class="alert alert-danger">
                    <strong><?= __('settings.google_error') ?></strong> <?= htmlspecialchars($_SESSION['google_error']) ?>
                    <?php unset($_SESSION['google_error']); ?>
                </div>
            <?php endif; ?>

            <!-- Google Sheets Integration Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title text-success">
                        <i class="fas fa-file-excel text-success mr-1"></i>
                        <b><?= __('settings.google_sheets_integration') ?></b>
                    </h3>
                </div>
                <div class="card-body">
                    <form method="post" action="index.php?action=settings&do=google_sheets&lang=<?= $_SESSION['lang'] ?? 'it' ?>" id="googleSettingsForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="google_sheet_id"><?= __('settings.google_sheet_id') ?></label>
                                    <input type="text" class="form-control" id="google_sheet_id" name="google_sheet_id"
                                        value="<?= htmlspecialchars($googleSheetSettings['sheet_id'] ?? '') ?>"
                                        placeholder="<?= __('settings.google_sheet_id') ?>">
                                    <small class="form-text text-muted"><?= __('settings.google_sheet_id_help') ?></small>
                                </div>

                                <div class="form-group">
                                    <label for="google_sheet_name"><?= __('settings.sheet_name') ?></label>
                                    <input type="text" class="form-control" id="google_sheet_name"
                                        name="google_sheet_name"
                                        value="<?= htmlspecialchars($googleSheetSettings['sheet_name'] ?? '') ?>"
                                        placeholder="<?= __('settings.sheet_name_help') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="google_credentials"><?= __('settings.service_account_credentials') ?></label>
                                    <textarea class="form-control" id="google_credentials" name="google_credentials"
                                        rows="7"
                                        placeholder="<?= __('settings.service_account_placeholder') ?>"><?= htmlspecialchars($googleSheetSettings['credentials'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="google_sync_enabled"
                                    name="google_sync_enabled" value="1"
                                    <?= ($googleSheetSettings['enabled'] ?? false) ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="google_sync_enabled"><?= __('settings.google_sync_enabled') ?></label>
                            </div>
                        </div>

                        <div class="btn-group mt-3">
                            <button type="submit" name="save_google_settings" class="btn btn-primary"><?= __('settings.save_google_settings') ?></button>
                            <button type="button" name="export_to_google" id="exportBtn"
                                class="btn btn-success ml-2"><?= __('settings.export_to_google') ?></button>
                            <button type="button" name="import_from_google" id="importBtn"
                                class="btn btn-info ml-2"><?= __('settings.import_from_google') ?></button>
                            <button type="button" id="compareBtn"
                                class="btn btn-warning ml-2"><i class="fas fa-balance-scale"></i> <?= __('settings.compare_data') ?></button>
                            <a href="index.php?action=settings&do=diagnostic_google_sheets" class="btn btn-secondary ml-2">
                                <i class="fas fa-stethoscope"></i> <?= __('settings.diagnostic_button') ?>
                            </a>
                        </div>
                    </form>

                    <!-- Merge Modal -->
                    <div class="modal fade" id="mergeModal" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title"><?= __('settings.merge_data') ?></h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <p><?= __('settings.select_merge_strategy') ?></p>
                                    <form id="mergeForm" method="post" action="index.php?action=settings&do=merge_google">
                                        <div class="form-group">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="mergeForward" name="merge_strategy" value="forward" class="custom-control-input" checked>
                                                <label class="custom-control-label" for="mergeForward">
                                                    <strong><?= __('settings.merge_forward') ?></strong>
                                                    <small class="d-block text-muted"><?= __('settings.merge_forward_desc') ?></small>
                                                </label>
                                            </div>
                                            <div class="custom-control custom-radio mt-3">
                                                <input type="radio" id="mergeBackward" name="merge_strategy" value="backward" class="custom-control-input">
                                                <label class="custom-control-label" for="mergeBackward">
                                                    <strong><?= __('settings.merge_backward') ?></strong>
                                                    <small class="d-block text-muted"><?= __('settings.merge_backward_desc') ?></small>
                                                </label>
                                            </div>
                                            <div class="custom-control custom-radio mt-3">
                                                <input type="radio" id="mergeBoth" name="merge_strategy" value="together" class="custom-control-input">
                                                <label class="custom-control-label" for="mergeBoth">
                                                    <strong><?= __('settings.merge_both') ?></strong>
                                                    <small class="d-block text-muted"><?= __('settings.merge_both_desc') ?></small>
                                                </label>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('websites.cancel') ?></button>
                                    <button type="button" class="btn btn-primary" id="confirmMergeBtn"><?= __('websites.confirm') ?></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden forms for import/export actions -->
                    <form id="exportForm" method="post" action="index.php?action=settings&do=google_sheets"
                        style="display:none;">
                        <input type="hidden" name="export_to_google" value="1">
                        <input type="hidden" name="google_sheet_id"
                            value="<?= htmlspecialchars($googleSheetSettings['sheet_id'] ?? '') ?>">
                        <input type="hidden" name="google_sheet_name"
                            value="<?= htmlspecialchars($googleSheetSettings['sheet_name'] ?? '') ?>">
                        <input type="hidden" name="google_credentials"
                            value="<?= htmlspecialchars($googleSheetSettings['credentials'] ?? '') ?>">
                        <input type="hidden" name="google_sync_enabled" value="<?= ($googleSheetSettings['enabled'] ?? false) ? '1' : '0' ?>">
                        <input type="hidden" name="save_google_settings" value="0">
                    </form>

                    <form id="importForm" method="post" action="index.php?action=settings&do=google_sheets"
                        style="display:none;">
                        <input type="hidden" name="import_from_google" value="1">
                        <input type="hidden" name="google_sheet_id"
                            value="<?= htmlspecialchars($googleSheetSettings['sheet_id'] ?? '') ?>">
                        <input type="hidden" name="google_sheet_name"
                            value="<?= htmlspecialchars($googleSheetSettings['sheet_name'] ?? '') ?>">
                        <input type="hidden" name="google_credentials"
                            value="<?= htmlspecialchars($googleSheetSettings['credentials'] ?? '') ?>">
                        <input type="hidden" name="google_sync_enabled" value="<?= ($googleSheetSettings['enabled'] ?? false) ? '1' : '0' ?>">
                        <input type="hidden" name="save_google_settings" value="0">
                    </form>
                </div>
            </div>

            <!-- WordPress Configuration & Database Setup Section -->
            <div class="row mt-4" id="migrations">
                <div class="col-md-6">
                    <div class="card card-info">
                        <div class="card-header">
                            <h5 class="m-0"><i class="fas fa-wordpress mr-2"></i>WordPress Integration</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Configure API keys for remote WordPress site diagnostics.</p>
                            <a href="index.php?action=settings&do=wordpress&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-info btn-block">
                                <i class="fas fa-cog mr-2"></i>Configure API Keys
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h5 class="m-0"><i class="fas fa-database mr-2"></i>Database Setup</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Create WordPress integration tables. Creates missing tables only, preserves existing data.</p>
                            <button type="button" class="btn btn-warning btn-block" id="migrateDbBtn">
                                <i class="fas fa-sync-alt mr-2"></i>Run Migrations
                            </button>
                            <div id="migrationResult" class="mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const exportBtn = document.getElementById('exportBtn');
        const importBtn = document.getElementById('importBtn');
        const compareBtn = document.getElementById('compareBtn');
        const googleSyncToggle = document.getElementById('google_sync_enabled');

        exportBtn.addEventListener('click', function() {
            // Validate that sheet name and ID are filled
            const sheetName = document.getElementById('google_sheet_name').value.trim();
            const sheetId = document.getElementById('google_sheet_id').value.trim();
            
            if (!sheetId) {
                alert('Sheet ID is required');
                return;
            }
            if (!sheetName) {
                alert('Sheet Name is required');
                return;
            }
            
            document.getElementById('confirmationMessage').innerHTML =
                '<?= __('settings.confirm_export_google') ?>';
            $('#confirmationModal').modal('show');
            document.getElementById('confirmActionBtn').onclick = function() {
                $('#confirmationModal').modal('hide');
                // Update hidden form with current values
                document.getElementById('exportForm').querySelector('input[name="google_sheet_id"]').value = sheetId;
                document.getElementById('exportForm').querySelector('input[name="google_sheet_name"]').value = sheetName;
                document.getElementById('exportForm').querySelector('input[name="google_credentials"]').value = document.getElementById('google_credentials').value;
                document.getElementById('exportForm').querySelector('input[name="google_sync_enabled"]').value = googleSyncToggle.checked ? '1' : '0';
                document.getElementById('exportForm').submit();
            };
        });

        importBtn.addEventListener('click', function() {
            // Validate that sheet name and ID are filled
            const sheetName = document.getElementById('google_sheet_name').value.trim();
            const sheetId = document.getElementById('google_sheet_id').value.trim();
            
            if (!sheetId) {
                alert('Sheet ID is required');
                return;
            }
            if (!sheetName) {
                alert('Sheet Name is required');
                return;
            }
            
            document.getElementById('confirmationMessage').innerHTML =
                '<?= __('settings.confirm_import_google') ?>';
            $('#confirmationModal').modal('show');
            document.getElementById('confirmActionBtn').onclick = function() {
                $('#confirmationModal').modal('hide');
                // Update hidden form with current values
                document.getElementById('importForm').querySelector('input[name="google_sheet_id"]').value = sheetId;
                document.getElementById('importForm').querySelector('input[name="google_sheet_name"]').value = sheetName;
                document.getElementById('importForm').querySelector('input[name="google_credentials"]').value = document.getElementById('google_credentials').value;
                document.getElementById('importForm').querySelector('input[name="google_sync_enabled"]').value = googleSyncToggle.checked ? '1' : '0';
                document.getElementById('importForm').submit();
            };
        });

        compareBtn.addEventListener('click', function() {
            // First compare, then show merge options
            document.getElementById('confirmationMessage').innerHTML =
                '<?= __('settings.confirm_compare_google') ?>';
            $('#confirmationModal').modal('show');
            document.getElementById('confirmActionBtn').onclick = function() {
                $('#confirmationModal').modal('hide');
                // Create a hidden form to submit the compare request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'index.php?action=settings&do=compare_google';
                form.innerHTML = '<input type="hidden" name="compare" value="1">';
                document.body.appendChild(form);
                form.submit();
            };
        });

        // Handle merge button
        const confirmMergeBtn = document.getElementById('confirmMergeBtn');
        if (confirmMergeBtn) {
            confirmMergeBtn.addEventListener('click', function() {
                $('#mergeModal').modal('hide');
                document.getElementById('mergeForm').submit();
            });
        }

        // Handle database migration
        const migrateDbBtn = document.getElementById('migrateDbBtn');
        if (migrateDbBtn) {
            migrateDbBtn.addEventListener('click', async function() {
                if (!confirm('Run database migrations? This will create missing tables without affecting existing data.')) {
                    return;
                }

                migrateDbBtn.disabled = true;
                migrateDbBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Running...';

                try {
                    const response = await fetch('index.php?action=settings&do=migrate_database', {
                        method: 'GET',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    
                    const result = await response.json();

                    // Build result HTML
                    let resultHtml = `<div class="alert alert-${result.success ? 'success' : 'danger'} alert-dismissible fade show">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <h5>${result.success ? '<i class="fas fa-check-circle mr-2"></i>Migration Complete' : '<i class="fas fa-exclamation-circle mr-2"></i>Migration Failed'}</h5>`;

                    if (result.tables && result.tables.length > 0) {
                        resultHtml += '<ul class="mb-0">';
                        result.tables.forEach(table => {
                            const icon = table.status === 'created' ? '✓' : '→';
                            resultHtml += `<li><strong>${icon} ${table.name}</strong>: ${table.reason}</li>`;
                        });
                        resultHtml += '</ul>';
                    }

                    if (result.errors && result.errors.length > 0) {
                        resultHtml += '<div class="mt-2"><strong>Errors:</strong><ul>';
                        result.errors.forEach(err => {
                            resultHtml += `<li>${err}</li>`;
                        });
                        resultHtml += '</ul></div>';
                    }

                    resultHtml += '</div>';

                    document.getElementById('migrationResult').innerHTML = resultHtml;

                } catch (error) {
                    console.error('Error:', error);
                    document.getElementById('migrationResult').innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            Migration error: ${error.message}
                        </div>`;
                } finally {
                    migrateDbBtn.disabled = false;
                    migrateDbBtn.innerHTML = '<i class="fas fa-database mr-2"></i>Run Migrations';
                }
            });
        }
    });
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>