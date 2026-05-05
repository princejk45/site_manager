<?php
/**
 * Automation Center — Edit view (GET fallback)
 * Redirects to index with the rule data so the modal can open.
 * The main flow is POST → DashboardController::automationEdit() which saves and redirects.
 * GET requests to do=edit are handled by including the index view with $editRule set.
 */

$pageTitle = __('automation.update_rule');

// Expose the rule as $editRule for JS auto-open
$editRule = $rule ?? [];

// Borrow the index view — it handles the case where $editRule is set
include APP_PATH . '/views/automation/index.php';
