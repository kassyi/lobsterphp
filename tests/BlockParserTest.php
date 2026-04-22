<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Tests;

use Kassyi\LobsterPhp\Core\{
    BlockquoteNode,
    BlockParser,
    BulletListNode,
    CodeBlockNode,
    Document,
    HeadingNode,
    HorizontalRuleNode,
    LineBreakNode,
    OrderedListNode,
    ParagraphNode,
    ParseContext,
    TableNode,
    TextNode,
};
use PHPUnit\Framework\TestCase;

/**
 * キャラクターライゼーション・テスト for BlockParser::parseBlocks()
 */
class BlockParserTest extends TestCase {
    private function parse(string $text): array {
        $parser = new BlockParser();
        $doc = $parser->parseDocument($text);
        return $doc->body;
    }

    public function testHeading(): void {
        $nodes = $this->parse("# Heading 1\n## Heading 2");
        $this->assertCount(2, $nodes);
        
        $this->assertInstanceOf(HeadingNode::class, $nodes[0]);
        $this->assertSame(1, $nodes[0]->level);
        $this->assertSame('Heading 1', $nodes[0]->children[0]->text);
        
        $this->assertInstanceOf(HeadingNode::class, $nodes[1]);
        $this->assertSame(2, $nodes[1]->level);
    }

    public function testParagraph(): void {
        $nodes = $this->parse("Paragraph 1\n\nParagraph 2");
        $this->assertCount(2, $nodes);
        $this->assertInstanceOf(ParagraphNode::class, $nodes[0]);
        $this->assertSame('Paragraph 1', $nodes[0]->children[0]->text);
        $this->assertInstanceOf(ParagraphNode::class, $nodes[1]);
    }

    public function testHorizontalRule(): void {
        $nodes = $this->parse("---");
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(HorizontalRuleNode::class, $nodes[0]);
    }

    public function testBlockquote(): void {
        $nodes = $this->parse("> blockquote\n> line 2");
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(BlockquoteNode::class, $nodes[0]);
        $children = $nodes[0]->children;
        $this->assertInstanceOf(ParagraphNode::class, $children[0]);
    }

    public function testCodeBlockWithoutLanguage(): void {
        $nodes = $this->parse("```\ncode\n```");
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(CodeBlockNode::class, $nodes[0]);
        $this->assertSame("code", $nodes[0]->code);
        $this->assertNull($nodes[0]->language);
    }

    public function testCodeBlockWithLanguageAndFile(): void {
        $nodes = $this->parse("```php:test.php\necho 'hello';\n```");
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(CodeBlockNode::class, $nodes[0]);
        $this->assertSame("echo 'hello';", $nodes[0]->code);
        $this->assertSame('php', $nodes[0]->language);
        $this->assertSame('test.php', $nodes[0]->filename);
    }

    public function testBulletList(): void {
        $nodes = $this->parse("- item 1\n- item 2");
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(BulletListNode::class, $nodes[0]);
        $this->assertCount(2, $nodes[0]->items);
    }

    public function testOrderedList(): void {
        $nodes = $this->parse("1. item 1\n2. item 2");
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(OrderedListNode::class, $nodes[0]);
        $this->assertCount(2, $nodes[0]->items);
        $this->assertSame(1, $nodes[0]->start);
    }

    public function testTable(): void {
        $markdown = <<<MD
| Header 1 | Header 2 |
| :--- | ---: |
| Cell 1 | Cell 2 |
MD;
        $nodes = $this->parse($markdown);
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(TableNode::class, $nodes[0]);
        $this->assertCount(2, $nodes[0]->headers);
        $this->assertCount(1, $nodes[0]->rows);
        $this->assertSame('left', $nodes[0]->alignments[0]->value);
        $this->assertSame('right', $nodes[0]->alignments[1]->value);
    }

    public function testWarpInsideCodeFenceIsPlainText(): void {
        $md = "```markdown\n:::warp my-block\nThis content can be placed anywhere.\n:::\n\nSee it here: [~my-block]\n```";
        $parser = new BlockParser();
        $doc = $parser->parseDocument($md);
        
        $this->assertArrayNotHasKey('my-block', $doc->warpDefs);
        $this->assertCount(1, $doc->body);
        $this->assertInstanceOf(CodeBlockNode::class, $doc->body[0]);
        $this->assertStringContainsString(':::warp my-block', $doc->body[0]->code);
        $this->assertStringContainsString('See it here: [~my-block]', $doc->body[0]->code);
    }

    public function testHeaderInsideCodeFenceIsPlainText(): void {
        $md = "```\n:::header\n# Title\n:::\n```";
        $parser = new BlockParser();
        $doc = $parser->parseDocument($md);
        
        $this->assertNull($doc->header);
        $this->assertCount(1, $doc->body);
        $this->assertInstanceOf(CodeBlockNode::class, $doc->body[0]);
    }

    public function testDetailsInsideCodeFenceIsPlainText(): void {
        $md = "~~~\n:::details Click me\nHidden content\n:::\n~~~";
        $parser = new BlockParser();
        $doc = $parser->parseDocument($md);
        
        $this->assertCount(1, $doc->body);
        $this->assertInstanceOf(CodeBlockNode::class, $doc->body[0]);
        $this->assertStringContainsString(':::details Click me', $doc->body[0]->code);
    }
}
