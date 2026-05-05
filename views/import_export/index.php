<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar-v2.php'; ?>
<?php
$userRole            = $userRole            ?? ($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'viewer');
$googleSheetSettings = $googleSheetSettings ?? [];
$canManage           = in_array($userRole, ['manager', 'super_admin'], true);
$lang                = $_SESSION['lang'] ?? 'it';
?>

<div class="content-wrapper">

    <!-- ── Page header ──────────────────────────────────────────── -->
    <section class="content-header" style="background:linear-gradient(135deg,#1a6fc4 0%,#0d47a1 100%);padding:24px 28px 20px;">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:12px;">
                <div>
                    <h1 class="mb-0 text-white" style="font-size:1.6rem;font-weight:700;letter-spacing:-.3px;">
                        <i class="fas fa-exchange-alt mr-2" aria-hidden="true"></i><?= __('import_export.title') ?>
                    </h1>
                    <p class="mb-0 text-white-50" style="font-size:.85rem;margin-top:4px;">
                        <?= __('import_export.subtitle') ?? 'Excel &amp; Google Sheets — import, export and sync' ?>
                    </p>
                </div>
                <nav aria-label="<?= __('common.breadcrumb') ?? 'Breadcrumb' ?>">
                    <ol class="breadcrumb bg-transparent p-0 m-0" style="font-size:.82rem;">
                        <li class="breadcrumb-item"><a href="index.php?action=dashboard" class="text-white-50"><?= __('menu.dashboard') ?></a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?= __('import_export.title') ?></li>
                    </ol>
                </nav>
            </div>
        </div>
    </section>

    <main class="content" id="main-content">
        <div class="container-fluid pt-3">

            <!-- ── Flash messages ──────────────────────────────────── -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-1" aria-hidden="true"></i>
                    <?= htmlspecialchars($_SESSION['message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="<?= __('common.close') ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle mr-1" aria-hidden="true"></i>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="<?= __('common.close') ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['google_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong><i class="fas fa-exclamation-triangle mr-1" aria-hidden="true"></i><?= __('settings.google_error') ?></strong>
                    <?= htmlspecialchars($_SESSION['google_error']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="<?= __('common.close') ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['google_error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['import_result'])): ?>
                <?php $ir = $_SESSION['import_result']; unset($_SESSION['import_result']); ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <strong><i class="fas fa-info-circle mr-1" aria-hidden="true"></i><?= __('common.import_results') ?>:</strong>
                    <?= __('common.imported') ?>: <strong><?= (int)$ir['imported'] ?></strong> &nbsp;|&nbsp;
                    <?= __('common.updated') ?>: <strong><?= (int)$ir['updated'] ?></strong> &nbsp;|&nbsp;
                    <?= __('common.skipped') ?>: <strong><?= (int)$ir['skipped'] ?></strong>
                    <?php if (!empty($ir['errors'])): ?>
                        <details class="mt-2">
                            <summary><?= __('common.error_details') ?></summary>
                            <ul class="small mb-0 mt-1">
                                <?php foreach ($ir['errors'] as $e): ?>
                                    <li><?= __('common.row') ?> <?= (int)$e['row'] ?> (<?= htmlspecialchars($e['domain']) ?>): <?= htmlspecialchars($e['message']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="<?= __('common.close') ?>"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['google_sync_result'])): ?>
                <?php $gsr = $_SESSION['google_sync_result']; unset($_SESSION['google_sync_result']); ?>
                <?php if (!empty($gsr['rollback'])): ?>
                    <div class="alert <?= !empty($gsr['errors']) ? 'alert-warning' : 'alert-success' ?> alert-dismissible fade show" role="alert">
                        <strong><i class="fas fa-undo mr-1" aria-hidden="true"></i>Rollback #<?= (int)$gsr['audit_id'] ?> Complete</strong>
                        <ul class="mb-0 mt-1">
                            <li>Rows restored: <strong><?= (int)($gsr['restored'] ?? 0) ?></strong></li>
                            <li>Rows removed (added by sync): <strong><?= (int)($gsr['deleted'] ?? 0) ?></strong></li>
                            <?php if (!empty($gsr['errors'])): ?>
                                <li class="text-danger"><?= implode('; ', array_map('htmlspecialchars', $gsr['errors'])) ?></li>
                            <?php endif; ?>
                        </ul>
                        <button type="button" class="close" data-dismiss="alert" aria-label="<?= __('common.close') ?>"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php else: ?>
                    <div class="alert <?= !empty($gsr['errors']) ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show" role="alert">
                        <strong><i class="fas fa-cloud mr-1" aria-hidden="true"></i><?= __('settings.google_sync_results') ?></strong>
                        <ul class="mb-0 mt-1">
                            <li><?= __('settings.exported') ?>: <strong><?= (int)($gsr['exported'] ?? 0) ?></strong></li>
                            <li><?= __('settings.imported') ?>: <strong><?= (int)($gsr['imported'] ?? 0) ?></strong></li>
                            <li><?= __('settings.updated') ?>: <strong><?= (int)($gsr['updated'] ?? 0) ?></strong></li>
                            <li><?= __('settings.conflicts_resolved') ?>: <strong><?= (int)($gsr['conflicts_resolved'] ?? 0) ?></strong></li>
                            <li>Conflicts Detected: <strong><?= (int)($gsr['conflicts_detected'] ?? 0) ?></strong></li>
                            <li>Dry Run: <strong><?= !empty($gsr['dry_run']) ? 'Yes' : 'No' ?></strong></li>
                            <?php if (!empty($gsr['errors']) && is_array($gsr['errors'])): ?>
                                <li class="text-danger"><strong><?= __('common.error') ?>:</strong>
                                    <ul>
                                        <?php foreach ($gsr['errors'] as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <button type="button" class="close" data-dismiss="alert" aria-label="<?= __('common.close') ?>"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ── Comparison results (from compare_google) ─────── -->
            <?php if (isset($_SESSION['comparison_result'])): ?>
                <?php $comparison = $_SESSION['comparison_result']; unset($_SESSION['comparison_result']); ?>
                <section aria-labelledby="comparison-heading" class="mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h2 class="card-title m-0 h5" id="comparison-heading">
                                <i class="fas fa-balance-scale mr-1" aria-hidden="true"></i> <?= __('settings.comparison_results') ?>
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="h2 text-primary mb-0"><?= $comparison['summary']['db_total'] ?></div>
                                    <small class="text-muted"><?= __('settings.database_total') ?></small>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="h2 text-success mb-0"><?= $comparison['summary']['google_total'] ?></div>
                                    <small class="text-muted"><?= __('settings.google_sheets_total') ?></small>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="h2 text-info mb-0"><?= $comparison['summary']['matches'] ?></div>
                                    <small class="text-muted"><?= __('settings.matching_records') ?></small>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="h2 text-warning mb-0"><?= $comparison['summary']['conflicts'] ?></div>
                                    <small class="text-muted"><?= __('common.conflicts') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($comparison['different_values']) || !empty($comparison['only_in_db']) || !empty($comparison['only_in_google'])): ?>
                        <div class="card mt-3 shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="m-0 h6"><?= __('settings.detailed_disparities') ?></h3>
                                <?php
                                $fieldDiffCount = 0;
                                foreach ($comparison['different_values'] as $conflict) {
                                    $fieldDiffCount += count($conflict['differences']);
                                }
                                ?>
                                <small class="text-muted"><?= $comparison['summary']['conflicts'] ?> <?= __('common.conflicts') ?></small>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover table-sm mb-0">
                                        <?php
                                        $serviceTypeLabel = function(string $st): string {
                                            $key = match (strtolower(trim($st))) {
                                                'domain'       => 'type_domain',
                                                'hosting_mail' => 'type_hosting_mail',
                                                default        => 'type_hosting_web',
                                            };
                                            return __('websites.' . $key);
                                        };
                                        ?>
                                        <thead class="bg-light">
                                            <tr>
                                                <th scope="col"><?= __('settings.domain') ?></th>
                                                <th scope="col"><?= __('websites.service_type') ?></th>
                                                <th scope="col"><?= __('settings.field') ?></th>
                                                <th scope="col"><?= __('settings.database_value') ?></th>
                                                <th scope="col"><?= __('settings.google_sheets_value') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($comparison['different_values'] as $conflict): ?>
                                                <?php foreach ($conflict['differences'] as $field => $diff): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($conflict['domain']) ?></strong></td>
                                                            <td><span class="badge badge-secondary"><?= htmlspecialchars($serviceTypeLabel($conflict['service_type'] ?? '')) ?></span></td>
                                                        <td><span class="badge badge-warning"><?= htmlspecialchars($field) ?></span></td>
                                                        <td><code class="bg-light px-1"><?= htmlspecialchars($diff['db_value']) ?></code></td>
                                                        <td><code class="bg-light px-1"><?= htmlspecialchars($diff['google_value']) ?></code></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                            <?php foreach ($comparison['only_in_db'] as $item): ?>
                                                <tr class="table-warning">
                                                    <td><strong><?= htmlspecialchars($item['domain']) ?></strong></td>
                                                        <td><span class="badge badge-secondary"><?= htmlspecialchars($serviceTypeLabel($item['service_type'] ?? '')) ?></span></td>
                                                    <td><span class="badge badge-info"><?= __('settings.only_in_db') ?></span></td>
                                                    <td><em><?= __('settings.exists_in_database') ?></em></td>
                                                    <td><em class="text-danger"><?= __('settings.not_in_google_sheets') ?></em></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php foreach ($comparison['only_in_google'] as $item): ?>
                                                <tr class="table-info">
                                                    <td><strong><?= htmlspecialchars($item['domain'] ?? 'N/A') ?></strong></td>
                                                        <td><span class="badge badge-secondary"><?= htmlspecialchars($serviceTypeLabel($item['service_type'] ?? '')) ?></span></td>
                                                    <td><span class="badge badge-success"><?= __('settings.only_in_google') ?></span></td>
                                                    <td><em class="text-danger"><?= __('settings.not_in_database') ?></em></td>
                                                    <td><em><?= __('settings.exists_in_google_sheets') ?></em></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 text-right">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#mergeModal">
                                <i class="fas fa-sync mr-1" aria-hidden="true"></i> <?= __('settings.proceed_to_merge') ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- ── Confirmation modal (shared) ──────────────────── -->
            <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-labelledby="confirmationModalTitle" aria-modal="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirmationModalTitle"><?= __('websites.confirm_action') ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="<?= __('common.close') ?>"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body" id="confirmationMessage"><?= __('websites.sure') ?></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('websites.cancel') ?></button>
                            <button type="button" class="btn btn-primary" id="confirmActionBtn"><?= __('websites.confirm') ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Merge modal ───────────────────────────────────── -->
            <div class="modal fade" id="mergeModal" tabindex="-1" role="dialog" aria-labelledby="mergeModalTitle" aria-modal="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="mergeModalTitle"><?= __('settings.merge_data') ?></h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="<?= __('common.close') ?>"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p><?= __('settings.select_merge_strategy') ?></p>
                            <form id="mergeForm" method="post" action="index.php?action=settings&do=merge_google">
                                <fieldset>
                                    <legend class="sr-only"><?= __('settings.merge_data') ?></legend>
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

                                    <hr class="my-3">

                                    <div class="form-group mb-2">
                                        <label for="conflictPolicy" class="font-weight-bold mb-1">Conflict policy</label>
                                        <select id="conflictPolicy" name="conflict_policy" class="form-control form-control-sm">
                                            <option value="manual" selected>Manual (detect and report conflicts)</option>
                                            <option value="prefer_db">Prefer database values</option>
                                            <option value="prefer_google">Prefer Google Sheets values</option>
                                        </select>
                                    </div>

                                    <div class="custom-control custom-checkbox mt-2">
                                        <input type="checkbox" id="dryRun" name="dry_run" value="1" class="custom-control-input">
                                        <label class="custom-control-label" for="dryRun">
                                            Dry run (preview only, do not write changes)
                                        </label>
                                    </div>
                                </fieldset>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('websites.cancel') ?></button>
                            <button type="button" class="btn btn-primary" id="confirmMergeBtn"><?= __('websites.confirm') ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════ -->
            <!-- Main grid                                          -->
            <!-- ══════════════════════════════════════════════════ -->
            <!-- ── Row 1: Websites (full width) ───────────────── -->
            <div class="row">
                <div class="col-12 mb-4">
                    <article class="card card-outline card-primary shadow-sm" aria-labelledby="card-websites-title">
                        <div class="card-header">
                            <h2 class="card-title h6 m-0" id="card-websites-title">
                                <i class="fas fa-globe mr-1 text-primary" aria-hidden="true"></i>
                                <?= __('import_export.websites_section') ?>
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-start">
                                <!-- Export column -->
                                <div class="col-sm-auto mb-3 mb-sm-0">
                                    <p class="text-muted small mb-2"><?= __('import_export.websites_desc') ?></p>
                                    <form method="post" action="index.php?action=websites&do=export&lang=<?= $lang ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-file-excel mr-1" aria-hidden="true"></i>
                                            <?= __('import_export.export_websites') ?>
                                        </button>
                                    </form>
                                </div>

                                <!-- Import column (managers+) -->
                                <?php if ($canManage): ?>
                                <div class="col-sm">
                                    <p class="font-weight-bold small mb-1"><?= __('import_export.import_websites') ?></p>
                                    <form method="post"
                                          action="index.php?action=websites&do=import&lang=<?= $lang ?>"
                                          enctype="multipart/form-data"
                                          aria-label="<?= __('import_export.import_websites') ?>">
                                        <div class="input-group">
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="websites_import_file"
                                                       name="import_file" accept=".xls,.xlsx" required
                                                       aria-describedby="websites_import_note"
                                                       onchange="this.nextElementSibling.textContent = this.files[0]?.name ?? '<?= addslashes(__('import_export.choose_file')) ?>'">
                                                <label class="custom-file-label" for="websites_import_file"><?= __('import_export.choose_file') ?></label>
                                            </div>
                                            <div class="input-group-append">
                                                <button class="btn btn-info" type="submit">
                                                    <i class="fas fa-upload mr-1" aria-hidden="true"></i>
                                                    <?= __('common.import') ?>
                                                </button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted" id="websites_import_note"><?= __('import_export.import_note') ?></small>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <!-- ── Row 2: Hosting + Notifications ─────────────── -->
            <div class="row">

                <!-- ── Excel: Hosting ──────────────────────────── -->
                <div class="col-md-6 mb-4">
                    <article class="card card-outline card-success h-100 shadow-sm" aria-labelledby="card-hosting-title">
                        <div class="card-header">
                            <h2 class="card-title h6 m-0" id="card-hosting-title">
                                <i class="fas fa-server mr-1 text-success" aria-hidden="true"></i>
                                <?= __('import_export.hosting_section') ?>
                            </h2>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small"><?= __('import_export.hosting_desc') ?></p>
                            <form method="post" action="index.php?action=import_export&do=export_hosting&lang=<?= $lang ?>" class="mt-3">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel mr-1" aria-hidden="true"></i>
                                    <?= __('import_export.export_hosting') ?>
                                </button>
                            </form>
                        </div>
                    </article>
                </div>

                <!-- ── Excel: Notifications ────────────────────── -->
                <div class="col-md-6 mb-4">
                    <article class="card card-outline card-warning h-100 shadow-sm" aria-labelledby="card-notifications-title">
                        <div class="card-header">
                            <h2 class="card-title h6 m-0" id="card-notifications-title">
                                <i class="fas fa-bell mr-1 text-warning" aria-hidden="true"></i>
                                <?= __('import_export.notifications_section') ?>
                            </h2>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small"><?= __('import_export.notifications_desc') ?></p>
                            <form method="post" action="index.php?action=import_export&do=export_notifications&lang=<?= $lang ?>" class="mt-3">
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="fas fa-file-csv mr-1" aria-hidden="true"></i>
                                    <?= __('import_export.export_notifications') ?>
                                </button>
                            </form>
                        </div>
                    </article>
                </div>

            </div><!-- /.row (Hosting + Notifications) -->

            <!-- ══════════════════════════════════════════════════ -->
            <!-- Google Sheets Integration                          -->
            <!-- ══════════════════════════════════════════════════ -->
            <section aria-labelledby="google-sheets-heading" class="mt-2 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header d-flex align-items-center" style="background:#e8f5e9;">
                        <h2 class="card-title h5 m-0 text-success" id="google-sheets-heading">
                            <i class="fas fa-table mr-2 text-success" aria-hidden="true"></i>
                            <?= __('settings.google_sheets_integration') ?>
                        </h2>
                        <span class="ml-2 badge <?= ($googleSheetSettings['enabled'] ?? false) ? 'badge-success' : 'badge-secondary' ?>" aria-label="<?= ($googleSheetSettings['enabled'] ?? false) ? __('common.active') : __('common.inactive') ?>">
                            <?= ($googleSheetSettings['enabled'] ?? false) ? __('common.active') : __('common.inactive') ?>
                        </span>
                    </div>

                    <div class="card-body">
                        <form method="post"
                              action="index.php?action=settings&do=google_sheets&lang=<?= $lang ?>"
                              id="googleSettingsForm"
                              aria-label="<?= __('settings.google_sheets_integration') ?>">

                            <div class="row">
                                <!-- Left column: Sheet ID + Name -->
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="google_sheet_id" class="font-weight-bold">
                                            <?= __('settings.google_sheet_id') ?>
                                            <span class="text-danger" aria-hidden="true">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="google_sheet_id"
                                               name="google_sheet_id"
                                               value="<?= htmlspecialchars($googleSheetSettings['sheet_id'] ?? '') ?>"
                                               placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms"
                                               aria-describedby="google_sheet_id_help"
                                               autocomplete="off">
                                        <small class="form-text text-muted" id="google_sheet_id_help">
                                            <?= __('settings.google_sheet_id_help') ?>
                                        </small>
                                    </div>
                                    <div class="form-group">
                                        <label for="google_sheet_name" class="font-weight-bold">
                                            <?= __('settings.sheet_name') ?>
                                            <span class="text-danger" aria-hidden="true">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="google_sheet_name"
                                               name="google_sheet_name"
                                               value="<?= htmlspecialchars($googleSheetSettings['sheet_name'] ?? '') ?>"
                                               placeholder="<?= __('settings.sheet_name_help') ?>"
                                               autocomplete="off">
                                    </div>
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input"
                                                   id="google_sync_enabled" name="google_sync_enabled"
                                                   value="1"
                                                   <?= ($googleSheetSettings['enabled'] ?? false) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="google_sync_enabled">
                                                <?= __('settings.google_sync_enabled') ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right column: Credentials -->
                                <div class="col-md-7">
                                    <div class="form-group h-100 d-flex flex-column">
                                        <label for="google_credentials" class="font-weight-bold">
                                            <?= __('settings.service_account_credentials') ?>
                                        </label>
                                        <textarea class="form-control flex-grow-1" id="google_credentials"
                                                  name="google_credentials" rows="7"
                                                  placeholder="<?= __('settings.service_account_placeholder') ?>"
                                                  aria-describedby="google_credentials_help"
                                                  style="font-family:monospace;font-size:.8rem;min-height:160px;"><?= htmlspecialchars($googleSheetSettings['credentials'] ?? '') ?></textarea>
                                        <small class="form-text text-muted" id="google_credentials_help">
                                            Paste the full JSON key from your Google Cloud Service Account.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                                        <button type="submit" name="save_google_settings" class="btn btn-primary">
                                            <i class="fas fa-save mr-1" aria-hidden="true"></i>
                                            <?= __('settings.save_google_settings') ?>
                                        </button>
                                        <button type="button" id="exportBtn" class="btn btn-success">
                                            <i class="fas fa-cloud-upload-alt mr-1" aria-hidden="true"></i>
                                            <?= __('settings.export_to_google') ?>
                                        </button>
                                        <button type="button" id="importBtn" class="btn btn-info">
                                            <i class="fas fa-cloud-download-alt mr-1" aria-hidden="true"></i>
                                            <?= __('settings.import_from_google') ?>
                                        </button>
                                        <button type="button" id="compareBtn" class="btn btn-warning">
                                            <i class="fas fa-balance-scale mr-1" aria-hidden="true"></i>
                                            <?= __('settings.compare_data') ?>
                                        </button>
                                        <a href="index.php?action=settings&do=diagnostic_google_sheets&lang=<?= $lang ?>"
                                           class="btn btn-secondary">
                                            <i class="fas fa-stethoscope mr-1" aria-hidden="true"></i>
                                            <?= __('settings.diagnostic_button') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Hidden forms for export/import (carry current form values) -->
                        <form id="exportForm" method="post" action="index.php?action=settings&do=google_sheets" style="display:none;" aria-hidden="true">
                            <input type="hidden" name="export_to_google" value="1">
                            <input type="hidden" name="google_sheet_id"   value="">
                            <input type="hidden" name="google_sheet_name" value="">
                            <input type="hidden" name="google_credentials" value="">
                            <input type="hidden" name="google_sync_enabled" value="0">
                            <input type="hidden" name="save_google_settings" value="0">
                        </form>
                        <form id="importForm" method="post" action="index.php?action=settings&do=google_sheets" style="display:none;" aria-hidden="true">
                            <input type="hidden" name="import_from_google" value="1">
                            <input type="hidden" name="google_sheet_id"   value="">
                            <input type="hidden" name="google_sheet_name" value="">
                            <input type="hidden" name="google_credentials" value="">
                            <input type="hidden" name="google_sync_enabled" value="0">
                            <input type="hidden" name="save_google_settings" value="0">
                        </form>
                    </div>
                </div>
            </section>

            <!-- ── Recent activity ──────────────────────────────── -->
            <section aria-labelledby="recent-jobs-heading" class="mb-4">
                <div class="card card-outline card-secondary shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="card-title h6 m-0" id="recent-jobs-heading">
                            <i class="fas fa-history mr-1" aria-hidden="true"></i>
                            <?= __('import_export.recent_jobs') ?>
                        </h2>
                        <a href="index.php?action=tasks&lang=<?= $lang ?>" class="btn btn-tool btn-sm">
                            <?= __('import_export.view_all') ?> <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentTasks)): ?>
                            <p class="text-muted p-3 mb-0 small"><?= __('import_export.no_recent') ?></p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush" role="list">
                                <?php foreach ($recentTasks as $task): ?>
                                    <?php $sc = ['completed'=>'success','failed'=>'danger','running'=>'info','pending'=>'secondary'][$task['status']] ?? 'secondary'; ?>
                                    <li class="list-group-item py-2 px-3 d-flex justify-content-between align-items-center" role="listitem">
                                        <div>
                                            <span class="font-weight-bold small"><?= htmlspecialchars($task['label']) ?></span>
                                            <br><small class="text-muted"><?= htmlspecialchars(date('d M H:i', strtotime($task['created_at']))) ?></small>
                                        </div>
                                        <span class="badge badge-<?= $sc ?>" aria-label="<?= __('common.status') ?>: <?= strtoupper($task['status']) ?>">
                                            <?= htmlspecialchars(strtoupper($task['status'])) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- ── Google Sheets Sync History ───────────────────── -->
            <?php if (!empty($recentSyncJobs)): ?>
            <section aria-labelledby="sync-history-heading" class="mb-4">
                <div class="card card-outline card-info shadow-sm">
                    <div class="card-header">
                        <h2 class="card-title h6 m-0" id="sync-history-heading">
                            <i class="fab fa-google-drive mr-1" aria-hidden="true"></i>
                            Google Sheets Sync History
                        </h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" style="font-size:0.82rem;">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Operation</th>
                                        <th>Policy</th>
                                        <th title="Added to DB">+DB</th>
                                        <th title="Updated in DB">~DB</th>
                                        <th title="Added to Sheet">+GS</th>
                                        <th title="Updated in Sheet">~GS</th>
                                        <th title="Conflicts detected">Conflicts</th>
                                        <th title="Errors">Errors</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentSyncJobs as $sj):
                                    $dirLabel = ['forward' => 'Import', 'backward' => 'Export/Merge', 'together' => 'Sync'][$sj['direction']] ?? ucfirst($sj['direction']);
                                    $isDryRun = (int)$sj['dry_run'];
                                    $hasErrors = (int)$sj['error_count'] > 0;
                                    $rowClass = $hasErrors ? 'table-warning' : '';
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="text-muted"><?= (int)$sj['id'] ?></td>
                                        <td><?= htmlspecialchars(date('d M H:i', strtotime($sj['executed_at']))) ?></td>
                                        <td>
                                            <?= htmlspecialchars($dirLabel) ?>
                                            <?php if ($isDryRun): ?>
                                                <span class="badge badge-secondary ml-1">dry-run</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?= htmlspecialchars($sj['conflict_policy']) ?></small></td>
                                        <td><?= (int)$sj['added_to_db'] ?></td>
                                        <td><?= (int)$sj['updated_in_db'] ?></td>
                                        <td><?= (int)$sj['added_to_google'] ?></td>
                                        <td><?= (int)$sj['updated_in_google'] ?></td>
                                        <td><?= (int)$sj['conflicts_detected'] > 0 ? '<span class="text-warning font-weight-bold">' . (int)$sj['conflicts_detected'] . '</span>' : '0' ?></td>
                                        <td><?= $hasErrors ? '<span class="text-danger font-weight-bold">' . (int)$sj['error_count'] . '</span>' : '0' ?></td>
                                        <td>
                                            <?php if (!$isDryRun && ((int)$sj['added_to_db'] > 0 || (int)$sj['updated_in_db'] > 0)): ?>
                                                <a href="#"
                                                   class="btn btn-xs btn-outline-danger rollback-btn"
                                                   data-audit-id="<?= (int)$sj['id'] ?>"
                                                   data-date="<?= htmlspecialchars(date('d M H:i', strtotime($sj['executed_at']))) ?>"
                                                   title="Rollback DB changes from this sync">
                                                    <i class="fas fa-undo"></i> Rollback
                                                </a>
                                            <?php else: ?>
                                                <small class="text-muted">—</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

        </div><!-- /.container-fluid -->
    </main>
