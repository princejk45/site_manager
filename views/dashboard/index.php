<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= __('dashboard.title') ?></h1>
                </div>
            </div>
        </div>
    </section>
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['message'] ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">

                <!-- All Websites Small Box -->
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= $totalWebsites ?></h3>
                            <p><?= __('dashboard.all_services') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <a href="index.php?action=websites&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="small-box-footer">
                            <?= __('dashboard.more_info') ?> <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Expiring Websites Small Box -->
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= $expiringWebsitesCount ?></h3>
                            <p><?= __('dashboard.services_expiring') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <a href="index.php?action=websites&sort=expiry_date&order=asc&search=&per_page=10&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                            class="small-box-footer">
                            <?= __('dashboard.more_info') ?> <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <!-- All Hosting Small Box -->
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= $totalHosting ?></h3>
                            <p><?= __('dashboard.all_clients') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <a href="index.php?action=hosting&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="small-box-footer">
                            <?= __('dashboard.more_info') ?> <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Expiring Hosting Small Box -->
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3><?= $buggyWebsitesCount ?></h3>
                            <p><small><?= __('dashboard.services_with_issues') ?></small></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <a href="#scaduti" class="small-box-footer">
                            <?= __('dashboard.see_below') ?> <i class="fas fa-arrow-circle-down"></i>
                        </a>
                    </div>
                </div>

                <!-- Free Domains Small Box -->
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-light">
                        <div class="inner">
                            <h3><?= $liberiCount ?></h3>
                            <p><?= __('dashboard.free_domains') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chain-broken"></i>
                        </div>
                        <a href="index.php?action=hosting&do=services&id=29<?= '&lang=' . ($_SESSION['lang'] ?? 'it') ?>" class="small-box-footer">
                            <?= __('dashboard.more_info') ?> <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Expired Services -->
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= $expiredWebsitesCount ?></h3>
                            <p><?= __('dashboard.expired_services') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                        <a href="#scaduti" class="small-box-footer">
                            <?= __('dashboard.see_below') ?> <i class="fas fa-arrow-circle-down"></i>
                        </a>
                    </div>
                </div>

            </div>

            <style>
                .view-more-link {
                    color: #6c757d;
                    text-decoration: none;
                    font-style: italic;
                    transition: all 0.3s ease;
                }

                .view-more-link:hover {
                    color: #007bff;
                    text-decoration: underline;
                }

                .view-more-link i {
                    margin-right: 5px;
                }

                .table-sm-text {
                    font-size: 0.85rem;
                }

                .table-sm-text th,
                .table-sm-text td {
                    padding: 0.5rem 0.8rem;
                }

                .badge-scaduto {
                    background-color: #dc3545;
                    color: white;
                }

                .badge-in-scadenza {
                    background-color: #ffc107;
                    color: #212529;
                }

                .badge-info {
                    min-width: 25px;
                    display: inline-block;
                }

                .text-center {
                    text-align: center;
                }
            </style>

            <!-- First Row of Tables -->
            <div class="row mt-4">
                <!-- Expiring Services Table -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-dark">
                            <h3 class="card-title text-white mb-0">
                                <?= __('dashboard.expiring_in_30_days') ?>
                                <span class="badge badge-warning"><?= $expiringWebsitesCount ?></span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($expiringWebsites)): ?>
                                <p><?= __('dashboard.no_expiring_services') ?></p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-sm-text">
                                        <thead>
                                            <tr>
                                                <th><?= __('dashboard.name') ?></th>
                                                <th><?= __('dashboard.services') ?></th>
                                                <th><?= __('dashboard.expiring_date') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="expiringTableBody">
                                            <?php foreach (array_slice($expiringWebsites, 0, 2) as $website): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($website['name']) ?></td>
                                                    <td>
                                                        <a href="index.php?action=websites&do=view&id=<?= $website['id'] ?>">
                                                            <?= htmlspecialchars($website['domain']) ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-in-scadenza">
                                                            <?= htmlspecialchars($website['expiry_date']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Hidden rows -->
                                            <?php foreach (array_slice($expiringWebsites, 2) as $website): ?>
                                                <tr class="d-none more-expiring-rows">
                                                    <td><?= htmlspecialchars($website['name']) ?></td>
                                                    <td>
                                                        <a href="index.php?action=websites&do=view&id=<?= $website['id'] ?>">
                                                            <?= htmlspecialchars($website['domain']) ?>
                                                        </a>
                                                    </td>

                                                    <td>
                                                        <span class="badge badge-in-scadenza">
                                                            <?= htmlspecialchars($website['expiry_date']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($expiringWebsites) > 2): ?>
                                    <div class="text-center mt-2">
                                        <a href="#" class="view-more-link" data-target="expiring">
                                            <i class="fas fa-chevron-down"></i> <?= __('dashboard.view_all') ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Buggy Service Table -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-dark">
                            <h3 class="card-title text-white mb-0">
                                <?= __('dashboard.buggy_services') ?>
                                <span class="badge badge-danger"><?= $buggyWebsitesCount ?></span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($buggyWebsites)): ?>
                                <p><?= __('dashboard.no_buggy_services') ?></p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-sm-text">
                                        <thead>
                                            <tr>
                                                <th><?= __('dashboard.client') ?></th>
                                                <th><?= __('dashboard.services') ?></th>
                                                <th><?= __('dashboard.bugs') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="buggyTableBody">
                                            <?php foreach (array_slice($buggyWebsites, 0, 2) as $website): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($website['hosting_server'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <a href="index.php?action=websites&do=view&id=<?= $website['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                                            <?= htmlspecialchars($website['domain']) ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-warning">
                                                            <?= __('dashboard.yes') ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Hidden rows -->
                                            <?php foreach (array_slice($buggyWebsites, 2) as $website): ?>
                                                <tr class="d-none more-buggy-rows">
                                                    <td><?= htmlspecialchars($website['hosting_server'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <a href="index.php?action=websites&do=view&id=<?= $website['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                                            <?= htmlspecialchars($website['domain']) ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-warning">
                                                            <?= __('dashboard.yes') ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($buggyWebsites) > 2): ?>
                                    <div class="text-center mt-2">
                                        <a href="#" class="view-more-link" data-target="buggy">
                                            <i class="fas fa-chevron-down"></i> <?= __('dashboard.view_all') ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Second Row of Tables -->
            <div class="row mt-4">
                <!-- Expired Services Table -->
                <div class="col-md-6" id="scaduti">
                    <div class="card">
                        <div class="card-header bg-danger">
                            <h3 class="card-title text-white mb-0">
                                <?= __('dashboard.expired_services') ?>
                                <span class="badge badge-light"><?= $expiredWebsitesCount ?></span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($expiredWebsites)): ?>
                                <p><?= __('dashboard.no_expired_services') ?></p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-sm-text">
                                        <thead>
                                            <tr>
                                                <th><?= __('dashboard.client') ?></th>
                                                <th><?= __('dashboard.services') ?></th>
                                                <th><?= __('dashboard.expiring_date') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="expiredTableBody">
                                            <?php foreach (array_slice($expiredWebsites, 0, 2) as $website): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($website['hosting_server'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <a href="index.php?action=websites&do=view&id=<?= $website['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                                            <?= htmlspecialchars($website['domain']) ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-scaduto">
                                                            <?= htmlspecialchars($website['expiry_date']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Hidden rows -->
                                            <?php foreach (array_slice($expiredWebsites, 2) as $website): ?>
                                                <tr class="d-none more-expired-rows">
                                                    <td><?= htmlspecialchars($website['hosting_server'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <a href="index.php?action=websites&do=view&id=<?= $website['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                                            <?= htmlspecialchars($website['domain']) ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-scaduto">
                                                            <?= htmlspecialchars($website['expiry_date']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($expiredWebsites) > 2): ?>
                                    <div class="text-center mt-2">
                                        <a href="#" class="view-more-link" data-target="expired">
                                            <i class="fas fa-chevron-down"></i> <?= __('dashboard.view_all') ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Hosting Summary Table -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary">
                            <h3 class="card-title text-white mb-0">
                                <?= __('dashboard.hosting_summary') ?>
                                <span class="badge badge-light"><?= $totalHosting ?></span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($hostingWithCounts)): ?>
                                <p><?= __('dashboard.no_clients_found') ?></p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-sm-text">
                                        <thead>
                                            <tr>
                                                <th><?= __('dashboard.client') ?></th>
                                                <th><?= __('dashboard.vat_number') ?></th>
                                                <th><?= __('dashboard.associated_services') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="hostingSummaryTableBody">
                                            <?php foreach (array_slice($hostingWithCounts, 0, 2) as $hosting): ?>
                                                <tr>
                                                    <td>
                                                        <a href="index.php?action=hosting&do=view&id=<?= $hosting['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                                            <?= htmlspecialchars($hosting['server_name']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($hosting['provider'] ?? 'N/A') ?></td>
                                                    <td class="text-center">
                                                        <a href="index.php?action=hosting&do=services&id=<?= $hosting['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                                            <span class="badge badge-info">
                                                                <?= $hosting['service_count'] ?>
                                                            </span>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Hidden rows -->
                                            <?php foreach (array_slice($hostingWithCounts, 2) as $hosting): ?>
                                                <tr class="d-none more-hosting-rows">
                                                    <td>
                                                        <a href="index.php?action=hosting&do=view&id=<?= $hosting['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                                            <?= htmlspecialchars($hosting['server_name']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($hosting['provider'] ?? 'N/A') ?></td>
                                                    <td class="text-center">
                                                        <a href="index.php?action=hosting&do=services&id=<?= $hosting['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                                                            <span class="badge badge-info">
                                                                <?= $hosting['service_count'] ?>
                                                            </span>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($hostingWithCounts) > 2): ?>
                                    <div class="text-center mt-2">
                                        <a href="#" class="view-more-link" data-target="hosting">
                                            <i class="fas fa-chevron-down"></i> <?= __('dashboard.view_all') ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>
<!-- /.content-wrapper -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const viewMoreLinks = document.querySelectorAll('.view-more-link');

        viewMoreLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('data-target');
                const icon = this.querySelector('i');
                const rows = document.querySelectorAll(`.more-${target}-rows`);

                if (this.classList.contains('expanded')) {
                    // Collapse the table
                    rows.forEach(row => row.classList.add('d-none'));
                    icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                    this.innerHTML = '<i class="fas fa-chevron-down"></i> <?= __('dashboard.view_all') ?>';
                    this.classList.remove('expanded');
                } else {
                    // Expand the table
                    rows.forEach(row => row.classList.remove('d-none'));
                    icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                    this.innerHTML = '<i class="fas fa-chevron-up"></i> Mostra meno';
                    this.classList.add('expanded');
                }
            });
        });
    });
</script>
<?php include APP_PATH . '/includes/footer.php'; ?>