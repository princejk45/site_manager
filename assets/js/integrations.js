/**
 * Integrations Dashboard JavaScript
 * 
 * Manages integrations and team member functionality.
 */

let currentPlatform = null;

$(document).ready(function() {
    loadIntegrations();
    loadTeamMembers();
    loadPendingInvitations();
    setupEventListeners();
});

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Tab switching
    $('a[data-toggle="tab"]').on('shown.bs.tab', function() {
        const target = $(this).attr('href');
        if (target === '#team') {
            loadTeamMembers();
            loadPendingInvitations();
        }
    });
    
    // Platform config change
    $('#platform').on('change', function() {
        updatePlatformConfig($(this).val());
    });
}

/**
 * Setup integration
 */
function setupIntegration(platform) {
    currentPlatform = platform;
    $('#platform').val(platform);
    updatePlatformConfig(platform);
    $('#addIntegrationModal').modal('show');
}

/**
 * Update platform config form
 */
function updatePlatformConfig(platform) {
    const configDiv = $('#platform-config');
    configDiv.html('');
    
    switch(platform) {
        case 'slack':
            configDiv.html(`
                <div class="form-group">
                    <label>Webhook URL:</label>
                    <input type="text" class="form-control" id="webhookUrl" placeholder="https://hooks.slack.com/services/...">
                    <small class="form-text text-muted">Get this from Slack app settings</small>
                </div>
                <div class="alert alert-info">
                    <strong>How to setup:</strong>
                    <ol>
                        <li>Go to your Slack workspace settings</li>
                        <li>Create an Incoming Webhook</li>
                        <li>Copy the webhook URL above</li>
                    </ol>
                </div>
            `);
            break;
        
        case 'teams':
            configDiv.html(`
                <div class="form-group">
                    <label>Webhook URL:</label>
                    <input type="text" class="form-control" id="webhookUrl" placeholder="https://outlook.webhook.office.com/...">
                    <small class="form-text text-muted">Get this from Teams connectors</small>
                </div>
                <div class="alert alert-info">
                    <strong>How to setup:</strong>
                    <ol>
                        <li>Go to your Teams channel</li>
                        <li>Click "Connectors" and select "Incoming Webhook"</li>
                        <li>Copy the webhook URL above</li>
                    </ol>
                </div>
            `);
            break;
        
        case 'webhook':
            configDiv.html(`
                <div class="form-group">
                    <label>Webhook URL:</label>
                    <input type="text" class="form-control" id="webhookUrl" placeholder="https://your-api.example.com/webhook">
                </div>
                <div class="form-group">
                    <label>Secret (optional):</label>
                    <input type="text" class="form-control" id="webhookSecret" placeholder="your-secret-key">
                    <small class="form-text text-muted">Used to generate HMAC signature</small>
                </div>
                <div class="form-group">
                    <label>Custom Headers (optional):</label>
                    <textarea class="form-control" id="webhookHeaders" placeholder="Authorization: Bearer token&#10;X-Custom-Header: value" rows="3"></textarea>
                    <small class="form-text text-muted">One per line</small>
                </div>
            `);
            break;
    }
}

/**
 * Save integration
 */
function saveIntegration() {
    const platform = $('#platform').val();
    const name = $('#integrationName').val();
    const events = [];
    
    $('.event-checkbox:checked').each(function() {
        events.push($(this).val());
    });
    
    if (!name) {
        showToast('Please enter integration name', 'error');
        return;
    }
    
    if (events.length === 0) {
        showToast('Please select at least one event', 'error');
        return;
    }
    
    const config = getConfigForPlatform(platform);
    
    $.ajax({
        url: 'index.php?action=integrations&method=addIntegration',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: {
            platform: platform,
            name: name,
            config: JSON.stringify(config),
            events: events
        },
        success: function(response) {
            response = JSON.parse(response);
            if (response.success) {
                showToast('Integration added successfully', 'success');
                $('#addIntegrationModal').modal('hide');
                loadIntegrations();
                clearIntegrationForm();
            } else {
                showToast(response.error || 'Failed to add integration', 'error');
            }
        },
        error: function() {
            showToast('Error adding integration', 'error');
        }
    });
}

/**
 * Get config for platform
 */
function getConfigForPlatform(platform) {
    switch(platform) {
        case 'slack':
        case 'teams':
            return { webhook_url: $('#webhookUrl').val() };
        
        case 'webhook':
            return {
                url: $('#webhookUrl').val(),
                secret: $('#webhookSecret').val() || '',
                headers: parseHeaders($('#webhookHeaders').val())
            };
        
        default:
            return {};
    }
}

