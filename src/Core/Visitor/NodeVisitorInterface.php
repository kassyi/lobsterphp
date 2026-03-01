<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Visitor;

use Kassyi\LobsterPhp\Core\{BlockquoteNode, BulletListNode, CodeBlockNode, CodeSpanNode, DetailsNode, EmphasisNode, FooterContainerNode, FootnoteRefNode, HeaderContainerNode, HeadingNode, HorizontalRuleNode, ImageNode, InlineFootnoteNode, InlineLinkNode, LineBreakNode, LinkNode, ListItemNode, OrderedListNode, ParagraphNode, StrikethroughNode, StrongNode, TableNode, TextNode, WarpDefinitionNode, WarpRefNode};

interface NodeVisitorInterface {
    // Inline Nodes
    public function visitTextNode(TextNode $node): mixed;
    public function visitLineBreakNode(LineBreakNode $node): mixed;
    public function visitEmphasisNode(EmphasisNode $node): mixed;
    public function visitStrongNode(StrongNode $node): mixed;
    public function visitStrikethroughNode(StrikethroughNode $node): mixed;
    public function visitCodeSpanNode(CodeSpanNode $node): mixed;
    public function visitInlineLinkNode(InlineLinkNode $node): mixed;
    public function visitLinkNode(LinkNode $node): mixed;
    public function visitImageNode(ImageNode $node): mixed;
    public function visitFootnoteRefNode(FootnoteRefNode $node): mixed;
    public function visitInlineFootnoteNode(InlineFootnoteNode $node): mixed;
    public function visitWarpRefNode(WarpRefNode $node): mixed;

    // Block Nodes
    public function visitHeadingNode(HeadingNode $node): mixed;
    public function visitParagraphNode(ParagraphNode $node): mixed;
    public function visitHorizontalRuleNode(HorizontalRuleNode $node): mixed;
    public function visitCodeBlockNode(CodeBlockNode $node): mixed;
    public function visitBlockquoteNode(BlockquoteNode $node): mixed;
    public function visitBulletListNode(BulletListNode $node): mixed;
    public function visitOrderedListNode(OrderedListNode $node): mixed;
    public function visitListItemNode(ListItemNode $node): mixed;
    public function visitTableNode(TableNode $node): mixed;
    
    // Custom Container Nodes
    public function visitHeaderContainerNode(HeaderContainerNode $node): mixed;
    public function visitFooterContainerNode(FooterContainerNode $node): mixed;
    public function visitDetailsNode(DetailsNode $node): mixed;
    public function visitWarpDefinitionNode(WarpDefinitionNode $node): mixed;
}