</div><!-- /.content-wrapper -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const exportBtn     = document.getElementById('exportBtn');
    const importBtn     = document.getElementById('importBtn');
    const compareBtn    = document.getElementById('compareBtn');
    const confirmModal  = document.getElementById('confirmationModal');
    const confirmMsg    = document.getElementById('confirmationMessage');
    const googleToggle  = document.getElementById('google_sync_enabled');

    function getSheetValues() {
        return {
            id:          document.getElementById('google_sheet_id').value.trim(),
            name:        document.getElementById('google_sheet_name').value.trim(),
            credentials: document.getElementById('google_credentials').value,
            enabled:     googleToggle.checked ? '1' : '0'
        };
    }

    function populateHiddenForm(formId, vals) {
        const f = document.getElementById(formId);
        f.querySelector('[name="google_sheet_id"]').value    = vals.id;
        f.querySelector('[name="google_sheet_name"]').value  = vals.name;
        f.querySelector('[name="google_credentials"]').value = vals.credentials;
        f.querySelector('[name="google_sync_enabled"]').value = vals.enabled;
    }

    function showConfirm(message, onConfirm) {
        confirmMsg.innerHTML = message;
        // Fresh lookup each time so we always get the current DOM node
        const confirmActBtn = document.getElementById('confirmActionBtn');
        const newBtn = confirmActBtn.cloneNode(true);
        confirmActBtn.parentNode.replaceChild(newBtn, confirmActBtn);
        newBtn.addEventListener('click', function () {
            $('#confirmationModal').modal('hide');
            onConfirm();
        });
        $('#confirmationModal').modal('show');
        // Return focus to confirm button when modal shown
        confirmModal.addEventListener('shown.bs.modal', function handler() {
            newBtn.focus();
            confirmModal.removeEventListener('shown.bs.modal', handler);
        });
    }

    function validateSheetConfig() {
        const v = getSheetValues();
        if (!v.id)   { alert('<?= addslashes(__('settings.google_sheet_id')) ?> is required.'); return false; }
        if (!v.name) { alert('<?= addslashes(__('settings.sheet_name')) ?> is required.'); return false; }
        return v;
    }

    exportBtn.addEventListener('click', function () {
        const vals = validateSheetConfig();
        if (!vals) return;
        showConfirm('<?= addslashes(__('settings.confirm_export_google')) ?>', function () {
            populateHiddenForm('exportForm', vals);
            document.getElementById('exportForm').submit();
        });
    });

    importBtn.addEventListener('click', function () {
        const vals = validateSheetConfig();
        if (!vals) return;
        showConfirm('<?= addslashes(__('settings.confirm_import_google')) ?>', function () {
            populateHiddenForm('importForm', vals);
            document.getElementById('importForm').submit();
        });
    });

    compareBtn.addEventListener('click', function () {
        showConfirm('<?= addslashes(__('settings.confirm_compare_google')) ?>', function () {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php?action=settings&do=compare_google';
            form.innerHTML = '<input type="hidden" name="compare" value="1">';
            document.body.appendChild(form);
            form.submit();
        });
    });

    const confirmMergeBtn = document.getElementById('confirmMergeBtn');
    if (confirmMergeBtn) {
        confirmMergeBtn.addEventListener('click', function () {
            $('#mergeModal').modal('hide');
            document.getElementById('mergeForm').submit();
        });
    }

    // Rollback buttons in sync history table
    document.querySelectorAll('.rollback-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const auditId = btn.dataset.auditId;
            const date    = btn.dataset.date;
            showConfirm(
                'Roll back DB changes from sync <strong>#' + auditId + '</strong> (' + date + ')?' +
                '<br><small class="text-muted">This will restore all database records to the state they were in <em>before</em> that operation. Google Sheet changes cannot be automatically undone.</small>',
                function () {
                    window.location.href = 'index.php?action=settings&do=rollback_google_sync&audit_id=' + auditId + '&lang=<?= $lang ?>';
                }
            );
        });
    });
});
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>

