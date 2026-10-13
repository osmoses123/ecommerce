<?php
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/ecommerce');

define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);
define('UPLOAD_ALLOWED', ['image/jpeg', 'image/png', 'image/webp']);

define('SHIPPING_FLAT', 1500);
define('SHIPPING_FREE_OVER', 50000);

date_default_timezone_set('Africa/Lagos');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
