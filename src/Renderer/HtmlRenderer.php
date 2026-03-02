<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Renderer;

use Kassyi\LobsterPhp\Core\Document;

/**
 * HTML Renderer
 */
class HtmlRenderer {
    // ============================================================
    // Public renderer
    // ============================================================

    /**
     * Renders a Document AST into separate HTML parts.
     *
     * @param Document $doc The parsed AST document.
     * @return RenderedParts Rendered header, body, footer, etc.
     */
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

    /**
     * Renders a Document AST strictly into a single full HTML string.
     *
     * @param Document $doc The parsed AST document.
     * @return string The combined HTML string.
     */
    public static function renderDocument(Document $doc): string {
        return self::renderDocumentParts($doc)->full;
    }

    // ============================================================
    // Footnote section (document-level aggregation)
    // ============================================================

    /**
     * Renders aggregated footnotes at the end of the document.
     *
     * @param RenderContext $ctx Processing context containing footnote definitions.
     * @param HtmlRendererVisitor $visitor Visitor instance to process inline formatting inside footnotes.
     * @return string Rendered footer HTML.
     */
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
