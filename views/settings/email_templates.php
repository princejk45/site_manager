<?php
// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

include APP_PATH . '/includes/header.php';
include APP_PATH . '/includes/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= __('email_templates.title') ?></h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['message'] ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?= __('email_templates.list') ?></h3>
                        </div>

                        <div class="card-body">
                            <?php if (empty($templates)): ?>
                                <div class="alert alert-info">
                                    <?= __('common.no_data') ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>#</th>
                                                <th><?= __('email_templates.name') ?></th>
                                                <th><?= __('email_templates.slug') ?></th>
                                                <th><?= __('email_templates.subject') ?></th>
                                                <th><?= __('email_templates.status') ?></th>
                                                <th><?= __('common.actions') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $i = 1; foreach ($templates as $template): ?>
                                                <tr>
                                                    <td><?= $i++ ?></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($template['name']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <code><?= htmlspecialchars($template['slug']) ?></code>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars(substr($template['subject'], 0, 50)) ?>
                                                        <?php if (strlen($template['subject']) > 50): ?>...<?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($template['status'] === 'active'): ?>
                                                            <span class="badge badge-success"><?= __('email_templates.active') ?></span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger"><?= __('email_templates.inactive') ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="index.php?action=settings&do=edit_email_template&id=<?= $template['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                            class="btn btn-sm btn-primary" title="<?= __('common.edit') ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>

