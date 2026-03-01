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
                        <div style="padding: 10px 15px; background-color: #f8f9fa; border-bottom: 1px solid #ddd; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="checkbox" id="selectAll" style="cursor: pointer;"> 
                            <label for="selectAll" style="cursor: pointer; margin-bottom: 0;"><?= __('messaging.select_all') ?></label>
                            <div id="bulkActions" style="display: none; gap: 8px; margin-left: auto; flex-wrap: wrap;">
                                <button class="btn btn-sm btn-outline-primary" id="btnMarkRead" onclick="bulkMarkRead(true)" style="display: none;"><?= __('messaging.mark_as_read') ?></button>
                                <button class="btn btn-sm btn-outline-warning" id="btnMarkUnread" onclick="bulkMarkRead(false)" style="display: none;"><?= __('messaging.mark_as_unread') ?></button>
                                <button class="btn btn-sm btn-outline-success" id="btnStar" onclick="bulkStar(true)" style="display: none;"><?= __('messaging.star') ?></button>
                                <button class="btn btn-sm btn-outline-warning" id="btnUnstar" onclick="bulkStar(false)" style="display: none;"><?= __('messaging.unstar') ?></button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()"><?= __('messaging.clear_selection') ?></button>
                            </div>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($threads as $thread): ?>
                                <?php
                                $participants = isset($threadModel) ? $threadModel->getThreadParticipantsWithDetails($thread['id']) : [];
                                $otherParticipants = array_filter($participants, function ($p) {
                                    return $p['id'] != $_SESSION['user_id'];
                                });
                                $isStarred = isset($threadModel) ? $threadModel->isStarred($thread['id'], $_SESSION['user_id']) : false;
                                ?>
                                <div class="list-group-item px-4 py-3 thread-item" style="transition: all 0.3s ease; display: flex; align-items: center; gap: 15px;">
                                    <!-- Checkbox -->
                                    <input type="checkbox" class="thread-checkbox" data-thread-id="<?= $thread['id'] ?>" data-starred="<?= $isStarred ? '1' : '0' ?>" style="cursor: pointer; flex-shrink: 0;" onchange="updateBulkUI()">

                                    <!-- Clickable link wrapper for thread content -->
                                    <a href="?action=messaging&do=view&id=<?= $thread['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>" style="flex: 1; text-decoration: none; color: inherit; display: flex; align-items: center; gap: 15px;">
                                        <!-- Avatars -->
                                        <div class="avatar-stack" style="display: flex; gap: -10px; flex-shrink: 0;">
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

                                    <!-- Thread Content -->
                                    <div style="flex: 1;">
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
                                        <div style="flex-shrink: 0;">
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
                                    </a>

                                    <!-- Star button - OUTSIDE link -->
                                    <button class="btn btn-sm btn-link" onclick="toggleStar(<?= $thread['id'] ?>, this); return false;" style="flex-shrink: 0; padding: 0.25rem 0.5rem; margin: 0;">
                                        <i class="fas fa-star" style="font-size: 18px; color: <?= $isStarred ? '#ffc107' : '#ddd' ?>;"></i>
                                    </button>
                                </div>
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
        border: 1px solid #dee2e6;
        border-left: 4px solid #f0f0f0;
        background-color: #fff;
    }

    .thread-item:hover {
        background-color: #f9f9f9 !important;
        border-left-color: #667eea;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .thread-item.unread {
        background-color: #f0f7ff;
    }

    .thread-link {
        display: contents;
    }

    .thread-link:hover {
        text-decoration: none;
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

<script>
function updateBulkUI() {
    const checked = Array.from(document.querySelectorAll('.thread-checkbox:checked'));
    const bulkActions = document.getElementById('bulkActions');
    
    if (checked.length > 0) {
        bulkActions.style.display = 'flex';
        
        // Check if any selected thread is starred
        const anyStarred = checked.some(cb => cb.dataset.starred === '1');
        const anyUnstarred = checked.some(cb => cb.dataset.starred === '0');
        
        // Show/hide star buttons based on selection
        document.getElementById('btnStar').style.display = anyUnstarred ? 'inline-block' : 'none';
        document.getElementById('btnUnstar').style.display = anyStarred ? 'inline-block' : 'none';
        document.getElementById('btnMarkRead').style.display = 'inline-block';
        document.getElementById('btnMarkUnread').style.display = 'inline-block';
    } else {
        bulkActions.style.display = 'none';
    }
}

function clearSelection() {
    document.querySelectorAll('.thread-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateBulkUI();
}

document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.thread-checkbox').forEach(cb => cb.checked = this.checked);
    updateBulkUI();
});

function getSelectedThreadIds() {
    return Array.from(document.querySelectorAll('.thread-checkbox:checked')).map(cb => cb.dataset.threadId);
}

function toggleStar(threadId, button) {
    const formData = new FormData();
    formData.append('thread_id', threadId);

    fetch('?action=messaging&do=toggle_star', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const icon = button.querySelector('i');
            const checkbox = document.querySelector(`.thread-checkbox[data-thread-id="${threadId}"]`);
            if (data.starred) {
                icon.style.color = '#ffc107';
                checkbox.dataset.starred = '1';
            } else {
                icon.style.color = '#ddd';
                checkbox.dataset.starred = '0';
            }
            // Update bulk UI in case checkbox is selected
            updateBulkUI();
        }
    })
    .catch(e => console.error('Error:', e));
}

function bulkMarkRead(isRead) {
    const threadIds = getSelectedThreadIds();
    console.log('Selected thread IDs:', threadIds);
    if (threadIds.length === 0) {
        alert('<?= __('messaging.no_messages') ?>');
        return;
    }

    const formData = new FormData();
    threadIds.forEach(id => formData.append('thread_ids[]', id));
    formData.append('is_read', isRead ? '1' : '0');

    console.log('Sending bulk mark request with:', {
        thread_ids: threadIds,
        is_read: isRead ? '1' : '0'
    });

    fetch('?action=messaging&do=bulk_mark', {
        method: 'POST',
        body: formData
    })
    .then(r => {
        console.log('Response status:', r.status, 'Content-Type:', r.headers.get('content-type'));
        if (!r.ok) {
            throw new Error(`HTTP error! status: ${r.status}`);
        }
        return r.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Refresh the page to show updated unread badges
            location.reload();
        } else {
            console.error('Operation failed:', data.error);
            alert('<?= __('messaging.operation_failed') ?>' + ': ' + (data.error || 'Unknown error'));
        }
    })
    .catch(e => {
        console.error('Error:', e);
        alert('<?= __('messaging.operation_failed') ?>' + ': ' + e.message);
    });
}

function bulkStar(starred) {
    const threadIds = getSelectedThreadIds();
    if (threadIds.length === 0) {
        alert('<?= __('messaging.no_messages') ?>');
        return;
    }

    const formData = new FormData();
    threadIds.forEach(id => formData.append('thread_ids[]', id));
    formData.append('starred', starred ? '1' : '0');

    fetch('?action=messaging&do=bulk_star', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(e => console.error('Error:', e));
}
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>