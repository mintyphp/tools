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

$files = scanFiles(['pages', 'templates']);

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
"X-Poedit-SearchPath-0: pages\\n"
"X-Poedit-SearchPath-1: templates\\n"
POT;

$body = '';
foreach ($strings as $string => $locations) {
    $body .= "\n";
    foreach ($locations as $location) {
        $body .= '#: ' . $location . "\n";
    }
    $body .= 'msgid "' . $string . '"' . "\n";
    $body .= 'msgstr ""' . "\n";
}

file_put_contents('i18n/default.pot', $head . "\n" . $body);
