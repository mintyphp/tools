<?php
session_save_path(sys_get_temp_dir());
$dir = $_SERVER['DOCUMENT_ROOT'];
$file = realpath($dir . $_SERVER['SCRIPT_NAME']);
if (!file_exists('config/config.php')) {
    require 'vendor/mintyphp/tools/configurator.php';
    die();
}
if (file_exists($file) && (strpos($file, $dir) === 0)) {
    return false;
}

if (in_array($_SERVER['SCRIPT_NAME'], array('/adminer.php', '/conventionist.php', '/configurator.php', '/generator.php'))) {
    require 'vendor/mintyphp/tools' . $_SERVER['SCRIPT_NAME'];
} else {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    chdir('web');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    require 'index.php';
}
