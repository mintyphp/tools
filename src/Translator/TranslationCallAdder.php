<?php

namespace MintyPHP\Tools\Translator;

class TranslationCallAdder
{
    /**
     * Add translation calls to PHTML file content
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
                $echoId++;
                $echoBlocks[$echoId] = $matches[1];
                return '__PHP-ECHO__' . $echoId;
            },
            $content
        );

        // Step 1b: replace PHP blocks (or translated echo blocks) with fake HTML tags
        $content = preg_replace_callback(
            '/<\?php(?:.*?\?>|.*$)/s',
            function ($matches) use (&$phpBlocks, &$phpId) {
                $phpId++;
                $phpBlocks[$phpId] = $matches[0];
                return '<php id=' . $phpId . '></php>';
            },
            $content
        );

        // Step 2: Process attributes (alt, title, placeholder) with translation calls
        $content = preg_replace_callback(
            '/\b(alt|title|placeholder)\s*=\s*"([^"<>]+)"/i',
            function ($matches) use (&$phpBlocks, &$phpId) {
                $attrName = $matches[1];
                $attrValue = $matches[2];

                // Skip if empty
                if (!trim($attrValue)) {
                    return $matches[0];
                }

                // If attrValue contains PHP echo placeholders, add parameters 
                $params = [];
                $paramAttrValue = preg_replace_callback(
                    '/__PHP-ECHO__(\d+)/',
                    function ($echoMatches) use (&$params) {
                        $params[] = $echoMatches[0];
                        return '%s';
                    },
                    $attrValue
                );

                $phpId++;
                if ($params && $paramAttrValue !== '%s') {
                    $escapedText = str_replace('\"', "'", addslashes(preg_replace('/\s+/', ' ', $paramAttrValue)));
                    $phpBlocks[$phpId] = '<?php e(t("' . $escapedText . '", ' . implode(', ', $params) . ')); ?>';
                } else {
                    $escapedText = str_replace('\"', "'", addslashes(preg_replace('/\s+/', ' ', $attrValue)));
                    $phpBlocks[$phpId] = '<?php e(t("' . $escapedText . '")); ?>';
                }
                return $attrName . '="<php id=' . $phpId . '></php>"';
            },
            $content
        );

        // Step 3: Process text content between tags with translation calls
        $content = preg_replace_callback(
            '/>([^<>]+)</s',
            function ($matches) use (&$phpBlocks, &$phpId) {
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

                // Skip if no actual content
                if (!$trimmedText) {
                    return $matches[0];
                }

                // If trimmedText contains PHP echo placeholders, add parameters 
                $params = [];
                $paramTrimmedText = preg_replace_callback(
                    '/__PHP-ECHO__(\d+)/',
                    function ($echoMatches) use (&$params) {
                        $params[] = $echoMatches[0];
                        return '%s';
                    },
                    $trimmedText
                );

                // Create new PHP block with translation call
                $phpId++;
                if ($params && $paramTrimmedText !== '%s') {
                    $escapedText = str_replace('\"', "'", addslashes(preg_replace('/\s+/', ' ', $paramTrimmedText)));
                    $phpBlocks[$phpId] = '<?php e(t("' . $escapedText . '", ' . implode(', ', $params) . ')); ?>';
                } else {
                    $escapedText = str_replace('\"', "'", addslashes(preg_replace('/\s+/', ' ', $trimmedText)));
                    $phpBlocks[$phpId] = '<?php e(t("' . $escapedText . '")); ?>';
                }
                return '>' . $leadingWhite . '<php id=' . $phpId . '></php>' . $trailingWhite . '<';
            },
            $content
        );

        $html = $content;

        // Step 4: Replace all fake PHP tags back with actual PHP blocks using string replacement
        foreach ($phpBlocks as $id => $block) {
            $html = str_replace('<php id=' . $id . '></php>', $block, $html);
        }

        // Step 5: Replace all fake PHP echo placeholders back with actual PHP echo blocks
        foreach ($echoBlocks as $id => $block) {
            $html = str_replace('__PHP-ECHO__' . $id, $block, $html);
        }

        return $html;
    }

    /**
     * Add translation calls to PHP file content
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
        );
    }
}
