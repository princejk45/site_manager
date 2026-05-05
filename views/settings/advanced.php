<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>
<?php
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= __('settings.advanced_title') ?></h1>
                </div>
            </div>
        </div>

            <!-- WordPress Configuration & Database Setup Section -->
            <div class="row mt-4" id="migrations">
                <div class="col-md-6">
                    <div class="card card-info">
                        <div class="card-header">
                            <h5 class="m-0"><i class="fas fa-wordpress mr-2"></i>WordPress Integration</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Configure API keys for remote WordPress site diagnostics.</p>
                            <a href="index.php?action=settings&do=wordpress&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-info btn-block">
                                <i class="fas fa-cog mr-2"></i>Configure API Keys
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h5 class="m-0"><i class="fas fa-database mr-2"></i>Database Setup</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Create WordPress integration tables. Creates missing tables only, preserves existing data.</p>
                            <button type="button" class="btn btn-warning btn-block" id="migrateDbBtn">
                                <i class="fas fa-sync-alt mr-2"></i>Run Migrations
                            </button>
                            <div id="migrationResult" class="mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle database migration
        const migrateDbBtn = document.getElementById('migrateDbBtn');
        if (migrateDbBtn) {
            migrateDbBtn.addEventListener('click', async function() {
                if (!confirm('Run database migrations? This will create missing tables without affecting existing data.')) {
                    return;
                }

                migrateDbBtn.disabled = true;
                migrateDbBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Running...';

                try {
                    const response = await fetch('index.php?action=settings&do=migrate_database', {
                        method: 'GET',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                    const result = await response.json();

                    let resultHtml = `<div class="alert alert-${result.success ? 'success' : 'danger'} alert-dismissible fade show">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <h5>${result.success ? '<i class="fas fa-check-circle mr-2"></i>Migration Complete' : '<i class="fas fa-exclamation-circle mr-2"></i>Migration Failed'}</h5>`;

                    if (result.tables && result.tables.length > 0) {
                        resultHtml += '<ul class="mb-0">';
                        result.tables.forEach(table => {
                            const icon = table.status === 'created' ? '✓' : '→';
                            resultHtml += `<li><strong>${icon} ${table.name}</strong>: ${table.reason}</li>`;
                        });
                        resultHtml += '</ul>';
                    }

                    if (result.errors && result.errors.length > 0) {
                        resultHtml += '<div class="mt-2"><strong>Errors:</strong><ul>';
                        result.errors.forEach(err => {
                            resultHtml += `<li>${err}</li>`;
                        });
                        resultHtml += '</ul></div>';
                    }

                    resultHtml += '</div>';
                    document.getElementById('migrationResult').innerHTML = resultHtml;

                } catch (error) {
                    console.error('Error:', error);
                    document.getElementById('migrationResult').innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            Migration error: ${error.message}
                        </div>`;
                } finally {
                    migrateDbBtn.disabled = false;
                    migrateDbBtn.innerHTML = '<i class="fas fa-database mr-2"></i>Run Migrations';
                }
            });
        }
    });
</script>

<?php include APP_PATH . '/includes/footer.php'; ?>
