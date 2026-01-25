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
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['google_sync_result'])): ?>
                <div class="mt-4 p-3 border rounded alert-primary">
                    <h5><?= __('settings.google_sync_results') ?></h5>
                    <ul>
                        <li><?= __('settings.exported') ?>: <?= $_SESSION['google_sync_result']['exported'] ?? 0 ?></li>
                        <li><?= __('settings.imported') ?>: <?= $_SESSION['google_sync_result']['imported'] ?? 0 ?></li>
                        <li><?= __('settings.updated') ?>: <?= $_SESSION['google_sync_result']['updated'] ?? 0 ?></li>
                        <li><?= __('settings.conflicts_resolved') ?>: <?= $_SESSION['google_sync_result']['conflicts'] ?? 0 ?></li>
                        <?php if (!empty($_SESSION['google_sync_result']['errors'])): ?>
                            <li><?= __('common.error') ?>:
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
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary"><?= __('settings.save_settings') ?></button>
                    </div>
                </form>
            </div>

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
                        </div>
                    </form>

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
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const exportBtn = document.getElementById('exportBtn');
        const importBtn = document.getElementById('importBtn');
        const googleSyncToggle = document.getElementById('google_sync_enabled');

        function updateButtonState() {
            const syncOn = googleSyncToggle.checked;
            exportBtn.disabled = !syncOn;
            importBtn.disabled = !syncOn;
        }


        updateButtonState();

        googleSyncToggle.addEventListener('change', updateButtonState);

        exportBtn.addEventListener('click', function() {
            document.getElementById('confirmationMessage').innerHTML =
                '<?= __('settings.confirm_export_google') ?>';
            $('#confirmationModal').modal('show');
            document.getElementById('confirmActionBtn').onclick = function() {
                $('#confirmationModal').modal('hide');
                document.getElementById('exportForm').submit();
            };
        });

        importBtn.addEventListener('click', function() {
            document.getElementById('confirmationMessage').innerHTML =
                '<?= __('settings.confirm_import_google') ?>';
            $('#confirmationModal').modal('show');
            document.getElementById('confirmActionBtn').onclick = function() {
                $('#confirmationModal').modal('hide');
                document.getElementById('importForm').submit();
            };
        });
    });
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>