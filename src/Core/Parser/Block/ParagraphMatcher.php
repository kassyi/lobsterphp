<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Block;

use Kassyi\LobsterPhp\Core\{BlockParser, InlineParser, LineBreakNode, ParagraphNode, ParseContext};
use Kassyi\LobsterPhp\Core\Parser\BlockMatcherInterface;

class ParagraphMatcher implements BlockMatcherInterface {
    /**
     * ブロック要素のパースを試みる
     *
     * @param string[] $lines パース対象の全行
     * @param int $i 現在解析中の行インデックス
     * @param ParseContext $ctx パースコンテキスト（リンク定義や脚注情報）
     * @return array{node: \Kassyi\LobsterPhp\Core\BlockNode, nextIndex: int}|null マッチ成功時は生成されたノードと次の行インデックス、失敗時はnull
     */
    public function tryMatch(array $lines, int $i, ParseContext $ctx): ?array {
        $textLines = [];
        $j = $i;
        $len = count($lines);

        while ($j < $len) {
            $line = $lines[$j];
            if (BlockParser::isBlankLine($line)) break;

            if (
                BlockParser::matchHeading($line) ||
                BlockParser::isHorizontalRule($line) ||
                BlockParser::matchCodeFence($line) ||
                str_starts_with($line, '>') ||
                BlockParser::matchListItem($line) ||
                preg_match('/^\s*\|/', $line) ||
                preg_match('/^\s*~\s*\|/', $line) ||
                preg_match('/^:::/', $line) ||
                preg_match('/^__DETAILS_PLACEHOLDER_/', $line)
            ) {
                break;
            }

            $textLines[] = $line;
            $j++;
        }

        if (empty($textLines)) return null;

        $text = implode("\n", $textLines);
        $children = InlineParser::parseInline($text, $ctx);

        while (!empty($children) && end($children) instanceof LineBreakNode) {
            array_pop($children);
        }

        return ['node' => new ParagraphNode($children), 'nextIndex' => $j];
    }
}
