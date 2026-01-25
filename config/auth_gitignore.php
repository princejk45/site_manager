<?php
// Authentication settings original auth.php
define('AUTH_SALT', 'your_random_salt_here');
// config/auth.php
define('ENCRYPTION_KEY', 'your-very-secret-key-32-characters-long');
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Session settings
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
