<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= __('wordpress.title') ?? 'WordPress Integration' ?></h1>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($_SESSION['message']) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['warning'])): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?= htmlspecialchars($_SESSION['warning']) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <div class="mt-3">
                        <p><strong><?= __('wordpress.database_setup_required') ?></strong></p>
                        <a href="index.php?action=settings&do=advanced&lang=<?= $_SESSION['lang'] ?? 'it' ?>#migrations" class="btn btn-warning btn-sm">
                            <i class="fas fa-database mr-2"></i><?= __('wordpress.go_to_database_setup') ?>
                        </a>
                    </div>
                </div>
                <?php unset($_SESSION['warning']); ?>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Info Card -->
            <div class="callout callout-info">
                <h5><?= __('wordpress.integration') ?></h5>
                <p><?= __('wordpress.description') ?></p>
                <p><small><?= __('wordpress.description_note') ?></small></p>
            </div>

            <!-- WordPress Sites List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('wordpress.configured_sites') ?></h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool btn-sm" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($wordpressSites)): ?>
                        <p class="text-muted"><?= __('wordpress.no_sites_configured') ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th><?= __('wordpress.website_name') ?></th>
                                        <th><?= __('wordpress.website_url') ?></th>
                                        <th><?= __('wordpress.api_key_masked') ?></th>
                                        <th><?= __('wordpress.status') ?></th>
                                        <th><?= __('wordpress.last_fetch') ?></th>
                                        <th><?= __('wordpress.actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wordpressSites as $site): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($site['website_domain'] ?? '') ?></strong>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars($site['wordpress_url']) ?></small>
                                            </td>
                                            <td>
                                                <code class="text-muted small">
                                                    <?= substr($site['api_key'], 0, 10) ?>...<?= substr($site['api_key'], -6) ?>
                                                </code>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBadge = [
                                                    'healthy' => 'success',
                                                    'degraded' => 'warning',
                                                    'auth_failed' => 'danger',
                                                    'unreachable' => 'danger',
                                                    'invalid_response' => 'warning',
                                                    'timeout' => 'warning',
                                                    null => 'secondary'
                                                ];
                                                $badgeClass = $statusBadge[$site['last_fetch_status']] ?? 'secondary';
                                                $statusKey = 'wordpress.' . ($site['last_fetch_status'] ?? 'not_tested');
                                                $statusLabel = __($statusKey) ?? ucfirst(str_replace('_', ' ', $site['last_fetch_status'] ?? 'Not tested'));
                                                ?>
                                                <span class="badge badge-<?= $badgeClass ?>">
                                                    <?= $statusLabel ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= $site['last_fetch_timestamp'] ? date('d/m/Y H:i', strtotime($site['last_fetch_timestamp'])) : __('wordpress.never') ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="index.php?action=settings&do=wordpress_edit&id=<?= $site['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                        class="btn btn-sm btn-primary" title="<?= __('wordpress.edit') ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="index.php?action=settings&do=wordpress_delete&id=<?= $site['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                        class="btn btn-sm btn-danger confirmable-delete" data-name="<?= htmlspecialchars($site['website_domain'] ?? '') ?>"
                                                        title="<?= __('wordpress.delete') ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add/Edit Form -->
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">
                        <?php if ($editingId ?? false): ?>
                            <?= __('wordpress.update_configuration') ?>
                        <?php else: ?>
                            <?= __('wordpress.add_configuration') ?>
                        <?php endif; ?>
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>

                <form method="POST" action="index.php?action=settings&do=wordpress_save&lang=<?= $_SESSION['lang'] ?? 'it' ?>">
                    <?php if ($editingId ?? false): ?>
                        <input type="hidden" name="wordpress_site_id" value="<?= htmlspecialchars($editingId) ?>">
                    <?php endif; ?>

                    <div class="card-body">
                        <div class="form-group">
                            <label for="website_search"><?= __('wordpress.select_domain') ?> <span class="text-danger">*</span></label>
                            <div style="position: relative; z-index: 100;">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="website_search" 
                                        placeholder="<?= __('wordpress.search_placeholder') ?>" 
                                        autocomplete="off">
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Autocomplete dropdown suggestions -->
                                <div id="website_suggestions" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 250px; overflow-y: auto; z-index: 1050; display: none; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><?= __('wordpress.selected_website') ?> <span class="text-danger">*</span></label>
                            <input type="hidden" id="website_id" name="website_id" required>
                            <div id="selected_website" style="padding: 12px 15px; background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; min-height: 40px; display: flex; align-items: center; color: #999;">
                                <?= __('wordpress.no_website_selected') ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="wordpress_url"><?= __('wordpress.wordpress_url') ?> <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="wordpress_url" name="wordpress_url"
                                placeholder="https://example.com" required
                                value="<?= htmlspecialchars($editingUrl ?? '') ?>">
                            <small class="form-text text-muted"><?= __('wordpress.wordpress_url_help') ?></small>
                        </div>

                        <div class="form-group">
                            <label for="api_key"><?= __('wordpress.api_key') ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="api_key" name="api_key"
                                    placeholder="sk_live_..." required
                                    value="<?= htmlspecialchars($editingKey ?? '') ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleApiKey">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted"><?= __('wordpress.api_key_help') ?></small>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                                <?= ($editingActive ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">
                                <?= __('wordpress.is_active') ?>
                            </label>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i>
                            <?php if ($editingId ?? false): ?>
                                <?= __('wordpress.update_configuration_btn') ?>
                            <?php else: ?>
                                <?= __('wordpress.save_configuration') ?>
                            <?php endif; ?>
                        </button>
                        <?php if ($editingId ?? false): ?>
                            <a href="index.php?action=settings&do=wordpress&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-secondary">
                                <i class="fas fa-times mr-2"></i><?= __('wordpress.cancel') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

        </div>
    </section>
</div>

<script>
    // Translation strings
    const translations = {
        noWebsiteSelected: '<?= __('wordpress.no_website_selected') ?>',
        clear: '<?= __('wordpress.clear') ?>',
        delete: '<?= __('wordpress.delete') ?>',
        deleteConfirmation: '<?= __('wordpress.delete_confirmation') ?>'
    };
    
    // Website autocomplete data
    let websitesData = <?= json_encode(array_values(array_map(function($w) {
        return [
            'id' => (int)$w['id'],
            'domain' => $w['domain'],
            'type' => $w['name'] ?? 'N/A'
        ];
    }, $websites ?? []))) ?>;

    const searchInput = document.getElementById('website_search');
    const suggestionsBox = document.getElementById('website_suggestions');
    const hiddenInput = document.getElementById('website_id');
    const selectedWebsiteDisplay = document.getElementById('selected_website');

    // Ensure websitesData is always an array
    if (!Array.isArray(websitesData)) {
        console.error('websitesData is not an array:', websitesData);
        websitesData = [];
    }
    
    console.log('Websites loaded:', websitesData.length, 'domains');

    // Function to render suggestions
    function renderSuggestions(searchTerm = '') {
        const lowerSearchTerm = searchTerm.toLowerCase().trim();

        // Filter websites based on search term
        let matches = websitesData;
        if (lowerSearchTerm.length > 0) {
            matches = websitesData.filter(site => 
                site.domain.toLowerCase().includes(lowerSearchTerm) || 
                site.type.toLowerCase().includes(lowerSearchTerm)
            );
        }

        // Build HTML
        if (matches.length === 0 && lowerSearchTerm.length > 0) {
            suggestionsBox.innerHTML = '<div style="padding: 12px 15px; color: #999; text-align: center;"><?= __('wordpress.no_domains_found') ?></div>';
        } else if (matches.length === 0) {
            suggestionsBox.innerHTML = '<div style="padding: 12px 15px; color: #999; text-align: center;"><?= __('wordpress.no_domains_available') ?></div>';
        } else {
            suggestionsBox.innerHTML = matches.map((site) => {
                let domainHtml = site.domain;
                // Only highlight if there's a search term
                if (lowerSearchTerm.length > 0) {
                    domainHtml = site.domain.replace(
                        new RegExp(lowerSearchTerm, 'gi'), 
                        '<strong style="color: #0066cc;">$&</strong>'
                    );
                }
                return `
                    <div class="autocomplete-item" data-id="${site.id}" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #eee; transition: background-color 0.2s;">
                        <div style="font-weight: 500; color: #333;">${domainHtml}</div>
                        <div style="font-size: 0.85rem; color: #666; margin-top: 2px;">${site.type}</div>
                    </div>
                `;
            }).join('');

            // Add hover and click effects
            suggestionsBox.querySelectorAll('.autocomplete-item').forEach(item => {
                item.addEventListener('mouseover', function() {
                    this.style.backgroundColor = '#f5f5f5';
                });
                item.addEventListener('mouseout', function() {
                    this.style.backgroundColor = 'transparent';
                });
                item.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const website = websitesData.find(w => w.id == id);
                    if (website) {
                        selectWebsite(id, website);
                    }
                });
            });
        }

        suggestionsBox.style.display = 'block';
    }

    // Show dropdown on focus (click in the input)
    searchInput.addEventListener('focus', function() {
        renderSuggestions(this.value);
    });

    // Filter suggestions on input
    searchInput.addEventListener('input', function() {
        renderSuggestions(this.value);
    });

    // Close on escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            suggestionsBox.style.display = 'none';
        }
    });

    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && !suggestionsBox.contains(e.target)) {
            suggestionsBox.style.display = 'none';
        }
    });

    // Select website
    function selectWebsite(id, website) {
        hiddenInput.value = id;
        selectedWebsiteDisplay.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div>
                    <strong style="color: #333; font-size: 1rem;">${website.domain}</strong>
                    <div style="font-size: 0.85rem; color: #666; margin-top: 2px;">${website.type}</div>
                </div>
                <button type="button" id="clearSelection" class="btn btn-sm btn-link text-danger" style="padding: 5px 10px; margin-left: auto;">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        `;
        
        searchInput.value = '';
        suggestionsBox.style.display = 'none';

        document.getElementById('clearSelection').addEventListener('click', clearSelection);
    }

    // Clear selection
    function clearSelection(e) {
        e.preventDefault();
        hiddenInput.value = '';
        selectedWebsiteDisplay.textContent = translations.noWebsiteSelected;
        selectedWebsiteDisplay.style.color = '#999';
        searchInput.value = '';
        searchInput.focus();
    }

    // Keyboard navigation
    let highlightedIndex = -1;
    searchInput.addEventListener('keydown', function(e) {
        const items = suggestionsBox.querySelectorAll('.autocomplete-item');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
            updateHighlight(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, -1);
            updateHighlight(items);
        } else if (e.key === 'Enter' && highlightedIndex >= 0) {
            e.preventDefault();
            items[highlightedIndex].click();
        }
    });

    function updateHighlight(items) {
        items.forEach((item, index) => {
            if (index === highlightedIndex) {
                item.style.backgroundColor = '#e8f0ff';
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.style.backgroundColor = 'transparent';
            }
        });
    }

    // Toggle API key visibility
    document.getElementById('toggleApiKey')?.addEventListener('click', function() {
        const input = document.getElementById('api_key');
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    // Confirm delete
    document.querySelectorAll('.confirmable-delete')?.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm(translations.deleteConfirmation + ' ' + this.dataset.name + '?')) {
                e.preventDefault();
            }
        });
    });

    // Pre-fill if editing
    <?php if ($editingId ?? false): ?>
        const editingWebsite = websitesData.find(w => w.id == <?= $editingWebsiteId ?>);
        if (editingWebsite) {
            selectWebsite(editingWebsite.id, editingWebsite);
        }
    <?php endif; ?>
</script>
