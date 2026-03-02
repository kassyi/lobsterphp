<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Visitor;

use Kassyi\LobsterPhp\Core\{BlockquoteNode, BulletListNode, CodeBlockNode, CodeSpanNode, DetailsNode, EmphasisNode,
    FooterContainerNode, FootnoteRefNode, HeaderContainerNode, HeadingNode, HorizontalRuleNode, ImageNode,
    InlineFootnoteNode, InlineLinkNode, LineBreakNode, LinkNode, ListItemNode, OrderedListNode, ParagraphNode,
    StrikethroughNode, StrongNode, TableNode, TextNode, WarpDefinitionNode, WarpRefNode};

/**

 * NodeVisitorInterface

 */

interface NodeVisitorInterface {
    // Inline Nodes
    /**
     * Visit TextNode
     *
     * @param TextNode $node
     * @return mixed
     */
    /**
     * visitTextNode
     */
    public function visitTextNode(TextNode $node): mixed;
    /**
     * Visit LineBreakNode
     *
     * @param LineBreakNode $node
     * @return mixed
     */
    /**
     * visitLineBreakNode
     */
    public function visitLineBreakNode(LineBreakNode $node): mixed;
    /**
     * Visit EmphasisNode
     *
     * @param EmphasisNode $node
     * @return mixed
     */
    /**
     * visitEmphasisNode
     */
    public function visitEmphasisNode(EmphasisNode $node): mixed;
    /**
     * Visit StrongNode
     *
     * @param StrongNode $node
     * @return mixed
     */
    /**
     * visitStrongNode
     */
    public function visitStrongNode(StrongNode $node): mixed;
    /**
     * Visit StrikethroughNode
     *
     * @param StrikethroughNode $node
     * @return mixed
     */
    /**
     * visitStrikethroughNode
     */
    public function visitStrikethroughNode(StrikethroughNode $node): mixed;
    /**
     * Visit CodeSpanNode
     *
     * @param CodeSpanNode $node
     * @return mixed
     */
    /**
     * visitCodeSpanNode
     */
    public function visitCodeSpanNode(CodeSpanNode $node): mixed;
    /**
     * Visit InlineLinkNode
     *
     * @param InlineLinkNode $node
     * @return mixed
     */
    /**
     * visitInlineLinkNode
     */
    public function visitInlineLinkNode(InlineLinkNode $node): mixed;
    /**
     * Visit LinkNode
     *
     * @param LinkNode $node
     * @return mixed
     */
    /**
     * visitLinkNode
     */
    public function visitLinkNode(LinkNode $node): mixed;
    /**
     * Visit ImageNode
     *
     * @param ImageNode $node
     * @return mixed
     */
    /**
     * visitImageNode
     */
    public function visitImageNode(ImageNode $node): mixed;
    /**
     * Visit FootnoteRefNode
     *
     * @param FootnoteRefNode $node
     * @return mixed
     */
    /**
     * visitFootnoteRefNode
     */
    public function visitFootnoteRefNode(FootnoteRefNode $node): mixed;
    /**
     * Visit InlineFootnoteNode
     *
     * @param InlineFootnoteNode $node
     * @return mixed
     */
    /**
     * visitInlineFootnoteNode
     */
    public function visitInlineFootnoteNode(InlineFootnoteNode $node): mixed;
    /**
     * Visit WarpRefNode
     *
     * @param WarpRefNode $node
     * @return mixed
     */
    /**
     * visitWarpRefNode
     */
    public function visitWarpRefNode(WarpRefNode $node): mixed;

    // Block Nodes
    /**
     * Visit HeadingNode
     *
     * @param HeadingNode $node
     * @return mixed
     */
    /**
     * visitHeadingNode
     */
    public function visitHeadingNode(HeadingNode $node): mixed;
    /**
     * Visit ParagraphNode
     *
     * @param ParagraphNode $node
     * @return mixed
     */
    /**
     * visitParagraphNode
     */
    public function visitParagraphNode(ParagraphNode $node): mixed;
    /**
     * Visit HorizontalRuleNode
     *
     * @param HorizontalRuleNode $node
     * @return mixed
     */
    /**
     * visitHorizontalRuleNode
     */
    public function visitHorizontalRuleNode(HorizontalRuleNode $node): mixed;
    /**
     * Visit CodeBlockNode
     *
     * @param CodeBlockNode $node
     * @return mixed
     */
    /**
     * visitCodeBlockNode
     */
    public function visitCodeBlockNode(CodeBlockNode $node): mixed;
    /**
     * Visit BlockquoteNode
     *
     * @param BlockquoteNode $node
     * @return mixed
     */
    /**
     * visitBlockquoteNode
     */
    public function visitBlockquoteNode(BlockquoteNode $node): mixed;
    /**
     * Visit BulletListNode
     *
     * @param BulletListNode $node
     * @return mixed
     */
    /**
     * visitBulletListNode
     */
    public function visitBulletListNode(BulletListNode $node): mixed;
    /**
     * Visit OrderedListNode
     *
     * @param OrderedListNode $node
     * @return mixed
     */
    /**
     * visitOrderedListNode
     */
    public function visitOrderedListNode(OrderedListNode $node): mixed;
    /**
     * Visit ListItemNode
     *
     * @param ListItemNode $node
     * @return mixed
     */
    /**
     * visitListItemNode
     */
    public function visitListItemNode(ListItemNode $node): mixed;
    /**
     * Visit TableNode
     *
     * @param TableNode $node
     * @return mixed
     */
    /**
     * visitTableNode
     */
    public function visitTableNode(TableNode $node): mixed;
    
    // Custom Container Nodes
    /**
     * Visit HeaderContainerNode
     *
     * @param HeaderContainerNode $node
     * @return mixed
     */
    /**
     * visitHeaderContainerNode
     */
    public function visitHeaderContainerNode(HeaderContainerNode $node): mixed;
    /**
     * Visit FooterContainerNode
     *
     * @param FooterContainerNode $node
     * @return mixed
     */
    /**
     * visitFooterContainerNode
     */
    public function visitFooterContainerNode(FooterContainerNode $node): mixed;
    /**
     * Visit DetailsNode
     *
     * @param DetailsNode $node
     * @return mixed
     */
    /**
     * visitDetailsNode
     */
    public function visitDetailsNode(DetailsNode $node): mixed;
    /**
     * Visit WarpDefinitionNode
     *
     * @param WarpDefinitionNode $node
     * @return mixed
     */
    /**
     * visitWarpDefinitionNode
     */
    public function visitWarpDefinitionNode(WarpDefinitionNode $node): mixed;
}


