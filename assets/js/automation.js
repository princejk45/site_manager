/**
 * Automation Rules Dashboard
 * 
 * Manages interactive rule builder and execution.
 * Handles conditions, actions, testing, and real-time updates.
 */

let currentRuleId = null;
let currentRules = [];

// Available field options for conditions
const AVAILABLE_FIELDS = {
    'components.uptime.score': 'Uptime Score',
    'components.security.score': 'Security Score',
    'components.performance.score': 'Performance Score',
    'components.plugins.score': 'Plugins Score',
    'components.backup.score': 'Backup Score',
    'health_score': 'Overall Health Score',
    'bugs_count': 'Active Bugs Count'
};

// Available operators for conditions
const OPERATORS = {
    'equals': '=',
    'gt': '>',
    'lt': '<',
    'gte': '≥',
    'lte': '≤',
    'contains': 'contains',
    'starts_with': 'starts with',
    'ends_with': 'ends with'
};

// Available action types
const ACTION_TYPES = {
    'send_email': 'Send Email',
    'create_ticket': 'Create Ticket',
    'update_status': 'Update Status',
    'webhook': 'Send Webhook',
    'log_event': 'Log Event'
};

$(document).ready(function() {
    loadRules();
    setupEventListeners();
    loadSummary();
});

/**
 * Setup event listeners
 */
function setupEventListeners() {
    $('#btn-new-rule').on('click', openNewRuleModal);
    $('#btn-add-condition').on('click', addCondition);
    $('#btn-add-action').on('click', addAction);
    $('#btn-save-rule').on('click', saveRule);
    $('#btn-test-rule').on('click', testRule);
}

/**
 * Load all rules for website
 */
function loadRules() {
    const websiteId = $('#website-id').val();
    
    $.ajax({
        url: 'index.php?action=automation&method=list',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { website_id: websiteId },
        success: function(response) {
            if (response.success) {
                currentRules = response.data.rules;
                renderRulesTable();
                updateStats();
            } else {
                showError(response.error || 'Failed to load rules');
            }
        },
        error: function() {
            showError('Failed to load rules');
        }
    });
}

/**
 * Render rules table
 */
function renderRulesTable() {
    const container = $('#rules-container');
    
    if (currentRules.length === 0) {
        container.html(`
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox"></i>
                <p>No rules yet. Create one to get started!</p>
            </div>
        `);
        return;
    }
    
    let html = `<table class="rules-table">
        <thead>
            <tr>
                <th style="width: 30%;">Rule Name</th>
                <th style="width: 15%;">Status</th>
                <th style="width: 15%;">Conditions</th>
                <th style="width: 15%;">Actions</th>
                <th style="width: 15%;">Executions (24h)</th>
                <th style="width: 10%;">Actions</th>
            </tr>
        </thead>
        <tbody>`;
    
    currentRules.forEach(rule => {
        const conditions = JSON.parse(rule.conditions_json || '[]').length;
        const actions = JSON.parse(rule.actions_json || '[]').length;
        const stats = rule.stats || {};
        const executions = stats.executions_today || 0;
        
        const statusClass = rule.is_active ? 'status-active' : 'status-inactive';
        const statusText = rule.is_active ? 'Active' : 'Inactive';
        
        html += `<tr>
            <td>
                <strong>${escapeHtml(rule.name)}</strong>
            </td>
            <td>
                <span class="rule-status ${statusClass}">
                    ${statusText}
                </span>
            </td>
            <td>
                <span class="badge badge-info">${conditions}</span>
            </td>
            <td>
                <span class="badge badge-warning">${actions}</span>
            </td>
            <td>
                <small class="text-muted">${executions} times</small>
            </td>
            <td>
                <button class="btn btn-sm btn-link" onclick="editRule(${rule.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-link" onclick="deleteRule(${rule.id})" title="Delete">
                    <i class="fas fa-trash text-danger"></i>
                </button>
            </td>
        </tr>`;
    });
    
    html += `</tbody></table>`;
    container.html(html);
}

/**
 * Open new rule modal
 */
