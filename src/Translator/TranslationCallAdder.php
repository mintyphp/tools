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
        $phpId = 0;

        // replace doctype with fake tag
        $content = preg_replace('/<!DOCTYPE(.*?)>/is', '<fakedoctype$1></fakedoctype>', $content);
        // replace html tag with fake tag
        $content = preg_replace('/<(\/)?html(.*?)>/is', '<$1fakehtml$2>', $content);

        // replace PHP blocks
        $content = preg_replace_callback(
            '/<\?php(?:.*?\?>|.*$)/s',
            function ($matches) use (&$phpBlocks, &$phpId) {
                $phpId++;
                $phpBlocks[$phpId] = $matches[0];
                return '<php id="' . $phpId . '"></php>';
            },
            $content
        );

        // Step 2: Load into DOMDocument
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);

        // Wrap content to ensure valid HTML structure
        $wrappedContent = '<?xml version="1.0" encoding="UTF-8"?><fakeroot>' . $content . '</fakeroot>';
        $dom->loadHTML($wrappedContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        // Step 3: Process the DOM tree
        $this->processNodeForTranslation($dom->documentElement, $phpBlocks, $phpId);

        // Step 4: Export HTML (extract innerHTML of fakeroot)
        $fakeroot = $dom->getElementsByTagName('fakeroot')->item(0);
        $html = '';
        if ($fakeroot) {
            foreach ($fakeroot->childNodes as $child) {
                $html .= $dom->saveHTML($child);
            }
        }
        // Restore doctype
        $html = preg_replace('/<fakedoctype(.*?)><\/fakedoctype>/is', '<!DOCTYPE$1>', $html);
        // Restore html tag
        $html = preg_replace('/<(\/)?fakehtml(.*?)>/is', '<$1html$2>', $html);

        // Step 5: Replace all fake PHP tags back with actual PHP blocks using string replacement
        foreach ($phpBlocks as $id => $block) {
            $html = str_replace('<php id="' . $id . '"></php>', $block, $html);
            // Also handle escaped without quote variant for attributes
            $html = str_replace('&lt;php id=' . $id . '&gt;&lt;/php&gt;', $block, $html);
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

    /**
     * Recursively process DOM nodes to add translations
     */
    private function processNodeForTranslation(\DOMNode $node, array &$phpBlocks, int &$phpId): void
    {
        // Process attributes for translation (alt, title, placeholder)
        if ($node instanceof \DOMElement) {
            foreach (['alt', 'title', 'placeholder'] as $attrName) {
                if ($node->hasAttribute($attrName)) {
                    $attrValue = $node->getAttribute($attrName);

                    // Skip if empty or already has translation marker
                    if (trim($attrValue) && !str_contains($attrValue, '<php id=')) {
                        $phpId++;
                        $phpBlocks[$phpId] = '<?php e(t("' . $attrValue . '")); ?>';

                        // We'll mark it in a way we can extract later
                        $node->setAttribute($attrName, '<php id=' . $phpId . '></php>');
                    }
                }
            }
        }

        // Process text nodes
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $node->nodeValue;

            // Skip if empty or whitespace-only
            if (!trim($text)) {
                return;
            }

            // Preserve leading and trailing whitespace
            $leadingWhite = '';
            $trailingWhite = '';
            if (preg_match('/^(\s*)(.*?)(\s*)$/s', $text, $whiteMatches)) {
                $leadingWhite = $whiteMatches[1];
                $trimmedText = $whiteMatches[2];
                $trailingWhite = $whiteMatches[3];
            } else {
                $trimmedText = $text;
            }

            // Skip if no actual content
            if (!$trimmedText) {
                return;
            }

            // Create new PHP block with translation call
            $phpId++;
            $phpBlocks[$phpId] = '<?php e(t("' . str_replace('\"', "'", addslashes(preg_replace('/\s+/', ' ', $trimmedText))) . '")); ?>';

            // Replace text node with php element
            $phpElement = $node->ownerDocument->createElement('php');
            $phpElement->setAttribute('id', (string)$phpId);

            $parent = $node->parentNode;
            if ($leadingWhite) {
                $parent->insertBefore($node->ownerDocument->createTextNode($leadingWhite), $node);
            }
            $parent->replaceChild($phpElement, $node);
            if ($trailingWhite) {
                $parent->insertBefore($node->ownerDocument->createTextNode($trailingWhite), $phpElement->nextSibling);
            }

            return;
        }

        // Recursively process child nodes (make a copy of the list since we modify it)
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            $this->processNodeForTranslation($child, $phpBlocks, $phpId);
        }
    }
}
