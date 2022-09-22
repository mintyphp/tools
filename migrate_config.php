<?php

$config = file_get_contents('config/config.php');
if (!strpos($config, 'namespace MintyPHP\Config')) {
    die("already migrated\n");
}
copy('config/config.php', 'config/config.php.old');
$lines = explode("\n", $config);
$class = '';
$result = ['<?php'];
foreach ($lines as $line) {
    if (preg_match('|class (\S+)|', $line, $matches)) {
        $class = "MintyPHP\\" . $matches[1];
    } elseif (preg_match('|static \$|', $line, $matches)) {
        $result[] = "$class::\$" . substr($line, strpos($line, '$') + 1);
    } else if (!trim($line)) {
        $result[] = '';
    }
}
file_put_contents('config/config.php', implode("\n", $result));
