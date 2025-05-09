<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'meow');  // Changed from 'meow'
define('DB_PASS', 'plumeria');  // Changed from 'plumeria'
define('DB_NAME', 'posty_post_data'); // my database name

// Security settings
define('API_KEY', bin2hex(random_bytes(32)));
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 1800);  // 30 minutes

// Enable HTTPS (only in a web server environment)
if (php_sapi_name() !== 'cli') { // Check if not running from the command line
    // if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    //     header("Location: https://" . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''));
    //     exit();
    // }
}
?>