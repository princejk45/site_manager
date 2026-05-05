<?php
/**
 * Integrations Dashboard
 * 
 * Manage third-party integrations and team members.
 */

if (!FEATURE_AVAILABLE('integrations')) {
    echo '<div class="alert alert-warning">Integrations feature is not available on your plan. <a href="plans.php">Upgrade to Professional or Enterprise</a></div>';
    return;
}
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-0">
                <i class="fas fa-plug"></i>
                Integrations & Team
            </h2>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-primary" data-toggle="modal" data-target="#addIntegrationModal">
                <i class="fas fa-plus"></i> Add Integration
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#integrations">Integrations</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#team">Team Members</a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Integrations Tab -->
        <div class="tab-pane fade show active" id="integrations">
            <div class="row">
                <!-- Slack -->
                <div class="col-md-6">
                    <div class="card integration-card" data-platform="slack">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 127 127'%3E%3Cpath fill='%23E01E5A' d='M27.2,80c0,7.5-6.1,13.6-13.6,13.6S0,87.5,0,80c0-7.5,6.1-13.6,13.6-13.6h13.6V80z'/%3E%3Cpath fill='%23E01E5A' d='M33.9,80c0-7.5,6.1-13.6,13.6-13.6s13.6,6.1,13.6,13.6v33.9c0,7.5-6.1,13.6-13.6,13.6s-13.6-6.1-13.6-13.6V80z'/%3E%3C/svg%3E" alt="Slack" width="40" height="40">
                                <h5 class="mb-0 ml-3">Slack</h5>
                            </div>
                            <p class="text-muted mb-3">Send notifications to your Slack workspace</p>
                            <button class="btn btn-outline-primary btn-sm" onclick="setupIntegration('slack')">Setup</button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="viewIntegrations('slack')">View</button>
                        </div>
                    </div>
                </div>

                <!-- Microsoft Teams -->
                <div class="col-md-6">
                    <div class="card integration-card" data-platform="teams">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Crect fill='%235B5FC7' width='48' height='48'/%3E%3Cpath fill='%23FFF' d='M11,28h4v8h-4V28z M19,14h4v22h-4V14z M27,21h4v15h-4V21z M35,18h4v18h-4V18z'/%3E%3C/svg%3E" alt="Teams" width="40" height="40">
                                <h5 class="mb-0 ml-3">Microsoft Teams</h5>
                            </div>
                            <p class="text-muted mb-3">Post to your Teams channels</p>
                            <button class="btn btn-outline-primary btn-sm" onclick="setupIntegration('teams')">Setup</button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="viewIntegrations('teams')">View</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <!-- Custom Webhooks -->
                <div class="col-md-6">
                    <div class="card integration-card" data-platform="webhook">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-code fa-2x text-primary"></i>
                                <h5 class="mb-0 ml-3">Custom Webhooks</h5>
                            </div>
                            <p class="text-muted mb-3">Send data to any webhook URL</p>
                            <button class="btn btn-outline-primary btn-sm" onclick="setupIntegration('webhook')">Setup</button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="viewIntegrations('webhook')">View</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Integrations Table -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Active Integrations</h5>
                </div>
                <div class="card-body">
                    <div id="integrations-table-container">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-spinner fa-spin"></i> Loading integrations...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Tab -->
        <div class="tab-pane fade" id="team">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Team Members</h5>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#inviteTeamModal">
                        <i class="fas fa-user-plus"></i> Invite Member
                    </button>
                </div>
                <div class="card-body">
                    <div id="team-members-container">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-spinner fa-spin"></i> Loading team members...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Invitations -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Pending Invitations</h5>
                </div>
                <div class="card-body">
                    <div id="invitations-container">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-spinner fa-spin"></i> Loading invitations...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Integration Modal -->
<div class="modal fade" id="addIntegrationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Integration</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Platform:</label>
                    <select id="platform" class="form-control">
                        <option value="slack">Slack</option>
                        <option value="teams">Microsoft Teams</option>
                        <option value="webhook">Custom Webhook</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Integration Name:</label>
                    <input type="text" id="integrationName" class="form-control" placeholder="My Slack workspace">
                </div>

                <div id="platform-config"></div>

                <div class="form-group">
                    <label>Events to Notify:</label>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input event-checkbox" value="website_down" id="event1">
                        <label class="custom-control-label" for="event1">Website Down</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input event-checkbox" value="security_alert" id="event2">
                        <label class="custom-control-label" for="event2">Security Alert</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input event-checkbox" value="rule_executed" id="event3">
                        <label class="custom-control-label" for="event3">Rule Executed</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input event-checkbox" value="report_generated" id="event4">
                        <label class="custom-control-label" for="event4">Report Generated</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveIntegration()">Save Integration</button>
            </div>
        </div>
    </div>
</div>

<!-- Invite Team Member Modal -->
<div class="modal fade" id="inviteTeamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invite Team Member</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Email Address:</label>
                    <input type="email" id="teamEmail" class="form-control" placeholder="colleague@example.com">
                </div>

                <div class="form-group">
                    <label>Role:</label>
                    <select id="teamRole" class="form-control">
                        <option value="viewer">Viewer - Read-only access</option>
                        <option value="member">Member - Can manage content</option>
                        <option value="manager">Manager - Can configure automation</option>
                        <option value="admin">Admin - Full access except billing</option>
                    </select>
                </div>

                <div class="alert alert-info">
                    <strong>Role Permissions:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>Viewer:</strong> View dashboards and reports</li>
                        <li><strong>Member:</strong> Manage websites and settings</li>
                        <li><strong>Manager:</strong> Create and manage automations</li>
                        <li><strong>Admin:</strong> Manage team and integrations</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="inviteTeamMember()">Send Invitation</button>
            </div>
        </div>
    </div>
</div>

<style>
    .integration-card {
        border-left: 4px solid #0066cc;
        transition: transform 0.2s;
    }
    
    .integration-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .integrations-table {
        width: 100%;
    }
    
    .integrations-table th {
        background: #f0f0f0;
        padding: 12px;
        font-weight: 600;
        border-bottom: 2px solid #0066cc;
    }
    
    .integrations-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
    
    .status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.85em;
        font-weight: 600;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    
    .status-error {
        background: #f8d7da;
        color: #721c24;
    }
</style>

<script src="assets/js/integrations.js"></script>
