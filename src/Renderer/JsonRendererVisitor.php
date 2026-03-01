<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Renderer;

use Kassyi\LobsterPhp\Core\{AstNode, BlockquoteNode, BulletListNode, CodeBlockNode, CodeSpanNode,
    DetailsNode, EmphasisNode, FooterContainerNode, FootnoteRefNode, HeaderContainerNode,
    HeadingNode, HorizontalRuleNode, ImageNode, InlineFootnoteNode, InlineLinkNode,
    LineBreakNode, LinkNode, ListItemNode, OrderedListNode, ParagraphNode, StrikethroughNode,
    StrongNode, TableAlignment, TableNode, TextNode, WarpDefinitionNode, WarpRefNode};
use Kassyi\LobsterPhp\Core\Visitor\NodeVisitorInterface;

/**
 * AST Visitor that converts the document tree into a structured PHP array,
 * suitable for json_encode() to produce a portable JSON representation.
 *
 * Each node returns an associative array with at minimum a "type" key.
 * Arrays of child nodes are stored as "children".
 *
 * This enables SPA integration (React, Vue, Inertia.js),
 * Headless CMS output, TOC generation, and structured data querying.
 */
class JsonRendererVisitor implements NodeVisitorInterface {
    public function __construct(
        private RenderContext $ctx
    ) {}

    // ── Helper ────────────────────────────────────────────────────────────────

    public function renderNodes(array $nodes): array {
        $result = [];
        foreach ($nodes as $node) {
            if ($node instanceof AstNode) {
                $rendered = $node->accept($this);
                if ($rendered !== null) {
                    $result[] = $rendered;
                }
            }
        }
        return $result;
    }

    // ── Inline Nodes ──────────────────────────────────────────────────────────

    public function visitTextNode(TextNode $node): mixed {
        return ['type' => 'text', 'value' => $node->text];
    }

    public function visitLineBreakNode(LineBreakNode $node): mixed {
        return ['type' => 'lineBreak'];
    }

    public function visitEmphasisNode(EmphasisNode $node): mixed {
        return ['type' => 'emphasis', 'children' => $this->renderNodes($node->children)];
    }

    public function visitStrongNode(StrongNode $node): mixed {
        return ['type' => 'strong', 'children' => $this->renderNodes($node->children)];
    }

    public function visitStrikethroughNode(StrikethroughNode $node): mixed {
        return ['type' => 'strikethrough', 'children' => $this->renderNodes($node->children)];
    }

    public function visitCodeSpanNode(CodeSpanNode $node): mixed {
        return ['type' => 'codeSpan', 'value' => $node->code];
    }

    public function visitInlineLinkNode(InlineLinkNode $node): mixed {
        return [
            'type' => 'link',
            'href' => $node->href,
            'title' => $node->title,
            'children' => $this->renderNodes($node->text),
        ];
    }

    public function visitLinkNode(LinkNode $node): mixed {
        return [
            'type' => 'link',
            'href' => $node->href,
            'title' => $node->title,
            'children' => $this->renderNodes($node->text),
        ];
    }

    public function visitImageNode(ImageNode $node): mixed {
        return [
            'type' => 'image',
            'src' => $node->src,
            'alt' => $node->alt,
            'title' => $node->title,
            'width' => $node->width,
            'height' => $node->height,
        ];
    }

    public function visitFootnoteRefNode(FootnoteRefNode $node): mixed {
        $idx = array_search($node->id, $this->ctx->footnoteRefs, true);
        $num = $idx !== false ? ((int)$idx + 1) : 0;
        return ['type' => 'footnoteRef', 'id' => $node->id, 'num' => $num];
    }

    public function visitInlineFootnoteNode(InlineFootnoteNode $node): mixed {
        return ['type' => 'inlineFootnote', 'children' => $this->renderNodes($node->children)];
    }

