<?php
// Use default autoload implementation
require 'vendor/mintyphp/core/src/Loader.php';
// Load the config parameters
require 'config/config.php';

// database auto-login credentials
if (!isset($_GET["username"])) {
    $_POST["auth"] = array(
        'driver' => 'server',
        'server' => \MintyPHP\Config\DB::$host . ':' . \MintyPHP\Config\DB::$port,
        'username' => \MintyPHP\Config\DB::$username,
        'password' => \MintyPHP\Config\DB::$password,
        'db' => \MintyPHP\Config\DB::$database,
    );
}

// Adminer Extension
function adminer_object()
{

    class AdminerSoftware extends Adminer
    {
        public function navigation($missing)
        {
            parent::navigation($missing);
            echo '<p class="links"><a href="/conventionist.php">Conventionist</a></p>';
        }
    }

    return new AdminerSoftware;

}

include 'vendor/mintyphp/tools/latest.php';
