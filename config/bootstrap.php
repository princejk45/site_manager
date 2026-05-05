<?php
// Configure temporary directory for PHP and Google API client
// This ensures that running as Apache daemon user can create temp files
if (!ini_get('upload_tmp_dir') || !is_writable(ini_get('upload_tmp_dir'))) {
    @ini_set('upload_tmp_dir', '/Applications/XAMPP/xamppfiles/temp');
}
if (!ini_get('sys_temp_dir') || !is_writable(ini_get('sys_temp_dir'))) {
    @ini_set('sys_temp_dir', '/Applications/XAMPP/xamppfiles/temp');
}

// Define base path FIRST
define('BASE_PATH', realpath(dirname(__DIR__)));
define('APP_PATH', BASE_PATH);
// Define web path for use in href/src attributes
define('WEB_PATH', '/fullmidia/site_manager');
if (file_exists(APP_PATH . '/vendor/autoload.php')) {
    require_once APP_PATH . '/vendor/autoload.php';
}
// Define default language
define('DEFAULT_LANG', 'it');

// Error Reporting:
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load auth config FIRST to get constants
require APP_PATH . '/config/auth.php';

// Configure session settings BEFORE starting session
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_lifetime', SESSION_TIMEOUT);

// Start session FIRST before any language checking
session_start([
    'cookie_lifetime' => SESSION_TIMEOUT,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Initialize dashboard version (default to v2 - modern)
if (!isset($_SESSION['dashboard_version'])) {
    $_SESSION['dashboard_version'] = 'v2';
}

// Initialize language
// Check if language is set in GET parameter
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'it', 'fr'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Set language, defaulting to session value or default
$lang = $_SESSION['lang'] ?? DEFAULT_LANG;
$_SESSION['lang'] = $lang; // Ensure session is always set
$translations = [];

// Load language file
$langFile = APP_PATH . "/lang/{$lang}.php";
if (file_exists($langFile)) {
    $translations = require $langFile;
}

// Helper function
function __(string $key, array $params = []): string
{
    global $translations;

    $keys = explode('.', $key);
    $value = $translations;

    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $key; // Return key if translation not found
        }
        $value = $value[$k];
    }

    // Simple parameter replacement
    foreach ($params as $k => $v) {
        $value = str_replace("{{$k}}", $v, $value);
    }

    return $value;
}

function sm_parse_date_value(mixed $value, bool $allowTime = false): ?DateTimeImmutable
{
    if ($value instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($value);
    }

    if ($value === null) {
        return null;
    }

    if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+(?:\.\d+)?$/', trim($value)))) {
        $numeric = (float) $value;
        if ($numeric > 1000 && class_exists('PhpOffice\\PhpSpreadsheet\\Shared\\Date')) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($numeric);
                return DateTimeImmutable::createFromMutable($dt);
            } catch (Throwable) {
                // Fall through to string parsing.
            }
        }
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $formats = $allowTime
        ? ['d-m-Y H:i:s', 'd-m-Y H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d']
        : ['d-m-Y', 'd/m/Y', 'Y-m-d', 'd.m.Y'];

    foreach ($formats as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $raw);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }
    }

    try {
        return new DateTimeImmutable($raw);
    } catch (Exception) {
        return null;
    }
}

function sm_normalize_date(mixed $value, ?string $default = null): ?string
{
    $parsed = sm_parse_date_value($value, false);
    if ($parsed instanceof DateTimeImmutable) {
        return $parsed->format('Y-m-d');
    }

    return $default;
}

function sm_format_date(mixed $value, string $fallback = ''): string
{
    $parsed = sm_parse_date_value($value, false);
    return $parsed instanceof DateTimeImmutable ? $parsed->format('d-m-Y') : $fallback;
}

function sm_format_datetime(mixed $value, bool $includeSeconds = false, string $fallback = ''): string
{
    $parsed = sm_parse_date_value($value, true);
    if (!$parsed instanceof DateTimeImmutable) {
        return $fallback;
    }

    return $parsed->format($includeSeconds ? 'd-m-Y H:i:s' : 'd-m-Y H:i');
}

function sm_form_date_value(mixed $value): string
{
    return sm_format_date($value, '');
}

function sm_form_datetime_local_value(mixed $value): string
{
    $parsed = sm_parse_date_value($value, true);
    return $parsed instanceof DateTimeImmutable ? $parsed->format('d-m-Y H:i') : '';
}

// Load configuration files (auth.php already loaded above)
require APP_PATH . '/config/database.php';
require APP_PATH . '/config/mailer.php';
require APP_PATH . '/config/constants.php';

if (!isset($dbConfig) || !is_array($dbConfig)) {
    $dbConfig = [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'website_manager',
        'charset' => 'utf8mb4',
        'socket' => '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock',
    ];
}

// Session timeout and security management
if (isset($_SESSION['LAST_ACTIVITY'])) {
    // Session timeout based on last activity
    if (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: index.php?action=login&timeout=1');
        exit();
    }

    // Session ID regeneration logic (every half of timeout period)
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['LAST_ACTIVITY'] > (SESSION_TIMEOUT / 2)) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time(); // Reset creation time
    }
}

// Always update last activity time
$_SESSION['LAST_ACTIVITY'] = time();

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default timezone
date_default_timezone_set('UTC');

// Enhanced autoloader with namespace support
spl_autoload_register(function ($class) {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);

    // Try controllers first
    $file = APP_PATH . "/controllers/$class.php";
    if (file_exists($file)) {
        require $file;
        return;
    }

    // Then try models
    $file = APP_PATH . "/models/$class.php";
    if (file_exists($file)) {
        require $file;
        return;
    }

    // Optional: Log missing class for debugging
    error_log("Autoload failed: Class $class not found");
});

// Database connection with improved error handling
try {
    // Try socket connection first (for CLI), then fallback to host (for web)
    if (!empty($dbConfig['socket']) && file_exists($dbConfig['socket'])) {
        $dsn = "mysql:unix_socket={$dbConfig['socket']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    } else {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    }
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false
    ];

    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
    $GLOBALS['pdo'] = $pdo;
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

// Optional: CSRF token generation if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
