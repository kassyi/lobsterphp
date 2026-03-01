<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Block;

use Kassyi\LobsterPhp\Core\{InlineParser, ParseContext, TableAlignment, TableCellNode,TableNode, TextNode};
use Kassyi\LobsterPhp\Core\Parser\BlockMatcherInterface;

class TableMatcher implements BlockMatcherInterface {
    /**
     * ブロック要素のパースを試みる
     *
     * @param string[] $lines パース対象の全行
     * @param int $i 現在解析中の行インデックス
     * @param ParseContext $ctx パースコンテキスト（リンク定義や脚注情報）
     * @return array{node: \Kassyi\LobsterPhp\Core\BlockNode, nextIndex: int}|null マッチ成功時は生成されたノードと次の行インデックス、失敗時はnull
     */
    public function tryMatch(array $lines, int $i, ParseContext $ctx): ?array {
        $line = $lines[$i];
        $isSilent = preg_match('/^\s*~\s+\|/', $line) || preg_match('/^\s*~\s+/', $line);

        $isTableLine = function (string $l) use ($isSilent): bool {
            if ($isSilent) return preg_match('/^\s*~\s*\|/', $l) || preg_match('/^\s*~\s+/', $l) === 1;
            return preg_match('/^\s*\|/', $l) || str_contains($l, '|');
        };

        if (!$isTableLine($line)) return null;

        if ($i + 1 >= count($lines)) return null;
        
        $nextRaw = $lines[$i + 1];
        $nextAlignRaw = $isSilent ? preg_replace('/^\s*~\s*/', '', $nextRaw) : $nextRaw;
        
        if (!self::isAlignmentRow($nextRaw) && !($isSilent && self::isAlignmentRow($nextAlignRaw))) {
            return null;
        }

        $rawHeader = $isSilent ? preg_replace('/^\s*~\s*/', '', $line) : $line;
        $headerCells = self::buildTableCells(self::splitTableRow($rawHeader), $ctx, false);

        $alignments = array_map(fn($c) => self::parseAlignment($c), self::splitTableRow($nextAlignRaw));

        $totalCols = array_reduce($headerCells, fn($sum, $cell) => $sum + ($cell->colspan ?? 1), 0);

        $rows = [];
        $j = $i + 2;
        $len = count($lines);
        while ($j < $len && $isTableLine($lines[$j])) {
            $rawRow = $isSilent ? preg_replace('/^\s*~\s*/', '', $lines[$j]) : $lines[$j];
            $cells = self::splitTableRow($rawRow);

            while (count($cells) < $totalCols) {
                $cells[] = '';
            }

            $rows[] = self::buildTableCells($cells, $ctx, true);
            $j++;
        }

        self::computeTableRowspans($rows);

        $node = new TableNode($isSilent, $headerCells, $alignments, $rows);
        return ['node' => $node, 'nextIndex' => $j];
    }

    private static function splitTableRow(string $line): array {
        $stripped = preg_replace('/^\s*~?\s*\|?\s*/', '', $line);
        $stripped = preg_replace('/\s*\|?\s*$/', '', $stripped);
        
        $cells = [];
        $cur = '';
        $inCode = false;
        $len = strlen($stripped);
        
        for ($i = 0; $i < $len; $i++) {
            $ch = $stripped[$i];
            if ($ch === '`') {
                $inCode = !$inCode;
                $cur .= $ch;
            } elseif ($ch === '|' && !$inCode) {
                $cells[] = trim($cur);
                $cur = '';
            } else {
                $cur .= $ch;
            }
        }
        $cells[] = trim($cur);
        return $cells;
    }

    private static function parseAlignment(string $cell): TableAlignment {
        $c = trim($cell);
        $left = str_starts_with($c, ':');
        $right = str_ends_with($c, ':');
        
        if ($left && $right) return TableAlignment::Center;
        if ($left) return TableAlignment::Left;
        if ($right) return TableAlignment::Right;
        return TableAlignment::Default;
    }

    private static function isAlignmentRow(string $line): bool {
        $cells = self::splitTableRow($line);
        if (empty($cells)) return false;
        foreach ($cells as $c) {
            if (!preg_match('/^:?-+:?$/', $c)) {
                return false;
            }
        }
        return true;
    }

    private static function buildTableCells(array $cells, ParseContext $ctx, bool $allowRowspan): array {
        $result = [];
        $c = 0;
        $len = count($cells);
        
        while ($c < $len) {
            $raw = $cells[$c];

            if (str_ends_with($raw, '\\') && !preg_match('/^\\\-+$/', $raw)) {
                $content = rtrim(substr($raw, 0, -1));
                $colspan = 2;
                $c++;
                
                while ($c < $len) {
                    if ($cells[$c] === '\\') {
                        $colspan++;
                        $c++;
                    } elseif ($cells[$c] === '') {
                        $c++;
                        break;
                    } else {
                        break;
                    }
                }
                $result[] = new TableCellNode(InlineParser::parseInline($content, $ctx), $colspan, null);
                continue;
            }

            if ($allowRowspan && preg_match('/^\\\-+$/', $raw)) {
                $result[] = new TableCellNode([new TextNode('__ROWSPAN__')], null, null);
                $c++;
                continue;
            }

            $result[] = new TableCellNode(InlineParser::parseInline($raw, $ctx), null, null);
            $c++;
        }
        return $result;
    }

    private static function computeTableRowspans(array &$rows): void {
        $isRowspanCell = function (TableCellNode $cell): bool {
            return count($cell->children) === 1 && 
                   $cell->children[0] instanceof TextNode && 
                   $cell->children[0]->text === '__ROWSPAN__';
        };

        $colMaps = [];
        foreach ($rows as $row) {
            $map = [];
            $col = 0;
            foreach ($row as $cell) {
                $map[$col] = $cell;
                $col += $cell->colspan ?? 1;
            }
            $colMaps[] = $map;
        }

        for ($r = 0; $r < count($rows); $r++) {
            $col = 0;
            foreach ($rows[$r] as $cell) {
                if ($isRowspanCell($cell)) {
                    for ($rr = $r - 1; $rr >= 0; $rr--) {
                        if (isset($colMaps[$rr][$col])) {
                            $above = $colMaps[$rr][$col];
                            if (!$isRowspanCell($above)) {
                                $newRowspan = ($above->rowspan ?? 1) + 1;
                                $newAbove = new TableCellNode($above->children, $above->colspan, $newRowspan);
                                
                                $colMaps[$rr][$col] = $newAbove;
                                
                                $colIter = 0;
                                foreach ($rows[$rr] as $idx => $c2) {
                                    if ($colIter === $col) {
                                        $rows[$rr][$idx] = $newAbove;
                                        break;
                                    }
                                    $colIter += $c2->colspan ?? 1;
                                }
                                break;
                            }
                        }
                    }
                }
                $col += $cell->colspan ?? 1;
            }
        }
    }
}
