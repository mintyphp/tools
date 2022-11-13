<?php

function createDocBlock(array $variables): string
{
    if (!$variables) return '';
    $docBlock = "/**\n";
    foreach ($variables as $name => $type) {
        $docBlock .= " * @var $type $name\n";
    }
    $docBlock .= " */\n\n";
    return $docBlock;
}

function replaceFirstDocBlock(string $filename, array $variables): bool
{
    $file = file_get_contents($filename);
    $docBlock = createDocBlock($variables);
    if (!preg_match('|^\s*<\?php|s', $file)) {
        $file = "<?php\n?>\n" . $file;
    }
    $file = preg_replace('|<\?php(\s*/\*\*(.*?)\*/)?\s*|s', "<?php\n\n" . $docBlock, $file, 1);
    return file_put_contents($filename, $file) ? true : false;
}

function getPathVariablesFromFilename(string $filename): array
{
    $filename = str_replace('\\', '/', $filename);
    $path = explode('/', $filename);
    if (!preg_match('/^([^\(]*)\((.*)\).php$/', array_pop($path), $matches)) {
        return [];
    }
    return array_fill_keys(array_filter(explode(',', $matches[2])), 'string|null');
}

function getViewVariablesFromFileContent(string $filename): array
{
    $viewVariables = [];
    $actionFile = file_get_contents($filename);
    $tokens = token_get_all($actionFile);
    $tokens = array_merge(array_filter($tokens, function ($token) {
        return $token[0] != T_WHITESPACE;
    }));
    foreach ($tokens as $i => $current) {
        $next = $tokens[$i + 1] ?? '';
        if (is_array($current) && $current[0] == T_VARIABLE && $next == '=') {
            $viewVariables[$current[1]] = 'mixed|null';
        }
    }
    return $viewVariables;
}

function getViewFilenamesFromFilename(string $filename): array
{
    $start = strrpos($filename, '/') + 1;
    $end = strpos($filename, '(', $start);
    $action = substr($filename, $start, $end - $start);
    return glob(substr($filename, 0, $start) . $action . '(*).phtml');
}

function scanDirectories($glob)
{
    $directories = glob($glob, GLOB_ONLYDIR);
    foreach ($directories as $directory) {
        scanDirectories("$directory/*");
        $filenames = glob("$directory/*(*).php");
        foreach ($filenames as $filename) {
            $pathVariables = getPathVariablesFromFilename($filename);
            replaceFirstDocBlock($filename, $pathVariables);
            $viewVariables = getViewVariablesFromFileContent($filename);
            $viewFilenames = getViewFilenamesFromFilename($filename);
            foreach ($viewFilenames as $viewFilename) {
                replaceFirstDocBlock($viewFilename, $pathVariables + $viewVariables);
            }
        }
    }
}

scanDirectories("pages*");
