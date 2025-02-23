<?php

use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

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
    $paths = $options['p'];
} else {
    $paths = ['pages', 'templates'];
}
if (isset($options['d'])) {
    var_dump($options);
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

$head = <<<POT
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
$head .= trim($head) . "\n";
foreach ($paths as $i => $path) {
    $head .= '"X-Poedit-SearchPath-' . $i . ': ' . $path . '\\n"' . "\n";
}

$body = '';
foreach ($strings as $string => $locations) {
    $body .= "\n";
    foreach ($locations as $location) {
        $body .= '#: ' . $location . "\n";
    }
    $msgid = wordAwareStringSplit($string, 80);
    $body .= 'msgid ';
    foreach ($msgid as $s) {
        $body .= json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
    $body .= 'msgstr ""' . "\n";
}

if (!file_exists('i18n')) {
    mkdir('i18n');
}
file_put_contents('i18n/' . $domain . '.pot', $head . "\n" . $body);
