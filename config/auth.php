<?php
// Authentication settings
define('AUTH_SALT', 'your_random_salt_here');
// config/auth.php
define('ENCRYPTION_KEY', 'x@/czqui,;cos[]#2@enzafav-+>4618');
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Session settings
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
