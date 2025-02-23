<?php

if (!file_exists('vendor/autoload.php') || !file_exists('pages')) {
    echo "Please run this script from the project root.\n";
    exit(1);
}

// Load the libraries
require 'vendor/autoload.php';

function recursiveDirectoryList($root)
{
    if (substr($root, -1) === DIRECTORY_SEPARATOR) {
        $root = substr($root, 0, strlen($root) - 1);
    }

    if (! is_dir($root)) return array();

    $files = array();
    $dir_handle = opendir($root);

    while (($entry = readdir($dir_handle)) !== false) {

        if ($entry === '.' || $entry === '..') continue;

        if (is_dir($root . DIRECTORY_SEPARATOR . $entry)) {
            $sub_files = recursiveDirectoryList(
                $root .
                    DIRECTORY_SEPARATOR .
                    $entry .
                    DIRECTORY_SEPARATOR
            );
            $files = array_merge($files, $sub_files);
        } else {
            $files[] = $root . DIRECTORY_SEPARATOR . $entry;
        }
    }

    return (array) $files;
}

$dirpaths = [
    'pages',
    'templates',
];

$files = [];
foreach ($dirpaths as $dirpath) {
    $files = array_merge($files, recursiveDirectoryList($dirpath));
}

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (str_ends_with($file, ".phtml")) {
        $content = preg_replace_callback_array(
            [
                '/(^|[^\?])>(\s*)(([^<]|(?=<\?php\s+e\(.*\);\s*\?>)<)+)(\s*)</m' => function ($matches) {
                    if (!trim($matches[3]) || str_starts_with($matches[3], '<?php e(')) {
                        return $matches[0];
                    }
                    $string = $matches[3];
                    $args = [];
                    $string = preg_replace_callback_array(
                        [
                            '/<\?php\s+e\((.*)\);\s*\?>/' => function ($matches) use (&$args) {
                                $args[] = $matches[1];
                                return '%s';
                            },
                        ],
                        $string
                    );
                    if ($args) {
                        return $matches[1] . '>' . $matches[2] . '<?php e(t("' . str_replace('\"', "'", addslashes($string)) . '", ' . implode(',', $args) . ')); ?>' . $matches[5] . '<';
                    }
                    return $matches[1] . '>' . $matches[2] . '<?php e(t("' . str_replace('\"', "'", addslashes($string)) . '")); ?>' . $matches[5] . '<';
                },
                '/((alt|title|placeholder)="(.*?)")/m' => function ($matches) {
                    if (!trim($matches[3]) || str_starts_with($matches[3], '<?php e(')) {
                        return $matches[0];
                    }
                    return str_replace($matches[1], $matches[2] . '="<?php e(t("' . $matches[3] . '")); ?>"', $matches[0]);
                },
            ],
            $content
        );
    }
    if (str_ends_with($file, ".php")) {
        $content = preg_replace_callback_array(
            [
                '/error[^=]+=[^"](".*?")/m' => function ($matches) {
                    return str_replace($matches[1], 't(' . $matches[1] . ')', $matches[0]);
                },
            ],
            $content
        );
    }
    file_put_contents($file, $content);
}
