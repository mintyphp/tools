<?php

use MintyPHP\DB;
use Adminer\Adminer;

// Use default autoload implementation
require 'vendor/autoload.php';
// Load the config parameters
require 'config/config.php';

// database auto-login credentials
if (!isset($_GET["username"])) {
    $_POST["auth"] = array(
        'driver' => 'server',
        'server' => DB::$host . ':' . DB::$port,
        'username' => DB::$username,
        'password' => DB::$password,
        'db' => DB::$database,
    );
}

// Adminer Extension
function adminer_object()
{

    class AdminerSoftware extends Adminer
    {
        public function loginForm()
        {
            echo "<p><a href='?'>Click to login</a></p>\n";
        }

        public function navigation($missing)
        {
            parent::navigation($missing);
            echo '<p class="links"><a href="/conventionist.php">Conventionist</a></p>';
            echo '<p class="links"><a href="/generator.php">Generator</a></p>';
        }
    }

    return new AdminerSoftware;
}

include 'vendor/mintyphp/tools/latest.php';
