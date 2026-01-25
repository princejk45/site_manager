<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= __('common.edit_user') ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php?action=users&do=create&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?= __('common.new_user') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('common.edit_user') ?></h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th><?= __('common.username') ?></th>
                                <th><?= __('common.role') ?></th>
                                <th><?= __('common.email') ?></th>
                                <th><?= __('common.status') ?></th>
                                <th><?= __('hosting.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= ucfirst($user['role']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $user['is_active'] ? __('common.active') : __('common.inactive') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="index.php?action=users&do=edit&id=<?= $user['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                            class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i> <?= __('hosting.edit') ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>

<style>
    .table {
        width: 100%;
        margin-bottom: 1rem;
        background-color: transparent;
    }

    .table th {
        background-color: #f8f9fa;
    }

    .badge {
        padding: 0.35em 0.65em;
        font-size: 0.75em;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
    }

    .bg-success {
        background-color: #28a745 !important;
    }

    .bg-secondary {
        background-color: #6c757d !important;
    }
</style>