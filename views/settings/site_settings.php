<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}
include APP_PATH . '/includes/header.php';
include APP_PATH . '/includes/sidebar.php';
$lang = $_SESSION['lang'] ?? 'it';
?>

<style>
.ss-hero {
    background: linear-gradient(135deg, #1e3a5f 0%, #2d6a9f 100%);
    border-radius: 12px;
    padding: 28px 32px;
    margin-bottom: 28px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 20px;
}
.ss-hero-icon {
    width: 60px; height: 60px;
    background: rgba(255,255,255,0.15);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px;
    flex-shrink: 0;
}
.ss-hero h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
.ss-hero p  { font-size: 13px; opacity: .75; margin: 0; }

.ss-section-label {
    font-size: 11px; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: #8898aa;
    margin: 28px 0 12px;
    display: flex; align-items: center; gap: 8px;
}
.ss-section-label::after {
    content: ''; flex: 1; height: 1px; background: #e9ecef;
}

.ss-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,.07), 0 4px 16px rgba(0,0,0,.04);
    margin-bottom: 20px;
    overflow: hidden;
}
.ss-card .ss-card-header {
    padding: 16px 22px;
    border-bottom: 1px solid #f0f2f5;
    display: flex; align-items: center; gap: 12px;
}
.ss-card-header-icon {
    width: 36px; height: 36px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; color: #fff; flex-shrink: 0;
}
.ss-card-header-icon.blue   { background: #3b82f6; }
.ss-card-header-icon.teal   { background: #0d9488; }
.ss-card-header-icon.amber  { background: #f59e0b; }
.ss-card-header-icon.violet { background: #7c3aed; }
.ss-card-header-icon.slate  { background: #475569; }
.ss-card-header h3 { font-size: 15px; font-weight: 700; margin: 0; color: #1e293b; }
.ss-card-header p  { font-size: 12px; color: #8898aa; margin: 2px 0 0; }
.ss-card-body { padding: 22px; }

.ss-form-group { margin-bottom: 20px; }
.ss-form-group label {
    font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; display: block;
}
.ss-form-group .form-control {
    border-radius: 8px; border: 1.5px solid #e2e8f0; font-size: 13px;
    padding: 9px 13px; transition: border-color .2s;
}
.ss-form-group .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.ss-form-group .form-text { font-size: 11.5px; color: #9ca3af; margin-top: 5px; }

.logo-upload-zone {
    border: 2px dashed #d1d5db;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    background: #f9fafb;
    position: relative;
}
.logo-upload-zone:hover { border-color: #3b82f6; background: #eff6ff; }
.logo-upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.logo-upload-zone .upload-icon { font-size: 28px; color: #9ca3af; margin-bottom: 6px; }
.logo-upload-zone p { font-size: 12px; color: #6b7280; margin: 0; }

.logo-preview-box {
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
    text-align: center;
    background: #fff;
    min-height: 110px;
    display: flex; align-items: center; justify-content: center;
}
.logo-preview-box img { max-height: 80px; max-width: 100%; object-fit: contain; }

.ss-btn-save {
    background: linear-gradient(135deg, #1e3a5f, #2d6a9f);
    color: #fff; border: none; border-radius: 8px;
    padding: 9px 22px; font-size: 13px; font-weight: 600;
    transition: opacity .2s;
}
.ss-btn-save:hover { opacity: .9; color: #fff; }

.ss-btn-danger {
    background: linear-gradient(135deg, #dc2626, #ef4444);
    color: #fff; border: none; border-radius: 8px;
    padding: 9px 22px; font-size: 13px; font-weight: 600;
    transition: opacity .2s;
}
.ss-btn-danger:hover { opacity: .9; color: #fff; }

.db-status-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 0; border-bottom: 1px solid #f0f2f5;
}
.db-status-row:last-child { border-bottom: none; }
.db-status-row .label { font-size: 13px; font-weight: 500; color: #374151; }
.db-status-row .badge { font-size: 11px; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <!-- Hero -->
            <div class="ss-hero">
                <div class="ss-hero-icon"><i class="fas fa-cog"></i></div>
                <div>
                    <h1><?= __('site_settings.title') ?></h1>
                    <p><?= __('site_settings.subtitle') ?? 'Manage branding, email templates and database configuration.' ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible rounded-lg shadow-sm">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-check-circle mr-2"></i><?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible rounded-lg shadow-sm">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- ─── BRANDING ─────────────────────────────────────────── -->
            <div class="ss-section-label"><i class="fas fa-paint-brush"></i> Branding</div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="ss-card">
                        <div class="ss-card-header">
                            <div class="ss-card-header-icon blue"><i class="fas fa-building"></i></div>
                            <div>
                                <h3><?= __('site_settings.title') ?></h3>
                                <p>Application name, company details and logo</p>
                            </div>
                        </div>
                        <div class="ss-card-body">
                            <form method="POST" action="index.php?action=settings&do=save_site_settings&lang=<?= $lang ?>" enctype="multipart/form-data">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="ss-form-group">
                                            <label for="site_name"><?= __('site_settings.site_name') ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="site_name" name="site_name"
                                                value="<?= htmlspecialchars($settings['site_name'] ?? APP_NAME) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="ss-form-group">
                                            <label for="company_name"><?= __('site_settings.company_name') ?></label>
                                            <input type="text" class="form-control" id="company_name" name="company_name"
                                                value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="ss-form-group">
                                    <label for="company_email"><?= __('site_settings.company_email') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text" style="border-radius:8px 0 0 8px;border:1.5px solid #e2e8f0;border-right:0;">
                                                <i class="fas fa-envelope text-muted"></i>
                                            </span>
                                        </div>
                                        <input type="email" class="form-control" id="company_email" name="company_email"
                                            style="border-radius:0 8px 8px 0;border-left:0;"
                                            value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
                                    </div>
                                    <small class="form-text text-muted"><?= __('common.leave_empty') ?></small>
                                </div>

                                <!-- Logo -->
                                <div class="ss-form-group">
                                    <label><?= __('site_settings.logo_path') ?></label>
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <label class="logo-upload-zone mb-0">
                                                <input type="file" name="logo" id="logo_upload" accept="image/*">
                                                <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                                <p><strong>Click to upload</strong> or drag and drop</p>
                                                <p style="margin-top:3px;">PNG, JPG — max 2 MB</p>
                                            </label>
                                            <input type="hidden" name="current_logo" value="<?= htmlspecialchars($settings['logo_path'] ?? 'assets/images/logo.png') ?>">
                                        </div>
                                        <div class="col-md-5 mt-3 mt-md-0">
                                            <div class="logo-preview-box">
                                                <img id="logoPreview"
                                                    src="<?= htmlspecialchars($settings['logo_path'] ?? 'assets/images/logo.png') ?>"
                                                    alt="Logo Preview">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn ss-btn-save">
                                        <i class="fas fa-save mr-2"></i><?= __('settings.save_settings') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Quick-info sidebar -->
                <div class="col-lg-4">
                    <div class="ss-card">
                        <div class="ss-card-header">
                            <div class="ss-card-header-icon slate"><i class="fas fa-info-circle"></i></div>
                            <div>
                                <h3>Current Configuration</h3>
                                <p>Active values at a glance</p>
                            </div>
                        </div>
                        <div class="ss-card-body" style="padding:16px 20px;">
                            <div class="db-status-row">
                                <span class="label">App Name</span>
                                <span class="badge badge-light"><?= htmlspecialchars($settings['site_name'] ?? APP_NAME) ?></span>
                            </div>
                            <div class="db-status-row">
                                <span class="label">Company</span>
                                <span class="badge badge-light"><?= htmlspecialchars($settings['company_name'] ?? '—') ?></span>
                            </div>
                            <div class="db-status-row">
                                <span class="label">Contact Email</span>
                                <span class="badge badge-light" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($settings['company_email'] ?? '—') ?></span>
                            </div>
                            <div class="db-status-row">
                                <span class="label">Language</span>
                                <span class="badge badge-info"><?= strtoupper($lang) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── EMAIL ─────────────────────────────────────────────── -->
            <div class="ss-section-label"><i class="fas fa-envelope"></i> Email Branding</div>

            <div class="ss-card">
                <div class="ss-card-header">
                    <div class="ss-card-header-icon teal"><i class="fas fa-envelope-open-text"></i></div>
                    <div>
                        <h3><?= __('site_settings.email_header_footer') ?></h3>
                        <p><?= __('site_settings.email_header_footer_note') ?></p>
                    </div>
                </div>
                <div class="ss-card-body">
                    <form method="POST" action="index.php?action=settings&do=save_email_header_footer&lang=<?= $lang ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="ss-form-group">
                                    <label for="email_global_header"><i class="fas fa-arrow-up mr-1 text-muted"></i><?= __('email_templates.header') ?></label>
                                    <textarea class="form-control ckeditor-editor" id="email_global_header" name="email_global_header" rows="6"><?= htmlspecialchars($settings['email_global_header'] ?? '') ?></textarea>
                                    <small class="form-text text-muted"><?= __('site_settings.header_note') ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="ss-form-group">
                                    <label for="email_global_footer"><i class="fas fa-arrow-down mr-1 text-muted"></i><?= __('email_templates.footer') ?></label>
                                    <textarea class="form-control ckeditor-editor" id="email_global_footer" name="email_global_footer" rows="6"><?= htmlspecialchars($settings['email_global_footer'] ?? '') ?></textarea>
                                    <small class="form-text text-muted"><?= __('site_settings.footer_note') ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn ss-btn-save">
                                <i class="fas fa-save mr-2"></i><?= __('settings.save_settings') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ─── DATABASE SETUP ────────────────────────────────────── -->
            <div class="ss-section-label"><i class="fas fa-database"></i> Database</div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="ss-card">
                        <div class="ss-card-header">
                            <div class="ss-card-header-icon amber"><i class="fas fa-database"></i></div>
                            <div>
                                <h3>Database Setup</h3>
                                <p>Create or update missing tables. Preserves all existing data.</p>
                            </div>
                        </div>
                        <div class="ss-card-body">
                            <p class="text-muted" style="font-size:13px;">
                                Run migrations to create any missing WordPress integration tables.
                                This operation is safe to run multiple times — it will only create tables that do not already exist.
                            </p>
                            <button type="button" class="btn ss-btn-danger" id="migrateDbBtn">
                                <i class="fas fa-sync-alt mr-2"></i>Run Migrations
                            </button>
                            <div id="migrationResult" class="mt-3"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="ss-card">
                        <div class="ss-card-header">
                            <div class="ss-card-header-icon violet"><i class="fab fa-wordpress"></i></div>
                            <div>
                                <h3>WordPress Integration</h3>
                                <p>Configure API keys for remote WordPress diagnostics</p>
                            </div>
                        </div>
                        <div class="ss-card-body">
                            <p class="text-muted" style="font-size:13px;">
                                Connect external WordPress sites to this manager by configuring application passwords and API endpoints.
                            </p>
                            <a href="index.php?action=settings&do=wordpress&lang=<?= $lang ?>" class="btn ss-btn-save">
                                <i class="fas fa-cog mr-2"></i>Configure API Keys
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
// Logo preview
document.getElementById('logo_upload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = ev => { document.getElementById('logoPreview').src = ev.target.result; };
        reader.readAsDataURL(file);
    }
});

// CKEditor
document.querySelectorAll('.ckeditor-editor').forEach(el => {
    ClassicEditor.create(el, {
        toolbar: { items: ['undo','redo','|','bold','italic','underline','|','alignment','|','bulletedList','numberedList','|','link','|','removeFormat'], shouldNotGroupWhenFull: true },
        alignment: { options: ['left','center','right'] }
    }).catch(console.error);
});

// Database migration
document.getElementById('migrateDbBtn').addEventListener('click', async function() {
    if (!confirm('Run database migrations? This will create missing tables without affecting existing data.')) return;
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Running…';
    try {
        const resp = await fetch('index.php?action=settings&do=migrate_database', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const r = await resp.json();

        let html = `<div class="alert alert-${r.success ? 'success' : 'danger'} alert-dismissible rounded-lg">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <strong>${r.success ? '<i class="fas fa-check-circle mr-1"></i>Migration Complete' : '<i class="fas fa-exclamation-circle mr-1"></i>Migration Failed'}</strong>`;

        if (r.tables && r.tables.length) {
            html += '<ul class="mb-0 mt-2">';
            r.tables.forEach(t => { html += `<li><strong>${t.status === 'created' ? '✓' : '→'} ${t.name}</strong>: ${t.reason}</li>`; });
            html += '</ul>';
        }
        if (r.errors && r.errors.length) {
            html += '<div class="mt-2"><strong>Errors:</strong><ul>';
            r.errors.forEach(e => { html += `<li>${e}</li>`; });
            html += '</ul></div>';
        }
        html += '</div>';
        document.getElementById('migrationResult').innerHTML = html;
    } catch (err) {
        document.getElementById('migrationResult').innerHTML =
            `<div class="alert alert-danger rounded-lg"><i class="fas fa-exclamation-triangle mr-1"></i>Request failed: ${err.message}</div>`;
    } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Run Migrations';
    }
});
</script>
<?php include APP_PATH . '/includes/footer.php'; ?>