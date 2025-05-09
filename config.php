<?php
// configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'meow');  
define('DB_PASS', 'plumeria'); 
define('DB_NAME', 'posty_post_data');

// security -> bro what
define('API_KEY', bin2hex(random_bytes(32)));
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 1800);  // 30 minutes

// HTTPS if not running from CLI
if (php_sapi_name() !== 'cli') { // Check if not running from the command line
    // if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    //     header("Location: https://" . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''));
    //     exit();
    // }
}
?>