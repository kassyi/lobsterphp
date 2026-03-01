<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Tests;

use Kassyi\LobsterPhp\Core\{CodeSpanNode, EmphasisNode, FootnoteRefNode, ImageNode, InlineFootnoteNode, InlineLinkNode,
    InlineParser, LineBreakNode, LinkDef, LinkNode, ParseContext, StrikethroughNode, StrongNode, TextNode, WarpRefNode};
use PHPUnit\Framework\TestCase;

/**
 * キャラクターライゼーション・テスト for InlineParser::parseInline()
 *
 * このテストは「現状の振る舞いを固定する」ことを目的とします。
 * リファクタリング前後で出力が変わらないことを保証します。
 */
class InlineParserTest extends TestCase {
    private function ctx(): ParseContext {
        return new ParseContext(
            linkDefs: [],
            footnoteDefs: [],
            warpDefs: [],
            footnoteRefs: [],
            inlineFootnoteCount: 0
        );
    }

    private function parse(string $text, ?ParseContext $ctx = null): array {
        return InlineParser::parseInline($text, $ctx ?? $this->ctx());
    }

    // ============================================================
    // テキスト
    // ============================================================

    public function testPlainText(): void {
        $nodes = $this->parse('hello world');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(TextNode::class, $nodes[0]);
        $this->assertSame('hello world', $nodes[0]->text);
    }

    public function testEmptyString(): void {
        $nodes = $this->parse('');
        $this->assertCount(0, $nodes);
    }

    public function testLineBreak(): void {
        $nodes = $this->parse("first\nsecond");
        $this->assertCount(3, $nodes);
        $this->assertInstanceOf(TextNode::class, $nodes[0]);
        $this->assertInstanceOf(LineBreakNode::class, $nodes[1]);
        $this->assertInstanceOf(TextNode::class, $nodes[2]);
        $this->assertSame('first', $nodes[0]->text);
        $this->assertSame('second', $nodes[2]->text);
    }

    // ============================================================
    // コードスパン
    // ============================================================

    public function testCodeSpanSingleBacktick(): void {
        $nodes = $this->parse('foo `bar` baz');
        $this->assertCount(3, $nodes);
        $this->assertInstanceOf(CodeSpanNode::class, $nodes[1]);
        $this->assertSame('bar', $nodes[1]->code);
    }

    public function testCodeSpanDoubleBacktick(): void {
        $nodes = $this->parse('`` `code` ``');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(CodeSpanNode::class, $nodes[0]);
        $this->assertSame('`code`', $nodes[0]->code);
    }

    public function testCodeSpanUnclosed(): void {
        $nodes = $this->parse('foo `bar');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(TextNode::class, $nodes[0]);
    }

    // ============================================================
    // Strong / Emphasis
    // ============================================================

    public function testStrongAsterisks(): void {
        $nodes = $this->parse('**bold**');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(StrongNode::class, $nodes[0]);
        $this->assertInstanceOf(TextNode::class, $nodes[0]->children[0]);
        $this->assertSame('bold', $nodes[0]->children[0]->text);
    }

    public function testStrongUnderscores(): void {
        $nodes = $this->parse('__bold__');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(StrongNode::class, $nodes[0]);
    }

    public function testEmphasisAsterisks(): void {
        $nodes = $this->parse('*italic*');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(EmphasisNode::class, $nodes[0]);
        $this->assertSame('italic', $nodes[0]->children[0]->text);
    }

    public function testEmphasisUnderscores(): void {
        $nodes = $this->parse('_italic_');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(EmphasisNode::class, $nodes[0]);
    }

    public function testStrongInsideText(): void {
        $nodes = $this->parse('foo **bar** baz');
        $this->assertCount(3, $nodes);
        $this->assertInstanceOf(TextNode::class, $nodes[0]);
        $this->assertInstanceOf(StrongNode::class, $nodes[1]);
        $this->assertInstanceOf(TextNode::class, $nodes[2]);
    }

    public function testTripleAsteriskContainsStrong(): void {
        // ***bold*** → literal * が先頭に、残りが **bold** として解釈される
        $nodes = $this->parse('***bold***');
        $hasStrong = false;
        foreach ($nodes as $node) {
            if ($node instanceof StrongNode) {
                $hasStrong = true;
            }
        }
        $this->assertTrue($hasStrong, '***text*** should contain a StrongNode');
    }

    // ============================================================
    // Strikethrough
    // ============================================================

    public function testStrikethrough(): void {
        $nodes = $this->parse('~~strike~~');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(StrikethroughNode::class, $nodes[0]);
        $this->assertSame('strike', $nodes[0]->children[0]->text);
    }

