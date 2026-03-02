<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Inline;

use Kassyi\LobsterPhp\Core\{EmphasisNode, InlineNode, InlineParser, ParseContext, StrongNode};
use Kassyi\LobsterPhp\Core\Parser\InlineMatcherInterface;

/**

 * EmphasisMatcher

 */

class EmphasisMatcher implements InlineMatcherInterface {
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
        if ($ch !== '*' && $ch !== '_') {
            return null;
        }

        if (isset($text[$pos + 1]) && $text[$pos + 1] === $ch && (!isset($text[$pos + 2]) || $text[$pos + 2] !== $ch)) {
            return $this->tryMatchStrong($text, $pos, $ctx, $ch);
        }
        if (!isset($text[$pos + 1]) || $text[$pos + 1] !== $ch) {
            return $this->tryMatchEmphasis($text, $pos, $ctx, $ch);
        }

        return null;
    }

    private function tryMatchStrong(string $text, int $pos, ParseContext $ctx, string $ch): ?array {
        // Don't consume *** (triple)
        if (isset($text[$pos + 2]) && $text[$pos + 2] === $ch) {
            return null;
        }

        $delim = $ch . $ch;
        $contentStart = $pos + 2;

        $closeIdx = strpos($text, $delim, $contentStart);
        if ($closeIdx === false) return null;

        $content = substr($text, $contentStart, $closeIdx - $contentStart);
        if (str_contains($content, "\n")) return null;

        $node = new StrongNode(InlineParser::parseInline($content, $ctx));
        return ['node' => $node, 'end' => $closeIdx + 2];
    }

    private function tryMatchEmphasis(string $text, int $pos, ParseContext $ctx, string $ch): ?array {
        $contentStart = $pos + 1;
        $searchPos = $contentStart;
        $len = strlen($text);

        while ($searchPos < $len) {
            $closeIdx = strpos($text, $ch, $searchPos);
            if ($closeIdx === false) return null;

            if (isset($text[$closeIdx + 1]) && $text[$closeIdx + 1] === $ch) {
                $searchPos = $closeIdx + 2;
                continue;
            }

            $content = substr($text, $contentStart, $closeIdx - $contentStart);
            if (str_contains($content, "\n")) return null;

            $node = new EmphasisNode(InlineParser::parseInline($content, $ctx));
            return ['node' => $node, 'end' => $closeIdx + 1];
        }
        return null;
    }
}

