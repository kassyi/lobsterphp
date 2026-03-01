<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Inline;

use Kassyi\LobsterPhp\Core\{CodeSpanNode, InlineNode, ParseContext};
use Kassyi\LobsterPhp\Core\Parser\InlineMatcherInterface;

class CodeSpanMatcher implements InlineMatcherInterface {
    /**
     * インライン要素のパースを試みる
     *
     * @param string $text パース対象のテキスト
     * @param int $pos 現在確認中の開始位置（インデックス）
     * @param ParseContext $ctx パースコンテキスト（リンク定義や脚注情報）
     * @return array{node: \Kassyi\LobsterPhp\Core\InlineNode, end: int}|null マッチ成功時は生成されたノードとパース終了位置、失敗時はnull
     */
    public function tryMatch(string $text, int $pos, ParseContext $ctx): ?array {
        if (!isset($text[$pos]) || $text[$pos] !== '`') return null;

        $len = strlen($text);
        $count = 0;
        while ($pos + $count < $len && $text[$pos + $count] === '`') {
            $count++;
        }

        $openStr = str_repeat('`', $count);
        $contentStart = $pos + $count;
        $searchPos = $contentStart;

        while ($searchPos < $len) {
            $closeIdx = strpos($text, $openStr, $searchPos);
            if ($closeIdx === false) return null;
            
            // Make sure it's not n+1 backticks
            if (!isset($text[$closeIdx + $count]) || $text[$closeIdx + $count] !== '`') {
                $code = substr($text, $contentStart, $closeIdx - $contentStart);
                // Strip one leading/trailing space if count > 1 and both present
                if ($count > 1 && str_starts_with($code, ' ') && str_ends_with($code, ' ')) {
                    $code = substr($code, 1, -1);
                }
                
                return ['node' => new CodeSpanNode($code), 'end' => $closeIdx + $count];
            }
            $searchPos = $closeIdx + 1;
        }
        return null;
    }
}
