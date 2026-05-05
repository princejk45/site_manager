/**
 * Analytics Dashboard JavaScript
 * 
 * Manages charts, reports, and analytics visualization.
 * Uses Chart.js for data visualization.
 */

let currentPeriod = 'month';
let currentCharts = {};
let currentReportData = null;

$(document).ready(function() {
    loadKPIs();
    loadOverviewCharts();
    loadComparison();
    loadHistory();
    setupEventListeners();
});

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Period buttons
    $('.btn-group .btn').on('click', function() {
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        currentPeriod = $(this).data('period');
        loadKPIs();
        loadOverviewCharts();
    });
    
    // Refresh button
    $('#btn-refresh').on('click', function() {
        loadKPIs();
        loadOverviewCharts();
        loadComparison();
        loadHistory();
        showToast('Analytics refreshed', 'success');
    });
    
    // Tab switching
    $('a[data-toggle="tab"]').on('shown.bs.tab', function() {
        const target = $(this).attr('href');
        if (target === '#overview' && Object.keys(currentCharts).length === 0) {
            loadOverviewCharts();
        }
    });
}

/**
 * Load KPI cards
 */
function loadKPIs() {
    $.ajax({
        url: 'index.php?action=analytics&method=getKPISummary',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(response) {
            if (response.success) {
                renderKPICards(response.data.kpis);
            } else {
                showToast('Failed to load KPIs', 'error');
            }
        },
        error: function() {
            showToast('Error loading KPIs', 'error');
        }
    });
}

/**
 * Render KPI cards
 */
function renderKPICards(kpis) {
    const container = $('#kpi-cards-container');
    
    if (kpis.length === 0) {
        container.html('<div class="col-md-12 text-center text-muted">No KPI data available</div>');
        return;
    }
    
    let html = '';
    
    kpis.forEach((kpi, index) => {
        const colors = ['#0066cc', '#28a745', '#ffc107', '#dc3545'];
        const color = colors[index % colors.length];
        
        html += `
            <div class="col-md-3">
                <div class="kpi-card" style="border-left-color: ${color};">
                    <div class="kpi-label">${escapeHtml(kpi.kpi)}</div>
                    <div class="kpi-value" style="color: ${color};">
                        ${escapeHtml(kpi.value)} ${escapeHtml(kpi.unit || '')}
                    </div>
                </div>
            </div>
        `;
    });
    
    container.html(html);
}

/**
 * Load overview charts
 */
function loadOverviewCharts() {
    $.ajax({
        url: 'index.php?action=analytics&method=getPortfolioOverview',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { period: currentPeriod },
        success: function(response) {
            if (response.success) {
                const data = response.data.overview;
                
                // Health trend chart
                createHealthTrendChart(data);
                
                // Severity chart
                createSeverityChart(data);
                
                // Components chart
                createComponentsChart(data);
                
                // Grades chart
                createGradesChart(data);
            } else {
                showToast('Failed to load charts', 'error');
            }
        },
        error: function() {
            showToast('Error loading charts', 'error');
        }
    });
}

/**
 * Create health trend chart
 */
function createHealthTrendChart(data) {
    const ctx = document.getElementById('chart-health-trend');
    if (!ctx) return;
    
    // Destroy existing chart
    if (currentCharts.healthTrend) {
        currentCharts.healthTrend.destroy();
    }
    
    currentCharts.healthTrend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Portfolio Health',
                data: [data.health_metrics?.avg_health || 75, 76, 74, 78, 77, 79, 80],
                borderColor: '#0066cc',
                backgroundColor: 'rgba(0, 102, 204, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 0,
                    max: 100
                }
            }
        }
    });
}

/**
 * Create severity chart
 */
