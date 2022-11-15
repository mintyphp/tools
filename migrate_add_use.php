<?php

function replaceUseBlock(string $filename, array $usedClasses): bool
{
    $newUseStatements = array_map(function ($class) {
        return "\nuse MintyPHP\\$class;";
    }, $usedClasses);
    $file = file_get_contents($filename);
    $oldUseStatements = [];
    if (preg_match_all('/\nuse [^;]*;/', $file, $matches)) {
        $oldUseStatements = $matches[0];
    }
    $allUseStatements = array_unique(array_merge($newUseStatements, $oldUseStatements));
    if (!preg_match('|^\s*<\?php|s', $file)) {
        $file = "<?php\n?>\n" . $file;
    }
    $file = preg_replace('|<\?php(\s*use (.*?);)*\s*|s', "<?php\n" . implode('', $allUseStatements) . "\n\n", $file, 1);
    return file_put_contents($filename, $file) ? true : false;
}

function getUsedClassesFromFileContent(string $filename, array $classes): array
{
    $usedClasses = [];
    $fileContents = file_get_contents($filename);
    $tokens = token_get_all($fileContents);
    $tokens = array_merge(array_filter($tokens, function ($token) {
        return $token[0] != T_WHITESPACE;
    }));
    if (preg_match('/catch\s*\(\s*DBError/', $fileContents)) {
        $usedClasses[] = 'DBError';
    }
    foreach ($tokens as $i => $current) {
        $previous = $tokens[$i - 1] ?? '';
        if (is_array($current) && $current[0] == T_DOUBLE_COLON && in_array($previous[1], $classes)) {
            $usedClasses[] = $previous[1];
        }
    }
    return $usedClasses;
}

function scanDirectories($glob, $classes)
{
    $directories = glob($glob, GLOB_ONLYDIR);
    foreach ($directories as $directory) {
        if ($directory == 'vendor') {
            continue;
        }
        scanDirectories("$directory/*", $classes);
        $filenames = array_merge(glob("$directory/*.php"), glob("$directory/*.phtml"));
        foreach ($filenames as $filename) {
            $usedClasses = getUsedClassesFromFileContent($filename, $classes);
            replaceUseBlock($filename, $usedClasses);
        }
    }
}

function getClasses($globs): array
{
    $classes = [];
    foreach ($globs as $glob) {
        $filenames = glob($glob);
        foreach ($filenames as $filename) {
            $classes[] = basename($filename, '.php');
        }
    }
    return $classes;
}

$classes = getClasses(['vendor/mintyphp/core/src/*.php', 'lib/*.php']);
scanDirectories("*", $classes);
