<?php
if (!defined('PHP_VERSION_ID')) {
    $version = explode('.', PHP_VERSION);
    define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}
if (PHP_VERSION_ID < 70000) {
    echo "ERROR: PHP 7.0 or higher required\n";
    exit(1);
}
if (!function_exists('mysqli_connect')) {
    echo "ERROR: MySQLi extension not found\n";
    exit(1);
}
if (!file_exists('vendor/mintyphp/tools/latest.php')) {
    echo "INFO: Adminer not found, downloading...\n";
    file_put_contents('vendor/mintyphp/tools/latest.php', file_get_contents('https://adminer.org/latest.php'));
}
if (!file_exists('vendor/mintyphp/tools/latest.php')) {
    echo "ERROR: Could not write 'vendor/mintyphp/tools/latest.php'\n";
    exit(1);
}
exit(0);
