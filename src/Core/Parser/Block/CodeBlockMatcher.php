<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Block;

use Kassyi\LobsterPhp\Core\{BlockParser, CodeBlockNode, ParseContext};
use Kassyi\LobsterPhp\Core\Parser\BlockMatcherInterface;

class CodeBlockMatcher implements BlockMatcherInterface {
    /**
     * ブロック要素のパースを試みる
     *
     * @param string[] $lines パース対象の全行
     * @param int $i 現在解析中の行インデックス
     * @param ParseContext $ctx パースコンテキスト（リンク定義や脚注情報）
     * @return array{node: \Kassyi\LobsterPhp\Core\BlockNode, nextIndex: int}|null マッチ成功時は生成されたノードと次の行インデックス、失敗時はnull
     */
    public function tryMatch(array $lines, int $i, ParseContext $ctx): ?array {
        $fence = BlockParser::matchCodeFence($lines[$i]);
        if (!$fence) return null;

        $codeLines = [];
        $markerChar = $fence['marker'][0];
        $markerLen = strlen($fence['marker']);
        $j = $i + 1;
        $len = count($lines);

        while ($j < $len) {
            if (
                preg_match('/^(`{3,}|~{3,})$/', $lines[$j]) &&
                $lines[$j][0] === $markerChar &&
                strlen($lines[$j]) >= $markerLen
            ) {
                $j++;
                break;
            }
            $codeLines[] = $lines[$j];
            $j++;
        }

        $node = new CodeBlockNode(implode("\n", $codeLines), $fence['language'], $fence['filename']);
        return ['node' => $node, 'nextIndex' => $j];
    }
}
