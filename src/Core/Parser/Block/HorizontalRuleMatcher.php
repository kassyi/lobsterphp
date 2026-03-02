<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Block;

use Kassyi\LobsterPhp\Core\{BlockParser, HorizontalRuleNode, ParseContext};
use Kassyi\LobsterPhp\Core\Parser\BlockMatcherInterface;

/**

 * HorizontalRuleMatcher

 */

class HorizontalRuleMatcher implements BlockMatcherInterface {
    /**
     * ブロック要素のパースを試みる
     *
     * @param string[] $lines パース対象の全行
     * @param int $i 現在解析中の行インデックス
     * @param ParseContext $ctx パースコンテキスト（リンク定義や脚注情報）
     * @return array{node: \Kassyi\LobsterPhp\Core\BlockNode, nextIndex: int}|null マッチ成功時は生成されたノードと次の行インデックス、失敗時はnull
     */
    /**
     * tryMatch
     */
    public function tryMatch(array $lines, int $i, ParseContext $ctx): ?array {
        if (!BlockParser::isHorizontalRule($lines[$i])) return null;
        $node = new HorizontalRuleNode();
        return ['node' => $node, 'nextIndex' => $i + 1];
    }
}