function openNewRuleModal() {
    currentRuleId = null;
    
    $('#ruleModalTitle').text('New Rule');
    $('#ruleForm')[0].reset();
    $('#rule-id').val('');
    $('#conditions-container').html('');
    $('#actions-container').html('');
    $('#execution-limit').val(10);
    $('#cooldown-minutes').val(60);
    $('#rule-active').prop('checked', true);
    
    // Add initial empty condition and action
    addCondition();
    addAction();
    
    $('#ruleModal').modal('show');
}

/**
 * Edit rule
 */
function editRule(ruleId) {
    $.ajax({
        url: 'index.php?action=automation&method=getRule',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { rule_id: ruleId },
        success: function(response) {
            if (response.success) {
                const rule = response.data.rule;
                populateRuleForm(rule);
                $('#ruleModal').modal('show');
            } else {
                showError(response.error || 'Failed to load rule');
            }
        },
        error: function() {
            showError('Failed to load rule');
        }
    });
}

/**
 * Populate rule form
 */
function populateRuleForm(rule) {
    currentRuleId = rule.id;
    
    $('#ruleModalTitle').text('Edit Rule: ' + rule.name);
    $('#rule-id').val(rule.id);
    $('#rule-name').val(rule.name);
    $('#execution-limit').val(rule.execution_limit_per_day);
    $('#cooldown-minutes').val(rule.cooldown_minutes);
    $('#rule-active').prop('checked', rule.is_active);
    
    // Set condition logic
    $('input[name="condition-logic"][value="' + rule.conditions_logic + '"]').prop('checked', true);
    
    // Load conditions
    $('#conditions-container').html('');
    const conditions = rule.conditions || [];
    conditions.forEach(condition => {
        addCondition(condition);
    });
    if (conditions.length === 0) addCondition();
    
    // Load actions
    $('#actions-container').html('');
    const actions = rule.actions || [];
    actions.forEach(action => {
        addAction(action);
    });
    if (actions.length === 0) addAction();
}

/**
 * Add condition row
 */
function addCondition(condition = null) {
    const id = 'condition-' + Math.random().toString(36).substr(2, 9);
    
    let fieldOptions = '';
    for (const [value, label] of Object.entries(AVAILABLE_FIELDS)) {
        const selected = condition && condition.field === value ? 'selected' : '';
        fieldOptions += `<option value="${value}" ${selected}>${label}</option>`;
    }
    
    let operatorOptions = '';
    for (const [value, label] of Object.entries(OPERATORS)) {
        const selected = condition && condition.operator === value ? 'selected' : '';
        operatorOptions += `<option value="${value}" ${selected}>${label}</option>`;
    }
    
    const conditionValue = condition ? condition.value : '';
    
    const html = `
        <div class="condition-group" id="${id}">
            <div class="d-flex">
                <select class="form-control condition-field" name="field">
                    ${fieldOptions}
                </select>
                <select class="form-control condition-operator" name="operator">
                    ${operatorOptions}
                </select>
                <input type="text" class="form-control condition-value" name="value" 
                       placeholder="Value" value="${escapeHtml(conditionValue)}">
                <button type="button" class="btn btn-sm btn-outline-danger" 
                        onclick="$('#${id}').remove()">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    $('#conditions-container').append(html);
}

/**
 * Add action row
 */
function addAction(action = null) {
    const id = 'action-' + Math.random().toString(36).substr(2, 9);
    const type = action ? action.type : 'send_email';
    
    let typeOptions = '';
    for (const [value, label] of Object.entries(ACTION_TYPES)) {
        const selected = type === value ? 'selected' : '';
        typeOptions += `<option value="${value}" ${selected}>${label}</option>`;
    }
    
    const html = `
        <div class="action-group" id="${id}">
            <div class="row">
                <div class="col-md-4">
                    <select class="form-control action-type" name="type" onchange="updateActionFields(this)">
                        ${typeOptions}
                    </select>
                </div>
                <div class="col-md-8">
                    <div class="action-fields" data-action-id="${id}">
                        <!-- Fields generated by updateActionFields -->
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" 
                    onclick="$('#${id}').remove()">
                <i class="fas fa-trash"></i> Remove Action
            </button>
        </div>
    `;
    
    $('#actions-container').append(html);
    
    // Initialize action fields
    $(`#${id} .action-type`).trigger('change');
}

