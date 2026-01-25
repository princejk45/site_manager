<?php
// Application constants (APP_PATH is now defined before this file loads)
define('APP_NAME', 'Fullmidia Web');
define('APP_VERSION', '1.0.0');
define('DEFAULT_TIMEZONE', 'UTC');

// Status constants
define('STATUS_ACTIVE', 'attivo');
define('STATUS_EXPIRING_SOON', 'scade_presto');
define('STATUS_EXPIRED', 'scaduto');

// Path constants (using existing APP_PATH)
define('UPLOAD_PATH', APP_PATH . '/uploads');
define('EXPORT_PATH', APP_PATH . '/exports');

// Create directories if they don't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!file_exists(EXPORT_PATH)) {
    mkdir(EXPORT_PATH, 0755, true);
}