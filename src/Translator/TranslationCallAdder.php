<?php

namespace MintyPHP\Tools\Translator;

class TranslationCallAdder
{
    /**
     * Add translation calls to PHTML file content
     * @param string $content PHTML file content
     * @return string Modified PHTML content with translation calls
     */
    public function addToPhtml(string $content): string
    {
        // Step 1: Extract all PHP blocks and replace with fake HTML tags
        $phpBlocks = [];
        $echoBlocks = [];
        $phpId = 0;
        $echoId = 0;

        // Step 1a: replace PHP echo blocks (that are not already translated) with placeholders
        $content = preg_replace_callback(
            '/<\?php\s*e\((?!t\()(.*?)\);\s*\?>/s',
            function ($matches) use (&$echoBlocks, &$echoId) {
                $echoBlocks[++$echoId] = $matches[0];
                return '__PHP-ECHO__' . $echoId;
            },
            $content
        ) ?? $content;

        // Step 1b: replace PHP blocks (or translated echo blocks) with fake HTML tags
        $content = preg_replace_callback(
            '/<\?php(.*?\?>|.*$)/s',
            function ($matches) use (&$phpBlocks, &$phpId) {
                $phpBlocks[++$phpId] = $matches[0];
                return '<php id=' . $phpId . '></php>';
            },
            $content
        ) ?? $content;

        // Step 2: Process attributes (alt, title, placeholder) with translation calls
        $content = preg_replace_callback(
            '/\b(alt|title|placeholder)\s*=\s*"([^"<>]+)"/i',
            function ($matches) use (&$phpBlocks, &$echoBlocks, &$phpId) {
                $attrName = $matches[1];
                $attrValue = $matches[2];

                // Skip if empty or only a PHP echo placeholder
                if (!trim($attrValue) || preg_match('/^__PHP-ECHO__\d+$/', $attrValue)) {
                    return $matches[0];
                }

                $phpBlocks[++$phpId] = $this->createTranslationBlock($attrValue, $echoBlocks);
                return $attrName . '="<php id=' . $phpId . '></php>"';
            },
            $content
        ) ?? $content;

        // Step 3: Process text content between tags with translation calls
        $content = preg_replace_callback(
            '/>([^<>]+)</s',
            function ($matches) use (&$phpBlocks, &$echoBlocks, &$phpId) {
                $text = $matches[1];

                // Skip if whitespace-only or contains fake PHP tags
                if (!trim($text) || str_contains($text, '<php id=')) {
                    return $matches[0];
                }

                // Preserve leading and trailing whitespace
                preg_match('/^(\s*)(.*?)(\s*)$/s', $text, $whiteMatches);
                $leadingWhite = $whiteMatches[1];
                $trimmedText = $whiteMatches[2];
                $trailingWhite = $whiteMatches[3];

                // Skip if no actual content or only a PHP echo placeholder
                if (!$trimmedText || preg_match('/^__PHP-ECHO__\d+$/', $trimmedText)) {
                    return $matches[0];
                }

                $phpBlocks[++$phpId] = $this->createTranslationBlock($trimmedText, $echoBlocks);
                return '>' . $leadingWhite . '<php id=' . $phpId . '></php>' . $trailingWhite . '<';
            },
            $content
        ) ?? $content;

        // Step 4: Restore PHP blocks
        foreach ($echoBlocks as $id => $block) {
            $content = str_replace('__PHP-ECHO__' . $id, $block, $content);
        }

        // Step 5Restore PHP block placeholders
        foreach ($phpBlocks as $id => $block) {
            $content = str_replace('<php id=' . $id . '></php>', $block, $content);
        }

        return $content;
    }

    /**
     * Process PHP echo placeholders and create a translation block
     * @param string $text Text content with possible PHP echo placeholders
     * @param array<int,string> $echoBlocks Array of PHP echo blocks
     * @return string Generated translation PHP block
     */
    private function createTranslationBlock(string $text, array $echoBlocks): string
    {
        $params = [];
        $processedText = preg_replace_callback(
            '/__PHP-ECHO__(\d+)/',
            function ($matches) use (&$params, $echoBlocks) {
                $echoBlock = $echoBlocks[$matches[1]];
                preg_match('/<\?php\s*e\((.*?)\);\s*\?>/', $echoBlock, $innerMatches);
                $params[] = $innerMatches[1];
                return '%s';
            },
            $text
        ) ?? $text;

        $escapedText = str_replace('\"', "'", addslashes(preg_replace('/\s+/', ' ', $processedText) ?? $processedText));

        if ($params) {
            return '<?php e(t("' . $escapedText . '", ' . implode(', ', $params) . ')); ?>';
        }

        return '<?php e(t("' . $escapedText . '")); ?>';
    }

    /**
     * Add translation calls to PHP file content
     * @param string $content PHP file content
     * @return string Modified PHP content with translation calls
     */
    public function addToPhp(string $content): string
    {
        return preg_replace_callback_array(
            [
                '/error[^=]+=[^"](".*?")/s' => function ($matches) {
                    return str_replace($matches[1], 't(' . $matches[1] . ')', $matches[0]);
                },
            ],
            $content
        ) ?? $content;
    }
}
