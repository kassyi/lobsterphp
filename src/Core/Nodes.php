<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core;

// ============================================================
// Base Interfaces
// ============================================================

interface AstNode {}

interface InlineNode extends AstNode {}
interface BlockNode extends AstNode {}

// ============================================================
// Inline AST Nodes
// ============================================================

readonly class TextNode implements InlineNode {
    public function __construct(
        public string $text
    ) {}
}

readonly class EmphasisNode implements InlineNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children
    ) {}
}

readonly class StrongNode implements InlineNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children
    ) {}
}

readonly class StrikethroughNode implements InlineNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children
    ) {}
}

readonly class CodeSpanNode implements InlineNode {
    public function __construct(
        public string $code
    ) {}
}

readonly class InlineLinkNode implements InlineNode {
    /** @param InlineNode[] $text */
    public function __construct(
        public array $text,
        public string $href,
        public ?string $title = null
    ) {}
}

readonly class LinkNode implements InlineNode {
    /** @param InlineNode[] $text */
    public function __construct(
        public array $text,
        public string $href,
        public ?string $title = null
    ) {}
}

readonly class ImageNode implements InlineNode {
    public function __construct(
        public string $alt,
        public string $src,
        public ?string $title = null,
        public ?int $width = null,
        public ?int $height = null
    ) {}
}

readonly class FootnoteRefNode implements InlineNode {
    public function __construct(
        public string $id
    ) {}
}

readonly class InlineFootnoteNode implements InlineNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children
    ) {}
}

readonly class WarpRefNode implements InlineNode {
    public function __construct(
        public string $id
    ) {}
}

readonly class LineBreakNode implements InlineNode {}

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
}

readonly class ParagraphNode implements BlockNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children
    ) {}
}

readonly class HorizontalRuleNode implements BlockNode {}

readonly class CodeBlockNode implements BlockNode {
    public function __construct(
        public string $code,
        public ?string $language = null,
        public ?string $filename = null
    ) {}
}

readonly class BlockquoteNode implements BlockNode {
    /** @param BlockNode[] $children */
    public function __construct(
        public array $children
    ) {}
}

readonly class ListItemNode {
    /** @param InlineNode[] $children */
    public function __construct(
        public array $children,
        public ?bool $checked = null,
        public BulletListNode|OrderedListNode|null $sublist = null
    ) {}
}

readonly class BulletListNode implements BlockNode {
    /** @param ListItemNode[] $items */
    public function __construct(
        public int $depth,
        public array $items
    ) {}
}

readonly class OrderedListNode implements BlockNode {
    /** @param ListItemNode[] $items */
    public function __construct(
        public int $depth,
        public int $start,
        public array $items
    ) {}
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
}

readonly class HeaderContainerNode implements BlockNode {
    /** @param BlockNode[] $children */
    public function __construct(
        public array $children
    ) {}
}

readonly class FooterContainerNode implements BlockNode {
    /** @param BlockNode[] $children */
    public function __construct(
        public array $children
    ) {}
}

readonly class DetailsNode implements BlockNode {
    /** @param BlockNode[] $children */
    public function __construct(
        public string $title,
        public array $children
    ) {}
}

readonly class WarpDefinitionNode implements BlockNode {
    /** @param BlockNode[] $children */
    public function __construct(
        public string $id,
        public array $children
    ) {}
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
