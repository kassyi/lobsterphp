<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Inline;

use Kassyi\LobsterPhp\Core\{FootnoteRefNode, InlineFootnoteNode, InlineLinkNode, InlineNode, InlineParser, LinkNode,
    ParseContext, WarpRefNode};
use Kassyi\LobsterPhp\Core\Parser\InlineMatcherInterface;

/**

 * LinkMatcher

 */

class LinkMatcher implements InlineMatcherInterface {
    /**
     * インライン要素のパースを試みる
     *
     * @param string $text パース対象のテキスト
     * @param int $pos 現在確認中の開始位置（インデックス）
     * @param ParseContext $ctx パースコンテキスト（リンク定義や脚注情報）
     * @return array{node: \Kassyi\LobsterPhp\Core\InlineNode, end: int}|null マッチ成功時は生成されたノードとパース終了位置、失敗時はnull
     */
    /**
     * tryMatch
     */
    public function tryMatch(string $text, int $pos, ParseContext $ctx): ?array {
        $ch = $text[$pos];
        if ($ch === '^' && isset($text[$pos + 1]) && $text[$pos + 1] === '[') {
            return $this->tryMatchInlineFootnote($text, $pos, $ctx);
        }
        if ($ch === '[') {
            return $this->tryMatchBracketExpression($text, $pos, $ctx);
        }
        return null;
    }

    private function tryMatchInlineFootnote(string $text, int $pos, ParseContext $ctx): ?array {
        $end = InlineParser::findClosingBracket($text, $pos + 2);
        if ($end === -1) return null;

        $content = substr($text, $pos + 2, $end - ($pos + 2));
        $ctx->inlineFootnoteCount++;
        $id = "__inline_" . $ctx->inlineFootnoteCount;
        $ctx->footnoteRefs[] = $id;
        
        $children = InlineParser::parseInline($content, $ctx);
        $ctx->footnoteDefs[$id] = $children;

        $node = new InlineFootnoteNode($children);
        return ['node' => $node, 'end' => $end + 1];
    }

    private function tryMatchBracketExpression(string $text, int $pos, ParseContext $ctx): ?array {
        if (isset($text[$pos + 1])) {
            if ($text[$pos + 1] === '^') return $this->tryMatchFootnoteRef($text, $pos, $ctx);
            if ($text[$pos + 1] === '~') return $this->tryMatchWarpRef($text, $pos);
        }

        $textEnd = InlineParser::findClosingBracket($text, $pos + 1);
        if ($textEnd === -1) return null;

        $linkText = substr($text, $pos + 1, $textEnd - ($pos + 1));
        $afterBracket = $textEnd + 1;

        return $this->tryMatchInlineLink($text, $afterBracket, $linkText, $ctx)
            ?? $this->tryMatchReferenceLink($text, $afterBracket, $linkText, $ctx);
    }

    private function tryMatchFootnoteRef(string $text, int $pos, ParseContext $ctx): ?array {
        $end = strpos($text, ']', $pos + 2);
        if ($end === false) return null;
        
        $id = substr($text, $pos + 2, $end - ($pos + 2));
        if (str_contains($id, ' ')) return null;

        if (!in_array($id, $ctx->footnoteRefs, true)) {
            $ctx->footnoteRefs[] = $id;
        }

        $node = new FootnoteRefNode($id);
        return ['node' => $node, 'end' => $end + 1];
    }

    private function tryMatchWarpRef(string $text, int $pos): ?array {
        $end = strpos($text, ']', $pos + 2);
        if ($end === false) return null;
        
        $id = substr($text, $pos + 2, $end - ($pos + 2));
        if ($id === '' || str_contains($id, ' ')) return null;

        $node = new WarpRefNode($id);
        return ['node' => $node, 'end' => $end + 1];
    }

    private function tryMatchInlineLink(string $text, int $afterBracket, string $linkText, ParseContext $ctx): ?array {
        if (isset($text[$afterBracket]) && $text[$afterBracket] === '(') {
            $urlEnd = InlineParser::findClosingParen($text, $afterBracket + 1);
            if ($urlEnd !== -1) {
                $urlContent = substr($text, $afterBracket + 1, $urlEnd - ($afterBracket + 1));
                $parsedUrl = InlineParser::parseLinkContent($urlContent);
                $node = new InlineLinkNode(
                    InlineParser::parseInline($linkText, $ctx),
                    $parsedUrl['href'],
                    $parsedUrl['title']
                );
                return ['node' => $node, 'end' => $urlEnd + 1];
            }
        }
        return null;
    }

    private function tryMatchReferenceLink(string $text, int $afterBracket, string $linkText, ParseContext $ctx): ?array {
        if (isset($text[$afterBracket]) && $text[$afterBracket] === '[') {
            $idEnd = strpos($text, ']', $afterBracket + 1);
            if ($idEnd !== false) {
                $rawId = substr($text, $afterBracket + 1, $idEnd - ($afterBracket + 1));
                $id = strtolower(trim($rawId) !== '' ? trim($rawId) : trim($linkText));
                if (isset($ctx->linkDefs[$id])) {
                    $def = $ctx->linkDefs[$id];
                    $node = new LinkNode(
                        InlineParser::parseInline($linkText, $ctx),
                        $def->href,
                        $def->title
                    );
                    return ['node' => $node, 'end' => $idEnd + 1];
                }
            }
        }

        if (isset($text[$afterBracket]) && $text[$afterBracket] === '[' && isset($text[$afterBracket+1]) && $text[$afterBracket + 1] === ']') {
            $id = strtolower(trim($linkText));
            if (isset($ctx->linkDefs[$id])) {
                $def = $ctx->linkDefs[$id];
                $node = new LinkNode(
                    InlineParser::parseInline($linkText, $ctx),
                    $def->href,
                    $def->title
                );
                return ['node' => $node, 'end' => $afterBracket + 2];
            }
        }

        $implicitId = strtolower(trim($linkText));
        if (isset($ctx->linkDefs[$implicitId])) {
            $implicitDef = $ctx->linkDefs[$implicitId];
            $node = new LinkNode(
                InlineParser::parseInline($linkText, $ctx),
                $implicitDef->href,
                $implicitDef->title
            );
            return ['node' => $node, 'end' => $afterBracket];
        }

        return null;
    }
}

