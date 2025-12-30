<?php

namespace MintyPHP\Tools;

use MintyPHP\Form\Elements as E;
use MintyPHP\Form\Form;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

class TranslatorTool
{
    /**
     * Static method to run the translator tool.
     */
    public static function run(): void
    {
        (new self())->execute();
    }

    public function getHtml(): string
    {
        $html = [];
        $html[] = '<!DOCTYPE html>';
        $html[] = '<html>';
        $html[] = '<head>';
        $html[] = '    <title>MintyPHP Translator</title>';
        $html[] = '    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $html[] = '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html[] = '    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/1.0.3/css/bulma.min.css">';
        $html[] = '</head>';
        $html[] = '<body class="container p-5">';
        $html[] = '    <div>';
        $html[] = '        <h1 class="title">';
        $html[] = '            MintyPHP Translator';
        $html[] = '        </h1>';
        $html[] = $_SERVER['REQUEST_METHOD'] == 'POST' ? $this->getPostHtml() : $this->getFormHtml();
        $html[] = '    </div>';
        $html[] = '</body>';
        $html[] = '';
        $html[] = '</html>';
        return implode("\n", $html);
    }

    /**
     * Execute the translator tool logic.
     */
    public function execute(): void
    {
        $this->ensureI18nDirectory();
        E::$style = 'bulma';
        echo $this->getHtml();
    }

    /**
     * Ensure i18n directory exists
     */
    private function ensureI18nDirectory(): void
    {
        if (!file_exists('i18n')) {
            mkdir('i18n', 0755, true);
        }
    }

    /**
     * Detect domains and languages from i18n folder
     */
    private function detectDomainsAndLanguages(): array
    {
        $info = [
            'domains' => [],
            'languages' => []
        ];

        // Detect domains from .pot files
        $potFiles = glob('i18n/*.pot');
        foreach ($potFiles as $file) {
            $domain = basename($file, '.pot');
            $info['domains'][] = $domain;
            $info['languages'][$domain] = [];
        }

        // Detect languages per domain from .po files
        $poFiles = glob('i18n/*.po');
        foreach ($poFiles as $file) {
            $filename = basename($file, '.po');
            // Pattern: domain_language.po (e.g., default_nl.po)
            if (preg_match('/^(.+?)_([a-z]{2}(?:_[A-Z]{2})?)$/', $filename, $matches)) {
                $domain = $matches[1];
                $language = $matches[2];
                if (!in_array($domain, $info['domains'])) {
                    $info['domains'][] = $domain;
                }
                if (!isset($info['languages'][$domain])) {
                    $info['languages'][$domain] = [];
                }
                if (!in_array($language, $info['languages'][$domain])) {
                    $info['languages'][$domain][] = $language;
                }
            }
        }

        return $info;
    }

