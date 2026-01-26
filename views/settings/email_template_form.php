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
                    <h1 class="m-0"><?= __('email_templates.edit') ?></h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-9">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">
                                <?php if ($template): ?>
                                    <?= htmlspecialchars($template['name']) ?>
                                <?php else: ?>
                                    <?= __('email_templates.error') ?>
                                <?php endif; ?>
                            </h3>
                        </div>

                        <?php if ($template): ?>
                            <form method="POST" class="form-horizontal">
                                <div class="card-body">
                                    <!-- Template Name -->
                                    <div class="form-group">
                                        <label for="name"><?= __('email_templates.name') ?> *</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                            value="<?= htmlspecialchars($template['name']) ?>" required>
                                        <small class="form-text text-muted"><?= __('email_templates.description') ?></small>
                                    </div>

                                    <!-- Slug (Read-only) -->
                                    <div class="form-group">
                                        <label for="slug"><?= __('email_templates.slug') ?></label>
                                        <input type="text" class="form-control" id="slug" 
                                            value="<?= htmlspecialchars($template['slug']) ?>" 
                                            readonly>
                                        <small class="form-text text-muted"><?= __('email_templates.slug_note') ?? 'Cannot be changed' ?></small>
                                    </div>

                                    <!-- Email Subject -->
                                    <div class="form-group">
                                        <label for="subject"><?= __('email_templates.subject') ?> *</label>
                                        <input type="text" class="form-control" id="subject" name="subject"
                                            value="<?= htmlspecialchars($template['subject']) ?>" required>
                                        <small class="form-text text-muted"><?= __('email_templates.subject_note') ?? 'Use variables like {domain}, {days}' ?></small>
                                    </div>

                                    <!-- Email Body (CKEditor) -->
                                    <div class="form-group">
                                        <label for="body"><?= __('email_templates.body') ?> *</label>
                                        <textarea class="form-control ckeditor-editor" id="body" name="body" required><?= htmlspecialchars($template['body']) ?></textarea>
                                        <small class="form-text text-muted"><?= __('email_templates.body_note') ?? 'Main email content' ?></small>
                                    </div>

                                    <!-- Description -->
                                    <div class="form-group">
                                        <label for="description"><?= __('email_templates.description') ?></label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($template['description'] ?? '') ?></textarea>
                                        <small class="form-text text-muted"><?= __('email_templates.description_note') ?? 'Internal notes' ?></small>
                                    </div>

                                    <!-- Status -->
                                    <div class="form-group">
                                        <label for="status"><?= __('common.status') ?></label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="active" <?= ($template['status'] === 'active') ? 'selected' : '' ?>>
                                                <?= __('email_templates.active') ?> (<?= __('email_templates.active_note') ?>)
                                            </option>
                                            <option value="inactive" <?= ($template['status'] === 'inactive') ? 'selected' : '' ?>>
                                                <?= __('email_templates.inactive') ?>
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <?= __('email_templates.save') ?>
                                    </button>
                                    <a href="index.php?action=settings&do=email_templates&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-secondary">
                                        <?= __('email_templates.back_to_list') ?>
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="card-body">
                                <div class="alert alert-danger">
                                    <?= __('email_templates.error') ?>
                                    <a href="index.php?action=settings&do=email_templates&lang=<?= $_SESSION['lang'] ?? 'it' ?>"><?= __('email_templates.back_to_list') ?></a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Column -->
                <div class="col-md-3">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><?= __('email_templates.guide_title') ?></h3>
                        </div>
                        <div class="card-body" style="font-size: 13px; max-height: 600px; overflow-y: auto;">
                            <h5><?= __('email_templates.available_variables') ?></h5>
                            <ul style="list-style-type: none; padding: 0;">
                                <li>✓ {domain}</li>
                                <li>✓ {days}</li>
                                <li>✓ {status_content}</li>
                                <li>✓ {new_expiry}</li>
                                <li>✓ {subject}</li>
                                <li>✓ {content}</li>
                                <li>✓ {sender_name}</li>
                                <li>✓ {thread_link}</li>
                            </ul>
                            
                            <hr>
                            
                            <h5><?= __('email_templates.note_title') ?></h5>
                            <p><small>
                                <?= __('email_templates.note_global_footer') ?>
                            </small></p>

                            <hr>

                            <h5><?= __('email_templates.example_title') ?></h5>
                            <p><small><strong><?= __('email_templates.example_slug') ?>:</strong> website_expiry</small></p>
                            <p><small><strong><?= __('email_templates.example_used_by') ?>:</strong> <?= __('email_templates.example_usage_expiry') ?></small></p>
                        </div>
                    </div>

                    <div class="card card-warning mt-3">
                        <div class="card-header">
                            <h3 class="card-title">⚠️ <?= __('common.note') ?? 'Important' ?></h3>
                        </div>
                        <div class="card-body" style="font-size: 12px;">
                            <p>
                                <strong><?= __('email_templates.slug_warning') ?? 'Do not change the Slug!' ?></strong><br>
                                <?= __('email_templates.slug_warning_desc') ?? 'The slug is used to identify templates. Changing it will break email sending.' ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
    // Initialize CKEditor for all textareas with class 'ckeditor-editor'
    document.querySelectorAll('.ckeditor-editor').forEach(element => {
        ClassicEditor
            .create(element, {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|',
                        'heading',
                        '|',
                        'bold', 'italic', 'underline', 'strikethrough',
                        '|',
                        'alignment',
                        '|',
                        'bulletedList', 'numberedList',
                        '|',
                        'link',
                        '|',
                        'blockQuote',
                        '|',
                        'removeFormat'
                    ],
                    shouldNotGroupWhenFull: true
                },
                heading: {
                    options: [
                        { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                        { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                        { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                    ]
                },
                alignment: {
                    options: ['left', 'center', 'right', 'justify']
                },
                image: {
                    insert: {
                        integrations: ['url']
                    }
                },
                menuBar: {
                    isVisible: false
                },
                height: '400px'
            })
            .catch(error => {
                console.error('CKEditor error:', error);
            });
    });
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>
