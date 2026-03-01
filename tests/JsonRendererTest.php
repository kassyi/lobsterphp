<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Tests;

use PHPUnit\Framework\TestCase;
use Kassyi\LobsterPhp\Renderer\JsonRenderer;

class JsonRendererTest extends TestCase {
    private function render(string $md): array {
        return JsonRenderer::renderDocument(
            \Kassyi\LobsterPhp\Core\BlockParser::parseDocument($md)
        );
    }

    public function testHeading(): void {
        $ast = $this->render('# Hello World');
        $this->assertSame('heading', $ast['body'][0]['type']);
        $this->assertSame(1, $ast['body'][0]['level']);
        $this->assertSame('text', $ast['body'][0]['children'][0]['type']);
        $this->assertSame('Hello World', $ast['body'][0]['children'][0]['value']);
    }

    public function testParagraph(): void {
        $ast = $this->render('Simple text.');
        $this->assertSame('paragraph', $ast['body'][0]['type']);
        $this->assertSame('Simple text.', $ast['body'][0]['children'][0]['value']);
    }

    public function testStrongAndEmphasis(): void {
        $ast = $this->render('**bold** and *italic*');
        $children = $ast['body'][0]['children'];
        $types = array_column($children, 'type');
        $this->assertContains('strong', $types);
        $this->assertContains('emphasis', $types);
    }

    public function testCodeBlock(): void {
        $ast = $this->render("```php\necho 'hi';\n```");
        $node = $ast['body'][0];
        $this->assertSame('codeBlock', $node['type']);
        $this->assertSame('php', $node['language']);
        $this->assertSame("echo 'hi';", trim($node['value']));
    }

    public function testBulletList(): void {
        $ast = $this->render("- Item 1\n- Item 2");
        $node = $ast['body'][0];
        $this->assertSame('list', $node['type']);
        $this->assertFalse($node['ordered']);
        $this->assertCount(2, $node['items']);
        $this->assertSame('listItem', $node['items'][0]['type']);
    }

    public function testOrderedList(): void {
        $ast = $this->render("1. First\n2. Second");
        $node = $ast['body'][0];
        $this->assertSame('list', $node['type']);
        $this->assertTrue($node['ordered']);
        $this->assertSame(1, $node['start']);
    }

    public function testTable(): void {
        $ast = $this->render("| A | B |\n|---|---|\n| 1 | 2 |");
        $node = $ast['body'][0];
        $this->assertSame('table', $node['type']);
        $this->assertCount(2, $node['headers']);
        $this->assertCount(1, $node['rows']);
        $this->assertCount(2, $node['rows'][0]);
    }

    public function testLink(): void {
        $ast = $this->render('[Google](https://google.com "Search")');
        $children = $ast['body'][0]['children'];
        $link = $children[0];
        $this->assertSame('link', $link['type']);
        $this->assertSame('https://google.com', $link['href']);
        $this->assertSame('Search', $link['title']);
    }

    public function testImage(): void {
        $ast = $this->render('![Alt Text](https://example.com/img.png)');
        $img = $ast['body'][0]['children'][0];
        $this->assertSame('image', $img['type']);
        $this->assertSame('Alt Text', $img['alt']);
        $this->assertSame('https://example.com/img.png', $img['src']);
    }

    public function testHorizontalRule(): void {
        $ast = $this->render('---');
        $this->assertSame('horizontalRule', $ast['body'][0]['type']);
    }

    public function testBlockquote(): void {
        $ast = $this->render('> Quote text');
        $node = $ast['body'][0];
        $this->assertSame('blockquote', $node['type']);
        $this->assertNotEmpty($node['children']);
    }

    public function testJsonOutput(): void {
        $json = JsonRenderer::render('# Title');
        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('heading', $decoded['body'][0]['type']);
    }
}
