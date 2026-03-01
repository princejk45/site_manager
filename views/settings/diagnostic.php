<?php
/**
 * Diagnostic page for Google Sheets integration
 * Displays sheet information, matching details, and API connectivity
 */
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= __('settings.diagnostic_title') ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php"><?= __('settings.home') ?></a></li>
                        <li class="breadcrumb-item"><a href="index.php?action=settings&do=advanced"><?= __('settings.title') ?></a></li>
                        <li class="breadcrumb-item active"><?= __('settings.sheet_diagnostic') ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <!-- Stored Settings Card -->
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><?= __('settings.stored_settings') ?></h3>
                        </div>
                        <div class="card-body">
                            <pre><?= htmlspecialchars($storedSettingsInfo ?? __('settings.no_data_available')) ?></pre>
                        </div>
                    </div>

                    <!-- Google Connection Card -->
                    <div class="card <?= strpos($googleConnectionInfo ?? '', 'ERROR') !== false ? 'card-danger' : 'card-success' ?>">
                        <div class="card-header">
                            <h3 class="card-title"><?= __('settings.google_connection') ?></h3>
                        </div>
                        <div class="card-body">
                            <pre><?= htmlspecialchars($googleConnectionInfo ?? __('settings.no_data_available')) ?></pre>
                        </div>
                    </div>

                    <!-- Available Sheets Card -->
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><?= __('settings.available_sheets') ?></h3>
                        </div>
                        <div class="card-body">
                            <pre><?= htmlspecialchars($availableSheetsInfo ?? __('settings.no_sheets_available')) ?></pre>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?= __('settings.actions') ?></h3>
                        </div>
                        <div class="card-body">
                            <a href="index.php?action=settings&do=advanced" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> <?= __('settings.back_to_settings') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
