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
                        <a href="index.php?action=hosting&do=create&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> <?= __('common.add_client') ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Alerts -->
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

<?php include APP_PATH . '/includes/footer.php'; ?>