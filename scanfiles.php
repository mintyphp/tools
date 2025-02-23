<?php

function scanFiles(array $dirpaths): array
{
    $f = function ($root) use (&$f) {
        if (substr($root, -1) === DIRECTORY_SEPARATOR) {
            $root = substr($root, 0, strlen($root) - 1);
        }

        if (! is_dir($root)) return array();

        $files = array();
        $dir_handle = opendir($root);

        while (($entry = readdir($dir_handle)) !== false) {

            if ($entry === '.' || $entry === '..') continue;

            if (is_dir($root . DIRECTORY_SEPARATOR . $entry)) {
                $sub_files = $f(
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
    };

    $files = [];
    foreach ($dirpaths as $dirpath) {
        $files = array_merge($files, $f($dirpath));
    }

    return $files;
}
