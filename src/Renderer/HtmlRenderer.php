<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Renderer;

use Kassyi\LobsterPhp\Core\Document;
use Kassyi\LobsterPhp\Core\AstNode;
use Kassyi\LobsterPhp\Core\BlockNode;
use Kassyi\LobsterPhp\Core\InlineNode;
use Kassyi\LobsterPhp\Core\HeadingNode;
use Kassyi\LobsterPhp\Core\ParagraphNode;
use Kassyi\LobsterPhp\Core\HorizontalRuleNode;
use Kassyi\LobsterPhp\Core\CodeBlockNode;
use Kassyi\LobsterPhp\Core\BlockquoteNode;
use Kassyi\LobsterPhp\Core\BulletListNode;
use Kassyi\LobsterPhp\Core\OrderedListNode;
use Kassyi\LobsterPhp\Core\ListItemNode;
use Kassyi\LobsterPhp\Core\TableNode;
use Kassyi\LobsterPhp\Core\TableAlignment;
use Kassyi\LobsterPhp\Core\HeaderContainerNode;
use Kassyi\LobsterPhp\Core\FooterContainerNode;
use Kassyi\LobsterPhp\Core\DetailsNode;
use Kassyi\LobsterPhp\Core\WarpDefinitionNode;
use Kassyi\LobsterPhp\Core\TextNode;
use Kassyi\LobsterPhp\Core\LineBreakNode;
use Kassyi\LobsterPhp\Core\EmphasisNode;
use Kassyi\LobsterPhp\Core\StrongNode;
use Kassyi\LobsterPhp\Core\StrikethroughNode;
use Kassyi\LobsterPhp\Core\CodeSpanNode;
use Kassyi\LobsterPhp\Core\InlineLinkNode;
use Kassyi\LobsterPhp\Core\LinkNode;
use Kassyi\LobsterPhp\Core\ImageNode;
use Kassyi\LobsterPhp\Core\FootnoteRefNode;
use Kassyi\LobsterPhp\Core\InlineFootnoteNode;
use Kassyi\LobsterPhp\Core\WarpRefNode;

class RenderContext {
    /**
     * @param string[] $footnoteRefs
     * @param array<string, int> $footnoteRefCount
     * @param array<string, InlineNode[]> $footnoteDefs
     * @param array<string, WarpDefinitionNode> $warpDefs
     */
    public function __construct(
        public array $footnoteRefs,
        public array $footnoteRefCount,
        public array $footnoteDefs,
        public array $warpDefs
    ) {}
}

class RenderedParts {
    /**
     * @param string $header
     * @param string $body
     * @param string $footer
     * @param string $footnotes
     * @param array<string, string> $warps
     * @param string $full
     */
    public function __construct(
        public readonly string $header,
        public readonly string $body,
        public readonly string $footer,
        public readonly string $footnotes,
        public readonly array $warps,
        public readonly string $full
    ) {}
}

class HtmlRenderer {
    // ============================================================
    // Escaping
    // ============================================================

