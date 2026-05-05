/**
 * Diagnostics Center JavaScript
 * Handles interactive features for the diagnostics dashboard
 */

// Constants
const API_BASE = '/fullmidia/site_manager/index.php?action=diagnostics';
const ANALYSIS_TIMEOUT = 120000; // 2 minutes

/**
 * Analyze a specific website
 */
function analyzeWebsite(websiteId) {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    
    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
    
    $.ajax({
        url: API_BASE + '&do=analyze',
        method: 'POST',
        dataType: 'json',
        timeout: ANALYSIS_TIMEOUT,
        data: {
            website_id: websiteId
        },
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            if (response.success) {
                showToast('✅ Analysis Complete', `Found ${response.data.active_bugs.length} issues`, 'success');
                
                // Update website row
                updateWebsiteRow(websiteId, response.data);
                
                // Show detailed report
                setTimeout(() => showWebsiteDetail(websiteId, response.data), 500);
            } else {
                showToast('❌ Analysis Failed', response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            showToast('❌ Error', 'Failed to analyze website: ' + error, 'error');
        },
        complete: function() {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
}

/**
 * Run analysis on all websites
 */
function runPortfolioAnalysis() {
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    
    // Count websites
    const websiteCount = $('#websites-table tbody tr').length;
    
    if (websiteCount === 0) {
        showToast('⚠️ No Sites', 'No websites to analyze', 'warning');
        return;
    }
    
    // Show confirmation
    if (!confirm(`Analyze all ${websiteCount} websites? This may take a few minutes.`)) {
        return;
    }
    
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing Portfolio...';
    
    // Get all website IDs
    const websiteIds = [];
    $('#websites-table tbody tr').each(function() {
        websiteIds.push($(this).data('website-id'));
    });
    
    // Create progress tracker
    let completed = 0;
    const progressBar = showProgressModal(websiteCount);
    
    // Analyze each website sequentially
    analyzeWebsitesSequential(websiteIds, 0, completed, progressBar, function() {
        button.disabled = false;
        button.innerHTML = originalText;
        
        showToast('✅ Portfolio Analysis Complete', `Analyzed ${websiteIds.length} websites`, 'success');
    });
}

/**
 * Analyze websites sequentially
 */
function analyzeWebsitesSequential(websiteIds, index, completed, progressBar, callback) {
    if (index >= websiteIds.length) {
        progressBar.modal('hide');
        callback();
        return;
    }
    
    const websiteId = websiteIds[index];
    
    $.ajax({
        url: API_BASE + '&do=analyze',
        method: 'POST',
        dataType: 'json',
        timeout: ANALYSIS_TIMEOUT,
        data: {
            website_id: websiteId
        },
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            if (response.success) {
                updateWebsiteRow(websiteId, response.data);
                completed++;
            }
        },
        error: function() {
            console.error('Failed to analyze website ' + websiteId);
        },
        complete: function() {
            updateProgressModal(progressBar, completed + 1, websiteIds.length);
            analyzeWebsitesSequential(websiteIds, index + 1, completed, progressBar, callback);
        }
    });
}

/**
 * View detailed diagnostics for a website
 */
function viewWebsiteDiagnostics(websiteId) {
    // Create modal for detailed view
    const modal = `
    <div class="modal fade" id="diagnosticsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Website Diagnostics</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="diagnosticsContent">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p>Loading diagnostics...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `;
    
    // Remove existing modal if present
    $('#diagnosticsModal').remove();
    
    // Add modal to page
    $('body').append(modal);
    
    // Show modal
    $('#diagnosticsModal').modal('show');
    
    // Fetch detailed metrics
    $.ajax({
        url: API_BASE + '&do=getSiteMetrics',
        method: 'POST',
        dataType: 'json',
        data: {
            website_id: websiteId
        },
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            if (response.success) {
                const html = renderDiagnosticsDetail(response.data);
                $('#diagnosticsContent').html(html);
            } else {
                $('#diagnosticsContent').html(`<div class="alert alert-danger">${response.error}</div>`);
            }
        },
        error: function() {
            $('#diagnosticsContent').html('<div class="alert alert-danger">Failed to load diagnostics</div>');
        }
    });
}

/**
 * Render detailed diagnostics HTML
 */
function renderDiagnosticsDetail(data) {
    const metric = data.metric;
    const trend = data.trend;
    const recommendations = data.recommendations;
    
    if (!metric) {
        return '<div class="alert alert-info">No diagnostics available. Run an analysis first.</div>';
    }
    
    let html = `
    <div class="diagnostics-detail">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <div class="health-score-large" style="font-size: 3.5rem; font-weight: 700; color: #0066cc; margin-right: 20px;">
                        ${Math.round(metric.overall_score)}
                    </div>
                    <div>
                        <h4>${metric.grade} - ${metric.status}</h4>
                        <p class="text-muted mb-0">Overall Health Score</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert" style="background: ${getTrendBackground(trend.trend)}; border: none; color: white;">
                    <strong>${trend.direction} ${trend.trend}</strong><br>
                    <small>${trend.message}</small>
                </div>
            </div>
        </div>

        <!-- Component Scores -->
        <div class="row mb-4">
            <div class="col-12">
                <h5>Component Breakdown</h5>
                <div class="components-grid">
    `;
    
    const components = [
        { key: 'uptime', icon: '📡', label: 'Uptime' },
        { key: 'security', icon: '🔒', label: 'Security' },
        { key: 'performance', icon: '⚡', label: 'Performance' },
        { key: 'plugins', icon: '📦', label: 'Plugins' },
        { key: 'backup', icon: '💾', label: 'Backup' }
    ];
    
    components.forEach(comp => {
        if (metric.components && metric.components[comp.key]) {
            const score = metric.components[comp.key].score;
            const weight = metric.components[comp.key].weight;
            const color = score >= 80 ? '#28a745' : (score >= 60 ? '#ffc107' : '#dc3545');
            
            html += `
            <div class="component-card">
                <div class="component-icon">${comp.icon}</div>
                <div class="component-label">${comp.label}</div>
                <div class="component-score">${Math.round(score)}</div>
                <div class="component-bar" style="background: linear-gradient(90deg, ${color} 0%, ${color} ${score}%, #e9ecef ${score}%, #e9ecef 100%);"></div>
                <small class="text-muted">${Math.round(weight)}% weight</small>
            </div>
            `;
        }
    });
    
    html += `
                </div>
            </div>
        </div>

        <!-- Recommendations -->
    `;
    
    if (recommendations && recommendations.length > 0) {
        html += `
        <div class="row">
            <div class="col-12">
                <h5>Recommendations</h5>
                <div class="recommendations-list">
        `;
        
        recommendations.forEach(rec => {
            const priorityColor = rec.priority === 'CRITICAL' ? '#dc3545' : 
                                 rec.priority === 'HIGH' ? '#fd7e14' : '#ffc107';
            
            html += `
            <div style="border-left: 4px solid ${priorityColor}; padding: 12px; background: #f8f9fa; margin-bottom: 12px; border-radius: 4px;">
                <div class="d-flex justify-content-between mb-2">
                    <strong>${rec.icon} ${rec.title}</strong>
                    <span class="badge" style="background: ${priorityColor}; color: white;">${rec.priority}</span>
                </div>
                <p class="mb-0">${rec.description}</p>
                <small class="text-muted">Score: ${Math.round(rec.score)}/100</small>
            </div>
            `;
        });
        
        html += `
                </div>
            </div>
        </div>
        `;
    }
    
    html += `
    </div>
    `;
    
    return html;
}

/**
 * Update website row with new data
 */
function updateWebsiteRow(websiteId, data) {
    const row = $(`tr[data-website-id="${websiteId}"]`);
    
    // Update health score bar
    const scorePercent = data.health_score || 0;
    const scoreColor = scorePercent >= 80 ? '#28a745' : (scorePercent >= 60 ? '#ffc107' : '#dc3545');
    row.find('td:eq(1)').html(`
        <div class="health-bar" style="width: 100px;">
            <div class="health-fill" style="width: ${scorePercent}%; background: ${scoreColor};"></div>
        </div>
        <small class="text-muted">${Math.round(scorePercent)}/100</small>
    `);
    
    // Update grade
    const gradeColor = data.grade === 'A' ? 'success' : 
                      data.grade === 'B' ? 'info' : 
                      data.grade === 'C' ? 'warning' : 'danger';
    row.find('td:eq(2)').html(`<span class="badge badge-${gradeColor}">${data.grade}</span>`);
    
    // Update bug counts
    row.find('td:eq(3)').html(`<span class="badge badge-info">${data.active_bugs.length}</span>`);
    
    const criticalCount = data.active_bugs.filter(b => b.severity === 'CRITICAL').length;
    row.find('td:eq(4)').html(criticalCount > 0 ? 
        `<span class="badge badge-danger">${criticalCount}</span>` :
        `<span class="badge badge-success">0</span>`
    );
    
    // Update last scan time
    row.find('td:eq(5)').html(`<small class="text-muted">${new Date().toLocaleString()}</small>`);
}

/**
 * Show website detail modal
 */
function showWebsiteDetail(websiteId, data) {
    viewWebsiteDiagnostics(websiteId);
}

/**
 * Show toast notification
 */
function showToast(title, message, type = 'info') {
    const bgColor = type === 'success' ? '#28a745' : 
                   type === 'error' ? '#dc3545' : 
                   type === 'warning' ? '#ffc107' : '#0066cc';
    
    const toast = `
    <div class="toast-notification" style="
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${bgColor};
        color: white;
        padding: 16px 20px;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
    ">
        <strong>${title}</strong>
        <p style="margin: 5px 0 0 0; font-size: 0.9rem;">${message}</p>
    </div>
    `;
    
    $('body').append(toast);
    
    setTimeout(() => {
        $('.toast-notification').fadeOut(() => {
            $('.toast-notification').remove();
        });
    }, 4000);
}

/**
 * Show progress modal
 */
function showProgressModal(total) {
    const modal = `
    <div class="modal" id="progressModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Analyzing Portfolio</h5>
                </div>
                <div class="modal-body">
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             id="progressBar" style="width: 0%;"></div>
                    </div>
                    <p class="mt-3 mb-0">
                        <span id="progressText">0</span> of ${total} websites analyzed
                    </p>
                </div>
            </div>
        </div>
    </div>
    `;
    
    $('body').append(modal);
    $('#progressModal').modal({ backdrop: 'static', keyboard: false });
    
    return $('#progressModal');
}

/**
 * Update progress modal
 */
function updateProgressModal(modal, current, total) {
    const percent = (current / total) * 100;
    modal.find('#progressBar').css('width', percent + '%');
    modal.find('#progressText').text(current);
}

/**
 * Get trend background color
 */
function getTrendBackground(trend) {
    switch(trend) {
        case 'IMPROVING': return '#28a745';
        case 'DECLINING': return '#dc3545';
        default: return '#ffc107';
    }
}

// CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .components-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 15px;
    }

    .component-card {
        text-align: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        transition: transform 0.2s ease;
    }

    .component-card:hover {
        transform: translateY(-4px);
    }

    .component-icon {
        font-size: 1.5rem;
        margin-bottom: 8px;
    }

    .component-label {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 5px;
    }

    .component-score {
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
    }

    .component-bar {
        height: 6px;
        border-radius: 3px;
        margin-bottom: 8px;
    }
`;

document.head.appendChild(style);