    public function testTripleTildeIsStrikethroughOnly(): void {
        // 仕様書：~~~not~~~ のような場合は内側のペアのみを認識し、余剰の ~ は破棄してキレイなStrikethroughにする
        $nodes = $this->parse('~~~not~~~');
        $this->assertCount(1, $nodes);
        
        $this->assertInstanceOf(StrikethroughNode::class, $nodes[0]);
        $this->assertInstanceOf(TextNode::class, $nodes[0]->children[0]);
        $this->assertSame('not', $nodes[0]->children[0]->text);
    }

    // ============================================================
    // リンク
    // ============================================================

    public function testInlineLink(): void {
        $nodes = $this->parse('[text](https://example.com)');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(InlineLinkNode::class, $nodes[0]);
        $this->assertSame('https://example.com', $nodes[0]->href);
        $this->assertNull($nodes[0]->title);
    }

    public function testInlineLinkWithTitle(): void {
        $nodes = $this->parse('[text](https://example.com "My Title")');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(InlineLinkNode::class, $nodes[0]);
        $this->assertSame('My Title', $nodes[0]->title);
    }

    public function testReferenceLink(): void {
        $ctx = $this->ctx();
        $ctx->linkDefs['example'] = new LinkDef('https://example.com', null);
        $nodes = $this->parse('[Example][example]', $ctx);
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(LinkNode::class, $nodes[0]);
        $this->assertSame('https://example.com', $nodes[0]->href);
    }

    public function testImplicitReferenceLink(): void {
        $ctx = $this->ctx();
        $ctx->linkDefs['example'] = new LinkDef('https://example.com', null);
        $nodes = $this->parse('[Example][]', $ctx);
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(LinkNode::class, $nodes[0]);
    }

    // ============================================================
    // 画像
    // ============================================================

    public function testImage(): void {
        $nodes = $this->parse('![alt](img.png)');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(ImageNode::class, $nodes[0]);
        $this->assertSame('alt', $nodes[0]->alt);
        $this->assertSame('img.png', $nodes[0]->src);
        $this->assertNull($nodes[0]->width);
        $this->assertNull($nodes[0]->height);
    }

    public function testImageWithSize(): void {
        $nodes = $this->parse('![alt](img.png =320x240)');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(ImageNode::class, $nodes[0]);
        $this->assertSame(320, $nodes[0]->width);
        $this->assertSame(240, $nodes[0]->height);
    }

    public function testImageWithWidthOnly(): void {
        $nodes = $this->parse('![alt](img.png =320x)');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(ImageNode::class, $nodes[0]);
        $this->assertSame(320, $nodes[0]->width);
        $this->assertNull($nodes[0]->height);
    }

    // ============================================================
    // 脚注
    // ============================================================

    public function testFootnoteRef(): void {
        $ctx = $this->ctx();
        $nodes = $this->parse('[^note]', $ctx);
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(FootnoteRefNode::class, $nodes[0]);
        $this->assertSame('note', $nodes[0]->id);
        $this->assertContains('note', $ctx->footnoteRefs);
    }

    public function testInlineFootnote(): void {
        $ctx = $this->ctx();
        $nodes = $this->parse('^[inline note]', $ctx);
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(InlineFootnoteNode::class, $nodes[0]);
        $this->assertCount(1, $ctx->footnoteRefs);
    }

    // ============================================================
    // Warp参照
    // ============================================================

    public function testWarpRef(): void {
        $nodes = $this->parse('[~my-warp]');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(WarpRefNode::class, $nodes[0]);
        $this->assertSame('my-warp', $nodes[0]->id);
    }

    public function testWarpRefWithSpaceIsInvalid(): void {
        // スペースを含むIDは無効 → テキストノードとしてパスする
        $nodes = $this->parse('[~my warp]');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(TextNode::class, $nodes[0]);
    }

    // ============================================================
    // 複合パターン
    // ============================================================

    public function testMixedInlines(): void {
        $nodes = $this->parse('Hello **world** and `code`!');
        $hasStrong = false;
        $hasCode = false;
        foreach ($nodes as $node) {
            if ($node instanceof StrongNode) $hasStrong = true;
            if ($node instanceof CodeSpanNode) $hasCode = true;
        }
        $this->assertTrue($hasStrong);
        $this->assertTrue($hasCode);
    }

    public function testPlainTextPassThrough(): void {
        $nodes = $this->parse('no special chars here');
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(TextNode::class, $nodes[0]);
    }
}
