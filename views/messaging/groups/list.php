<?php
include APP_PATH . '/includes/header.php';
// Get unread count for sidebar badge
if (isset($_SESSION['user_id'])) {
    require_once APP_PATH . '/models/MessageThread.php';
    $threadModel = new MessageThread($GLOBALS['pdo']);
    $userThreads = $threadModel->getUserThreads($_SESSION['user_id']);
    $unreadMessageCount = array_sum(array_column($userThreads, 'unread_count'));
} else {
    $unreadMessageCount = 0;
}
?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= __('menu.groups') ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php?action=messaging&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('common.back') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('menu.groups') ?></h3>
                </div>
                <div class="card-body">
                    <?php if (empty($groups)): ?>
                        <div class="alert alert-info">
                            <p><?= __('common.leave_empty') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th><?= __('common.type') ?></th>
                                        <th><?= __('hosting.actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groups as $group): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($group['name']) ?></td>
                                            <td>
                                                <a href="index.php?action=messaging&do=compose&group_id=<?= $group['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                    class="btn btn-sm btn-primary">
                                                    <i class="fas fa-envelope"></i> <?= __('messaging.send') ?>
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
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>