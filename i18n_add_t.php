<?php

if (!file_exists('vendor/autoload.php') || !file_exists('pages')) {
    echo "Please run this script from the project root.\n";
    exit(1);
}

// Load the libraries
require 'vendor/autoload.php';
// Import scanFiles function
require __DIR__ . '/scanfiles.php';

$options = getopt('hp:');
if (isset($options['h'])) {
    echo "Usage: php i18n_add_t.php [-p pages,templates]\n";
    exit(0);
}
if (isset($options['p'])) {
    $paths = array_map('trim', explode(',', $options['p']));
} else {
    $paths = ['pages', 'templates'];
}

$files = scanFiles($paths);

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (str_ends_with($file, ".phtml")) {
        $content = preg_replace_callback_array(
            [
                '/(^|[^\?])>(([^<]|(?=<\?php\s+e\(.*\);\s*\?>)<)+)</s' => function ($matches) {
                    if (!trim($matches[2]) || str_starts_with(trim($matches[2]), '<?php e(')) {
                        return $matches[0];
                    }
                    $string = $matches[2];
                    $leadingWhite = '';
                    $trailingWhite = '';
                    if (preg_match('/^(\s*)(.*?)(\s*)$/s', $string, $whiteMatches)) {
                        $leadingWhite = $whiteMatches[1];
                        $string = $whiteMatches[2];
                        $trailingWhite = $whiteMatches[3];
                    }
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
                        return $matches[1] . '>' . $leadingWhite . '<?php e(t("' . str_replace('\"', "'", addslashes(preg_replace('/\s+/', ' ', $string))) . '", ' . implode(',', $args) . ')); ?>' . $trailingWhite . '<';
                    }
                    return $matches[1] . '>' . $leadingWhite . '<?php e(t("' . str_replace('\"', "'", addslashes(preg_replace('/\s+/', ' ', $string))) . '")); ?>' . $trailingWhite . '<';
                },
                '/((alt|title|placeholder)="(.*?)")/s' => function ($matches) {
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
                '/error[^=]+=[^"](".*?")/s' => function ($matches) {
                    return str_replace($matches[1], 't(' . $matches[1] . ')', $matches[0]);
                },
            ],
            $content
        );
    }
    file_put_contents($file, $content);
}
