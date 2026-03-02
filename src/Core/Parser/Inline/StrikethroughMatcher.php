<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Inline;

use Kassyi\LobsterPhp\Core\{InlineNode, InlineParser, ParseContext, StrikethroughNode};
use Kassyi\LobsterPhp\Core\Parser\InlineMatcherInterface;

/**

 * StrikethroughMatcher

 */

class StrikethroughMatcher implements InlineMatcherInterface {
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
        if (!isset($text[$pos+1]) || $text[$pos] !== '~' || $text[$pos + 1] !== '~') return null;

        $startTildes = 0;
        $tempPos = $pos;
        while (isset($text[$tempPos]) && $text[$tempPos] === '~') {
            $startTildes++;
            $tempPos++;
        }

        $contentStart = $tempPos;
        $searchPos = $contentStart;
        $len = strlen($text);

        while ($searchPos < $len) {
            $closeIdx = strpos($text, '~~', $searchPos);
            if ($closeIdx === false) return null;

            $endTildes = 0;
            $tempEnd = $closeIdx;
            while (isset($text[$tempEnd]) && $text[$tempEnd] === '~') {
                $endTildes++;
                $tempEnd++;
            }

            if ($endTildes >= 2) {
                $content = substr($text, $contentStart, $closeIdx - $contentStart);
                if (str_contains($content, "\n")) return null;

                $node = new StrikethroughNode(InlineParser::parseInline($content, $ctx));
                return ['node' => $node, 'end' => $tempEnd];
            }
            $searchPos = $closeIdx + 2;
        }

        return null;
    }
}