    private static function escapeHtml(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ============================================================
    // Inline renderer
    // ============================================================

    /**
     * @param InlineNode[] $nodes
     */
    public static function renderInlineNodes(array $nodes, RenderContext $ctx): string {
        $out = '';
        foreach ($nodes as $n) {
            $out .= self::renderInlineNode($n, $ctx);
        }
        return $out;
    }

    private static function renderInlineNode(InlineNode $node, RenderContext $ctx): string {
        if ($node instanceof TextNode) {
            return self::escapeHtml($node->text);
        }

        if ($node instanceof LineBreakNode) {
            return '<br>';
        }

        if ($node instanceof EmphasisNode) {
            return '<span class="lbs-emphasis">' . self::renderInlineNodes($node->children, $ctx) . '</span>';
        }

        if ($node instanceof StrongNode) {
            return '<span class="lbs-strong">' . self::renderInlineNodes($node->children, $ctx) . '</span>';
        }

        if ($node instanceof StrikethroughNode) {
            return '<span class="lbs-strikethrough">' . self::renderInlineNodes($node->children, $ctx) . '</span>';
        }

        if ($node instanceof CodeSpanNode) {
            return '<code class="lbs-code-span">' . self::escapeHtml($node->code) . '</code>';
        }

        if ($node instanceof InlineLinkNode) {
            $title = $node->title !== null ? ' title="' . self::escapeHtml($node->title) . '"' : '';
            return '<a href="' . self::escapeHtml($node->href) . '"' . $title . '>' . self::renderInlineNodes($node->text, $ctx) . '</a>';
        }

        if ($node instanceof LinkNode) {
            $title = $node->title !== null ? ' title="' . self::escapeHtml($node->title) . '"' : '';
            return '<a href="' . self::escapeHtml($node->href) . '"' . $title . '>' . self::renderInlineNodes($node->text, $ctx) . '</a>';
        }

        if ($node instanceof ImageNode) {
            $title = $node->title !== null ? ' title="' . self::escapeHtml($node->title) . '"' : '';
            $width = $node->width !== null ? ' width="' . $node->width . '"' : '';
            $height = $node->height !== null ? ' height="' . $node->height . '"' : '';
            return '<img src="' . self::escapeHtml($node->src) . '" alt="' . self::escapeHtml($node->alt) . '"' . $title . $width . $height . ' class="lbs-image">';
        }

        if ($node instanceof FootnoteRefNode) {
            $idx = array_search($node->id, $ctx->footnoteRefs, true);
            $num = $idx !== false ? ((int)$idx + 1) : 0;
            
            $refCount = $ctx->footnoteRefCount[$node->id] ?? 0;
            $ctx->footnoteRefCount[$node->id] = $refCount + 1;
            
            $suffix = $refCount === 0 ? '' : ':' . $refCount;
            $label = '[' . $num . $suffix . ']';
            return '<sup class="lbs-footnote-ref"><a href="#lbs-fn-' . self::escapeHtml($node->id) . '" id="lbs-fnref-' . self::escapeHtml($node->id) . '-' . $refCount . '">' . $label . '</a></sup>';
        }

        if ($node instanceof InlineFootnoteNode) {
            // Find the id by matching children
            $id = null;
            foreach ($ctx->footnoteRefs as $ref) {
                if (str_starts_with($ref, '__inline_') && isset($ctx->footnoteDefs[$ref]) && $ctx->footnoteDefs[$ref] === $node->children) {
                    $id = $ref;
                    break;
                }
            }
            if ($id === null) {
                return self::renderInlineNodes($node->children, $ctx);
            }
            $idx = array_search($id, $ctx->footnoteRefs, true);
            $num = $idx !== false ? ((int)$idx + 1) : 0;
            return '<sup class="lbs-footnote-ref"><a href="#lbs-fn-' . self::escapeHtml($id) . '">[' . $num . ']</a></sup>';
        }

        if ($node instanceof WarpRefNode) {
            if (!isset($ctx->warpDefs[$node->id])) return '';
            return self::renderBlockNodes($ctx->warpDefs[$node->id]->children, $ctx);
        }

        return '';
    }

    // ============================================================
    // Block renderer
    // ============================================================

    /**
     * @param BlockNode[] $nodes
     */
    public static function renderBlockNodes(array $nodes, RenderContext $ctx): string {
        $out = [];
        foreach ($nodes as $n) {
            $res = self::renderBlockNode($n, $ctx);
            if ($res !== '') $out[] = $res;
        }
        return implode("\n", $out);
    }

    private static function renderBlockNode(BlockNode $node, RenderContext $ctx): string {
        if ($node instanceof HeadingNode) {
            $content = self::renderInlineNodes($node->children, $ctx);
            $idAttr = $node->id !== null ? ' id="' . self::escapeHtml($node->id) . '"' : '';
            return '<h' . $node->level . ' class="lbs-heading-' . $node->level . '"' . $idAttr . '>' . $content . '</h' . $node->level . '>';
        }

        if ($node instanceof ParagraphNode) {
            $content = self::renderInlineNodes($node->children, $ctx);
            return '<p class="lbs-paragraph">' . $content . '</p>';
        }

        if ($node instanceof HorizontalRuleNode) {
            return '<hr class="lbs-hr">';
        }

        if ($node instanceof CodeBlockNode) {
            $langAttr = $node->language !== null ? ' data-language="' . self::escapeHtml($node->language) . '"' : '';
            $filenameAttr = $node->filename !== null ? ' data-filename="' . self::escapeHtml($node->filename) . '"' : '';
            $header = $node->filename !== null ? '<div class="lbs-code-filename">' . self::escapeHtml($node->filename) . '</div>' : '';
            $langClass = $node->language !== null ? ' class="language-' . self::escapeHtml($node->language) . '"' : '';
            return '<div class="lbs-code-block">' . $header . '<pre' . $langAttr . $filenameAttr . '><code' . $langClass . '>' . self::escapeHtml($node->code) . '</code></pre></div>';
        }

        if ($node instanceof BlockquoteNode) {
            $content = self::renderBlockNodes($node->children, $ctx);
            return '<blockquote class="lbs-blockquote">' . $content . '</blockquote>';
        }

        if ($node instanceof BulletListNode) {
            $items = [];
            foreach ($node->items as $item) {
                $items[] = self::renderListItem($item, $ctx);
            }
            return '<ul class="lbs-ul lbs-ul-depth-' . $node->depth . "\">\n" . implode("\n", $items) . "\n</ul>";
        }

        if ($node instanceof OrderedListNode) {
            $startAttr = $node->start !== 1 ? ' start="' . $node->start . '"' : '';
            $items = [];
            foreach ($node->items as $item) {
                $items[] = self::renderListItem($item, $ctx);
            }
            return '<ol class="lbs-ol lbs-ol-depth-' . $node->depth . '"' . $startAttr . ">\n" . implode("\n", $items) . "\n</ol>";
        }

        if ($node instanceof TableNode) {
            $tableClass = $node->isSilent ? 'lbs-table lbs-table-silent' : 'lbs-table';

            // Header
            $headerCells = '';
            foreach ($node->headers as $i => $cell) {
                $align = $node->alignments[$i] ?? TableAlignment::Default;
                $alignAttr = $align !== TableAlignment::Default ? ' style="text-align:' . $align->value . '"' : '';
                $colspanAttr = $cell->colspan !== null ? ' colspan="' . $cell->colspan . '"' : '';
                $headerCells .= '<th' . $colspanAttr . $alignAttr . '>' . self::renderInlineNodes($cell->children, $ctx) . '</th>';
            }

            // Body
            $bodyRows = [];
            foreach ($node->rows as $row) {
                $cellsStr = '';
                foreach ($row as $i => $cell) {
                    $align = $node->alignments[$i] ?? TableAlignment::Default;
                    $alignAttr = $align !== TableAlignment::Default ? ' style="text-align:' . $align->value . '"' : '';
                    $colspanAttr = $cell->colspan !== null ? ' colspan="' . $cell->colspan . '"' : '';
                    $rowspanAttr = $cell->rowspan !== null ? ' rowspan="' . $cell->rowspan . '"' : '';

                    // Skip rowspan placeholder cells
                    if (count($cell->children) === 1 && $cell->children[0] instanceof TextNode && $cell->children[0]->text === '__ROWSPAN__') {
                        continue;
                    }

                    $cellsStr .= '<td' . $colspanAttr . $rowspanAttr . $alignAttr . '>' . self::renderInlineNodes($cell->children, $ctx) . '</td>';
                }
                $bodyRows[] = '<tr>' . $cellsStr . '</tr>';
            }

            return '<table class="' . $tableClass . "\">\n<thead><tr>" . $headerCells . "</tr></thead>\n<tbody>\n" . implode("\n", $bodyRows) . "\n</tbody>\n</table>";
        }

        if ($node instanceof HeaderContainerNode) {
            $content = self::renderBlockNodes($node->children, $ctx);
            return '<header class="lbs-header">' . $content . '</header>';
        }

        if ($node instanceof FooterContainerNode) {
            $content = self::renderBlockNodes($node->children, $ctx);
            return '<footer class="lbs-footer">' . $content . '</footer>';
        }

        if ($node instanceof DetailsNode) {
            $content = self::renderBlockNodes($node->children, $ctx);
            return '<details class="lbs-details">' . "\n" . '<summary class="lbs-summary">' . self::escapeHtml($node->title) . "</summary>\n" . $content . "\n" . '</details>';
        }

        if ($node instanceof WarpDefinitionNode) {
            return '';
        }

        return '';
    }

    private static function renderListItem(ListItemNode $item, RenderContext $ctx): string {
        $checkboxHtml = '';
        if ($item->checked !== null) {
            $checkedAttr = $item->checked ? ' checked' : '';
            $checkboxHtml = '<input type="checkbox" class="lbs-checkbox"' . $checkedAttr . ' disabled> ';
        }
        $textHtml = self::renderInlineNodes($item->children, $ctx);
        $sublistHtml = '';
        if ($item->sublist !== null) {
            $sublistHtml = "\n" . self::renderBlockNode($item->sublist, $ctx);
        }
        return '<li class="lbs-list-item">' . $checkboxHtml . $textHtml . $sublistHtml . '</li>';
    }

    // ============================================================
    // Footnote section
    // ============================================================

    private static function renderFootnotes(Document $doc, RenderContext $ctx): string {
        if (empty($ctx->footnoteRefs)) return '';

        $items = [];
        foreach ($ctx->footnoteRefs as $i => $id) {
            $num = $i + 1;
            $defNodes = $ctx->footnoteDefs[$id] ?? null;
            $content = $defNodes !== null ? self::renderInlineNodes($defNodes, $ctx) : '';
            $items[] = '<li id="lbs-fn-' . self::escapeHtml($id) . '" class="lbs-footnote-item">[' . $num . '] ' . $content . '</li>';
        }

        return '<section class="lbs-footnotes">' . "\n" . "<ol>\n" . implode("\n", $items) . "\n</ol>\n</section>";
    }

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

        $headerStr = $doc->header ? self::renderHeaderContainer($doc->header, $ctx) : '';
        $bodyStr = self::renderBlockNodes($doc->body, $ctx);
        
        $warps = [];
        foreach ($doc->warpDefs as $id => $warpDef) {
            $warps[$id] = self::renderBlockNodes($warpDef->children, $ctx);
        }

        $footnotesStr = '';
        if (!empty($doc->footnoteRefs)) {
            $footnotesStr = self::renderFootnotes($doc, $ctx);
        }

        $footerStr = $doc->footer ? self::renderFooterContainer($doc->footer, $ctx) : '';

        $parts = [];
        if ($headerStr !== '') $parts[] = $headerStr;
        if ($bodyStr !== '') $parts[] = $bodyStr;
        if ($footnotesStr !== '') $parts[] = $footnotesStr;
        if ($footerStr !== '') $parts[] = $footerStr;
        
        $full = implode("\n", $parts);

        return new RenderedParts(
            $headerStr,
            $bodyStr,
            $footerStr,
            $footnotesStr,
            $warps,
            $full
        );
    }

    private static function renderHeaderContainer(HeaderContainerNode $node, RenderContext $ctx): string {
        $content = self::renderBlockNodes($node->children, $ctx);
        return '<header class="lbs-header">' . $content . '</header>';
    }

    private static function renderFooterContainer(FooterContainerNode $node, RenderContext $ctx): string {
        $content = self::renderBlockNodes($node->children, $ctx);
        return '<footer class="lbs-footer">' . $content . '</footer>';
    }

    public static function renderDocument(Document $doc): string {
        return self::renderDocumentParts($doc)->full;
    }
}
