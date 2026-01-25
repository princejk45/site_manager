<?php
include APP_PATH . '/includes/header.php';
// Get total unread count for sidebar badge
$totalUnread = array_sum(array_column($threads, 'unread_count'));
$unreadMessageCount = $totalUnread;

// Get participants for each thread
if (isset($_SESSION['user_id'])) {
    require_once APP_PATH . '/models/MessageThread.php';
    $threadModel = new MessageThread($GLOBALS['pdo']);
}
?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>
                        <i class="fas fa-inbox text-primary mr-2"></i><?= __('messaging.title') ?>
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="?action=messaging&do=compose&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?= __('messaging.create_new_message') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (empty($threads)): ?>
                <div class="card card-outline">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h4><?= __('messaging.no_messages') ?? 'No messages yet' ?></h4>
                        <p class="text-muted"><?= __('messaging.start_conversation') ?? 'Start a new conversation' ?></p>
                        <a href="?action=messaging&do=compose&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-primary mt-3">
                            <i class="fas fa-pen"></i> <?= __('messaging.compose') ?? 'Compose Message' ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card card-outline">
                    <div class="card-header with-border">
                        <h3 class="card-title">
                            <i class="fas fa-list mr-2"></i><?= count($threads) ?> <?= __('messaging.threads') ?? 'Threads' ?>
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($threads as $thread): ?>
                                <?php
                                $participants = isset($threadModel) ? $threadModel->getThreadParticipantsWithDetails($thread['id']) : [];
                                $otherParticipants = array_filter($participants, function ($p) {
                                    return $p['id'] != $_SESSION['user_id'];
                                });
                                ?>
                                <a href="?action=messaging&do=view&id=<?= $thread['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="list-group-item list-group-item-action px-4 py-3 thread-item" style="transition: all 0.3s ease;">
                                    <div class="row align-items-center">
                                        <!-- Avatars -->
                                        <div class="col-auto mr-3">
                                            <div class="avatar-stack" style="display: flex; gap: -10px;">
                                                <?php
                                                $shown = 0;
                                                foreach (array_slice($otherParticipants, 0, 2) as $participant):
                                                    if ($shown < 2):
                                                ?>
                                                        <div class="avatar" style="
                                                        width: 40px;
                                                        height: 40px;
                                                        border-radius: 50%;
                                                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                                        display: flex;
                                                        align-items: center;
                                                        justify-content: center;
                                                        color: white;
                                                        font-weight: bold;
                                                        border: 2px solid white;
                                                        margin-left: -10px;
                                                        font-size: 14px;
                                                    " title="<?= htmlspecialchars($participant['username']) ?>">
                                                            <?= strtoupper(substr($participant['username'], 0, 1)) ?>
                                                        </div>
                                                    <?php $shown++;
                                                    endif; ?>
                                                <?php endforeach; ?>
                                                <?php if (count($otherParticipants) > 2): ?>
                                                    <div class="avatar" style="
                                                        width: 40px;
                                                        height: 40px;
                                                        border-radius: 50%;
                                                        background: #e0e0e0;
                                                        display: flex;
                                                        align-items: center;
                                                        justify-content: center;
                                                        color: #666;
                                                        font-weight: bold;
                                                        border: 2px solid white;
                                                        margin-left: -10px;
                                                        font-size: 12px;
                                                    " title="<?= count($otherParticipants) - 2 ?> more">
                                                        +<?= count($otherParticipants) - 2 ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Thread Content -->
                                        <div class="col">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <div class="d-flex align-items-center">
                                                        <span class="font-weight-bold" style="font-size: 16px;">
                                                            <?php if ($thread['unread_count'] > 0): ?>
                                                                <i class="fas fa-circle text-danger" style="font-size: 8px; margin-right: 8px;"></i>
                                                            <?php endif; ?>
                                                            <?= htmlspecialchars($thread['subject']) ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-muted mb-1 text-truncate" style="max-width: 600px;">
                                                        <small><?= nl2br(substr(htmlspecialchars($thread['last_message'] ?? ''), 0, 80)) ?>...</small>
                                                    </p>
                                                    <div class="text-muted" style="font-size: 12px;">
                                                        <small>
                                                            <strong><?= htmlspecialchars($thread['last_sender'] ?? 'Unknown') ?></strong>
                                                            <span class="mx-1">•</span>
                                                            <span><?= date('M j, H:i', strtotime($thread['created_at'])) ?></span>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Unread Badge -->
                                        <div class="col-auto">
                                            <?php if ($thread['unread_count'] > 0): ?>
                                                <span class="badge badge-danger badge-lg" style="padding: 8px 10px; font-size: 14px;">
                                                    <?= $thread['unread_count'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-light text-muted">
                                                    <i class="fas fa-check-circle"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<style>
    .thread-item {
        border-left: 4px solid #f0f0f0;
        transition: all 0.3s ease;
    }

    .thread-item:hover {
        background-color: #f9f9f9 !important;
        border-left-color: #667eea;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .thread-item.unread {
        background-color: #f0f7ff;
    }

    .avatar-stack {
        display: flex;
        margin-left: 0;
    }

    .avatar-stack .avatar {
        margin-left: -12px;
        border: 2px solid white;
    }

    .avatar-stack .avatar:first-child {
        margin-left: 0;
    }
</style>

<?php include APP_PATH . '/includes/footer.php'; ?>