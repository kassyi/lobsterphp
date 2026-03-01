<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Tests;

use PHPUnit\Framework\TestCase;
use Kassyi\LobsterPhp\Lobster;

class LobsterTest extends TestCase {
    public function testStandardMarkdown() {
        $md = "# Hello\n\nThis is a **bold** test.";
        $html = Lobster::toHtml($md);
        $this->assertStringContainsString('lbs-heading-1', $html);
        $this->assertStringContainsString('lbs-strong', $html);
    }
    
    public function testToHtmlParts() {
        $md = <<<MD
:::header
# Site Title
:::

This is the body.

:::warp sidebar
This is the sidebar.
:::

:::footer
Copyright 2026
:::
MD;
        $parts = Lobster::toHtmlParts($md);
        
        $this->assertStringContainsString('Site Title', $parts->header);
        $this->assertStringContainsString('This is the body', $parts->body);
        $this->assertStringContainsString('Copyright 2026', $parts->footer);
        $this->assertArrayHasKey('sidebar', $parts->warps);
        $this->assertStringContainsString('This is the sidebar', $parts->warps['sidebar']);
        
        $this->assertStringContainsString('Site Title', $parts->full);
    }
}
