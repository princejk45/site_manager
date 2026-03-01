<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<div class="content-wrapper">

    <!-- Page Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1><?= __('hosting.manage_clients') ?></h1>
                </div>
                <div class="col-sm-6 text-sm-right">
                    <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                        <a href="index.php?action=hosting&do=create&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-primary btn-sm mr-2">
                            <i class="fas fa-plus"></i> <?= __('common.add_client') ?>
                        </a>
                        <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-sm mr-2" style="display:none;">
                            <i class="fas fa-trash"></i> <span id="selectedCount">0</span> <?= __('common.delete') ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Alerts -->
    <section class="content">
        <div class="container-fluid">

            <!-- Confirmation Modal -->
            <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-dark">
                            <h5 class="modal-title"><?= __('common.confirm') ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="<?= __('common.close') ?>">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body" id="confirmationMessage">Are you sure?</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-dismiss="modal"><?= __('common.cancel') ?></button>
                            <button type="button" class="btn btn-success" id="confirmActionBtn"><?= __('common.confirm') ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
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
                td a,
                td button,
                td form {
                    margin-right: 0.3rem;
                    margin-bottom: 0.3rem;
                }

                td form {
                    display: inline-block;
                    margin: 0 0.3rem 0.3rem 0;
                }

                td .d-inline {
                    display: inline-block !important;
                    margin-right: 0.3rem;
                }

                /* Ensure buttons wrap nicely on smaller screens */
                @media (max-width: 768px) {
                    td a,
                    td button {
                        margin-right: 0.25rem;
                        margin-bottom: 0.25rem;
                        padding: 0.25rem 0.5rem !important;
                    }
                }
            </style>

            <!-- Hosting Plans Table Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0"><?= __('dashboard.all_clients') ?></h3>
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
                                    <th class="site-wrap"><?= __('hosting.client_name') ?></th>
                                    <th class="site-wrap"><?= __('hosting.vat') ?></th>
                                    <th class="site-wrap"><?= __('hosting.email') ?></th>
                                    <th class="site-wrap"><?= __('hosting.address') ?></th>
                                    <th class=""><?= __('hosting.services') ?></th>
                                    <th class=""><?= __('hosting.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hostingPlans as $plan): ?>
                                    <tr>
                                        <!-- Checkbox Column -->
                                        <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                            <td style="width: 30px;">
                                                <input type="checkbox" class="hosting-checkbox" value="<?= $plan['id'] ?>" data-name="<?= htmlspecialchars($plan['server_name']) ?>" />
                                            </td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($plan['server_name']) ?></td>
                                        <td><?= htmlspecialchars($plan['provider'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($plan['email_address'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($plan['ip_address'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php
                                            $serviceCount = $plan['service_count'] ?? 0;
                                            if ($serviceCount > 0): ?>
                                                <a href="index.php?action=hosting&do=services&id=<?= $plan['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                    class="btn btn-sm btn-info">
                                                    <?= __('hosting.see') ?> <?= $serviceCount ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted"><?= __('hosting.no_services') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="index.php?action=hosting&do=view&id=<?= $plan['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                class="btn btn-sm btn-success" data-custom-tooltip="<?= __('hosting.view') ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                                <!--<a href="index.php?action=hosting&do=edit&id=<?= $plan['id'] ?>"
                                                    class="btn btn-sm btn-primary" data-custom-tooltip="<?= __('hosting.edit') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>-->
                                                <form method="post"
                                                    action="index.php?action=hosting&do=delete&id=<?= $plan['id'] ?>"
                                                    class="d-inline"
                                                    onsubmit="return confirm('<?= __('common.sure_delete_client') ?>')">
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                        data-custom-tooltip="<?= __('hosting.delete') ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<script>
    // Translation strings
    const transMessages = {
        warning: '<?= __('common.bulk_delete_warning') ?>',
        clients: '<?= __('common.bulk_delete_clients') ?>',
        cannotUndo: '<?= __('common.cannot_be_undone') ?>'
    };

    document.addEventListener('DOMContentLoaded', function() {
        let pendingAction = null;

        // ===== BULK DELETE FUNCTIONALITY =====
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const hostingCheckboxes = document.querySelectorAll('.hosting-checkbox');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const selectedCount = document.getElementById('selectedCount');

        function updateBulkDeleteButton() {
            const checkedCount = document.querySelectorAll('.hosting-checkbox:checked').length;
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
                hostingCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkDeleteButton();
            });
        }

        // Individual checkboxes
        hostingCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateBulkDeleteButton();
                // Update select all checkbox state
                if (selectAllCheckbox) {
                    const allChecked = Array.from(hostingCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(hostingCheckboxes).some(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;
                }
            });
        });

        // Bulk delete button
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.hosting-checkbox:checked');
                const selectedNames = Array.from(checkedBoxes).map(cb => cb.dataset.name).join(', ');
                const message = `<strong>${transMessages.warning}</strong> ${checkedBoxes.length} ${transMessages.clients}:<br/><br/><strong>${selectedNames}</strong><br/><br/>${transMessages.cannotUndo}.`;
                document.getElementById('confirmationMessage').innerHTML = message;
                $('#confirmationModal').modal('show');

                pendingAction = () => {
                    const ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'index.php?action=hosting&do=bulk_delete';
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