/**
 * Update action fields based on type
 */
function updateActionFields(selectElement) {
    const actionId = $(selectElement).closest('.action-group').attr('id');
    const type = $(selectElement).val();
    const fieldsContainer = $(`#${actionId} .action-fields`);
    
    let fieldsHtml = '';
    
    switch(type) {
        case 'send_email':
            fieldsHtml = `
                <input type="email" class="form-control form-control-sm" 
                       placeholder="Recipient email" name="recipients">
                <input type="text" class="form-control form-control-sm mt-2" 
                       placeholder="Subject" name="subject">
                <textarea class="form-control form-control-sm mt-2" 
                          placeholder="Message body (HTML)" name="body" rows="2"></textarea>
            `;
            break;
        case 'create_ticket':
            fieldsHtml = `
                <input type="text" class="form-control form-control-sm" 
                       placeholder="Ticket title" name="title">
                <select class="form-control form-control-sm mt-2" name="priority">
                    <option value="LOW">Low Priority</option>
                    <option value="MEDIUM" selected>Medium Priority</option>
                    <option value="HIGH">High Priority</option>
                </select>
                <textarea class="form-control form-control-sm mt-2" 
                          placeholder="Ticket description" name="description" rows="2"></textarea>
            `;
            break;
        case 'update_status':
            fieldsHtml = `
                <select class="form-control form-control-sm" name="new_status">
                    <option value="NORMAL">Normal</option>
                    <option value="ALERT">Alert</option>
                    <option value="WARNING">Warning</option>
                    <option value="CRITICAL">Critical</option>
                </select>
            `;
            break;
        case 'webhook':
            fieldsHtml = `
                <input type="url" class="form-control form-control-sm" 
                       placeholder="Webhook URL" name="url">
                <select class="form-control form-control-sm mt-2" name="method">
                    <option value="POST">POST</option>
                    <option value="GET">GET</option>
                    <option value="PUT">PUT</option>
                </select>
            `;
            break;
        case 'log_event':
            fieldsHtml = `
                <input type="text" class="form-control form-control-sm" 
                       placeholder="Log message" name="message">
                <select class="form-control form-control-sm mt-2" name="level">
                    <option value="INFO">Info</option>
                    <option value="WARNING">Warning</option>
                    <option value="ERROR">Error</option>
                </select>
            `;
            break;
    }
    
    fieldsContainer.html(fieldsHtml);
}

/**
 * Save rule
 */
function saveRule() {
    const ruleName = $('#rule-name').val().trim();
    
    if (!ruleName) {
        showError('Rule name is required');
        return;
    }
    
    // Collect conditions
    const conditions = [];
    $('#conditions-container .condition-group').each(function() {
        const field = $(this).find('[name="field"]').val();
        const operator = $(this).find('[name="operator"]').val();
        const value = $(this).find('[name="value"]').val();
        
        if (field && operator && value) {
            conditions.push({ field, operator, value });
        }
    });
    
    if (conditions.length === 0) {
        showError('At least one condition is required');
        return;
    }
    
    // Collect actions
    const actions = [];
    $('#actions-container .action-group').each(function() {
        const type = $(this).find('.action-type').val();
        const action = { type };
        
        $(this).find('[name]').each(function() {
            if ($(this).attr('name') !== 'type') {
                action[$(this).attr('name')] = $(this).val();
            }
        });
        
        actions.push(action);
    });
    
    if (actions.length === 0) {
        showError('At least one action is required');
        return;
    }
    
    const ruleData = {
        name: ruleName,
        conditions: conditions,
        conditions_logic: $('input[name="condition-logic"]:checked').val(),
        actions: actions,
        execution_limit_per_day: $('#execution-limit').val(),
        cooldown_minutes: $('#cooldown-minutes').val(),
        is_active: $('#rule-active').prop('checked')
    };
    
    const url = currentRuleId ? 
        'index.php?action=automation&method=update' :
        'index.php?action=automation&method=create';
    
    const data = {
        website_id: $('#website-id').val(),
        rule_data: ruleData
    };
    
    if (currentRuleId) {
        data.rule_id = currentRuleId;
        data.updates = ruleData;
    }
    
    $.ajax({
        url: url,
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: data,
        success: function(response) {
            if (response.success) {
                showSuccess(currentRuleId ? 'Rule updated' : 'Rule created');
                $('#ruleModal').modal('hide');
                loadRules();
            } else {
                showError(response.error || 'Failed to save rule');
            }
        },
        error: function() {
            showError('Failed to save rule');
        }
    });
}

