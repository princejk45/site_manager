<?php
/**
 * Automation Rules Dashboard
 * 
 * Manage automation rules for websites.
 * Build rules with visual condition editor and action configuration.
 */

// Check feature access
if (!FEATURE_AVAILABLE('automation_rules')) {
    echo '<div class="alert alert-warning">Automation Rules feature is not available on your plan. <a href="plans.php">Upgrade to Professional or Enterprise</a></div>';
    return;
}

$websiteId = $_GET['website_id'] ?? null;
$website = $websiteId ? $websiteModel->getById($websiteId) : null;

if (!$website || !$websiteModel->ownsWebsite($_SESSION['user_id'], $website['id'])) {
    echo '<div class="alert alert-danger">Website not found</div>';
    return;
}
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-0">
                <i class="fas fa-magic"></i>
                Automation Rules
                <small class="text-muted">for <?php echo htmlspecialchars($website['domain']); ?></small>
            </h2>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-primary" id="btn-new-rule">
                <i class="fas fa-plus"></i> New Rule
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="text-muted">Total Rules</h6>
                    <h3 id="total-rules">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="text-muted">Active</h6>
                    <h3 id="active-rules" style="color: #28a745;">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="text-muted">Executions (24h)</h6>
                    <h3 id="executions-24h">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="text-muted">Success Rate</h6>
                    <h3 id="success-rate">0%</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Rules Table -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">Rules</h5>
        </div>
        <div class="card-body">
            <div id="rules-container">
                <div class="text-center text-muted py-5">
                    <i class="fas fa-spinner fa-spin"></i> Loading rules...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New/Edit Rule Modal -->
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ruleModalTitle">New Rule</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="ruleForm">
                    <input type="hidden" id="rule-id">
                    <input type="hidden" id="website-id" value="<?php echo $websiteId; ?>">

                    <!-- Rule Name -->
                    <div class="form-group">
                        <label>Rule Name</label>
                        <input type="text" class="form-control" id="rule-name" placeholder="e.g., Alert when backup fails" required>
                    </div>

                    <!-- Conditions Section -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-filter"></i> Conditions (IF)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="conditions-container"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-condition">
                                <i class="fas fa-plus"></i> Add Condition
                            </button>
                        </div>
                    </div>

                    <!-- Condition Logic -->
                    <div class="form-group">
                        <label>Condition Logic</label>
                        <div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="logic-and" name="condition-logic" value="AND" checked>
                                <label class="custom-control-label" for="logic-and">ALL conditions must be true</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="logic-or" name="condition-logic" value="OR">
                                <label class="custom-control-label" for="logic-or">ANY condition must be true</label>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Section -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-arrow-right"></i> Actions (THEN)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="actions-container"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-action">
                                <i class="fas fa-plus"></i> Add Action
                            </button>
                        </div>
                    </div>

                    <!-- Execution Limits -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-clock"></i> Execution Limits
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Max Executions Per Day</label>
                                        <input type="number" class="form-control" id="execution-limit" value="10" min="1" max="1000">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Cooldown (minutes)</label>
                                        <input type="number" class="form-control" id="cooldown-minutes" value="60" min="0" max="10080">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Status -->
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="rule-active" checked>
                        <label class="custom-control-label" for="rule-active">
                            Rule is active
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-rule">Save Rule</button>
                <button type="button" class="btn btn-info" id="btn-test-rule">Test Rule</button>
            </div>
        </div>
    </div>
</div>

<!-- Available Fields Reference -->
<div class="modal fade" id="fieldsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Available Fields</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6>Diagnostic Components</h6>
                <table class="table table-sm">
                    <tr>
                        <td><code>components.uptime.score</code></td>
                        <td>Uptime score (0-100)</td>
                    </tr>
                    <tr>
                        <td><code>components.security.score</code></td>
                        <td>Security score (0-100)</td>
                    </tr>
                    <tr>
                        <td><code>components.performance.score</code></td>
                        <td>Performance score (0-100)</td>
                    </tr>
                    <tr>
                        <td><code>components.plugins.score</code></td>
                        <td>Plugins score (0-100)</td>
                    </tr>
                    <tr>
                        <td><code>components.backup.score</code></td>
                        <td>Backup score (0-100)</td>
                    </tr>
                    <tr>
                        <td><code>health_score</code></td>
                        <td>Overall health score (0-100)</td>
                    </tr>
                    <tr>
                        <td><code>bugs_count</code></td>
                        <td>Number of active bugs</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Test Result Modal -->
<div class="modal fade" id="testResultModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test Result</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="test-result-content"></div>
            </div>
        </div>
    </div>
</div>

<style>
    .stat-card {
        border-left: 4px solid #0066cc;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .condition-group, .action-group {
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 12px;
        margin-bottom: 10px;
    }
    
    .condition-group button, .action-group button {
        margin-top: 10px;
    }
    
    .rules-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .rules-table th {
        background: #f0f0f0;
        padding: 12px;
        border-bottom: 2px solid #ddd;
        font-weight: 600;
        font-size: 0.9em;
    }
    
    .rules-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
    
    .rule-status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.85em;
        font-weight: 600;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    
    .status-inactive {
        background: #f8f9fa;
        color: #6c757d;
    }
    
    .condition-field {
        flex: 1;
        margin-right: 10px;
    }
    
    .condition-operator {
        flex: 0 0 120px;
        margin-right: 10px;
    }
    
    .condition-value {
        flex: 1;
    }
</style>

<script src="assets/js/automation.js"></script>
