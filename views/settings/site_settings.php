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
                    <h1 class="m-0"><?= __('site_settings.title') ?></h1>
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

            <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php endif; ?>

            <!-- Basic Settings Card -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><?= __('site_settings.title') ?></h3>
                        </div>

                        <form method="POST" action="index.php?action=settings&do=save_site_settings&lang=<?= $_SESSION['lang'] ?? 'it' ?>" enctype="multipart/form-data">
                            <div class="card-body">
                                <!-- Site Name -->
                                <div class="form-group">
                                    <label for="site_name"><?= __('site_settings.site_name') ?> *</label>
                                    <input type="text" class="form-control" id="site_name" name="site_name"
                                        value="<?= htmlspecialchars($settings['site_name'] ?? APP_NAME) ?>" required>
                                    <small class="form-text text-muted"><?= __('common.enter_notes') ?></small>
                                </div>

                                <!-- Logo Section -->
                                <div class="form-group">
                                    <label><?= __('site_settings.logo_path') ?> *</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <!-- File Upload Button -->
                                            <div class="form-group mb-0">
                                                <label class="btn btn-primary btn-sm" style="cursor: pointer; display: inline-block; margin-bottom: 10px;">
                                                    <i class="fas fa-upload"></i> <?= __('common.add') ?>
                                                    <input type="file" name="logo" id="logo_upload" accept="image/*" style="display: none;">
                                                </label>
                                                <small class="form-text text-muted d-block">PNG, JPG (max 2MB)</small>
                                                <input type="hidden" name="current_logo" value="<?= htmlspecialchars($settings['logo_path'] ?? 'assets/images/logo.png') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <!-- Logo Preview -->
                                            <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px; text-align: center; min-height: 120px; display: flex; align-items: center; justify-content: center;">
                                                <img id="logoPreview" 
                                                    src="<?= htmlspecialchars($settings['logo_path'] ?? 'assets/images/logo.png') ?>" 
                                                    alt="Logo Preview" 
                                                    style="max-width: 100%; max-height: 120px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Company Name -->
                                <div class="form-group">
                                    <label for="company_name"><?= __('site_settings.company_name') ?></label>
                                    <input type="text" class="form-control" id="company_name" name="company_name"
                                        value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>">
                                    <small class="form-text text-muted"><?= __('common.enter_notes') ?></small>
                                </div>

                                <!-- Company Email -->
                                <div class="form-group">
                                    <label for="company_email"><?= __('site_settings.company_email') ?></label>
                                    <input type="email" class="form-control" id="company_email" name="company_email"
                                        value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
                                    <small class="form-text text-muted"><?= __('common.leave_empty') ?></small>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?= __('settings.save_settings') ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Email Header/Footer Section -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><?= __('site_settings.email_header_footer') ?></h3>
                            <small class="ml-2 text-muted"><?= __('site_settings.email_header_footer_note') ?></small>
                        </div>

                        <form method="POST" action="index.php?action=settings&do=save_email_header_footer&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                            <div class="card-body">
                                <!-- Email Header -->
                                <div class="form-group">
                                    <label for="email_global_header"><?= __('email_templates.header') ?></label>
                                    <textarea class="form-control ckeditor-editor" id="email_global_header" name="email_global_header"><?= htmlspecialchars($settings['email_global_header'] ?? '') ?></textarea>
                                    <small class="form-text text-muted"><?= __('site_settings.header_note') ?></small>
                                </div>

                                <!-- Email Footer -->
                                <div class="form-group">
                                    <label for="email_global_footer"><?= __('email_templates.footer') ?></label>
                                    <textarea class="form-control ckeditor-editor" id="email_global_footer" name="email_global_footer"><?= htmlspecialchars($settings['email_global_footer'] ?? '') ?></textarea>
                                    <small class="form-text text-muted"><?= __('site_settings.footer_note') ?></small>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?= __('settings.save_settings') ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
    // Handle logo file upload and preview
    document.getElementById('logo_upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('logoPreview').src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // Initialize CKEditor for header and footer
    document.querySelectorAll('.ckeditor-editor').forEach(element => {
        ClassicEditor
            .create(element, {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|',
                        'heading',
                        '|',
                        'bold', 'italic', 'underline',
                        '|',
                        'alignment',
                        '|',
                        'bulletedList', 'numberedList',
                        '|',
                        'link',
                        '|',
                        'removeFormat'
                    ],
                    shouldNotGroupWhenFull: true
                },
                heading: {
                    options: [
                        { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                        { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                    ]
                },
                alignment: {
                    options: ['left', 'center', 'right']
                },
                height: '200px'
            })
            .catch(error => {
                console.error('CKEditor error:', error);
            });
    });
</script>
<?php include APP_PATH . '/includes/footer.php'; ?>