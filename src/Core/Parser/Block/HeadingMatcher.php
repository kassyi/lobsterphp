<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Block;

use Kassyi\LobsterPhp\Core\{BlockParser, HeadingNode, InlineParser, ParseContext};
use Kassyi\LobsterPhp\Core\Parser\BlockMatcherInterface;

class HeadingMatcher implements BlockMatcherInterface {
    /**
     * ブロック要素のパースを試みる
     *
     * @param string[] $lines パース対象の全行
     * @param int $i 現在解析中の行インデックス
     * @param ParseContext $ctx パースコンテキスト（リンク定義や脚注情報）
     * @return array{node: \Kassyi\LobsterPhp\Core\BlockNode, nextIndex: int}|null マッチ成功時は生成されたノードと次の行インデックス、失敗時はnull
     */
    public function tryMatch(array $lines, int $i, ParseContext $ctx): ?array {
        $m = BlockParser::matchHeading($lines[$i]);
        if (!$m) return null;
        $node = new HeadingNode($m['level'], InlineParser::parseInline($m['text'], $ctx), $m['id']);
        return ['node' => $node, 'nextIndex' => $i + 1];
    }
}
