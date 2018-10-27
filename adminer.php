<?php
// Use default autoload implementation
require 'vendor/mintyphp/core/src/Loader.php';
// Load the config parameters
require 'config/config.php';

// database auto-login credentials
$_GET["username"] = "";
// bypass database selection bug
$_GET["db"] = MintyPHP\Config\DB::$database;

// Adminer Extension
function adminer_object()
{

    class AdminerSoftware extends Adminer
    {

        public function credentials()
        {
            return array(\MintyPHP\Config\DB::$host . ':' . \MintyPHP\Config\DB::$port, \MintyPHP\Config\DB::$username, \MintyPHP\Config\DB::$password);
        }

        public function database()
        {
            return \MintyPHP\Config\DB::$database;
        }

        public function navigation($missing)
        {
            parent::navigation($missing);
            echo '<p class="links"><a href="/conventionist.php">Conventionist</a></p>';
        }

        public function login($username, $password)
        {
            return true;
        }

    }

    return new AdminerSoftware;

}

include 'vendor/mintyphp/tools/latest.php';