    public function visitWarpRefNode(WarpRefNode $node): mixed {
        if (!isset($this->ctx->warpDefs[$node->id])) return null;
        return [
            'type' => 'warpRef',
            'id' => $node->id,
            'children' => $this->renderNodes($this->ctx->warpDefs[$node->id]->children),
        ];
    }

    // ── Block Nodes ───────────────────────────────────────────────────────────

    public function visitHeadingNode(HeadingNode $node): mixed {
        return [
            'type' => 'heading',
            'level' => $node->level,
            'id' => $node->id,
            'children' => $this->renderNodes($node->children),
        ];
    }

    public function visitParagraphNode(ParagraphNode $node): mixed {
        return ['type' => 'paragraph', 'children' => $this->renderNodes($node->children)];
    }

    public function visitHorizontalRuleNode(HorizontalRuleNode $node): mixed {
        return ['type' => 'horizontalRule'];
    }

    public function visitCodeBlockNode(CodeBlockNode $node): mixed {
        return [
            'type' => 'codeBlock',
            'language' => $node->language,
            'filename' => $node->filename,
            'value' => $node->code,
        ];
    }

    public function visitBlockquoteNode(BlockquoteNode $node): mixed {
        return ['type' => 'blockquote', 'children' => $this->renderNodes($node->children)];
    }

    public function visitBulletListNode(BulletListNode $node): mixed {
        return [
            'type' => 'list',
            'ordered' => false,
            'depth' => $node->depth,
            'items' => array_map(fn($item) => $item->accept($this), $node->items),
        ];
    }

    public function visitOrderedListNode(OrderedListNode $node): mixed {
        return [
            'type' => 'list',
            'ordered' => true,
            'depth' => $node->depth,
            'start' => $node->start,
            'items' => array_map(fn($item) => $item->accept($this), $node->items),
        ];
    }

    public function visitListItemNode(ListItemNode $node): mixed {
        $result = [
            'type' => 'listItem',
            'checked' => $node->checked,
            'children' => $this->renderNodes($node->children),
        ];
        if ($node->sublist !== null) {
            $result['sublist'] = $node->sublist->accept($this);
        }
        return $result;
    }

    public function visitTableNode(TableNode $node): mixed {
        $headers = [];
        foreach ($node->headers as $i => $cell) {
            $align = $node->alignments[$i] ?? TableAlignment::Default;
            $headers[] = [
                'children' => $this->renderNodes($cell->children),
                'alignment' => $align !== TableAlignment::Default ? $align->value : null,
                'colspan' => $cell->colspan,
            ];
        }

        $rows = [];
        foreach ($node->rows as $row) {
            $cells = [];
            foreach ($row as $i => $cell) {
                // Skip rowspan placeholders
                if (count($cell->children) === 1
                    && $cell->children[0] instanceof TextNode
                    && $cell->children[0]->text === '__ROWSPAN__'
                ) {
                    continue;
                }
                $align = $node->alignments[$i] ?? TableAlignment::Default;
                $cells[] = [
                    'children' => $this->renderNodes($cell->children),
                    'alignment' => $align !== TableAlignment::Default ? $align->value : null,
                    'colspan' => $cell->colspan,
                    'rowspan' => $cell->rowspan,
                ];
            }
            $rows[] = $cells;
        }

        return [
            'type' => 'table',
            'silent' => $node->isSilent,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    public function visitHeaderContainerNode(HeaderContainerNode $node): mixed {
        return ['type' => 'header', 'children' => $this->renderNodes($node->children)];
    }

    public function visitFooterContainerNode(FooterContainerNode $node): mixed {
        return ['type' => 'footer', 'children' => $this->renderNodes($node->children)];
    }

    public function visitDetailsNode(DetailsNode $node): mixed {
        return [
            'type' => 'details',
            'title' => $node->title,
            'children' => $this->renderNodes($node->children),
        ];
    }

    public function visitWarpDefinitionNode(WarpDefinitionNode $node): mixed {
        // Warp definitions are referenced via WarpRefNode; skip direct output.
        return null;
    }
}
