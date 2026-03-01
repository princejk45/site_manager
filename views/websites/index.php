<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= __('websites.manage_services') ?></h1>
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

            <!-- Toolbar -->

            <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center">
                <div><?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                        <a href="index.php?action=websites&do=create&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-primary btn-sm mr-2">
                            <i class="fas fa-plus"></i> <?= __('websites.add_service') ?>
                        </a>
                        <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-sm mr-2" style="display:none;">
                            <i class="fas fa-trash"></i> <span id="selectedCount">0</span> <?= __('common.delete') ?>
                        </button>
                    <?php endif; ?>
                </div>
                <form method="post" action="index.php?action=websites&do=export&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="d-inline">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-file-export"></i> <?= __('websites.export_excel') ?>
                    </button>
                </form>
                <!--<?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                    <form method="post" action="index.php?action=websites&do=import" enctype="multipart/form-data"
                        class="d-flex"
                        onsubmit="return confirm('Questo aggiornerà i siti web esistenti con i domini corrispondenti. Continuare?')">
                        <div class="input-group input-group-sm">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="import_file" name="import_file" required
                                    accept=".xls,.xlsx" onchange="updateFileName(this)">
                                <label class="custom-file-label" for="import_file">Scegli il file Excel</label>
                            </div>
                            <div class="input-group-append">
                                <button class="btn btn-info" type="submit">
                                    <i class="fas fa-file-import"></i> Importare
                                </button>
                            </div>
                        </div>
                    </form><?php endif; ?>-->
                <script>
                    function updateFileName(input) {
                        if (input.files && input.files[0]) {
                            // Get the file name and update the label
                            const fileName = input.files[0].name;
                            const label = input.nextElementSibling; // The label is the next sibling
                            label.textContent = fileName;
                        }
                    }
                </script>
            </div>

            <!-- Session messages -->
            <?php if (isset($_SESSION['import_result'])): ?>
                <div class="alert alert-info">
                    <h5><?= __('common.import_results') ?>:</h5>
                    <p><?= __('common.imported') ?>: <?= $_SESSION['import_result']['imported'] ?></p>
                    <p><?= __('common.updated') ?>: <?= $_SESSION['import_result']['updated'] ?></p>
                    <p><?= __('common.skipped') ?>: <?= $_SESSION['import_result']['skipped'] ?></p>

                    <?php if (!empty($_SESSION['import_result']['errors'])): ?>
                        <details class="mt-3">
                            <summary><?= __('common.error_details') ?></summary>
                            <ul class="small">
                                <?php foreach ($_SESSION['import_result']['errors'] as $error): ?>
                                    <li><?= __('common.row') ?> <?= $error['row'] ?> (<?= $error['domain'] ?>): <?= $error['message'] ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>
                <?php unset($_SESSION['import_result']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success"><?= $_SESSION['message'] ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Search and filter controls -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <form method="get" action="index.php" class="form-inline">
                        <input type="hidden" name="action" value="websites">
                        <input type="hidden" name="lang" value="<?= $_SESSION['lang'] ?? 'it' ?>">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" name="search" placeholder="<?= __('common.search') ?>..."
                                value="<?= htmlspecialchars($search) ?>">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="submit"><i
                                        class="fas fa-search"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-md-6 text-right">
                    <form method="get" action="index.php" class="form-inline float-right">
                        <input type="hidden" name="action" value="websites">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
                        <input type="hidden" name="lang" value="<?= $_SESSION['lang'] ?? 'it' ?>">
                        <label class="mr-2"><?= __('common.show') ?>:</label>
                        <select name="per_page" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10 <?= __('common.results') ?></option>
                            <option value="30" <?= $perPage == 30 ? 'selected' : '' ?>>30 <?= __('common.results') ?></option>
                            <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50 <?= __('common.results') ?></option>
                        </select>
                    </form>
                </div>
            </div>
            <style>
                /* Ensure the table header and body columns align */
                .table-responsive {
                    overflow-x: auto;
                }

                /* Make sure the action buttons stay together */
                .btn-group-actions {
                    display: inline-flex;
                    flex-wrap: nowrap;
                }

                /* Optional: Add horizontal scroll for small screens */
                @media (max-width: 768px) {
                    .table-responsive {
                        -webkit-overflow-scrolling: touch;
                    }
                }

                .no-wrap {
                    white-space: nowrap;
                    width: 17%;
                }

                .site-wrap {
                    white-space: nowrap;
                    width: 20%;
                }

                .date-wrap {
                    white-space: nowrap;
                    width: 10%;
                }

                .table-sm-text {
                    font-size: 0.90rem;
                }

                .table-sm-text th,
                .table-sm-text td {
                    padding: 0.5rem 0.8rem;
                }

                .table td,
                .table th {
                    white-space: normal !important;
                    /* word-break: break-word;*/
                    max-width: 200px;

                    vertical-align: top;
                }


                /* Custom Tooltip Styles */
                [data-custom-tooltip] {
                    position: relative;
                    cursor: pointer;
                }

                [data-custom-tooltip]::after {
                    content: attr(data-custom-tooltip);
                    position: absolute;
                    bottom: 100%;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #333;
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 14px;
                    font-weight: bold;
                    white-space: nowrap;
                    opacity: 0;
                    visibility: hidden;
                    transition: opacity 0.2s, visibility 0.2s;
                    z-index: 1000;
                    pointer-events: none;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                }

                [data-custom-tooltip]:hover::after {
                    opacity: 1;
                    visibility: visible;
                }

                /* Remove default tooltips */
                [title] {
                    position: relative;
                }

                [title]:hover::before,
                [title]:hover::after {
                    display: none !important;
                }

                /* Action buttons styling - keep horizontal with good spacing */
                td.no-wrap {
                    vertical-align: middle !important;
                }

                td.no-wrap a,
                td.no-wrap button,
                td.no-wrap form {
                    margin-right: 0.3rem;
                    margin-bottom: 0.3rem;
                    display: inline-block;
                }

                td.no-wrap form {
                    margin: 0;
                }

                td.no-wrap .d-inline {
                    display: inline-block !important;
                    margin-right: 0.3rem;
                }

                /* Ensure buttons wrap nicely on smaller screens */
                @media (max-width: 768px) {
                    td.no-wrap a,
                    td.no-wrap button {
                        margin-right: 0.25rem;
                        margin-bottom: 0.25rem;
                        padding: 0.25rem 0.5rem !important;
                    }

                    td.no-wrap {
                        min-width: 140px;
                    }
                }
            </style>
            <!-- Website Table (Wrapped in a card) -->
            <div class="card">
                <div class="card-header">
                    <h5><?= __('websites.manage_services') ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover mb-0 table-sm-text">
                            <thead class="thead-dark">
                                <tr>
                                    <!-- Checkbox Column -->
                                    <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                        <th style="width: 30px;">
                                            <input type="checkbox" id="selectAllCheckbox" />
                                        </th>
                                    <?php endif; ?>
                                    <!-- Hosting Server Column -->
                                    <th>
                                        <a
                                            href="?action=websites&sort=hosting_server&order=<?= ($sort == 'hosting_server' && $order == 'asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                            <?= __('websites.client') ?>
                                            <?php if ($sort == 'hosting_server'): ?>
                                                <i class="fas fa-sort-<?= $order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>

                                    <!-- Domain Column -->
                                    <th class="site-wrap">
                                        <a
                                            href="?action=websites&sort=domain&order=<?= ($sort == 'domain' && $order == 'asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                            <?= __('websites.domain_name') ?>
                                            <?php if ($sort == 'domain'): ?>
                                                <i class="fas fa-sort-<?= $order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>

                                    <!-- Service Type Column -->
                                    <th>
                                        <a
                                            href="?action=websites&sort=name&order=<?= ($sort == 'name' && $order == 'asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                            <?= __('common.type') ?>
                                            <?php if ($sort == 'name'): ?>
                                                <i class="fas fa-sort-<?= $order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>

                                    <!-- Registrar Column -->
                                    <th>
                                        <a
                                            href="?action=websites&sort=email_server&order=<?= ($sort == 'email_server' && $order == 'asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                            <?= __('common.registrant') ?>
                                            <?php if ($sort == 'email_server'): ?>
                                                <i class="fas fa-sort-<?= $order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>

                                    <!-- Expiry Date Column -->
                                    <th class="date-wrap">
                                        <a
                                            href="?action=websites&sort=expiry_date&order=<?= ($sort == 'expiry_date' && $order == 'asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                            <?= __('websites.expiring_date') ?>
                                            <?php if ($sort == 'expiry_date'): ?>
                                                <i class="fas fa-sort-<?= $order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>

                                    <th><?= __('common.bug') ?></th>
                                    <th><?= __('websites.status') ?></th>
                                    <th class="no-wrap"><?= __('websites.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($websites as $website): ?>
                                    <tr>
                                        <!-- Checkbox Column -->
                                        <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                            <td style="width: 30px;">
                                                <input type="checkbox" class="website-checkbox" value="<?= $website['id'] ?>" data-domain="<?= htmlspecialchars($website['domain']) ?>" />
                                            </td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($website['hosting_server'] ?? 'N/A') ?></td>
                                        <td class="no-wrap"><?= htmlspecialchars($website['domain']) ?></td>
                                        <td><?= htmlspecialchars($website['name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($website['email_server']) ?></td>
                                        <td><?= htmlspecialchars($website['expiry_date']) ?></td>
                                        <td>
                                            <?php
                                            $notes = strtolower(trim($website['notes'] ?? ''));
                                            $hasIssues = !empty($notes) && !in_array($notes, ['none', 'nessuno', '']);
                                            ?>
                                            <span class="badge badge-<?= $hasIssues ? 'warning' : 'success' ?>">
                                                <?= $hasIssues ? __('common.yes') : __('common.no') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $website['dynamic_status'] ?? 'attivo';
                                            $badgeClass = [
                                                'attivo' => 'success',
                                                'scade_presto' => 'warning',
                                                'scaduto' => 'danger'
                                            ][$status];
                                            $statusTranslations = [
                                                'attivo' => __('common.active'),
                                                'scade_presto' => __('common.expiring_soon'),
                                                'scaduto' => __('common.expired')
                                            ];
                                            ?>
                                            <span class="badge badge-<?= $badgeClass ?>">
                                                <?= $statusTranslations[$status] ?? ucwords(str_replace('_', ' ', $status)) ?>
                                            </span>
                                        </td>
                                        <td class="no-wrap">
                                            <a href="index.php?action=websites&do=view&id=<?= $website['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                class="btn btn-sm btn-success" data-custom-tooltip="<?= __('websites.view') ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                                <!--<a href="index.php?action=websites&do=edit&id=<?= $website['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                    class="btn btn-sm btn-primary" data-custom-tooltip="<?= __('websites.edit') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>-->
                                                <a href="index.php?action=email&do=expiry&id=<?= $website['id'] ?>"
                                                    class="btn btn-sm btn-info confirmable" data-type="email"
                                                    data-name="<?= htmlspecialchars($website['domain']) ?>"
                                                    data-custom-tooltip="<?= __('common.send_expiry_email') ?>">
                                                    <i class="fas fa-envelope"></i>
                                                </a>
                                                <a href="index.php?action=email&do=status&id=<?= $website['id'] ?>"
                                                    class="btn btn-sm btn-secondary confirmable" data-type="email"
                                                    data-name="<?= htmlspecialchars($website['domain']) ?>"
                                                    data-custom-tooltip="<?= __('common.send_status_email') ?>">
                                                    <i class="fas fa-bell"></i>
                                                </a>
                                                <form method="post"
                                                    action="index.php?action=websites&do=delete&id=<?= $website['id'] ?>"
                                                    class="d-inline confirmable" data-type="delete"
                                                    data-name="<?= htmlspecialchars($website['domain']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                        data-custom-tooltip="<?= __('websites.delete') ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link"
                                    href="?action=websites&page=<?= $page - 1 ?>&sort=<?= $sort ?>&order=<?= $order ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>">Precedente</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link"
                                    href="?action=websites&page=<?= $i ?>&sort=<?= $sort ?>&order=<?= $order ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link"
                                    href="?action=websites&page=<?= $page + 1 ?>&sort=<?= $sort ?>&order=<?= $order ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>"><?= __('common.next') ?></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        </div>
    </section>
</div>
<!-- /.content-wrapper -->

<script>
    // Translation strings
    const transMessages = {
        warning: '<?= __('common.bulk_delete_warning') ?>',
        services: '<?= __('common.bulk_delete_services') ?>',
        cannotUndo: '<?= __('common.cannot_be_undone') ?>'
    };

    document.addEventListener('DOMContentLoaded', function() {
        let pendingAction = null;

        // ===== BULK DELETE FUNCTIONALITY =====
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const websiteCheckboxes = document.querySelectorAll('.website-checkbox');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const selectedCount = document.getElementById('selectedCount');

        function updateBulkDeleteButton() {
            const checkedCount = document.querySelectorAll('.website-checkbox:checked').length;
            if (checkedCount > 0) {
                bulkDeleteBtn.style.display = 'inline-block';
                selectedCount.textContent = checkedCount;
            } else {
                bulkDeleteBtn.style.display = 'none';
                selectAllCheckbox.checked = false;
            }
        }

        // Select all checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                websiteCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkDeleteButton();
            });
        }

        // Individual checkboxes
        websiteCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateBulkDeleteButton();
                // Update select all checkbox state
                if (selectAllCheckbox) {
                    const allChecked = Array.from(websiteCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(websiteCheckboxes).some(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;
                }
            });
        });

        // Bulk delete button
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.website-checkbox:checked');
                const selectedDomains = Array.from(checkedBoxes).map(cb => cb.dataset.domain).join(', ');
                const message = `<strong>${transMessages.warning}</strong> ${checkedBoxes.length} ${transMessages.services}:<br/><br/><strong>${selectedDomains}</strong><br/><br/>${transMessages.cannotUndo}.`;
                document.getElementById('confirmationMessage').innerHTML = message;
                $('#confirmationModal').modal('show');

                pendingAction = () => {
                    const ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'index.php?action=websites&do=bulk_delete';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids';
                    input.value = ids;
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                };
            });
        }

        // Handle confirmable actions (email and delete)
        document.querySelectorAll('.confirmable').forEach(el => {
            if (el.tagName === 'A') {
                // For email links
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    const type = this.dataset.type;
                    const name = this.dataset.name;
                    const message =
                        `Sei sicuro di voler inviare una email per il servizio: <strong>${name}</strong>?`;
                    document.getElementById('confirmationMessage').innerHTML = message;
                    $('#confirmationModal').modal('show');

                    pendingAction = () => {
                        window.location.href = this.href;
                    };
                });
            } else if (el.tagName === 'FORM') {
                // For delete forms
                const button = el.querySelector('button[type="submit"]');
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const type = el.dataset.type;
                    const name = el.dataset.name;
                    const message =
                        `Sei sicuro di voler eliminare il servizio: <strong>${name}</strong>?`;
                    document.getElementById('confirmationMessage').innerHTML = message;
                    $('#confirmationModal').modal('show');

                    pendingAction = () => {
                        el.submit();
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

        // File name update function
        function updateFileName(input) {
            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const label = input.nextElementSibling;
                label.textContent = fileName;
            }
        }
    });
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>