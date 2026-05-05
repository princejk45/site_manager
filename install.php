<?php
/**
 * Fullmidia Site Manager - One-Click Installation Wizard
 * 
 * This file guides users through a complete setup without technical knowledge
 * - System requirements check
 * - Database configuration
 * - Admin user creation
 * - License activation
 * - Initial configuration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);

session_start();

// Check if already installed
// Add ?force=1 to re-run installer when credentials/config are broken.
$forceInstall = isset($_GET['force']) && $_GET['force'] === '1';
if ($forceInstall) {
    $_SESSION['force_install'] = true;
}
$forceModeActive = $forceInstall || (isset($_SESSION['force_install']) && $_SESSION['force_install'] === true);
if (file_exists(__DIR__ . '/config/.installed') && !$forceModeActive) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Fullmidia Installer</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #1f2937;
            }
            .card {
                width: min(560px, 92vw);
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.22);
                padding: 28px;
            }
            h1 {
                margin: 0 0 8px;
                font-size: 24px;
            }
            p {
                margin: 0 0 14px;
                line-height: 1.5;
                color: #4b5563;
            }
            .actions {
                margin-top: 18px;
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            a.btn {
                text-decoration: none;
                padding: 11px 14px;
                border-radius: 8px;
                font-weight: 600;
                display: inline-block;
            }
            .primary {
                background: #2563eb;
                color: #fff;
            }
            .secondary {
                background: #f3f4f6;
                color: #111827;
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Installation already completed</h1>
            <p>
                This instance already has <strong>config/.installed</strong>. If the app fails due to database credentials,
                start installer recovery mode.
            </p>
            <div class="actions">
                <a class="btn primary" href="install.php?force=1">Repair database configuration</a>
                <a class="btn secondary" href="index.php">Open application</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

class InstallerWizard
{
    private $baseDir;
    private $currentStep;
    private bool $forceMode;
    private $steps = [
        'welcome' => 'Welcome',
        'requirements' => 'Requirements Check',
        'database' => 'Database Setup',
        'database_migrate' => 'Migrating Database',
        'admin' => 'Create Admin Account',
        'license' => 'License Configuration',
        'summary' => 'Installation Summary',
        'complete' => 'Complete!'
    ];

    public function __construct()
    {
        $this->baseDir = __DIR__;
        $this->forceMode = isset($_SESSION['force_install']) && $_SESSION['force_install'] === true;

        if (($_GET['action'] ?? '') === 'run_migrations') {
            $this->runMigrations();
            exit;
        }

        $this->currentStep = $_GET['step'] ?? 'welcome';
        
        $_SESSION['install_step'] = $this->currentStep;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleFormSubmission();
        }
    }

    private function installUrl(string $query): string
    {
        if ($this->forceMode && strpos($query, 'force=1') === false) {
            return 'install.php?' . $query . '&force=1';
        }

        return 'install.php?' . $query;
    }

    private function runMigrations(): void
    {
        header('Content-Type: application/json');

        try {
            $config = $this->getDbConfig();
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['host'],
                (int)$config['port'],
                $config['database']
            );

            $pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            require_once $this->baseDir . '/services/Database/DbMigrator.php';
            $migrator = new DbMigrator($pdo);
            $result = $migrator->migrate();

            if (($result['success'] ?? false) === false && empty($result['error'])) {
                $result['error'] = $result['errors'][0] ?? 'Unknown migration error';
            }

            echo json_encode($result);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'tables' => [],
                'migrations' => [],
                'errors' => [$e->getMessage()]
            ]);
        }
    }

    public function render()
    {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fullmidia Site Manager - Installation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .progress-bar {
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            width: 100%;
        }
        
        .progress-fill {
            height: 100%;
            background: white;
            width: 20%;
            transition: width 0.3s ease;
        }
        
        .content {
            padding: 40px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .step-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .step-header h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .step-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert.warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        
        .alert.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .check-list {
            list-style: none;
        }
        
        .check-item {
            padding: 12px;
            margin: 8px 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            font-size: 14px;
        }
        
        .check-item.pass {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .check-item.fail {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .check-item.warn {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .check-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .check-item.pass .check-icon { background: #28a745; color: white; }
        .check-item.fail .check-icon { background: #dc3545; color: white; }
        .check-item.warn .check-icon { background: #ffc107; color: white; }
        
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 40px;
            justify-content: space-between;
        }
        
        button {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            flex: 1;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #764ba2;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-disabled {
            background: #e9ecef;
            color: #666;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-disabled:hover {
            background: #e9ecef;
            transform: none;
            box-shadow: none;
        }
        
        .hidden { display: none; }
        
        .success-box {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            margin: 20px 0;
        }
        
        .success-box h3 {
            color: #155724;
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .success-box p {
            color: #155724;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .next-steps {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 6px;
            margin-top: 25px;
        }
        
        .next-steps h4 {
            color: #667eea;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        .next-steps ol {
            margin-left: 20px;
            color: #555;
            font-size: 13px;
            line-height: 1.8;
        }
        
        .password-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        @media (max-width: 600px) {
            .container { margin: 0; }
            .content { padding: 25px; }
            .button-group { flex-direction: column; }
            button { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $this->getProgressPercent() ?>%"></div>
            </div>
            <h1>Fullmidia Site Manager</h1>
            <p>Installation Wizard</p>
        </div>
        
        <div class="content">
            <?php $this->renderStep(); ?>
        </div>
    </div>
</body>
</html>
        <?php
    }

    private function renderStep()
    {
        switch ($this->currentStep) {
            case 'welcome':
                $this->renderWelcome();
                break;
            case 'requirements':
                $this->renderRequirements();
                break;
            case 'database':
                $this->renderDatabase();
                break;
            case 'database_migrate':
                $this->renderDatabaseMigrate();
                break;
            case 'admin':
                $this->renderAdmin();
                break;
            case 'license':
                $this->renderLicense();
                break;
            case 'summary':
                $this->renderSummary();
                break;
            case 'complete':
                $this->renderComplete();
                break;
            default:
                $this->renderWelcome();
        }
    }

    private function renderWelcome()
    {
        ?>
        <div class="step-header">
            <h2>Welcome to Fullmidia!</h2>
            <p>We'll set up your installation in just a few steps</p>
        </div>

        <?php if ((isset($_GET['force']) && $_GET['force'] === '1') || (isset($_SESSION['force_install']) && $_SESSION['force_install'] === true)): ?>
            <div class="alert alert-warning">
                <strong>Recovery mode enabled.</strong> You are running the installer on an existing installation to repair configuration.
            </div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <strong>ℹ No technical knowledge required.</strong> This wizard handles all the configuration automatically.
        </div>
        
        <p style="color: #666; line-height: 1.8; margin-bottom: 30px;">
            Fullmidia Site Manager is a comprehensive platform for managing WordPress sites, automating tasks, and tracking analytics across your entire portfolio. This installation will:
        </p>
        
        <ul style="color: #666; margin-left: 25px; line-height: 2; margin-bottom: 30px;">
            <li>✓ Check your system requirements</li>
            <li>✓ Set up the database</li>
            <li>✓ Create your admin account</li>
            <li>✓ Activate your license (or start trial)</li>
            <li>✓ Complete initial configuration</li>
        </ul>
        
        <div class="button-group">
            <form method="GET" style="flex: 1;">
                <input type="hidden" name="step" value="requirements">
                <button type="submit" class="btn-primary" style="width: 100%;">Start Installation →</button>
            </form>
        </div>
        <?php
    }

    private function renderRequirements()
    {
        $checks = $this->getRequirementChecks();
        $allPass = array_reduce($checks, fn($c, $ch) => $c && $ch['pass'], true);

        ?>
        <div class="step-header">
            <h2>System Requirements</h2>
            <p>Verifying your server configuration</p>
        </div>
        
        <ul class="check-list">
            <?php foreach ($checks as $name => $check): ?>
                <li class="check-item <?= $check['pass'] ? 'pass' : ($check['required'] ? 'fail' : 'warn') ?>">
                    <span class="check-icon"><?= $check['pass'] ? '✓' : ($check['required'] ? '✗' : '⚠') ?></span>
                    <div>
                        <strong><?= $name ?></strong><br>
                        <small><?= $check['value'] ?> (Req: <?= $check['required_val'] ?>)</small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <div class="button-group">
            <form method="GET" style="flex: 1;">
                <input type="hidden" name="step" value="welcome">
                <button type="submit" class="btn-secondary" style="width: 100%;">← Back</button>
            </form>
            <?php if ($allPass): ?>
                <form method="GET" style="flex: 1;">
                    <input type="hidden" name="step" value="database">
                    <button type="submit" class="btn-primary" style="width: 100%;">Continue →</button>
                </form>
            <?php else: ?>
                <button class="btn-disabled" style="width: 100%;" disabled>Fix errors to continue</button>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderDatabase()
    {
        $error = $_SESSION['db_error'] ?? null;
        unset($_SESSION['db_error']);

        ?>
        <div class="step-header">
            <h2>Database Configuration</h2>
            <p>Connect to your MySQL database</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>Connection Failed:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="test_database">
            
            <div class="form-group">
                <label for="db_host">Database Host</label>
                <input type="text" id="db_host" name="db_host" value="localhost" required>
                <small style="color: #666;">Usually "localhost" for local servers</small>
            </div>
            
            <div class="form-group">
                <label for="db_port">Database Port</label>
                <input type="number" id="db_port" name="db_port" value="3306" required>
            </div>
            
            <div class="form-group">
                <label for="db_name">Database Name</label>
                <input type="text" id="db_name" name="db_name" value="website_manager" required>
                <small style="color: #666;">Will be created if it doesn't exist</small>
            </div>
            
            <div class="form-group">
                <label for="db_user">Database Username</label>
                <input type="text" id="db_user" name="db_user" value="root" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">Database Password</label>
                <input type="password" id="db_pass" name="db_pass" placeholder="Leave blank if none">
            </div>
            
            <div class="button-group">
                <form method="GET" style="flex: 1;">
                    <input type="hidden" name="step" value="requirements">
                    <button type="submit" class="btn-secondary" style="width: 100%;">← Back</button>
                </form>
                <button type="submit" class="btn-primary" style="flex: 1;">Test & Continue →</button>
            </div>
        </form>
        <?php
    }

    private function renderDatabaseMigrate()
    {
        ?>
        <div class="step-header">
            <h2>Initializing Database</h2>
            <p>Creating tables and schema...</p>
        </div>
        
        <div id="migration-status" style="background: #f8f9fa; padding: 20px; border-radius: 6px; min-height: 200px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; color: #666;">
            <p>Starting migrations...</p>
        </div>
        
        <script>
            // Auto-migrate in background
            async function runMigrations() {
                const statusDiv = document.getElementById('migration-status');
                const migrationEndpoint = <?= json_encode($this->installUrl('action=run_migrations')) ?>;
                const nextStepUrl = <?= json_encode($this->installUrl('step=admin')) ?>;

                try {
                    const endpoint = new URL(migrationEndpoint, window.location.href).toString();
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });

                    const bodyText = await response.text();
                    let result;
                    try {
                        result = JSON.parse(bodyText);
                    } catch (parseError) {
                        throw new Error('Invalid migration response: ' + bodyText.slice(0, 300));
                    }
                    
                    if (result.success) {
                        statusDiv.innerHTML += `<p style="color: green;">✓ All migrations completed successfully</p>`;
                        setTimeout(() => {
                            window.location.href = nextStepUrl;
                        }, 2000);
                    } else {
                        const errorMessage = result.error
                            || (Array.isArray(result.errors) && result.errors.length ? result.errors.join(' | ') : null)
                            || bodyText.slice(0, 300)
                            || 'Unknown migration error';
                        statusDiv.innerHTML += `<p style="color: red;">✗ Migration error: ${errorMessage}</p>`;
                    }
                } catch (e) {
                    statusDiv.innerHTML += `<p style="color: red;">✗ Error: ${e.message}</p>`;
                }
            }
            
            runMigrations();
        </script>
        
        <div class="button-group">
            <button class="btn-disabled" style="width: 100%;" disabled>Migrations in progress...</button>
        </div>
        <?php
    }

    private function renderAdmin()
    {
        $error = $_SESSION['admin_error'] ?? null;
        unset($_SESSION['admin_error']);

        ?>
        <div class="step-header">
            <h2>Create Administrator Account</h2>
            <p>This will be your login account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="create_admin">
            
            <div class="form-group">
                <label for="admin_username">Username</label>
                <input type="text" id="admin_username" name="admin_username" required minlength="3">
                <small style="color: #666;">3-50 characters, letters and numbers</small>
            </div>
            
            <div class="form-group">
                <label for="admin_email">Email Address</label>
                <input type="email" id="admin_email" name="admin_email" required>
                <small style="color: #666;">Use your personal email</small>
            </div>
            
            <div class="form-group">
                <label for="admin_password">Password</label>
                <input type="password" id="admin_password" name="admin_password" required minlength="8">
                <div class="password-hint">
                    <strong>Password Requirements:</strong><br>
                    • At least 8 characters<br>
                    • Mix of letters, numbers, and symbols
                </div>
            </div>
            
            <div class="form-group">
                <label for="admin_password_confirm">Confirm Password</label>
                <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
            </div>
            
            <div class="button-group">
                <form method="GET" style="flex: 1;">
                    <input type="hidden" name="step" value="database">
                    <button type="submit" class="btn-secondary" style="width: 100%;">← Back</button>
                </form>
                <button type="submit" class="btn-primary" style="flex: 1;">Continue →</button>
            </div>
        </form>
        <?php
    }

    private function renderLicense()
    {
        $licenseError   = $_SESSION['license_error'] ?? null;
        $licenseSuccess = $_SESSION['license_success'] ?? null;
        unset($_SESSION['license_error'], $_SESSION['license_success']);
        ?>
        <div class="step-header">
            <h2>License Configuration</h2>
            <p>Activate your license or start with trial</p>
        </div>

        <?php if ($licenseError): ?>
            <div class="alert alert-danger" style="background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:4px;padding:12px 16px;margin-bottom:16px;">
                ✗ <?= htmlspecialchars($licenseError) ?>
            </div>
        <?php endif; ?>
        <?php if ($licenseSuccess): ?>
            <div class="alert alert-success" style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:4px;padding:12px 16px;margin-bottom:16px;">
                ✓ <?= htmlspecialchars($licenseSuccess) ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-info">
            <strong>Have a license key?</strong> Paste it below and click <em>Verify &amp; Activate</em>. Otherwise, click <em>Use Trial Mode</em> for 30 days of free access.
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="setup_license">

            <div class="form-group">
                <label for="license_key">License Key</label>
                <textarea id="license_key" name="license_key" rows="3" placeholder="FM-XXXX-XXXX-XXXX-XX" style="font-family:monospace;font-size:13px;"></textarea>
                <small style="color:#666;">Paste your license key here</small>
            </div>

            <div class="alert alert-info">
                <strong>📝 No license yet?</strong> You can start with our 30-day trial. All features included. <a href="https://fullmidia.it/pricing" target="_blank" style="color:#0c5460;font-weight:600;">Get a license →</a>
            </div>

            <div class="button-group">
                <a href="?step=admin" class="btn-secondary" style="flex:1;text-align:center;padding:12px;text-decoration:none;display:block;">← Back</a>
                <button type="submit" name="use_trial" value="1" class="btn-secondary" style="flex:1;background:#6c757d;">Use Trial Mode →</button>
                <button type="submit" name="verify_license" value="1" class="btn-primary" style="flex:1;">Verify &amp; Activate →</button>
            </div>
        </form>
        <?php
    }

    private function renderSummary()
    {
        $config = $_SESSION['install_config'] ?? [];
        $completeError = $_SESSION['complete_error'] ?? null;
        unset($_SESSION['complete_error']);

        ?>
        <div class="step-header">
            <h2>Installation Summary</h2>
            <p>Review your settings before completing setup</p>
        </div>

        <?php if ($completeError): ?>
            <div class="alert alert-error">
                <strong>Error:</strong> <?= htmlspecialchars($completeError) ?>
            </div>
        <?php endif; ?>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px;">
            <h4 style="margin-bottom: 15px; color: #333;">Configuration Details:</h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 14px;">
                <div>
                    <strong style="color: #667eea;">Database</strong><br>
                    <small style="color: #666;"><?= htmlspecialchars($config['db_host'] ?? 'localhost') ?>:<?= htmlspecialchars($config['db_port'] ?? '3306') ?></small>
                </div>
                <div>
                    <strong style="color: #667eea;">Database Name</strong><br>
                    <small style="color: #666;"><?= htmlspecialchars($config['db_name'] ?? 'website_manager') ?></small>
                </div>
                <div>
                    <strong style="color: #667eea;">Admin User</strong><br>
                    <small style="color: #666;"><?= htmlspecialchars($config['admin_username'] ?? 'admin') ?></small>
                </div>
                <div>
                    <strong style="color: #667eea;">License Mode</strong><br>
                    <small style="color: #666;"><?= $config['license_mode'] === 'trial' ? '📅 Trial (30 days)' : '🔐 Licensed' ?></small>
                </div>
            </div>
        </div>
        
        <div class="alert alert-success">
            ✓ All settings verified and ready to complete installation!
        </div>
        
        <div class="button-group">
            <form method="GET" style="flex: 1;">
                <input type="hidden" name="step" value="license">
                <button type="submit" class="btn-secondary" style="width: 100%;">← Back</button>
            </form>
            <form method="POST" style="flex: 1;">
                <input type="hidden" name="action" value="complete_installation">
                <button type="submit" class="btn-primary" style="width: 100%; background: #28a745;">✓ Complete Installation</button>
            </form>
        </div>
        <?php
    }

    private function renderComplete()
    {
        $username = $_SESSION['install_config']['admin_username'] ?? 'admin';

        ?>
        <div class="success-box">
            <h3>🎉 Installation Complete!</h3>
            <p>Fullmidia Site Manager is ready to use</p>
        </div>
        
        <div class="next-steps">
            <h4>Next Steps:</h4>
            <ol>
                <li><strong>Delete install.php</strong> - Remove this file from your server for security</li>
                <li><strong>Log in</strong> - Use your admin credentials (<?= htmlspecialchars($username) ?>)</li>
                <li><strong>Configure Settings</strong> - Set up SMTP, Google Sheets integration, and automation rules</li>
                <li><strong>Add Your Sites</strong> - Import your WordPress sites and start managing</li>
                <li><strong>Enable Automations</strong> - Set up health checks and notifications</li>
            </ol>
        </div>
        
        <div style="background: #f0f4ff; padding: 20px; border-radius: 6px; margin-top: 25px; text-align: center;">
            <p style="color: #667eea; font-size: 14px; margin-bottom: 15px;">
                <strong>Documentation & Support</strong>
            </p>
            <a href="https://fullmidia.it/docs" target="_blank" style="display: inline-block; margin: 0 10px; color: #667eea; text-decoration: none; font-weight: 600;">📖 Documentation</a>
            <a href="https://fullmidia.it/support" target="_blank" style="display: inline-block; margin: 0 10px; color: #667eea; text-decoration: none; font-weight: 600;">💬 Support</a>
            <a href="https://fullmidia.it/community" target="_blank" style="display: inline-block; margin: 0 10px; color: #667eea; text-decoration: none; font-weight: 600;">👥 Community</a>
        </div>
        
        <div class="button-group">
            <a href="index.php" style="flex: 1; text-decoration: none;">
                <button class="btn-primary" style="width: 100%;">Go to Dashboard →</button>
            </a>
        </div>
        <?php
    }

    private function handleFormSubmission()
    {
        $action = $_POST['action'] ?? null;

        switch ($action) {
            case 'test_database':
                $this->testDatabaseConnection();
                break;
            case 'create_admin':
                $this->createAdminUser();
                break;
            case 'setup_license':
                $this->setupLicense();
                break;
            case 'complete_installation':
                $this->completeInstallation();
                break;
        }
    }

    private function testDatabaseConnection()
    {
        try {
            $host = trim($_POST['db_host'] ?? 'localhost');
            $port = (int) ($_POST['db_port'] ?? 3306);
            if ($port <= 0) {
                $port = 3306;
            }
            $database = trim($_POST['db_name'] ?? 'website_manager');
            $user = $_POST['db_user'] ?? 'root';
            $pass = $_POST['db_pass'] ?? '';

            // Validate database name - only allow safe identifier characters
            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $database)) {
                throw new Exception('Database name may only contain letters, numbers, underscores and hyphens.');
            }
            if (strlen($database) > 64) {
                throw new Exception('Database name must not exceed 64 characters.');
            }

            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE      => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT      => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);

            // Create database if not exists (name validated above — safe to interpolate)
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Store config
            $_SESSION['install_config']['db_host'] = $host;
            $_SESSION['install_config']['db_port'] = $port;
            $_SESSION['install_config']['db_name'] = $database;
            $_SESSION['install_config']['db_user'] = $user;
            $_SESSION['install_config']['db_pass'] = $pass;
            $_SESSION['install_config']['host'] = $host;
            $_SESSION['install_config']['port'] = $port;
            $_SESSION['install_config']['database'] = $database;
            $_SESSION['install_config']['user'] = $user;
            $_SESSION['install_config']['password'] = $pass;

            // Write to config file
            $this->writeConfigFile([
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'user' => $user,
                'password' => $pass
            ]);

            header('Location: ' . $this->installUrl('step=database_migrate'));
        } catch (Exception $e) {
            $_SESSION['db_error'] = $e->getMessage();
            header('Location: ' . $this->installUrl('step=database'));
        }
    }

    private function createAdminUser()
    {
        $username = $_POST['admin_username'] ?? '';
        $email = $_POST['admin_email'] ?? '';
        $password = $_POST['admin_password'] ?? '';
        $confirmPassword = $_POST['admin_password_confirm'] ?? '';

        if ($password !== $confirmPassword) {
            $_SESSION['admin_error'] = 'Passwords do not match';
            header('Location: ' . $this->installUrl('step=admin'));
            return;
        }

        if (strlen($password) < 8) {
            $_SESSION['admin_error'] = 'Password must be at least 8 characters';
            header('Location: ' . $this->installUrl('step=admin'));
            return;
        }

        if (strlen($username) < 3) {
            $_SESSION['admin_error'] = 'Username must be at least 3 characters';
            header('Location: ' . $this->installUrl('step=admin'));
            return;
        }

        try {
            $config = $this->getDbConfig();
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->fetch()) {
                $_SESSION['admin_error'] = 'Username or email already exists';
                header('Location: ' . $this->installUrl('step=admin'));
                return;
            }

            // Create admin user
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, role, is_active, created_at)
                VALUES (?, ?, ?, 'super_admin', 1, NOW())
            ");
            $stmt->execute([$username, $email, $passwordHash]);

            $_SESSION['install_config']['admin_username'] = $username;
            $_SESSION['install_config']['admin_email'] = $email;

            header('Location: ' . $this->installUrl('step=license'));
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error creating admin user: ' . $e->getMessage();
            header('Location: ' . $this->installUrl('step=admin'));
        }
    }

    private function setupLicense()
    {
        $licenseKey  = trim($_POST['license_key'] ?? '');
        $useTrial    = isset($_POST['use_trial']);
        $verifyKey   = isset($_POST['verify_license']);

        if ($useTrial) {
            $_SESSION['install_config']['license_mode'] = 'trial';
            unset($_SESSION['install_config']['license_key']);
            header('Location: ' . $this->installUrl('step=summary'));
            return;
        }

        if ($verifyKey) {
            if (empty($licenseKey)) {
                $_SESSION['license_error'] = 'Please enter a license key before clicking Verify & Activate.';
                header('Location: ' . $this->installUrl('step=license'));
                return;
            }

            // Basic format check before doing anything heavy
            if (!preg_match('/^FM-[A-Za-z0-9\-\.]+$/', $licenseKey)) {
                $_SESSION['license_error'] = 'Invalid license key format. Keys start with FM- followed by alphanumeric segments.';
                header('Location: ' . $this->installUrl('step=license'));
                return;
            }

            // Save key so LicenseValidator::validate() can read it
            if (!defined('APP_PATH')) {
                define('APP_PATH', $this->baseDir);
            }
            if (!class_exists('LicenseValidator')) {
                $lv = $this->baseDir . '/models/LicenseValidator.php';
                if (file_exists($lv)) require_once $lv;
            }

            if (class_exists('LicenseValidator')) {
                LicenseValidator::saveLicenseKey($licenseKey);
                $result = LicenseValidator::check();

                if (!$result['valid'] && !in_array($result['reason'], ['no_enforcement', 'disabled'], true)) {
                    // Revert the saved key
                    LicenseValidator::saveLicenseKey('');
                    $msg = match ($result['reason'] ?? '') {
                        'expired'          => 'License key has expired (expired: ' . ($result['expires_at'] ?? 'unknown') . ').',
                        'invalid_signature'=> 'License key signature is invalid. Please check the key and try again.',
                        'invalid_format'   => 'License key format is not recognised.',
                        'invalid_payload'  => 'License key payload could not be decoded.',
                        'invalid_public_key' => 'Server RSA public key is not configured.',
                        default            => 'License validation failed: ' . ($result['reason'] ?? 'unknown error'),
                    };
                    $_SESSION['license_error'] = $msg;
                    header('Location: ' . $this->installUrl('step=license'));
                    return;
                }
            }

            $_SESSION['install_config']['license_key']  = $licenseKey;
            $_SESSION['install_config']['license_mode'] = 'licensed';
            $_SESSION['license_success'] = 'License key verified and activated successfully!';
            header('Location: ' . $this->installUrl('step=summary'));
            return;
        }

        // Fallback: just store whatever was submitted and proceed
        $_SESSION['install_config']['license_mode'] = empty($licenseKey) ? 'trial' : 'licensed';
        if (!empty($licenseKey)) {
            $_SESSION['install_config']['license_key'] = $licenseKey;
        }
        header('Location: ' . $this->installUrl('step=summary'));
    }

    private function completeInstallation()
    {
        try {
            // Verify the database is still reachable before marking install as done
            $config = $this->getDbConfig();
            if (empty($config['host']) || empty($config['database'])) {
                throw new Exception('Database configuration is missing from session. Please go back to the database step.');
            }

            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE  => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT  => 5
            ]);

            // Quick sanity check — the users table must exist
            $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($count < 1) {
                throw new Exception('Admin user was not created. Please go back and complete the admin step.');
            }

            // Verify config/database.php was written correctly
            $dbPhpPath = $this->baseDir . '/config/database.php';
            if (!file_exists($dbPhpPath)) {
                // Re-write it now
                $this->writeConfigFile([
                    'host'     => $config['host'],
                    'port'     => $config['port'],
                    'database' => $config['database'],
                    'user'     => $config['user'],
                    'password' => $config['password']
                ]);
            }

            // Create .installed marker file
            if (file_put_contents($this->baseDir . '/config/.installed', date('Y-m-d H:i:s')) === false) {
                throw new Exception('Cannot write config/.installed — check directory permissions.');
            }
            chmod($this->baseDir . '/config/.installed', 0600);

            // Clean up session install data
            unset($_SESSION['install_config']);

            header('Location: ' . $this->installUrl('step=complete'));
        } catch (Exception $e) {
            $_SESSION['complete_error'] = $e->getMessage();
            header('Location: ' . $this->installUrl('step=summary'));
        }
    }

    private function getRequirementChecks(): array
    {
        return [
            'PHP Version' => [
                'pass' => version_compare(PHP_VERSION, '7.4.0') >= 0,
                'value' => PHP_VERSION,
                'required_val' => '7.4.0+',
                'required' => true
            ],
            'PDO MySQL' => [
                'pass' => extension_loaded('pdo_mysql'),
                'value' => extension_loaded('pdo_mysql') ? '✓ Available' : '✗ Missing',
                'required_val' => 'Required',
                'required' => true
            ],
            'JSON Extension' => [
                'pass' => extension_loaded('json'),
                'value' => extension_loaded('json') ? '✓ Available' : '✗ Missing',
                'required_val' => 'Required',
                'required' => true
            ],
            'cURL Extension' => [
                'pass' => extension_loaded('curl'),
                'value' => extension_loaded('curl') ? '✓ Available' : '✗ Missing',
                'required_val' => 'Required',
                'required' => true
            ],
            'File Permissions' => [
                'pass' => is_writable($this->baseDir) && is_writable($this->baseDir . '/config'),
                'value' => (is_writable($this->baseDir) && is_writable($this->baseDir . '/config')) ? '✓ Writable' : '✗ Not Writable',
                'required_val' => 'Writable',
                'required' => true
            ]
        ];
    }

    private function writeConfigFile(array $dbConfig): void
    {
        $configPath = $this->baseDir . '/config';
        if (!file_exists($configPath)) {
            mkdir($configPath, 0755, true);
        }

        // Use var_export for correct PHP string escaping (handles quotes, backslashes, etc.)
        $eHost     = var_export($dbConfig['host'], true);
        $ePort     = (int) $dbConfig['port'];
        $eDatabase = var_export($dbConfig['database'], true);
        $eUser     = var_export($dbConfig['user'], true);
        $ePassword = var_export($dbConfig['password'], true);

        // database.php defines ONLY $dbConfig — bootstrap.php handles the actual PDO connection.
        // This avoids a wasted double-connection on every request.
        $content  = "<?php\n";
        $content .= "// Database Configuration - Auto-generated by installer on " . date('Y-m-d H:i:s') . "\n";
        $content .= "\$dbConfig = [\n";
        $content .= "    'host'     => $eHost,\n";
        $content .= "    'port'     => $ePort,\n";
        $content .= "    'database' => $eDatabase,\n";
        $content .= "    'username' => $eUser,\n";
        $content .= "    'password' => $ePassword,\n";
        $content .= "    'charset'  => 'utf8mb4',\n";
        $content .= "];\n";

        // Write as the canonical database.php the app reads
        if (file_put_contents($configPath . '/database.php', $content) === false) {
            throw new Exception('Could not write config/database.php — check file permissions.');
        }
        chmod($configPath . '/database.php', 0600);

        // Also write the legacy installer config for getDbConfig() fallback
        $legacyContent = "<?php\nreturn [" .
            "'host'=>$eHost," .
            "'port'=>$ePort," .
            "'database'=>$eDatabase," .
            "'user'=>$eUser," .
            "'password'=>$ePassword" .
            "];\n";
        file_put_contents($configPath . '/database_installer.php', $legacyContent);
        chmod($configPath . '/database_installer.php', 0600);
    }

    private function getDbConfig(): array
    {
        $config = $_SESSION['install_config'] ?? [];

        if (empty($config)) {
            $installerConfigPath = $this->baseDir . '/config/database_installer.php';
            if (file_exists($installerConfigPath)) {
                $fileConfig = require $installerConfigPath;
                if (is_array($fileConfig)) {
                    $config = $fileConfig;
                }
            }
        }

        $normalized = [
            'host' => $config['host'] ?? $config['db_host'] ?? 'localhost',
            'port' => (int) ($config['port'] ?? $config['db_port'] ?? 3306),
            'database' => $config['database'] ?? $config['db_name'] ?? 'website_manager',
            'user' => $config['user'] ?? $config['db_user'] ?? 'root',
            'password' => $config['password'] ?? $config['db_pass'] ?? ''
        ];

        if ($normalized['port'] <= 0) {
            $normalized['port'] = 3306;
        }

        return $normalized;
    }

    private function getProgressPercent(): int
    {
        $stepOrder = array_keys($this->steps);
        $currentIndex = array_search($this->currentStep, $stepOrder);
        return ($currentIndex + 1) / count($stepOrder) * 100;
    }
}

$wizard = new InstallerWizard();
$wizard->render();
?>
