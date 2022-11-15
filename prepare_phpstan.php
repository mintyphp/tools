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
        $previous = $tokens[$i - 1] ?? '';
        $next = $tokens[$i + 1] ?? '';
        if (is_array($current) && $current[0] == T_VARIABLE && $next == '=' && ($previous[0] ?? 0) != T_DOUBLE_COLON) {
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

function getTemplateFilenameFromViewFilename(string $filename): string
{
    $start = strrpos($filename, '(') + 1;
    $end = strpos($filename, ')', $start);
    $template = substr($filename, $start, $end - $start);
    $directory = str_replace('pages', 'templates', explode('/', $filename)[0]);
    return "$directory/$template.php";
}

function scanDirectories($glob)
{
    $templateViews = [];
    $directories = glob($glob, GLOB_ONLYDIR);
    foreach ($directories as $directory) {
        scanDirectories("$directory/*");
        $filenames = glob("$directory/*(*).php");
        foreach ($filenames as $filename) {
            $pathVariables = getPathVariablesFromFilename($filename);
            $viewVariables = getViewVariablesFromFileContent($filename);
            $viewFilenames = getViewFilenamesFromFilename($filename);
            $templateVariables = [];
            foreach ($viewFilenames as $viewFilename) {
                $templateFilename = getTemplateFilenameFromViewFilename($viewFilename);
                $templateViewFilename = preg_replace('|\.php$|', '.phtml', $templateFilename);
                $templateVariables += file_exists($templateFilename) ? getViewVariablesFromFileContent($templateFilename) : [];
                if ($templateVariables && !isset($templateViews[$templateViewFilename])) {
                    replaceFirstDocBlock($templateViewFilename, $templateVariables);
                    $templateViews[$templateViewFilename] = true;
                }
                replaceFirstDocBlock($viewFilename, $pathVariables + $viewVariables + $templateVariables);
            }
            replaceFirstDocBlock($filename, $pathVariables + $templateVariables);
        }
    }
}

scanDirectories("pages*");
