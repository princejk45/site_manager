<?php
include APP_PATH . '/includes/header.php';
// Get unread count for sidebar badge
if (isset($_SESSION['user_id'])) {
    require_once APP_PATH . '/models/MessageThread.php';
    $threadModel = new MessageThread($GLOBALS['pdo']);
    $userThreads = $threadModel->getUserThreads($_SESSION['user_id']);
    $unreadMessageCount = array_sum(array_column($userThreads, 'unread_count'));

    // Get thread summary and participants
    $threadSummary = $threadModel->getThreadSummary($threadId);
    $participants = $threadModel->getThreadParticipantsWithDetails($threadId);
    $threadCreator = $threadModel->getThreadCreator($threadId);
    $isThreadCreator = $threadCreator == $_SESSION['user_id'];
} else {
    $unreadMessageCount = 0;
    $threadSummary = [];
    $participants = [];
    $threadCreator = null;
    $isThreadCreator = false;
}
?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-8">
                    <h1 class="m-0">
                        <i class="fas fa-comment-dots text-primary mr-2"></i>
                        <?= htmlspecialchars($thread['subject'] ?? __('messaging.title')) ?>
                    </h1>
                    <small class="text-muted mt-2 d-block">
                        <?= count($participants) ?> <?= __('messaging.participants') ?? 'Participants' ?> •
                        <?= $threadSummary['message_count'] ?? 0 ?> <?= __('messaging.messages') ?? 'Messages' ?>
                    </small>
                </div>
                <div class="col-sm-4 text-right">
                    <a href="?action=messaging&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('common.back') ?>
                    </a>
                    <?php if ($isThreadCreator): ?>
                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteThread()">
                        <i class="fas fa-trash"></i> <?= __('messaging.delete_thread') ?? 'Delete Thread' ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Main Chat Area -->
                <div class="col-md-8">
                    <div class="card card-primary card-outline">
                        <!-- Thread Info Header -->
                        <div class="card-header with-border"
                            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h3 class="card-title text-white">
                                <i
                                    class="fas fa-comments mr-2"></i><?= htmlspecialchars($thread['subject'] ?? 'Conversation') ?>
                            </h3>
                        </div>

                        <!-- Messages Area -->
                        <div class="card-body chat-messages"
                            style="height: 550px; overflow-y: auto; background-color: #f5f5f5;">
                            <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                                <p><?= __('messaging.no_messages') ?? 'No messages yet' ?></p>
                            </div>
                            <?php else: ?>
                            <?php
                                $lastSender = null;
                                foreach ($messages as $index => $message):
                                    $isCurrentUser = $message['sender_id'] == $_SESSION['user_id'];
                                    $nextMessage = $messages[$index + 1] ?? null;
                                    $isLastFromSender = !$nextMessage || $nextMessage['sender_id'] != $message['sender_id'];
                                    $showTimestamp = !$lastSender || $lastSender != $message['sender_id'];
                                ?>
                            <div class="message-item mb-3 <?= $isCurrentUser ? 'text-right' : 'text-left' ?>">
                                <div
                                    class="d-flex <?= $isCurrentUser ? 'flex-row-reverse' : 'flex-row' ?> align-items-flex-end gap-2">
                                    <!-- Avatar -->
                                    <div class="message-avatar flex-shrink-0">
                                        <div class="avatar" style="
                                                    width: 40px;
                                                    height: 40px;
                                                    border-radius: 50%;
                                                    background: <?= $isCurrentUser ? '#667eea' : '#764ba2' ?>;
                                                    display: flex;
                                                    align-items: center;
                                                    justify-content: center;
                                                    color: white;
                                                    font-weight: bold;
                                                    font-size: 18px;
                                                ">
                                            <?= strtoupper(substr($message['username'], 0, 1)) ?>
                                        </div>
                                    </div>

                                    <!-- Message Content -->
                                    <div class="message-content" style="max-width: 70%;">
                                        <?php if ($showTimestamp): ?>
                                        <div class="message-info mb-1">
                                            <small class="text-muted font-weight-bold">
                                                <?= htmlspecialchars($message['username']) ?>
                                                <span class="mx-1">•</span>
                                                <span
                                                    title="<?= htmlspecialchars(date('M j, Y H:i:s', strtotime($message['created_at']))) ?>">
                                                    <?= date('H:i', strtotime($message['created_at'])) ?>
                                                </span>
                                            </small>
                                        </div>
                                        <?php endif; ?>

                                        <div class="bubble" style="
                                                    background: <?= $isCurrentUser ? '#667eea' : '#ffffff' ?>;
                                                    color: <?= $isCurrentUser ? '#ffffff' : '#333333' ?>;
                                                    padding: 10px 15px;
                                                    border-radius: 18px;
                                                    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
                                                    border: <?= $isCurrentUser ? 'none' : '1px solid #e0e0e0' ?>;
                                                    word-wrap: break-word;
                                                    line-height: 1.4;
                                                ">
                                            <?= nl2br(htmlspecialchars($message['content'])) ?>
                                        </div>

                                        <?php if ($isLastFromSender && count($messages) > 1): ?>
                                        <div class="message-timestamp text-muted"
                                            style="font-size: 12px; margin-top: 4px;">
                                            <?= date('M j, Y H:i', strtotime($message['created_at'])) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php $lastSender = $message['sender_id']; ?>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Reply Form -->
                        <div class="card-footer">
                            <form action="?action=messaging&do=reply&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                method="post" onsubmit="return validateReply();">
                                <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                                <div class="input-group">
                                    <textarea name="content" id="messageContent" class="form-control"
                                        placeholder="<?= __('messaging.type_message') ?? 'Type your message here...' ?>"
                                        rows="3" style="border-radius: 20px; padding: 10px 15px; resize: none;"
                                        maxlength="5000"></textarea>
                                    <span class="input-group-append">
                                        <button type="submit" class="btn btn-primary"
                                            style="border-radius: 0 20px 20px 0;">
                                            <i class="fas fa-paper-plane"></i> <?= __('messaging.send') ?? 'Send' ?>
                                        </button>
                                    </span>
                                </div>
                                <small class="text-muted form-text d-block mt-2">
                                    <span id="charCount">0</span>/5000 characters
                                </small>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Participants Sidebar -->
                <div class="col-md-4">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users mr-2"></i><?= __('messaging.participants') ?? 'Participants' ?>
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($participants as $participant): ?>
                                <li class="list-group-item d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar mr-2" style="
                                                width: 36px;
                                                height: 36px;
                                                border-radius: 50%;
                                                background: #667eea;
                                                display: flex;
                                                align-items: center;
                                                justify-content: center;
                                                color: white;
                                                font-weight: bold;
                                                font-size: 14px;
                                            ">
                                            <?= strtoupper(substr($participant['username'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-weight-bold small">
                                                <?= htmlspecialchars($participant['username']) ?>
                                                <?php if ($participant['id'] == $_SESSION['user_id']): ?>
                                                <span class="badge badge-primary badge-sm ml-1">(You)</span>
                                                <?php endif; ?>
                                            </div>
                                            <small
                                                class="text-muted"><?= htmlspecialchars($participant['email']) ?></small>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <small
                                            class="badge badge-secondary"><?= $participant['message_count'] ?></small>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Thread Info Card -->
                    <div class="card card-outline card-secondary mt-3">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i
                                    class="fas fa-info-circle mr-2"></i><?= __('messaging.thread_info') ?? 'Thread Info' ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="info-item mb-3">
                                <span
                                    class="text-muted small font-weight-bold"><?= __('messaging.created') ?? 'Created' ?></span>
                                <div>
                                    <?= $threadSummary['created_at'] ? date('M j, Y H:i', strtotime($threadSummary['created_at'])) : date('M j, Y H:i') ?>
                                </div>
                            </div>
                            <div class="info-item mb-3">
                                <span
                                    class="text-muted small font-weight-bold"><?= __('messaging.total_messages') ?? 'Total Messages' ?></span>
                                <div><?= $threadSummary['message_count'] ?? 0 ?></div>
                            </div>
                            <div class="info-item">
                                <span
                                    class="text-muted small font-weight-bold"><?= __('messaging.total_participants') ?? 'Total Participants' ?></span>
                                <div><?= count($participants) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
function validateReply() {
    const content = document.getElementById('messageContent').value.trim();
    if (!content) {
        alert('<?= __("messaging.please_enter_message") ?? "Please enter a message" ?>');
        return false;
    }
    return true;
}

// Character counter
document.getElementById('messageContent').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

// Auto-scroll to bottom
document.addEventListener('DOMContentLoaded', function() {
    const chatArea = document.querySelector('.chat-messages');
    if (chatArea) {
        chatArea.scrollTop = chatArea.scrollHeight;
    }
});
</script>

<style>
.chat-messages {
    padding: 20px;
}

.message-item {
    margin-bottom: 15px;
    animation: slideIn 0.3s ease-in;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 0;
        transform: translateY(0);
    }
}

.bubble {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 18px;
    max-width: 100%;
    word-wrap: break-word;
}

.list-group-item {
    border-left: 4px solid #f0f0f0;
}

.list-group-item:hover {
    background-color: #f9f9f9;
    border-left-color: #667eea;
}
</style>

<script>
function deleteThread() {
    if (!confirm(
            '<?= __('messaging.confirm_delete_thread') ?? 'Are you sure you want to delete this thread? This action cannot be undone.' ?>'
            )) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '?action=messaging&do=delete&lang=<?= $_SESSION['lang'] ?? 'it' ?>';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'thread_id';
    input.value = '<?= $threadId ?>';

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>