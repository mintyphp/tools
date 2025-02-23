<?php

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

if (!file_exists('vendor/autoload.php') || !file_exists('pages')) {
    echo "Please run this script from the project root.\n";
    exit(1);
}

// Load the libraries
require 'vendor/autoload.php';
// Import scanFiles function
require __DIR__ . '/scanfiles.php';

$options = getopt('hp:d:');
if (isset($options['h'])) {
    echo "Usage: php i18n_extract.php [-p pages,templates] [-d default]\n";
    exit(0);
}
if (isset($options['p'])) {
    $paths = array_map('trim', explode(',', $options['p']));
} else {
    $paths = ['pages', 'templates'];
}
if (isset($options['d'])) {
    $domain = $options['d'];
} else {
    $domain = 'default';
}

$files = scanFiles($paths);

class I18nExtractor extends NodeVisitorAbstract
{
    private string $filename;
    private array $strings;

    public function __construct(string $filename, array $strings)
    {
        $this->filename = $filename;
        $this->strings = $strings;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\FuncCall) {
            if ($node->name->name === 't') {
                if ($node->args[0] instanceof PhpParser\Node\Arg) {
                    if ($node->args[0]->value instanceof PhpParser\Node\Scalar\String_) {
                        $string = $node->args[0]->value->value;
                        if (!isset($this->strings[$string])) {
                            $this->strings[$string] = [];
                        }
                        $this->strings[$string][] = $this->filename . ':' . $node->getLine();
                    }
                }
            }
        }
    }

    public function getStrings(): array
    {
        return $this->strings;
    }
}

function wordAwareStringSplit(string $string, int $maxLengthOfLine): array
{
    if (strlen($string) < $maxLengthOfLine - 6) {
        return [$string];
    }

    $lines = [''];
    $words = explode(' ', $string);

    $currentLine = '';
    $lineAccumulator = '';
    foreach ($words as $currentWord) {

        $currentWordWithSpace = sprintf('%s ', $currentWord);
        $lineAccumulator .= $currentWordWithSpace;
        if (strlen($lineAccumulator) < $maxLengthOfLine) {
            $currentLine = $lineAccumulator;
            continue;
        }

        $lines[] = $currentLine;

        // Overwrite the current line and accumulator with the current word
        $currentLine = $currentWordWithSpace;
        $lineAccumulator = $currentWordWithSpace;
    }

    if ($currentLine !== '') {
        $lines[] = $currentLine;
    }

    $lines[count($lines) - 1] = rtrim($lines[count($lines) - 1], ' ');

    return $lines;
}

$parserFactory = new ParserFactory();
$parser = $parserFactory->createForNewestSupportedVersion();

$strings = [];
foreach ($files as $file) {
    $content = file_get_contents($file);
    $ast = $parser->parse($content);
    $traverser = new NodeTraverser();
    $extractor = new I18nExtractor($file, $strings);
    $traverser->addVisitor($extractor);
    $traverser->traverse($ast);
    $strings = $extractor->getStrings();
}

$potCreationDate = date('Y-m-d H:i:O');

$potfile = <<<POT
#, fuzzy
msgid ""
msgstr ""
"POT-Creation-Date: $potCreationDate\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Poedit-Basepath: ..\\n"
"X-Poedit-KeywordsList: t\\n"
POT;

// Add search paths
$potfile .= trim($potfile) . "\n";
foreach ($paths as $i => $path) {
    $potfile .= '"X-Poedit-SearchPath-' . $i . ': ' . $path . '\\n"' . "\n";
}

foreach ($strings as $string => $locations) {
    $potfile .= "\n";
    foreach ($locations as $location) {
        $potfile .= '#: ' . $location . "\n";
    }
    $msgid = wordAwareStringSplit($string, 80);
    $potfile .= 'msgid ';
    foreach ($msgid as $s) {
        $potfile .= json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
    $potfile .= 'msgstr ""' . "\n";
}

if (!file_exists('i18n')) {
    mkdir('i18n');
}
file_put_contents('i18n/' . $domain . '.pot', $potfile);
