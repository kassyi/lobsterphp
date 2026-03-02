<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Renderer;

use Kassyi\LobsterPhp\Core\{BlockquoteNode, BulletListNode, CodeBlockNode, CodeSpanNode, DetailsNode,
    EmphasisNode, FooterContainerNode, FootnoteRefNode, HeaderContainerNode, HeadingNode, HorizontalRuleNode,
    ImageNode, InlineFootnoteNode, InlineLinkNode, LineBreakNode, LinkNode, ListItemNode, OrderedListNode,
    ParagraphNode, StrikethroughNode, StrongNode, TableAlignment, TableNode, TextNode, WarpDefinitionNode, WarpRefNode};
use Kassyi\LobsterPhp\Core\Visitor\NodeVisitorInterface;

/**
 * HtmlRendererVisitor
 */

class HtmlRendererVisitor implements NodeVisitorInterface {
    /**
     * __construct
     */
    public function __construct(
        private RenderContext $ctx
    ) {}

    private function escapeHtml(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * renderBlockNodes
     */
    public function renderBlockNodes(array $nodes): string {
        $out = [];
        foreach ($nodes as $n) {
            $res = $n->accept($this);
            if ($res !== '') $out[] = $res;
        }
        return implode("\n", $out);
    }

    /**
     * renderInlineNodes
     */
    public function renderInlineNodes(array $nodes): string {
        $result = '';
        foreach ($nodes as $node) {
            $result .= $node->accept($this);
        }
        return $result;
    }

    // ============================================================
    // Inline Nodes
    // ============================================================

    /**
     * {@inheritDoc}
     */
    public function visitTextNode(TextNode $node): mixed {
        return $this->escapeHtml($node->text);
    }

    /**
     * {@inheritDoc}
     */
    public function visitLineBreakNode(LineBreakNode $node): mixed {
        return '<br>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitEmphasisNode(EmphasisNode $node): mixed {
        return '<span class="lbs-emphasis">' . $this->renderInlineNodes($node->children) . '</span>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitStrongNode(StrongNode $node): mixed {
        return '<span class="lbs-strong">' . $this->renderInlineNodes($node->children) . '</span>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitStrikethroughNode(StrikethroughNode $node): mixed {
        return '<span class="lbs-strikethrough">' . $this->renderInlineNodes($node->children) . '</span>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitCodeSpanNode(CodeSpanNode $node): mixed {
        return '<code class="lbs-code-span">' . $this->escapeHtml($node->code) . '</code>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitInlineLinkNode(InlineLinkNode $node): mixed {
        $title = $node->title !== null ? ' title="' . $this->escapeHtml($node->title) . '"' : '';
        return '<a href="' . $this->escapeHtml($node->href) . '"' . $title . '>' . $this->renderInlineNodes($node->text) . '</a>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitLinkNode(LinkNode $node): mixed {
        $title = $node->title !== null ? ' title="' . $this->escapeHtml($node->title) . '"' : '';
        return '<a href="' . $this->escapeHtml($node->href) . '"' . $title . '>' . $this->renderInlineNodes($node->text) . '</a>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitImageNode(ImageNode $node): mixed {
        $title = $node->title !== null ? ' title="' . $this->escapeHtml($node->title) . '"' : '';
        $width = $node->width !== null ? ' width="' . $node->width . '"' : '';
        $height = $node->height !== null ? ' height="' . $node->height . '"' : '';
        return '<img src="' . $this->escapeHtml($node->src) . '" alt="' . $this->escapeHtml($node->alt) . '"' . $title . $width . $height . ' class="lbs-image">';
    }

    /**
     * {@inheritDoc}
     */
    public function visitFootnoteRefNode(FootnoteRefNode $node): mixed {
        $idx = array_search($node->id, $this->ctx->footnoteRefs, true);
        $num = $idx !== false ? ((int)$idx + 1) : 0;
        
        $refCount = $this->ctx->footnoteRefCount[$node->id] ?? 0;
        $this->ctx->footnoteRefCount[$node->id] = $refCount + 1;
        
        $suffix = $refCount === 0 ? '' : ':' . $refCount;
        $label = '[' . $num . $suffix . ']';
        return '<sup class="lbs-footnote-ref"><a href="#lbs-fn-' . $this->escapeHtml($node->id) . '" id="lbs-fnref-' . $this->escapeHtml($node->id) . '-' . $refCount . '">' . $label . '</a></sup>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitInlineFootnoteNode(InlineFootnoteNode $node): mixed {
        $id = null;
        foreach ($this->ctx->footnoteRefs as $ref) {
            if (str_starts_with($ref, '__inline_') && isset($this->ctx->footnoteDefs[$ref]) && $this->ctx->footnoteDefs[$ref] === $node->children) {
                $id = $ref;
                break;
            }
        }
        if ($id === null) {
            return $this->renderInlineNodes($node->children);
        }
        $idx = array_search($id, $this->ctx->footnoteRefs, true);
        $num = $idx !== false ? ((int)$idx + 1) : 0;
        return '<sup class="lbs-footnote-ref"><a href="#lbs-fn-' . $this->escapeHtml($id) . '">[' . $num . ']</a></sup>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitWarpRefNode(WarpRefNode $node): mixed {
        if (!isset($this->ctx->warpDefs[$node->id])) return '';
        return $this->renderBlockNodes($this->ctx->warpDefs[$node->id]->children);
    }

    // ============================================================
    // Block Nodes
    // ============================================================

    /**
     * {@inheritDoc}
     */
    public function visitHeadingNode(HeadingNode $node): mixed {
        $content = $this->renderInlineNodes($node->children);
        $idAttr = $node->id !== null ? ' id="' . $this->escapeHtml($node->id) . '"' : '';
        return '<h' . $node->level . ' class="lbs-heading-' . $node->level . '"' . $idAttr . '>' . $content . '</h' . $node->level . '>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitParagraphNode(ParagraphNode $node): mixed {
        return '<p class="lbs-paragraph">' . $this->renderInlineNodes($node->children) . '</p>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitHorizontalRuleNode(HorizontalRuleNode $node): mixed {
        return '<hr class="lbs-hr">';
    }

    /**
     * {@inheritDoc}
     */
    public function visitCodeBlockNode(CodeBlockNode $node): mixed {
        $langAttr = $node->language !== null ? ' data-language="' . $this->escapeHtml($node->language) . '"' : '';
        $filenameAttr = $node->filename !== null ? ' data-filename="' . $this->escapeHtml($node->filename) . '"' : '';
        $header = $node->filename !== null ? '<div class="lbs-code-filename">' . $this->escapeHtml($node->filename) . '</div>' : '';
        $langClass = $node->language !== null ? ' class="language-' . $this->escapeHtml($node->language) . '"' : '';
        return '<div class="lbs-code-block">' . $header . '<pre' . $langAttr . $filenameAttr . '><code' . $langClass . '>' . $this->escapeHtml($node->code) . '</code></pre></div>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitBlockquoteNode(BlockquoteNode $node): mixed {
        return '<blockquote class="lbs-blockquote">' . $this->renderBlockNodes($node->children) . '</blockquote>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitBulletListNode(BulletListNode $node): mixed {
        $items = [];
        foreach ($node->items as $item) {
            $items[] = $item->accept($this);
        }
        return '<ul class="lbs-ul lbs-ul-depth-' . $node->depth . "\">\n" . implode("\n", $items) . "\n</ul>";
    }

    /**
     * {@inheritDoc}
     */
    public function visitOrderedListNode(OrderedListNode $node): mixed {
        $startAttr = $node->start !== 1 ? ' start="' . $node->start . '"' : '';
        $items = [];
        foreach ($node->items as $item) {
            $items[] = $item->accept($this);
        }
        return '<ol class="lbs-ol lbs-ol-depth-' . $node->depth . '"' . $startAttr . ">\n" . implode("\n", $items) . "\n</ol>";
    }

    /**
     * {@inheritDoc}
     */
    public function visitListItemNode(ListItemNode $node): mixed {
        $content = $this->renderInlineNodes($node->children);

        if ($node->checked !== null) {
            $checkedAttr = $node->checked ? ' checked' : '';
            $checkbox = '<input type="checkbox" class="lbs-task-list-item-checkbox" disabled' . $checkedAttr . '>';
            $content = '<span class="lbs-task-list-item-text">' . $checkbox . ' ' . $content . '</span>';
            $liClass = 'lbs-li lbs-task-list-item';
        } else {
            $liClass = 'lbs-li';
        }

        if ($node->sublist !== null) {
            $sublistHtml = $node->sublist->accept($this);
            $content .= "\n" . $sublistHtml;
        }

        return '<li class="' . $liClass . '">' . $content . '</li>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitTableNode(TableNode $node): mixed {
        $tableClass = $node->isSilent ? 'lbs-table lbs-table-silent' : 'lbs-table';
        $headerCells = $this->renderTableHeaders($node);
        $bodyRows = $this->renderTableRows($node);
        return '<table class="' . $tableClass . "\">\n<thead><tr>" . $headerCells . "</tr></thead>\n<tbody>\n" . implode("\n", $bodyRows) . "\n</tbody>\n</table>";
    }

    private function renderTableHeaders(TableNode $node): string {
        $headerCells = '';
        foreach ($node->headers as $i => $cell) {
            $align = $node->alignments[$i] ?? TableAlignment::Default;
            $alignAttr = $align !== TableAlignment::Default ? ' style="text-align:' . $align->value . '"' : '';
            $colspanAttr = $cell->colspan !== null ? ' colspan="' . $cell->colspan . '"' : '';
            $headerCells .= '<th' . $colspanAttr . $alignAttr . '>' . $this->renderInlineNodes($cell->children) . '</th>';
        }
        return $headerCells;
    }

    /** @return string[] */
    private function renderTableRows(TableNode $node): array {
        $bodyRows = [];
        foreach ($node->rows as $row) {
            $cellsStr = '';
            foreach ($row as $i => $cell) {
                // Skip rowspan placeholder cells
                if (count($cell->children) === 1 && $cell->children[0] instanceof TextNode && $cell->children[0]->text === '__ROWSPAN__') {
                    continue;
                }

                $align = $node->alignments[$i] ?? TableAlignment::Default;
                $alignAttr = $align !== TableAlignment::Default ? ' style="text-align:' . $align->value . '"' : '';
                $colspanAttr = $cell->colspan !== null ? ' colspan="' . $cell->colspan . '"' : '';
                $rowspanAttr = $cell->rowspan !== null ? ' rowspan="' . $cell->rowspan . '"' : '';

                $cellsStr .= '<td' . $colspanAttr . $rowspanAttr . $alignAttr . '>' . $this->renderInlineNodes($cell->children) . '</td>';
            }
            $bodyRows[] = '<tr>' . $cellsStr . '</tr>';
        }
        return $bodyRows;
    }

    /**
     * {@inheritDoc}
     */
    public function visitHeaderContainerNode(HeaderContainerNode $node): mixed {
        return '<header class="lbs-header">' . $this->renderBlockNodes($node->children) . '</header>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitFooterContainerNode(FooterContainerNode $node): mixed {
        return '<footer class="lbs-footer">' . $this->renderBlockNodes($node->children) . '</footer>';
    }

    /**
     * {@inheritDoc}
     */
    public function visitDetailsNode(DetailsNode $node): mixed {
        return '<details class="lbs-details">' . "\n" . '<summary class="lbs-summary">' . $this->escapeHtml($node->title) . "</summary>\n" . $this->renderBlockNodes($node->children) . "\n</details>";
    }

    /**
     * {@inheritDoc}
     */
    public function visitWarpDefinitionNode(WarpDefinitionNode $node): mixed {
        return '';
    }
}


