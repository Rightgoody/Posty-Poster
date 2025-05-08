<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'readit_user');  // Changed from 'meow'
define('DB_PASS', 'readit');  // Changed from 'plumeria'
define('DB_NAME', 'ReadIt_data');

// Security settings
define('API_KEY', bin2hex(random_bytes(32)));
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 1800);  // 30 minutes

// Enable HTTPS
if ($_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}
?>