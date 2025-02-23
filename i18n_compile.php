<?php

if (!file_exists('vendor/autoload.php') || !file_exists('pages')) {
    echo "Please run this script from the project root.\n";
    exit(1);
}

// Load the libraries
require 'vendor/autoload.php';

$options = getopt('hp:');
if (isset($options['h'])) {
    echo "Usage: php i18n_compile.php [-d default]\n";
    exit(0);
}
if (isset($options['d'])) {
    $domain = $options['d'];
} else {
    $domain = 'default';
}

$files = glob('i18n/' . $domain . '_*.po');
foreach ($files as $file) {
    $read = fopen($file, "r");
    if (!$read) {
        die("could not read po file: $file");
    }
    $strings = [];
    $scanid = true;
    $msgid = [];
    $msgstr = [];
    while (($line = fgets($read)) !== false) {
        if (substr($line, 0, 5) == 'msgid') {
            $msgid = [json_decode(substr(rtrim($line, "\n"), 6))];
            $scanid = true;
        } elseif (substr($line, 0, 6) == 'msgstr') {
            $msgstr = [json_decode(substr(rtrim($line, "\n"), 7))];
            $scanid = false;
        } elseif (substr($line, 0, 1) == '"') {
            if ($scanid) {
                $msgid[] = json_decode(rtrim($line, "\n"));
            } else {
                $msgstr[] = json_decode(rtrim($line, "\n"));
            }
        } else {
            $strings[implode('', $msgid)] = implode('', $msgstr);
        }
    }
    $strings[implode('', $msgid)] = implode('', $msgstr);
    unset($strings['']);
    fclose($read);
    file_put_contents(str_replace('.po', '.json', $file), json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
