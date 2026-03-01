<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Block;

use Kassyi\LobsterPhp\Core\{BlockParser, BulletListNode, InlineParser, ListItemNode, OrderedListNode,ParseContext};
use Kassyi\LobsterPhp\Core\Parser\BlockMatcherInterface;

class ListMatcher implements BlockMatcherInterface {
    /**
     * ブロック要素のパースを試みる
     *
     * @param string[] $lines パース対象の全行
     * @param int $i 現在解析中の行インデックス
     * @param ParseContext $ctx パースコンテキスト（リンク定義や脚注情報）
     * @return array{node: \Kassyi\LobsterPhp\Core\BlockNode, nextIndex: int}|null マッチ成功時は生成されたノードと次の行インデックス、失敗時はnull
     */
    public function tryMatch(array $lines, int $i, ParseContext $ctx): ?array {
        $firstItem = BlockParser::matchListItem($lines[$i]);
        if (!$firstItem) return null;

        return $firstItem['marker'] === 'bullet'
            ? self::parseBulletList($lines, $i, 0, $ctx)
            : self::parseOrderedList($lines, $i, 0, $ctx);
    }

    private static function parseBulletList(array $lines, int $startI, int $depth, ParseContext $ctx): array {
        $items = [];
        $i = $startI;
        $len = count($lines);

        while ($i < $len) {
            if (BlockParser::isBlankLine($lines[$i])) {
                $i++;
                continue;
            }

            $itemInfo = BlockParser::matchListItem($lines[$i]);
            if (!$itemInfo || $itemInfo['marker'] !== 'bullet') break;
            if ($itemInfo['indent'] < $depth) break;

            $textLines = [$itemInfo['text']];
            $i++;

            while ($i < $len) {
                if (BlockParser::isBlankLine($lines[$i])) break;
                $next = BlockParser::matchListItem($lines[$i]);
                if ($next) {
                    if ($next['indent'] > $itemInfo['indent']) {
                        break; // sub-list
                    } else {
                        break;
                    }
                }
                if (preg_match('/^\s/', $lines[$i])) {
                    $textLines[] = ltrim($lines[$i]);
                    $i++;
                } else {
                    break;
                }
            }

            $sublist = null;
            if ($i < $len) {
                $next = BlockParser::matchListItem($lines[$i]);
                if ($next && $next['indent'] > $itemInfo['indent']) {
                    $subResult = $next['marker'] === 'bullet'
                        ? self::parseBulletList($lines, $i, $next['indent'], $ctx)
                        : self::parseOrderedList($lines, $i, $next['indent'], $ctx);
                    $sublist = $subResult['node'];
                    $i = $subResult['nextIndex'];
                }
            }

            $items[] = new ListItemNode(
                InlineParser::parseInline(implode(' ', $textLines), $ctx),
                $itemInfo['checked'],
                $sublist
            );
        }

        return ['node' => new BulletListNode($depth, $items), 'nextIndex' => $i];
    }

    private static function parseOrderedList(array $lines, int $startI, int $depth, ParseContext $ctx): array {
        $items = [];
        $i = $startI;
        $start = 1;
        $firstItem = true;
        $len = count($lines);

        while ($i < $len) {
            if (BlockParser::isBlankLine($lines[$i])) {
                $i++;
                continue;
            }

            $itemInfo = BlockParser::matchListItem($lines[$i]);
            if (!$itemInfo || $itemInfo['marker'] !== 'ordered') break;
            if ($itemInfo['indent'] < $depth) break;

            if ($firstItem) {
                $start = $itemInfo['start'] ?? 1;
                $firstItem = false;
            }

            $textLines = [$itemInfo['text']];
            $i++;

            while ($i < $len) {
                if (BlockParser::isBlankLine($lines[$i])) break;
                $next = BlockParser::matchListItem($lines[$i]);
                if ($next) break;
                if (preg_match('/^\s/', $lines[$i])) {
                    $textLines[] = ltrim($lines[$i]);
                    $i++;
                } else {
                    break;
                }
            }

            $sublist = null;
            if ($i < $len) {
                $next = BlockParser::matchListItem($lines[$i]);
                if ($next && $next['indent'] > $itemInfo['indent']) {
                    $subResult = $next['marker'] === 'bullet'
                        ? self::parseBulletList($lines, $i, $next['indent'], $ctx)
                        : self::parseOrderedList($lines, $i, $next['indent'], $ctx);
                    $sublist = $subResult['node'];
                    $i = $subResult['nextIndex'];
                }
            }

            $items[] = new ListItemNode(
                InlineParser::parseInline(implode(' ', $textLines), $ctx),
                $itemInfo['checked'],
                $sublist
            );
        }

        return ['node' => new OrderedListNode($depth, $start, $items), 'nextIndex' => $i];
    }
}