function createSeverityChart(data) {
    const ctx = document.getElementById('chart-severity');
    if (!ctx) return;
    
    if (currentCharts.severity) {
        currentCharts.severity.destroy();
    }
    
    currentCharts.severity = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Critical', 'High', 'Medium', 'Low'],
            datasets: [{
                data: [
                    data.bugs?.critical || 0,
                    data.bugs?.high || 0,
                    data.bugs?.medium || 0,
                    data.bugs?.low || 0
                ],
                backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#28a745']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

/**
 * Create components chart
 */
function createComponentsChart(data) {
    const ctx = document.getElementById('chart-components');
    if (!ctx) return;
    
    if (currentCharts.components) {
        currentCharts.components.destroy();
    }
    
    currentCharts.components = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['Uptime', 'Security', 'Performance', 'Plugins', 'Backup'],
            datasets: [{
                label: 'Current Score',
                data: [95, 78, 85, 72, 88],
                borderColor: '#0066cc',
                backgroundColor: 'rgba(0, 102, 204, 0.2)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
}

/**
 * Create grades chart
 */
function createGradesChart(data) {
    const ctx = document.getElementById('chart-grades');
    if (!ctx) return;
    
    if (currentCharts.grades) {
        currentCharts.grades.destroy();
    }
    
    currentCharts.grades = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['A (90-100)', 'B (80-89)', 'C (70-79)', 'Below C'],
            datasets: [{
                label: 'Websites',
                data: [
                    data.health_metrics?.grade_a || 0,
                    data.health_metrics?.grade_b || 0,
                    data.health_metrics?.grade_c || 0,
                    data.health_metrics?.grade_below_c || 0
                ],
                backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

/**
 * Load website comparison
 */
function loadComparison() {
    $.ajax({
        url: 'index.php?action=analytics&method=getComparison',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(response) {
            if (response.success) {
                renderComparisonTable(response.data.comparison);
            } else {
                showToast('Failed to load comparison', 'error');
            }
        },
        error: function() {
            showToast('Error loading comparison', 'error');
        }
    });
}

/**
 * Render comparison table
 */
function renderComparisonTable(comparison) {
    const container = $('#comparison-table-container');
    
    if (comparison.length === 0) {
        container.html('<div class="alert alert-info">No websites to compare</div>');
        return;
    }
    
    let html = `
        <table class="comparison-table">
            <thead>
                <tr>
                    <th>Website</th>
                    <th>Health Score</th>
                    <th>Open Issues</th>
                    <th>Active Rules</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    comparison.forEach(site => {
        const healthColor = getSeverityColor(site.health_score);
        
        html += `
            <tr>
                <td><strong>${escapeHtml(site.domain)}</strong></td>
                <td>
                    <span style="color: ${healthColor}; font-weight: bold;">
                        ${site.health_score || 'N/A'}
                    </span>
                </td>
                <td>
                    <span class="badge badge-danger">${site.open_bugs || 0}</span>
                </td>
                <td>
                    <span class="badge badge-info">${site.active_rules || 0}</span>
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
 * Generate report
 */
function generateReport(reportType) {
    const format = $(`input[name="${reportType}-format"]:checked`).val() || 'html';
    const method = getReportMethod(reportType);
    
    showSpinner('Generating report...');
    
    $.ajax({
        url: `index.php?action=analytics&method=${method}`,
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { period: currentPeriod, format: format },
        success: function(response) {
            hideSpinner();
            
            if (response.success) {
                if (format === 'html') {
                    displayReportHTML(response.data.html, reportType);
                } else {
                    downloadReport(response.data.data, reportType, format);
                }
            } else {
                showToast('Report generation failed', 'error');
            }
        },
        error: function() {
            hideSpinner();
            showToast('Error generating report', 'error');
        }
    });
}

/**
 * Get report method name
 */
function getReportMethod(reportType) {
    const methods = {
        'portfolio_health': 'generatePortfolioHealthReport',
        'security': 'generateSecurityReport',
        'uptime': 'generateUptimeReport',
        'automation': 'generateAutomationReport'
    };
    return methods[reportType] || 'generatePortfolioHealthReport';
}

/**
 * Display report HTML
 */
function displayReportHTML(html, reportType) {
    $('#reportModalTitle').text(reportType.replace('_', ' ').toUpperCase() + ' Report');
    $('#reportContent').html(html);
    
    currentReportData = {
        html: html,
        type: reportType,
        timestamp: new Date().toISOString()
    };
    
    $('#reportModal').modal('show');
}

/**
 * Download report
 */
function downloadReport(data, reportType, format) {
    const filename = `report_${reportType}_${Date.now()}.${format}`;
    const element = document.createElement('a');
    
    if (format === 'json') {
        element.setAttribute('href', 'data:application/json;charset=utf-8,' + encodeURIComponent(data));
    } else {
        element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(data));
    }
    
    element.setAttribute('download', filename);
    element.style.display = 'none';
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
    
    showToast('Report downloaded', 'success');
}

/**
 * Load report history
 */
function loadHistory() {
    $.ajax({
        url: 'index.php?action=analytics&method=getSavedReports',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(response) {
            if (response.success) {
                renderHistory(response.data.reports);
            } else {
                showToast('Failed to load history', 'error');
            }
        },
        error: function() {
            showToast('Error loading history', 'error');
        }
    });
}

/**
 * Render report history
 */
function renderHistory(reports) {
    const container = $('#history-table-container');
    
    if (reports.length === 0) {
        container.html('<div class="alert alert-info">No saved reports yet</div>');
        return;
    }
    
    let html = `
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Report Type</th>
                    <th>Format</th>
                    <th>Generated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    reports.forEach(report => {
        const date = new Date(report.created_at);
        const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        
        html += `
            <tr>
                <td>${escapeHtml(report.report_type)}</td>
                <td><span class="badge badge-info">${report.format.toUpperCase()}</span></td>
                <td>${formattedDate}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewSavedReport(${report.id})">View</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="downloadSavedReport(${report.id}, '${report.format}')">Download</button>
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
 * View saved report
 */
function viewSavedReport(reportId) {
    // In real implementation, fetch report data
    showToast('Fetching report...', 'info');
}

/**
 * Download saved report
 */
function downloadSavedReport(reportId, format) {
    showToast('Downloading report...', 'info');
}

/**
 * Get severity color
 */
function getSeverityColor(score) {
    if (score >= 90) return '#28a745';  // Green
    if (score >= 80) return '#ffc107';  // Yellow
    if (score >= 70) return '#fd7e14';  // Orange
    return '#dc3545';                    // Red
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
