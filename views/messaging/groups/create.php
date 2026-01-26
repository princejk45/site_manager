<?php
include APP_PATH . '/includes/header.php';
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
                    <h1><?= __('messaging.create_group') ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php?action=messaging&do=groups&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-secondary">
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
                    <h3 class="card-title"><?= __('messaging.create_group') ?></h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($_SESSION['flash_error'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
                    <?php endif; ?>

                    <form method="post" action="index.php?action=messaging&do=groups_store">
                        <div class="form-group">
                            <label><?= __('messaging.group_name') ?></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label><?= __('messaging.group_members') ?></label>
                            <div style="max-height:300px; overflow:auto; border:1px solid #eee; padding:10px;">
                                <?php foreach ($users as $u): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="members[]" value="<?= $u['id'] ?>" id="user_<?= $u['id'] ?>">
                                        <label class="form-check-label" for="user_<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> &lt;<?= htmlspecialchars($u['email'] ?? '') ?>&gt;</label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button class="btn btn-primary" type="submit"><?= __('common.save') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>
