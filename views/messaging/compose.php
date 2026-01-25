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
                    <h1><?= __('messaging.create_new_message') ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="?action=messaging&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('common.back') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <form action="?action=messaging&do=send&lang=<?= $_SESSION['lang'] ?? 'it' ?>" method="post">
                        <div class="form-group">
                            <label><?= __('messaging.subject') ?></label>
                            <input type="text" name="subject" class="form-control" required>
                        </div>

                        <!-- Recipients Selection with Chips/Tags -->
                        <div class="form-group">
                            <label><?= __('messaging.recipients') ?></label>

                            <!-- Display selected recipients as chips -->
                            <div id="selectedRecipients" class="mb-3" style="min-height: 40px;">
                                <!-- Chips will be added here by JavaScript -->
                            </div>

                            <!-- User selection dropdown and add button -->
                            <div class="input-group">
                                <select id="userSelect" class="form-control">
                                    <option value=""><?= __('messaging.select_recipient') ?></option>
                                    <?php foreach ($users as $user): ?>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <option value="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                                                <?= htmlspecialchars($user['username']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-primary" id="addRecipientBtn">
                                        <i class="fas fa-plus"></i> <?= __('messaging.add_recipient') ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Hidden input to store selected recipients for form submission -->
                            <input type="hidden" id="recipientsInput" name="recipients[]">
                        </div>

                        <div class="form-group">
                            <label><?= __('messaging.or_group') ?></label>
                            <select class="form-control" id="groupSelect" name="group_id">
                                <option value=""><?= __('messaging.select_group') ?></option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?= $group['id'] ?>">
                                        <?= htmlspecialchars($group['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?= __('common.notes') ?></label>
                            <textarea name="content" class="form-control" rows="5" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-success" id="sendBtn">
                            <i class="fas fa-paper-plane"></i> <?= __('messaging.send') ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>

<style>
    .recipient-chip {
        display: inline-block;
        background: #007bff;
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        margin-right: 8px;
        margin-bottom: 8px;
        font-size: 14px;
        align-items: center;
        gap: 8px;
    }

    .recipient-chip .remove-btn {
        cursor: pointer;
        margin-left: 8px;
        font-weight: bold;
        transition: opacity 0.2s;
    }

    .recipient-chip .remove-btn:hover {
        opacity: 0.7;
    }

    #selectedRecipients {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px;
        background: #f8f9fa;
        border-radius: 4px;
        min-height: 40px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectedRecipients = {};
        const userSelect = document.getElementById('userSelect');
        const addBtn = document.getElementById('addRecipientBtn');
        const selectedContainer = document.getElementById('selectedRecipients');
        const groupSelect = document.getElementById('groupSelect');
        const recipientsInput = document.getElementById('recipientsInput');
        const sendBtn = document.getElementById('sendBtn');

        // Add recipient on button click
        addBtn.addEventListener('click', function() {
            const selectedOption = userSelect.options[userSelect.selectedIndex];
            if (!selectedOption.value) {
                alert('<?= __('messaging.select_recipient') ?>');
                return;
            }

            const userId = selectedOption.value;
            const username = selectedOption.dataset.username;

            // Check if already selected
            if (selectedRecipients[userId]) {
                alert(`${username} is already selected`);
                return;
            }

            // Add to selected list
            selectedRecipients[userId] = username;
            renderChips();

            // Reset dropdown
            userSelect.value = '';
        });

        // Add recipient on Enter key
        userSelect.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addBtn.click();
            }
        });

        // Disable group selection when recipients are selected
        function updateFormState() {
            const hasRecipients = Object.keys(selectedRecipients).length > 0;
            const hasGroup = groupSelect.value !== '';

            if (hasRecipients && hasGroup) {
                groupSelect.value = '';
                alert('<?php echo __('messaging.or_group'); ?> - Please choose either recipients or group');
            }

            // Disable group select if recipients selected
            groupSelect.disabled = hasRecipients;
            // Disable recipient select if group selected
            userSelect.disabled = hasGroup;
            addBtn.disabled = hasGroup;

            // Require either recipients or group
            sendBtn.disabled = !hasRecipients && !hasGroup;
        }

        // Render chip elements
        function renderChips() {
            selectedContainer.innerHTML = '';
            for (const userId in selectedRecipients) {
                const chip = document.createElement('div');
                chip.className = 'recipient-chip';
                chip.innerHTML = `
                ${selectedRecipients[userId]}
                <span class="remove-btn" data-user-id="${userId}">
                    <i class="fas fa-times"></i>
                </span>
            `;

                // Remove chip on click
                chip.querySelector('.remove-btn').addEventListener('click', function() {
                    delete selectedRecipients[userId];
                    renderChips();
                    updateFormState();
                });

                selectedContainer.appendChild(chip);
            }
            updateRecipientInput();
            updateFormState();
        }

        // Update hidden input with selected recipients
        function updateRecipientInput() {
            const recipientIds = Object.keys(selectedRecipients);
            if (recipientIds.length > 0) {
                // Create hidden inputs for each recipient
                let existingInputs = document.querySelectorAll('input[name="recipients[]"][type="hidden"]');
                existingInputs.forEach(input => input.remove());

                recipientIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'recipients[]';
                    input.value = id;
                    document.querySelector('form').appendChild(input);
                });
            }
        }

        // Handle group selection
        groupSelect.addEventListener('change', function() {
            if (this.value && Object.keys(selectedRecipients).length > 0) {
                // Clear recipients
                for (const userId in selectedRecipients) {
                    delete selectedRecipients[userId];
                }
                renderChips();
            }
            updateFormState();
        });

        // Initial state
        updateFormState();
    });
</script>