    private function getPostHtml(): string
    {
        $operation = $_POST['operation'] ?? '';
        $paths = isset($_POST['paths']) ? array_map('trim', explode(',', $_POST['paths'])) : ['pages', 'templates'];
        $domain = $_POST['domain'] ?? 'default';

        $html = [];
        $html[] = '<div class="content">';

        ob_start();
        try {
            switch ($operation) {
                case 'add_t':
                    $this->addTranslations($paths);
                    echo "\nTranslation function calls added successfully.\n";
                    break;
                case 'extract':
                    $this->extractTranslations($paths, $domain);
                    echo "\nTranslations extracted to i18n/$domain.pot successfully.\n";
                    break;
                case 'compile':
                    $this->compileTranslations($domain);
                    echo "\nTranslations compiled successfully.\n";
                    break;
                case 'add_language':
                    $targetLanguage = $_POST['target_language'] ?? 'nl';
                    $this->addLanguage($domain, $targetLanguage);
                    echo "\nLanguage $targetLanguage added successfully for domain $domain.\n";
                    break;
                case 'remove_language':
                    $targetLanguage = $_POST['target_language'] ?? '';
                    $this->removeLanguage($domain, $targetLanguage);
                    echo "\nLanguage $targetLanguage removed successfully from domain $domain.\n";
                    break;
                default:
                    echo "Unknown operation.\n";
            }
        } catch (\Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
        $output = ob_get_clean();

        $html[] = '<pre>' . htmlspecialchars($output ?: '') . '</pre>';
        $html[] = '<a href="?" class="button is-primary">Back</a>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function getFormHtml(): string
    {
        $i18nInfo = $this->detectDomainsAndLanguages();

        $html = [];

        // Show detected domains and languages
        $html[] = '<div class="notification is-info is-light">';
        $html[] = '<h2 class="subtitle">Current Status:</h2>';

        if (empty($i18nInfo['domains'])) {
            $html[] = '<p>No translation domains found.</p>';
        } else {
            $html[] = '<p><strong>Domains:</strong> ' . implode(', ', $i18nInfo['domains']) . '</p>';
            foreach ($i18nInfo['domains'] as $domain) {
                $languages = $i18nInfo['languages'][$domain] ?? [];
                if (!empty($languages)) {
                    $html[] = '<p><strong>' . htmlspecialchars($domain) . ':</strong> ' . implode(', ', array_map('htmlspecialchars', $languages)) . '</p>';
                } else {
                    $html[] = '<p><strong>' . htmlspecialchars($domain) . ':</strong> No translations yet</p>';
                }
            }
        }
        $html[] = '</div>';

        // Language Management Form
        $html[] = '<div class="box">';
        $html[] = '<h2 class="subtitle">Language Management</h2>';
        $html[] = '<p class="content">Add or remove translation languages.</p>';
        $languageManagementForm = $this->buildLanguageManagementForm($i18nInfo);
        $html[] = $languageManagementForm->toString();
        $html[] = '</div>';

        // Main Operations Form
        $html[] = '<div class="box mt-5">';
        $html[] = '<h2 class="subtitle">Translation Operations:</h2>';
        $html[] = '<div class="content">';
        $html[] = '<ol>';
        $html[] = '<li><strong>Add Translation Calls:</strong> Wraps text in your template files with t() translation function calls.</li>';
        $html[] = '<li><strong>Extract Translations:</strong> Scans your code for t() calls and creates/updates a .pot file with all translatable strings.</li>';
        $html[] = '<li><strong>Actual Translation:</strong> Use the external tool <a href="https://poedit.net/">Poedit</a> to update the .po files for each language.</li>';
        $html[] = '<li><strong>Compile Translations:</strong> Converts .po files to .json files for use at runtime.</li>';
        $html[] = '</ol>';
        $html[] = '</div>';
        $mainOperationsForm = $this->buildMainOperationsForm();
        $html[] = $mainOperationsForm->toString();
        $html[] = '</div>';
        $html[] = '<a href="/" class="button">Back</a>';

        return implode("\n", $html);
    }

    private function buildLanguageManagementForm(array $i18nInfo): Form
    {
        E::$style = 'bulma';
        $form = E::form()->method('POST');

        // Operation selection
        $form->field(E::field(
            E::select('operation', [
                'add_language' => 'Add Language',
                'remove_language' => 'Remove Language'
            ]),
            E::label('Operation')
        ));

        // Domain field
        $form->field(E::field(
            E::text('domain')->value('default'),
            E::label('Domain')
        ));

        // Target language field (en = English)
        $form->field(E::field(
            E::text('target_language')->value('en'),
            E::label('Language (en = English)')
        ));

        // Submit button
        $form->field(E::field(E::submit('Execute')));

        return $form;
    }

    private function buildMainOperationsForm(): Form
    {
        E::$style = 'bulma';
        $form = E::form()->method('POST');

        // Operation selection
        $form->field(E::field(
            E::select('operation', [
                'add_t' => '1. Add Translation Calls',
                'extract' => '2. Extract Translations',
                'compile' => '4. Compile Translations'
            ]),
            E::label('Operation')
        ));

        // Paths field
        $form->field(E::field(
            E::text('paths')->value('pages,templates'),
            E::label('Paths (comma-separated)')
        ));

        // Domain field
        $form->field(E::field(
            E::text('domain')->value('default'),
            E::label('Domain')
        ));

        // Submit button
        $form->field(E::field(E::submit('Execute')));

        return $form;
    }

    /**
     * Add translation function calls to template files
     */
    private function addTranslations(array $paths): void
    {
        $files = $this->scanFiles($paths);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_ends_with($file, ".phtml")) {
                $content = preg_replace_callback_array(
                    [
                        '/(^|[^=\?])>(([^<]|(?=<\?php\s+e\(.*\);\s*\?>)<)+)</s' => function ($matches) {
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
            echo "Processed: $file\n";
        }
    }

    /**
     * Extract translations from code files
     */
    private function extractTranslations(array $paths, string $domain): void
    {
        $files = $this->scanFiles($paths);

        $parserFactory = new ParserFactory();
        $parser = $parserFactory->createForNewestSupportedVersion();

        $strings = [];
        foreach ($files as $file) {
            echo "Scanning: $file\n";
            $content = file_get_contents($file);
            $ast = $parser->parse($content);
            $traverser = new NodeTraverser();
            $extractor = new I18nExtractor($file, $strings);
            $traverser->addVisitor($extractor);
            $traverser->traverse($ast);
            $strings = $extractor->getStrings();
            echo "Scanned: $file\n";
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
        $potfile = trim($potfile) . "\n";
        foreach ($paths as $i => $path) {
            $potfile .= '"X-Poedit-SearchPath-' . $i . ': ' . $path . '\\n"' . "\n";
        }

        foreach ($strings as $string => $locations) {
            $potfile .= "\n";
            foreach ($locations as $location) {
                $potfile .= '#: ' . $location . "\n";
            }
            $msgid = $this->wordAwareStringSplit($string, 80);
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
        echo "Created: i18n/$domain.pot with " . count($strings) . " strings\n";
    }

    /**
     * Add a new language without auto-translation
     */
    private function addLanguage(string $domain, string $targetLanguage): void
    {
        $potFile = "i18n/$domain.pot";

        if (!file_exists($potFile)) {
            throw new \Exception("POT file not found: $potFile. Please extract translations first.");
        }

        // Parse the POT file to get all strings
        $strings = $this->parsePotFile($potFile);

        if (empty($strings)) {
            echo "No strings found in $potFile\n";
            return;
        }

        echo "Found " . count($strings) . " strings\n";

        // Generate PO file with empty translations
        $emptyStrings = [];
        foreach ($strings as $string => $locations) {
            $emptyStrings[$string] = [
                'translation' => '',
                'locations' => $locations
            ];
        }

        $this->generatePoFile($domain, $targetLanguage, $emptyStrings);
        echo "Created: i18n/{$domain}_{$targetLanguage}.po with " . count($strings) . " empty translations\n";
    }

    /**
     * Remove a language
     */
    private function removeLanguage(string $domain, string $targetLanguage): void
    {
        $poFile = "i18n/{$domain}_{$targetLanguage}.po";
        $jsonFile = "i18n/{$domain}_{$targetLanguage}.json";

        $removed = [];

        if (file_exists($poFile)) {
            unlink($poFile);
            $removed[] = $poFile;
            echo "Removed: $poFile\n";
        }

        if (file_exists($jsonFile)) {
            unlink($jsonFile);
            $removed[] = $jsonFile;
            echo "Removed: $jsonFile\n";
        }

        if (empty($removed)) {
            throw new \Exception("No files found for language '$targetLanguage' in domain '$domain'");
        }
    }

    /**
     * Parse POT file and extract strings with their locations
     */
    private function parsePotFile(string $potFile): array
    {
        $content = file_get_contents($potFile);
        $strings = [];
        $lines = explode("\n", $content);

        $currentLocations = [];
        $currentMsgid = [];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            if (str_starts_with($line, '#:')) {
                $currentLocations[] = trim(substr($line, 2));
            } elseif (str_starts_with($line, 'msgid')) {
                $currentMsgid = [json_decode(substr(trim($line), 6))];
                // Check for multi-line msgid
                $j = $i + 1;
                while ($j < count($lines) && str_starts_with(trim($lines[$j]), '"')) {
                    $currentMsgid[] = json_decode(trim($lines[$j]));
                    $j++;
                }
                $i = $j - 1;
            } elseif (str_starts_with($line, 'msgstr')) {
                $msgid = implode('', $currentMsgid);
                if ($msgid !== '') {
                    $strings[$msgid] = $currentLocations;
                }
                $currentLocations = [];
                $currentMsgid = [];
            }
        }

        return $strings;
    }

    /**
     * Parse PO file and extract translations (msgid => msgstr mapping)
     */
    private function parsePoFile(string $poFile): array
    {
        $content = file_get_contents($poFile);
        $translations = [];
        $lines = explode("\n", $content);

        $currentMsgid = [];
        $currentMsgstr = [];
        $inMsgid = false;
        $inMsgstr = false;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            if (str_starts_with($line, 'msgid')) {
                // Save previous entry if exists
                if (!empty($currentMsgid)) {
                    $msgid = implode('', $currentMsgid);
                    $msgstr = implode('', $currentMsgstr);
                    if ($msgid !== '') {
                        $translations[$msgid] = $msgstr;
                    }
                }

                $currentMsgid = [json_decode(substr(trim($line), 6))];
                $currentMsgstr = [];
                $inMsgid = true;
                $inMsgstr = false;
            } elseif (str_starts_with($line, 'msgstr')) {
                $currentMsgstr = [json_decode(substr(trim($line), 7))];
                $inMsgid = false;
                $inMsgstr = true;
            } elseif (str_starts_with(trim($line), '"')) {
                if ($inMsgid) {
                    $currentMsgid[] = json_decode(trim($line));
                } elseif ($inMsgstr) {
                    $currentMsgstr[] = json_decode(trim($line));
                }
            } else {
                // Reset on empty line or comment
                $inMsgid = false;
                $inMsgstr = false;
            }
        }

        // Save last entry
        if (!empty($currentMsgid)) {
            $msgid = implode('', $currentMsgid);
            $msgstr = implode('', $currentMsgstr);
            if ($msgid !== '') {
                $translations[$msgid] = $msgstr;
            }
        }

        return $translations;
    }

    /**
     * Generate a PO file from translated strings
     */
    private function generatePoFile(string $domain, string $language, array $translatedStrings): void
    {
        $poFile = "i18n/{$domain}_{$language}.po";
        $creationDate = date('Y-m-d H:i:O');

        $content = <<<POT
# Translation file for $domain ($language)
msgid ""
msgstr ""
"POT-Creation-Date: $creationDate\\n"
"PO-Revision-Date: $creationDate\\n"
"Language: $language\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"

POT;

        foreach ($translatedStrings as $original => $data) {
            $content .= "\n";
            foreach ($data['locations'] as $location) {
                $content .= "#: $location\n";
            }

            $msgid = $this->wordAwareStringSplit($original, 80);
            $content .= 'msgid ';
            foreach ($msgid as $s) {
                $content .= json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            }

            $msgstr = $this->wordAwareStringSplit($data['translation'], 80);
            $content .= 'msgstr ';
            foreach ($msgstr as $s) {
                $content .= json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }

        file_put_contents($poFile, $content);
    }

    /**
     * Compile .po files to .json files
     */
    private function compileTranslations(string $domain): void
    {
        $files = glob('i18n/' . $domain . '_*.po');

        if (empty($files)) {
            echo "No .po files found for domain '$domain'\n";
            return;
        }

        foreach ($files as $file) {
            $read = fopen($file, "r");
            if (!$read) {
                throw new \Exception("Could not read po file: $file");
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

            $jsonFile = str_replace('.po', '.json', $file);
            file_put_contents($jsonFile, json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            echo "Compiled: $file -> $jsonFile (" . count($strings) . " strings)\n";
        }
    }

    /**
     * Recursively scan directories for files
     */
    private function scanFiles(array $dirpaths): array
    {
        $f = function ($root) use (&$f) {
            if (substr($root, -1) === DIRECTORY_SEPARATOR) {
                $root = substr($root, 0, strlen($root) - 1);
            }

            if (!is_dir($root)) return array();

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

    private function wordAwareStringSplit(string $string, int $maxLengthOfLine): array
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
}

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
                if ($node->args[0] instanceof \PhpParser\Node\Arg) {
                    if ($node->args[0]->value instanceof \PhpParser\Node\Scalar\String_) {
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
