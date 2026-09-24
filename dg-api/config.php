<?php
// API Configuration. The key and database login come from the server's
// settings (SetEnv / fastcgi_param), never from this file.
define('SECRET_API_KEY', (string) getenv('DG_API_KEY'));
define('ALLOWED_ORIGIN', getenv('DG_API_ALLOWED_ORIGIN') ?: 'https://www.my.dast-garmi.ir');

// Database Configuration: the panel's own database, as in dbconfig.php.
define('DB_HOST', getenv('LMS_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('LMS_DB_NAME') ?: 'myhona_myhonar');
define('DB_USER', getenv('LMS_DB_USER') ?: '');
define('DB_PASS', getenv('LMS_DB_PASS') !== false ? (string) getenv('LMS_DB_PASS') : '');
?>
