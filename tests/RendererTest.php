<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Tests;

use PHPUnit\Framework\TestCase;
use Kassyi\LobsterPhp\Lobster;

class RendererTest extends TestCase {
    
    // ==========================================
    // Headings
    // ==========================================
    public function testRendersH1() {
        $html = Lobster::toHtml("# Hello");
        $this->assertStringContainsString('class="lbs-heading-1"', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function testRendersH3() {
        $html = Lobster::toHtml("### Section");
        $this->assertStringContainsString('class="lbs-heading-3"', $html);
    }

    // ==========================================
    // Paragraph
    // ==========================================
    public function testWrapsTextInP() {
        $html = Lobster::toHtml("Hello world");
        $this->assertStringContainsString('<p', $html);
        $this->assertStringContainsString('Hello world', $html);
    }

    public function testHtmlEscapesSpecialChars() {
        $html = Lobster::toHtml("5 < 10 & 10 > 5");
        $this->assertStringContainsString('&lt;', $html);
        $this->assertStringContainsString('&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
    }

    // ==========================================
    // Inline decorations
    // ==========================================
    public function testBoldRendersStrong() {
        $html = Lobster::toHtml("**bold text**");
        $this->assertStringContainsString('class="lbs-strong"', $html);
        $this->assertStringContainsString('bold text', $html);
    }

    public function testItalicRendersEmphasis() {
        $html = Lobster::toHtml("*italic text*");
        $this->assertStringContainsString('class="lbs-emphasis"', $html);
    }

    public function testStrikeRendersStrikethrough() {
        $html = Lobster::toHtml("~~strikethrough~~");
        $this->assertStringContainsString('class="lbs-strikethrough"', $html);
    }

    public function testCodeRendersCode() {
        $html = Lobster::toHtml("`inline code`");
        $this->assertStringContainsString('<code', $html);
        $this->assertStringContainsString('inline code', $html);
    }

    // ==========================================
    // Horizontal Rule
    // ==========================================
    public function testHorizontalRule() {
        $html = Lobster::toHtml("---");
        $this->assertStringContainsString('<hr', $html);
    }

    // ==========================================
    // Code block
    // ==========================================
    public function testCodeBlockRendersPreCode() {
        $html = Lobster::toHtml("```\nconst x = 1;\n```");
        $this->assertStringContainsString('<pre', $html);
        $this->assertStringContainsString('<code>', $html);
        $this->assertStringContainsString('const x = 1;', $html);
    }

    public function testCodeBlockLanguage() {
        $html = Lobster::toHtml("```typescript\nlet x: number;\n```");
        $this->assertStringContainsString('data-language="typescript"', $html);
    }

    public function testCodeBlockFilename() {
        $html = Lobster::toHtml("```ts:index.ts\nlet x;\n```");
        $this->assertStringContainsString('index.ts', $html);
        $this->assertStringContainsString('lbs-code-filename', $html);
    }

    public function testCodeBlockEscapesHtml() {
        $html = Lobster::toHtml("```\n<script>alert('xss')</script>\n```");
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ==========================================
    // Blockquote
    // ==========================================
    public function testBlockquote() {
        $html = Lobster::toHtml("> A quote");
        $this->assertStringContainsString('<blockquote', $html);
    }

    // ==========================================
    // Lists
    // ==========================================
    public function testBulletList() {
        $html = Lobster::toHtml("- Item 1\n- Item 2");
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('<li', $html);
        $this->assertStringContainsString('Item 1', $html);
        $this->assertStringContainsString('Item 2', $html);
    }

    public function testOrderedList() {
        $html = Lobster::toHtml("1. First\n2. Second");
        $this->assertStringContainsString('<ol', $html);
        $this->assertStringContainsString('First', $html);
        $this->assertStringContainsString('Second', $html);
    }

    public function testChecklist() {
        $html = Lobster::toHtml("- [ ] Todo\n- [x] Done");
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('checked', $html);
    }

    // ==========================================
    // Links
    // ==========================================
    public function testInlineLink() {
        $html = Lobster::toHtml("[Click](https://example.com)");
        $this->assertStringContainsString('<a href="https://example.com"', $html);
        $this->assertStringContainsString('Click', $html);
    }

    public function testLinkWithTitle() {
        $html = Lobster::toHtml('[Go](https://example.com "My Site")');
        $this->assertStringContainsString('title="My Site"', $html);
    }

    public function testReferenceLink() {
        $md = "[example]: https://example.com\n\n[Click][example]";
        $html = Lobster::toHtml($md);
        $this->assertStringContainsString('<a href="https://example.com"', $html);
        $this->assertStringContainsString('Click', $html);
    }

    // ==========================================
    // Images
    // ==========================================
    public function testImage() {
        $html = Lobster::toHtml("![Logo](logo.png)");
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('src="logo.png"', $html);
        $this->assertStringContainsString('alt="Logo"', $html);
    }

    public function testImageWithSize() {
        // NOTE: The original vitest checks for 'width=' but looking closely at the regex
        // `![img](photo.jpg =800x600)` does not include `width=` directly in the parsed output 
        // string for `width`? Ah, yes `width="800"` in html output.
        $html = Lobster::toHtml("![img](photo.jpg =800x600)");
        $this->assertStringContainsString('width="800"', $html);
        $this->assertStringContainsString('height="600"', $html);
    }

    // ==========================================
    // Tables
    // ==========================================
    public function testTable() {
        $md = "| Name | Age |\n| ---- | --- |\n| Alice | 30 |";
        $html = Lobster::toHtml($md);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<thead', $html);
        $this->assertStringContainsString('<tbody', $html);
        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('<td', $html);
        $this->assertStringContainsString('Alice', $html);
    }

    public function testSilentTable() {
        $md = "~ | A | B |\n~ | -- | -- |\n~ | 1 | 2 |";
        $html = Lobster::toHtml($md);
        $this->assertStringContainsString('lbs-table-silent', $html);
    }

    public function testTableAlignment() {
        $md = "| A | B |\n| :--- | ---: |\n| left | right |";
        $html = Lobster::toHtml($md);
        $this->assertStringContainsString('text-align:left', $html);
        $this->assertStringContainsString('text-align:right', $html);
    }

    // ==========================================
    // Custom Blocks
    // ==========================================
    public function testHeader() {
        $md = ":::header\nSite Name\n:::";
        $html = Lobster::toHtml($md);
        $this->assertStringContainsString('<header', $html);
        $this->assertStringContainsString('Site Name', $html);
    }

    public function testFooter() {
        $md = ":::footer\n© 2025\n:::";
        $html = Lobster::toHtml($md);
        $this->assertStringContainsString('<footer', $html);
        $this->assertStringContainsString('© 2025', $html);
    }

    public function testDetails() {
        $md = ":::details Click to expand\nHidden content\n:::";
        $html = Lobster::toHtml($md);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('<summary', $html);
        $this->assertStringContainsString('Click to expand', $html);
        $this->assertStringContainsString('Hidden content', $html);
    }

    // ==========================================
    // Footnotes
    // ==========================================
    public function testFootnoteLinks() {
        $md = "See[^note]\n\n[^note]: Footnote text";
        $html = Lobster::toHtml($md);
        $this->assertStringContainsString('<sup', $html);
        $this->assertStringContainsString('[1]', $html);
    }

    public function testFootnoteDefsAtBottom() {
        $md = "Hello[^a]\n\n[^a]: Note content";
        $html = Lobster::toHtml($md);
        $this->assertStringContainsString('lbs-footnotes', $html);
        $this->assertStringContainsString('Note content', $html);
    }
}
