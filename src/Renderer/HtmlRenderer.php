<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Renderer;

use Kassyi\LobsterPhp\Core\Document;

class HtmlRenderer {
    // ============================================================
    // Public renderer
    // ============================================================

    public static function renderDocumentParts(Document $doc): RenderedParts {
        $ctx = new RenderContext(
            $doc->footnoteRefs,
            [], // refCount
            $doc->footnoteDefs,
            $doc->warpDefs
        );
        $visitor = new HtmlRendererVisitor($ctx);

        $headerStr = $doc->header !== null ? $doc->header->accept($visitor) : '';
        $bodyStr = $visitor->renderBlockNodes($doc->body);

        $warps = [];
        foreach ($doc->warpDefs as $id => $warpDef) {
            $warps[$id] = $visitor->renderBlockNodes($warpDef->children);
        }

        $footnotesStr = self::renderFootnotes($ctx, $visitor);
        $footerStr = $doc->footer !== null ? $doc->footer->accept($visitor) : '';

        $parts = [];
        if ($headerStr !== '') $parts[] = $headerStr;
        if ($bodyStr !== '') $parts[] = $bodyStr;
        if ($footnotesStr !== '') $parts[] = $footnotesStr;
        if ($footerStr !== '') $parts[] = $footerStr;

        return new RenderedParts(
            $headerStr,
            $bodyStr,
            $footerStr,
            $footnotesStr,
            $warps,
            implode("\n", $parts)
        );
    }

    public static function renderDocument(Document $doc): string {
        return self::renderDocumentParts($doc)->full;
    }

    // ============================================================
    // Footnote section (document-level aggregation)
    // ============================================================

    private static function renderFootnotes(RenderContext $ctx, HtmlRendererVisitor $visitor): string {
        if (empty($ctx->footnoteRefs)) return '';

        $items = [];
        foreach ($ctx->footnoteRefs as $i => $id) {
            $num = $i + 1;
            $defNodes = $ctx->footnoteDefs[$id] ?? null;
            $content = $defNodes !== null ? $visitor->renderInlineNodes($defNodes) : '';
            $escapedId = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $items[] = '<li id="lbs-fn-' . $escapedId . '" class="lbs-footnote-item">[' . $num . '] ' . $content . '</li>';
        }

        return '<section class="lbs-footnotes">' . "\n" . "<ol>\n" . implode("\n", $items) . "\n</ol>\n</section>";
    }
}
