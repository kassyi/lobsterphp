<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core;

use Kassyi\LobsterPhp\Core\Visitor\NodeVisitorInterface;

// ============================================================
// Base Interfaces
// ============================================================

interface AstNode {
    public function accept(NodeVisitorInterface $visitor): mixed;
}

interface InlineNode extends AstNode {}
interface BlockNode extends AstNode {}

// ============================================================
// Inline AST Nodes
// ============================================================

readonly class TextNode implements InlineNode {
    public function __construct(
        public string $text
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitTextNode($this); }
}

readonly class EmphasisNode implements InlineNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitEmphasisNode($this); }
}

readonly class StrongNode implements InlineNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitStrongNode($this); }
}

readonly class StrikethroughNode implements InlineNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitStrikethroughNode($this); }
}

readonly class CodeSpanNode implements InlineNode {
    public function __construct(
        public string $code
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitCodeSpanNode($this); }
}

readonly class InlineLinkNode implements InlineNode {
    /** @param InlineNode[] $text */
    public function __construct(
        public array $text,
        public string $href,
        public ?string $title = null
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitInlineLinkNode($this); }
}

readonly class LinkNode implements InlineNode {
    /** @param InlineNode[] $text */
    public function __construct(
        public array $text,
        public string $href,
        public ?string $title = null
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitLinkNode($this); }
}

readonly class ImageNode implements InlineNode {
    public function __construct(
        public string $alt,
        public string $src,
        public ?string $title = null,
        public ?int $width = null,
        public ?int $height = null
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitImageNode($this); }
}

readonly class FootnoteRefNode implements InlineNode {
    public function __construct(
        public string $id
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitFootnoteRefNode($this); }
}

readonly class InlineFootnoteNode implements InlineNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitInlineFootnoteNode($this); }
}

readonly class WarpRefNode implements InlineNode {
    public function __construct(
        public string $id
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitWarpRefNode($this); }
}

readonly class LineBreakNode implements InlineNode {
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitLineBreakNode($this); }
}

// ============================================================
// Block AST Nodes
// ============================================================

readonly class HeadingNode implements BlockNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public int $level, // 1-6
        public array $children,
        public ?string $id = null
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitHeadingNode($this); }
}

readonly class ParagraphNode implements BlockNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitParagraphNode($this); }
}

readonly class HorizontalRuleNode implements BlockNode {
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitHorizontalRuleNode($this); }
}

readonly class CodeBlockNode implements BlockNode {
    public function __construct(
        public string $code,
        public ?string $language = null,
        public ?string $filename = null
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitCodeBlockNode($this); }
}

readonly class BlockquoteNode implements BlockNode {
    /** @param BlockNode[] $children */
    public function __construct(
        public array $children
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitBlockquoteNode($this); }
}

readonly class ListItemNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children,
        public ?bool $checked = null,
        public BulletListNode|OrderedListNode|null $sublist = null
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitListItemNode($this); }
}

readonly class BulletListNode implements BlockNode {
    /** @param ListItemNode[] $items */
    public function __construct(
        public int $depth,
        public array $items
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitBulletListNode($this); }
}

readonly class OrderedListNode implements BlockNode {
    /** @param ListItemNode[] $items */
    public function __construct(
        public int $depth,
        public int $start,
        public array $items
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitOrderedListNode($this); }
}

enum TableAlignment: string {
    case Default = 'default';
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';
}

readonly class TableCellNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children,
        public ?int $colspan = null,
        public ?int $rowspan = null
    ) {}
}

readonly class TableNode implements BlockNode {
    /**
     * @param TableCellNode[] $headers
     * @param TableAlignment[] $alignments
     * @param TableCellNode[][] $rows
     */
    public function __construct(
        public bool $isSilent,
        public array $headers,
        public array $alignments,
        public array $rows
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitTableNode($this); }
}

readonly class HeaderContainerNode implements BlockNode {
    /** @param BlockNode[] $children */
    public function __construct(
        public array $children
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitHeaderContainerNode($this); }
}

readonly class FooterContainerNode implements BlockNode {
    /** @param BlockNode[] $children */
    public function __construct(
        public array $children
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitFooterContainerNode($this); }
}

readonly class DetailsNode implements BlockNode {
    /** @param BlockNode[] $children */
    public function __construct(
        public string $title,
        public array $children
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitDetailsNode($this); }
}

readonly class WarpDefinitionNode implements BlockNode {
    /** @param BlockNode[] $children */
    public function __construct(
        public string $id,
        public array $children
    ) {}
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitWarpDefinitionNode($this); }
}

// ============================================================
// Document & Parse Context
// ============================================================

readonly class LinkDef {
    public function __construct(
        public string $href,
        public ?string $title = null
    ) {}
}

class Document {
    /**
     * @param BlockNode[] $body
     * @param array<string, LinkDef> $linkDefs
     * @param array<string, InlineNode[]> $footnoteDefs
     * @param string[] $footnoteRefs
     * @param array<string, WarpDefinitionNode> $warpDefs 
     */
    public function __construct(
        public array $body,
        public array $linkDefs,
        public array $footnoteDefs,
        public array $footnoteRefs,
        public array $warpDefs,
        public ?HeaderContainerNode $header = null,
        public ?FooterContainerNode $footer = null
    ) {}
}

class ParseContext {
    /**
     * @param array<string, LinkDef> $linkDefs
     * @param array<string, InlineNode[]> $footnoteDefs
     * @param array<string, WarpDefinitionNode> $warpDefs
     * @param string[] $footnoteRefs
     */
    public function __construct(
        public array $linkDefs,
        public array $footnoteDefs,
        public array $warpDefs,
        public array $footnoteRefs,
        public int $inlineFootnoteCount
    ) {}
}