/**
 * Parse headers
 */
function parseHeaders(headerText) {
    const headers = [];
    if (!headerText) return headers;
    
    headerText.split('\n').forEach(line => {
        line = line.trim();
        if (line) headers.push(line);
    });
    
    return headers;
}

/**
 * Clear integration form
 */
function clearIntegrationForm() {
    $('#integrationName').val('');
    $('.event-checkbox').prop('checked', false);
    $('#platform-config').html('');
}

/**
 * Load integrations
 */
function loadIntegrations() {
    $.ajax({
        url: 'index.php?action=integrations&method=listIntegrations',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(response) {
            response = JSON.parse(response);
            if (response.success) {
                renderIntegrationsTable(response.integrations);
            } else {
                showToast('Failed to load integrations', 'error');
            }
        },
        error: function() {
            showToast('Error loading integrations', 'error');
        }
    });
}

/**
 * Render integrations table
 */
function renderIntegrationsTable(integrations) {
    const container = $('#integrations-table-container');
    
    if (integrations.length === 0) {
        container.html('<div class="alert alert-info">No integrations configured yet</div>');
        return;
    }
    
    let html = `
        <table class="integrations-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Platform</th>
                    <th>Events</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    integrations.forEach(integration => {
        const statusClass = integration.status === 'active' ? 'status-active' : 'status-error';
        const events = integration.events.join(', ') || 'None';
        
        html += `
            <tr>
                <td><strong>${escapeHtml(integration.name)}</strong></td>
                <td>${escapeHtml(integration.platform)}</td>
                <td><small>${escapeHtml(events)}</small></td>
                <td><span class="status-badge ${statusClass}">${integration.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="testIntegration(${integration.id})">Test</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="editIntegration(${integration.id})">Edit</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteIntegration(${integration.id})">Delete</button>
                </td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    container.html(html);
}

/**
 * View integrations
 */
function viewIntegrations(platform) {
    // Switch to integrations tab
    $('a[href="#integrations"]').tab('show');
    showToast('Showing ' + platform + ' integrations', 'info');
}

/**
 * Test integration
 */
function testIntegration(integrationId) {
    showSpinner('Testing integration...');
    
    $.ajax({
        url: 'index.php?action=integrations&method=testIntegration',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { id: integrationId },
        success: function(response) {
            hideSpinner();
            response = JSON.parse(response);
            if (response.success) {
                showToast('Test message sent successfully', 'success');
            } else {
                showToast(response.error || 'Test failed', 'error');
            }
        },
        error: function() {
            hideSpinner();
            showToast('Error testing integration', 'error');
        }
    });
}

/**
 * Edit integration
 */
function editIntegration(integrationId) {
    showToast('Edit functionality coming soon', 'info');
}

/**
 * Delete integration
 */
function deleteIntegration(integrationId) {
    if (!confirm('Are you sure? This will stop notifications for this integration.')) {
        return;
    }
    
    $.ajax({
        url: 'index.php?action=integrations&method=deleteIntegration',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { id: integrationId },
        success: function(response) {
            response = JSON.parse(response);
            if (response.success) {
                showToast('Integration deleted successfully', 'success');
                loadIntegrations();
            } else {
                showToast(response.error || 'Failed to delete', 'error');
            }
        },
        error: function() {
            showToast('Error deleting integration', 'error');
        }
    });
}

/**
 * Load team members
 */
function loadTeamMembers() {
    $.ajax({
        url: 'index.php?action=integrations&method=getTeamMembers',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(response) {
            response = JSON.parse(response);
            if (response.success) {
                renderTeamMembers(response.members || []);
            }
        },
        error: function() {
            console.log('Error loading team members');
        }
    });
}

/**
 * Render team members
 */
function renderTeamMembers(members) {
    const container = $('#team-members-container');
    
    if (members.length === 0) {
        container.html('<div class="alert alert-info">No team members yet</div>');
        return;
    }
    
    let html = `
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    members.forEach(member => {
        const name = (member.first_name || '') + ' ' + (member.last_name || '');
        const date = new Date(member.accepted_at).toLocaleDateString();
        
        html += `
            <tr>
                <td>${escapeHtml(name)}</td>
                <td>${escapeHtml(member.email)}</td>
                <td><span class="badge badge-info">${escapeHtml(member.role)}</span></td>
                <td>${date}</td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary" onclick="editTeamMember(${member.user_id})">Edit</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="removeTeamMember(${member.user_id})">Remove</button>
                </td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    container.html(html);
}

/**
 * Load pending invitations
 */
function loadPendingInvitations() {
    $.ajax({
        url: 'index.php?action=integrations&method=getPendingInvitations',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(response) {
            response = JSON.parse(response);
            if (response.success) {
                renderPendingInvitations(response.invitations || []);
            }
        },
        error: function() {
            console.log('Error loading invitations');
        }
    });
}

/**
 * Render pending invitations
 */
function renderPendingInvitations(invitations) {
    const container = $('#invitations-container');
    
    if (invitations.length === 0) {
        container.html('<div class="alert alert-info">No pending invitations</div>');
        return;
    }
    
    let html = `
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Sent</th>
                    <th>Expires</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    invitations.forEach(invitation => {
        const sent = new Date(invitation.created_at).toLocaleDateString();
        const expires = new Date(invitation.expires_at).toLocaleDateString();
        
        html += `
            <tr>
                <td>${escapeHtml(invitation.email)}</td>
                <td><span class="badge badge-warning">${escapeHtml(invitation.role)}</span></td>
                <td>${sent}</td>
                <td>${expires}</td>
                <td>
                    <button class="btn btn-sm btn-outline-danger" onclick="cancelInvitation(${invitation.id})">Cancel</button>
                </td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    container.html(html);
}

/**
 * Invite team member
 */
function inviteTeamMember() {
    const email = $('#teamEmail').val();
    const role = $('#teamRole').val();
    
    if (!email) {
        showToast('Please enter email address', 'error');
        return;
    }
    
    $.ajax({
        url: 'index.php?action=integrations&method=inviteTeamMember',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: {
            email: email,
            role: role,
            permissions: []
        },
        success: function(response) {
            response = JSON.parse(response);
            if (response.success) {
                showToast('Invitation sent to ' + email, 'success');
                $('#inviteTeamModal').modal('hide');
                $('#teamEmail').val('');
                loadPendingInvitations();
            } else {
                showToast(response.error || 'Failed to send invitation', 'error');
            }
        },
        error: function() {
            showToast('Error sending invitation', 'error');
        }
    });
}

/**
 * Edit team member
 */
function editTeamMember(userId) {
    showToast('Edit functionality coming soon', 'info');
}

/**
 * Remove team member
 */
function removeTeamMember(userId) {
    if (!confirm('Are you sure you want to remove this team member?')) {
        return;
    }
    
    $.ajax({
        url: 'index.php?action=integrations&method=removeTeamMember',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { user_id: userId },
        success: function(response) {
            response = JSON.parse(response);
            if (response.success) {
                showToast('Team member removed', 'success');
                loadTeamMembers();
            } else {
                showToast(response.error || 'Failed to remove', 'error');
            }
        },
        error: function() {
            showToast('Error removing team member', 'error');
        }
    });
}

/**
 * Cancel invitation
 */
function cancelInvitation(invitationId) {
    if (!confirm('Are you sure you want to cancel this invitation?')) {
        return;
    }
    
    $.ajax({
        url: 'index.php?action=integrations&method=cancelInvitation',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { id: invitationId },
        success: function(response) {
            response = JSON.parse(response);
            if (response.success) {
                showToast('Invitation cancelled', 'success');
                loadPendingInvitations();
            } else {
                showToast(response.error || 'Failed to cancel', 'error');
            }
        },
        error: function() {
            showToast('Error cancelling invitation', 'error');
        }
    });
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
 * Utility: Show toast
 */
function showToast(message, type = 'info') {
    const typeClass = {
        'info': 'alert-info',
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning'
    }[type] || 'alert-info';
    
    $('body').append(`
        <div class="alert ${typeClass} alert-dismissible fade show" role="alert" 
             style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `);
    
    setTimeout(() => $('.alert').fadeOut(function() { $(this).remove(); }), 4000);
}

/**
 * Utility: Show spinner
 */
function showSpinner(message = 'Processing...') {
    $('body').append(`
        <div id="spinner-overlay" style="
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); display: flex; align-items: center;
            justify-content: center; z-index: 10000;
        ">
            <div style="background: white; padding: 30px; border-radius: 8px; text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: #0066cc;"></i>
                <p style="margin-top: 15px; color: #666;">${message}</p>
            </div>
        </div>
    `);
}

/**
 * Utility: Hide spinner
 */
function hideSpinner() {
    $('#spinner-overlay').remove();
}
