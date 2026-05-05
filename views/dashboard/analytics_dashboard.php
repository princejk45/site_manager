<?php
/**
 * Analytics Dashboard
 * 
 * Comprehensive analytics and reporting interface.
 * Display KPIs, trends, comparisons, and generate reports.
 */

// Check feature access
if (!FEATURE_AVAILABLE('analytics')) {
    echo '<div class="alert alert-warning">Analytics feature is not available on your plan. <a href="plans.php">Upgrade to Professional or Enterprise</a></div>';
    return;
}
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-0">
                <i class="fas fa-chart-line"></i>
                Analytics & Reporting
            </h2>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-primary" id="btn-refresh">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <div class="btn-group ml-2" role="group">
                <button type="button" class="btn btn-outline-secondary" data-period="today">Today</button>
                <button type="button" class="btn btn-outline-secondary" data-period="week">Week</button>
                <button type="button" class="btn btn-outline-secondary active" data-period="month">Month</button>
                <button type="button" class="btn btn-outline-secondary" data-period="year">Year</button>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row mb-4" id="kpi-cards-container">
        <div class="col-md-12 text-center text-muted">
            <i class="fas fa-spinner fa-spin"></i> Loading KPIs...
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#overview">Overview</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#comparison">Website Comparison</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#reports">Reports</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#history">Report History</a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Portfolio Health Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="chart-health-trend"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Issues by Severity</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="chart-severity"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Component Scores</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="chart-components"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Grade Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="chart-grades"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Website Comparison Tab -->
        <div class="tab-pane fade" id="comparison">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Website Comparison</h5>
                </div>
                <div class="card-body">
                    <div id="comparison-table-container">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-spinner fa-spin"></i> Loading comparison...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Tab -->
        <div class="tab-pane fade" id="reports">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Portfolio Health Report</h5>
                        </div>
                        <div class="card-body">
                            <p>Generate comprehensive portfolio health analysis including health scores, grade distribution, and trends.</p>
                            <div class="form-group">
                                <label>Format:</label>
                                <div>
                                    <div class="custom-control custom-radio">
                                        <input type="radio" class="custom-control-input" id="health-format-html" name="health-format" value="html" checked>
                                        <label class="custom-control-label" for="health-format-html">HTML (View)</label>
                                    </div>
                                    <div class="custom-control custom-radio">
                                        <input type="radio" class="custom-control-input" id="health-format-csv" name="health-format" value="csv">
                                        <label class="custom-control-label" for="health-format-csv">CSV (Download)</label>
                                    </div>
                                    <div class="custom-control custom-radio">
                                        <input type="radio" class="custom-control-input" id="health-format-json" name="health-format" value="json">
                                        <label class="custom-control-label" for="health-format-json">JSON (API)</label>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-primary btn-block" onclick="generateReport('portfolio_health')">
                                <i class="fas fa-file-pdf"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Security Report</h5>
                        </div>
                        <div class="card-body">
                            <p>Analyze security issues across portfolio including vulnerabilities, SSL status, and recommendations.</p>
                            <div class="form-group">
                                <label>Format:</label>
                                <div>
                                    <div class="custom-control custom-radio">
                                        <input type="radio" class="custom-control-input" id="security-format-html" name="security-format" value="html" checked>
                                        <label class="custom-control-label" for="security-format-html">HTML (View)</label>
                                    </div>
                                    <div class="custom-control custom-radio">
                                        <input type="radio" class="custom-control-input" id="security-format-csv" name="security-format" value="csv">
                                        <label class="custom-control-label" for="security-format-csv">CSV (Download)</label>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-danger btn-block" onclick="generateReport('security')">
                                <i class="fas fa-shield-alt"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Uptime Report</h5>
                        </div>
                        <div class="card-body">
                            <p>Track uptime metrics across all websites with trend analysis and availability summaries.</p>
                            <button class="btn btn-success btn-block" onclick="generateReport('uptime')">
                                <i class="fas fa-clock"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Automation Report</h5>
                        </div>
                        <div class="card-body">
                            <p>Monitor automation rule execution, success rates, and performance metrics.</p>
                            <button class="btn btn-info btn-block" onclick="generateReport('automation')">
                                <i class="fas fa-magic"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report History Tab -->
        <div class="tab-pane fade" id="history">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Generated Reports</h5>
                </div>
                <div class="card-body">
                    <div id="history-table-container">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-spinner fa-spin"></i> Loading history...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Viewer Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportModalTitle">Report Viewer</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="reportContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn-download-report">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .kpi-card {
        background: white;
        border-left: 4px solid #0066cc;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .kpi-value {
        font-size: 2.5em;
        font-weight: bold;
        color: #0066cc;
        margin: 10px 0;
    }
    
    .kpi-label {
        color: #666;
        font-size: 0.95em;
    }
    
    .comparison-table {
        width: 100%;
    }
    
    .comparison-table th {
        background: #f0f0f0;
        padding: 12px;
        font-weight: 600;
        border-bottom: 2px solid #0066cc;
    }
    
    .comparison-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
    
    .btn-group .btn {
        border-color: #ddd;
    }
    
    .btn-group .btn.active {
        background: #0066cc;
        border-color: #0066cc;
        color: white;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="assets/js/analytics.js"></script>