/**
 * Test rule
 */
function testRule() {
    if (!currentRuleId) {
        showError('Save the rule first before testing');
        return;
    }
    
    $.ajax({
        url: 'index.php?action=automation&method=test',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: {
            rule_id: currentRuleId,
            website_id: $('#website-id').val()
        },
        success: function(response) {
            if (response.success) {
                const result = response.data.execution_result;
                displayTestResult(result);
            } else {
                showError(response.error || 'Test failed');
            }
        },
        error: function() {
            showError('Test execution failed');
        }
    });
}

/**
 * Display test result
 */
function displayTestResult(result) {
    let html = `
        <div class="alert ${result.status === 'SUCCESS' ? 'alert-success' : 
                           result.status === 'PARTIAL' ? 'alert-warning' : 'alert-info'}">
            <strong>Status:</strong> ${result.status}
            <br><strong>Execution Time:</strong> ${result.execution_time_ms}ms
        </div>
    `;
    
    if (result.result && result.result.actions) {
        html += '<h6>Action Results:</h6><ul>';
        result.result.actions.forEach(action => {
            const icon = action.success ? '✓' : '✗';
            html += `<li>${icon} ${action.type}: ${action.success ? 'Success' : 'Failed'}</li>`;
        });
        html += '</ul>';
    }
    
    $('#test-result-content').html(html);
    $('#testResultModal').modal('show');
}

/**
 * Delete rule
 */
function deleteRule(ruleId) {
    if (!confirm('Delete this rule? This action cannot be undone.')) {
        return;
    }
    
    $.ajax({
        url: 'index.php?action=automation&method=delete',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { rule_id: ruleId },
        success: function(response) {
            if (response.success) {
                showSuccess('Rule deleted');
                loadRules();
            } else {
                showError(response.error || 'Failed to delete rule');
            }
        },
        error: function() {
            showError('Failed to delete rule');
        }
    });
}

/**
 * Load summary statistics
 */
function loadSummary() {
    $.ajax({
        url: 'index.php?action=automation&method=getSummary',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { website_id: $('#website-id').val(), period: 'today' },
        success: function(response) {
            if (response.success) {
                const data = response.data;
                updateStats();
            }
        }
    });
}

/**
 * Update statistics
 */
function updateStats() {
    if (currentRules.length === 0) {
        $('#total-rules').text('0');
        $('#active-rules').text('0');
        $('#executions-24h').text('0');
        $('#success-rate').text('0%');
        return;
    }
    
    const activeCount = currentRules.filter(r => r.is_active).length;
    let total24h = 0;
    let success24h = 0;
    
    currentRules.forEach(rule => {
        const stats = rule.stats || {};
        total24h += stats.executions_today || 0;
        success24h += stats.successful_today || 0;
    });
    
    const successRate = total24h > 0 ? Math.round((success24h / total24h) * 100) : 0;
    
    $('#total-rules').text(currentRules.length);
    $('#active-rules').text(activeCount);
    $('#executions-24h').text(total24h);
    $('#success-rate').text(successRate + '%');
}

/**
 * Utility: Escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

/**
 * Utility: Show success toast
 */
function showSuccess(message) {
    $('body').append(`
        <div class="alert alert-success alert-dismissible fade show" role="alert" 
             style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas fa-check-circle"></i> ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `);
    
    setTimeout(() => $('.alert.alert-success').fadeOut(), 4000);
}

/**
 * Utility: Show error toast
 */
function showError(message) {
    $('body').append(`
        <div class="alert alert-danger alert-dismissible fade show" role="alert" 
             style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas fa-exclamation-circle"></i> ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `);
}
