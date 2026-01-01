<?php

namespace MintyPHP\Tools\Tests;

use MintyPHP\Tools\Translator\TranslationCallAdder;
use PHPUnit\Framework\TestCase;

class TranslationCallAdderTest extends TestCase
{
    private TranslationCallAdder $adder;

    protected function setUp(): void
    {
        $this->adder = new TranslationCallAdder();
    }

    public function testAddToPhtml_SimpleText(): void
    {
        $input = '<div>Hello World</div>';
        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('<?php e(t("Hello World")); ?>', $output);
        $this->assertStringContainsString('<div>', $output);
        $this->assertStringContainsString('</div>', $output);
    }

    public function testAddToPhtml_PreservesWhitespace(): void
    {
        $input = '<div>  Hello  </div>';
        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('<div>  <?php e(t("Hello")); ?>  </div>', $output);
    }

    public function testAddToPhtml_AltAttribute(): void
    {
        $input = '<img src="test.jpg" alt="Test Image" />';
        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('alt="<?php e(t("Test Image")); ?>"', $output);
    }

    public function testAddToPhtml_TitleAttribute(): void
    {
        $input = '<a href="#" title="Click here">Link</a>';
        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('title="<?php e(t("Click here")); ?>"', $output);
        $this->assertStringContainsString('<?php e(t("Link")); ?>', $output);
    }

    public function testAddToPhtml_PlaceholderAttribute(): void
    {
        $input = '<input type="text" placeholder="Enter name" />';
        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('placeholder="<?php e(t("Enter name")); ?>"', $output);
    }

    public function testAddToPhtml_PreservesExistingPhp(): void
    {
        $input = '<div><?php echo $var; ?></div>';
        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('<?php echo $var; ?>', $output);
    }

    public function testAddToPhtml_MultipleElements(): void
    {
        $input = '<div>First</div><p>Second</p>';
        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('<?php e(t("First")); ?>', $output);
        $this->assertStringContainsString('<?php e(t("Second")); ?>', $output);
    }

    public function testAddToPhtml_NestedElements(): void
    {
        $input = '<div><span>Nested text</span></div>';
        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('<?php e(t("Nested text")); ?>', $output);
    }

    public function testAddToPhtml_EmptyElements(): void
    {
        $input = '<div></div>';
        $output = $this->adder->addToPhtml($input);

        // Should not add translation calls for empty elements
        $this->assertEquals('<div></div>', trim($output));
    }

    public function testAddToPhtml_WhitespaceOnly(): void
    {
        $input = '<div>   </div>';
        $output = $this->adder->addToPhtml($input);

        // Should not add translation calls for whitespace-only content
        $this->assertStringNotContainsString('e(t(', $output);
    }

    public function testAddToPhtml_CollapsesMultipleSpaces(): void
    {
        $input = '<div>Hello    World</div>';
        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('<?php e(t("Hello World")); ?>', $output);
    }

    public function testAddToPhtml_EscapesQuotes(): void
    {
        $input = '<div>It\'s working</div>';
        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('e(t("It', $output);
    }

    public function testAddToPhtml_SkipsAlreadyTranslated(): void
    {
        $input = '<div><?php e(t("Already translated")); ?></div>';
        $output = $this->adder->addToPhtml($input);

        // Should preserve the existing translation
        $this->assertStringContainsString('<?php e(t("Already translated")); ?>', $output);
        // Should not add another translation wrapper
        $this->assertEquals(1, substr_count($output, 'e(t("Already translated"))'));
    }

    public function testAddToPhp_ErrorMessages(): void
    {
        $input = '$error = "This is an error message";';
        $output = $this->adder->addToPhp($input);

        $this->assertStringContainsString('$error = t("This is an error message");', $output);
    }

    public function testAddToPhp_MultipleErrors(): void
    {
        $input = '$error1 = "First error";' . "\n" . '$error2 = "Second error";';
        $output = $this->adder->addToPhp($input);

        $this->assertStringContainsString('$error1 = t("First error");', $output);
        $this->assertStringContainsString('$error2 = t("Second error");', $output);
    }

    public function testAddToPhp_PreservesNonErrorStrings(): void
    {
        $input = '$name = "John";';
        $output = $this->adder->addToPhp($input);

        // Should not add translation for non-error variables
        $this->assertEquals($input, $output);
    }

    public function testAddToPhtml_ComplexHtmlStructure(): void
    {
        $input = <<<HTML
<div class="container">
    <h1>Welcome</h1>
    <p>This is a paragraph with <strong>bold text</strong> in it.</p>
    <form>
        <input type="text" placeholder="Username" />
        <button type="submit">Submit</button>
    </form>
</div>
HTML;

        $output = $this->adder->addToPhtml($input);

        $this->assertStringContainsString('<?php e(t("Welcome")); ?>', $output);
        $this->assertStringContainsString('<?php e(t("bold text")); ?>', $output);
        $this->assertStringContainsString('placeholder="<?php e(t("Username")); ?>"', $output);
        $this->assertStringContainsString('<?php e(t("Submit")); ?>', $output);
    }

    public function testAddToPhtml_NonClosingPhpBlock(): void
    {
        $input = '<div>Text before</div><?php echo "code";';
        $output = $this->adder->addToPhtml($input);

        // Should preserve the non-closing PHP block as-is
        $this->assertEquals('<div><?php e(t("Text before")); ?></div><?php echo "code";', trim($output));
    }

    public function testAddToPhtml_FullHtmlDocument(): void
    {
        $input = '<?php use bla; ?><!DOCTYPE html><html lang="en"><head></head><body></body></html>';
        $output = $this->adder->addToPhtml($input);

        // Should preserve DOCTYPE and html tags
        $this->assertEquals('<?php use bla; ?><!DOCTYPE html><html lang="en"><head></head><body></body></html>', $output);
    }
}
