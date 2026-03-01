<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core;

class BlockParser {
    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @param string[] $lines
     * @return string[]
     */
    private static function trimTrailingSpaces(array $lines): array {
        return array_map('rtrim', $lines);
    }

    private static function isBlankLine(string $line): bool {
        return trim($line) === '';
    }

    /** Returns true if line is a horizontal rule (---, ***, with optional spaces) */
    private static function isHorizontalRule(string $line): bool {
        return preg_match('/^\s*(-\s*){3,}$/', $line) || preg_match('/^\s*(\*\s*){3,}$/', $line);
    }

    /** Returns heading level, content, and optional anchor id, or null */
    private static function matchHeading(string $line): ?array {
        if (!preg_match('/^(#{1,6})\s+(.+?)(\s+#+\s*)?$/', $line, $m)) {
            return null;
        }
        $raw = rtrim($m[2]);
        if (preg_match('/^(.*?)\s*\{#([^}]+)\}\s*$/', $raw, $idMatch)) {
            return [
                'level' => strlen($m[1]),
                'text'  => rtrim($idMatch[1]),
                'id'    => $idMatch[2]
            ];
        }
        return [
            'level' => strlen($m[1]),
            'text'  => $raw,
            'id'    => null
        ];
    }

    /** Returns code fence info (marker + language + filename) or null */
    private static function matchCodeFence(string $line): ?array {
        if (!preg_match('/^(`{3,}|~{3,})([\w+-]*)(?::(.+))?/', $line, $m)) {
            return null;
        }
        return [
            'marker'   => $m[1],
            'language' => !empty($m[2]) ? $m[2] : null,
            'filename' => isset($m[3]) && $m[3] !== '' ? $m[3] : null,
        ];
    }

    /** Returns the `>` stripped prefix lines for a blockquote block */
    private static function stripBlockquotePrefix(array $lines): array {
        return array_map(fn($l) => preg_replace('/^>\s?/', '', $l), $lines);
    }

    /** Returns list item info if line starts a list item */
    private static function matchListItem(string $line): ?array {
        // Bullet list
        if (preg_match('/^(\s*)([-*+])\s+(.*)/', $line, $bulletM)) {
            $indent = strlen($bulletM[1]);
            $textStart = $indent + 2; // marker + space
            $text = $bulletM[3];
            $checked = null;

            // Checklist
            if (preg_match('/^\[([ xX])\]\s+(.*)/', $text, $checkM)) {
                $checked = $checkM[1] !== ' ';
                $text = $checkM[2];
            }
            return [
                'indent'    => $indent,
                'marker'    => 'bullet',
                'checked'   => $checked,
                'textStart' => $textStart,
                'text'      => $text,
                'start'     => null
            ];
        }

        // Ordered list (but not `N\.`)
        if (preg_match('/^(\s*)(\d+)\.\s+(.*)/', $line, $orderedM)) {
            $indent = strlen($orderedM[1]);
            $start = (int)$orderedM[2];
            $textStart = $indent + strlen($orderedM[2]) + 2;
            $text = $orderedM[3];
            $checked = null;

            if (preg_match('/^\[([ xX])\]\s+(.*)/', $text, $checkM)) {
                $checked = $checkM[1] !== ' ';
                $text = $checkM[2];
            }
            return [
                'indent'    => $indent,
                'marker'    => 'ordered',
                'start'     => $start,
                'checked'   => $checked,
                'textStart' => $textStart,
                'text'      => $text
            ];
        }

        return null;
    }

    /** Parse `| a | b | c |` into cell strings (strips leading/trailing `|`). */
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

    /**
     * @param string[] $cells
     * @return TableCellNode[]
     */
    private static function buildTableCells(array $cells, ParseContext $ctx, bool $allowRowspan): array {
        $result = [];
        $c = 0;
        $len = count($cells);
        
        while ($c < $len) {
            $raw = $cells[$c];

            // Horizontal merge
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

            // Vertical merge
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

    // ============================================================
    // Pre-scan: collect definitions
    // ============================================================

    private const LINK_DEF_RE = '/^\[([^\]]+)\]:\s+(\S+)(?:\s+(?:"([^"]+)"|\'([^\']+)\'|\(([^)]+)\)))?/';
    private const FOOTNOTE_DEF_RE = '/^\[\^([^\]\s]+)\]:\s*(.*)/';

    /**
     * @return array{linkDefs: array<string, LinkDef>, rawFootnoteDefs: array<string, string>}
     */
    private static function collectDefinitions(array $lines): array {
        $linkDefs = [];
        $rawFootnoteDefs = [];

        foreach ($lines as $line) {
            if (preg_match(self::FOOTNOTE_DEF_RE, $line, $fm)) {
                $rawFootnoteDefs[$fm[1]] = $fm[2];
                continue;
            }
            if (preg_match(self::LINK_DEF_RE, $line, $lm)) {
                $id = strtolower($lm[1]);
                $title = $lm[3] ?? $lm[4] ?? $lm[5] ?? null;
                if ($title === '') $title = null;
                $linkDefs[$id] = new LinkDef($lm[2], $title);
                continue;
            }
        }

        return ['linkDefs' => $linkDefs, 'rawFootnoteDefs' => $rawFootnoteDefs];
    }

    private static function removeDefinitionLines(array $lines): array {
        return array_filter($lines, function ($l) {
            return !preg_match(self::LINK_DEF_RE, $l) && !preg_match(self::FOOTNOTE_DEF_RE, $l);
        });
    }

    // ============================================================
    // Custom block extraction
    // ============================================================

    /**
     * Helper to extract inner lines of a custom block, advancing the index.
     * @return string[]
     */
    private static function extractWarpLikeBlockContent(array $lines, int &$i, int $len): array {
        $innerLines = [];
        $i++; // Move past the opening tag
        $depth = 0;
        while ($i < $len) {
            $l = $lines[$i];
            if (preg_match('/^:::(header|footer)\s*$/', $l) || preg_match('/^:::warp\s+\S/', $l) || preg_match('/^:::details\s+/', $l)) {
                $depth++;
            } elseif (preg_match('/^\s*:::\s*$/', $l)) {
                if ($depth === 0) break;
                $depth--;
            }
            $innerLines[] = $l;
            $i++;
        }
        $i++; // skip :::
        return $innerLines;
    }

    /**
     * @return array{header: ?HeaderContainerNode, footer: ?FooterContainerNode, warpDefs: array<string, WarpDefinitionNode>, remainingLines: string[], detailsBlocks: array{startIdx: int, node: DetailsNode}[]}
     */
    private static function extractCustomBlocks(array $lines, ParseContext $ctx): array {
        $header = null;
        $footer = null;
        $warpDefs = [];
        $remainingLines = [];
        $detailsBlocks = [];

        $i = 0;
        $len = count($lines);
        $lines = array_values($lines); // Reindex

        while ($i < $len) {
            $line = $lines[$i];

            if (preg_match('/^:::header\s*$/', $line)) {
                $children = self::processCustomBlock($lines, $i, $len, $ctx, $warpDefs);
                $header = new HeaderContainerNode($children);
                continue;
            }

            if (preg_match('/^:::footer\s*$/', $line)) {
                $children = self::processCustomBlock($lines, $i, $len, $ctx, $warpDefs);
                $footer = new FooterContainerNode($children);
                continue;
            }

            if (preg_match('/^:::warp\s+(\S+)\s*$/', $line, $warpM)) {
                $id = $warpM[1];
                $innerLines = self::extractWarpLikeBlockContent($lines, $i, $len);
                $children = self::parseBlocks($innerLines, $ctx);
                if (isset($warpDefs[$id])) {
                    $id = "__duplicate_" . $id;
                }
                $warpDefs[$id] = new WarpDefinitionNode($id, $children);
                continue;
            }

            if (preg_match('/^:::details\s+(.*?)\s*$/', $line, $detailsM)) {
                $title = $detailsM[1];
                $startIdx = count($remainingLines);
                
                $children = self::processCustomBlock($lines, $i, $len, $ctx, $warpDefs);
                
                $detailsNode = new DetailsNode($title, $children);
                $remainingLines[] = "__DETAILS_PLACEHOLDER_" . count($detailsBlocks) . "__";
                $detailsBlocks[] = ['startIdx' => $startIdx, 'node' => $detailsNode];
                continue;
            }

            $remainingLines[] = $line;
            $i++;
        }

        return [
            'header'         => $header,
            'footer'         => $footer,
            'warpDefs'       => $warpDefs,
            'remainingLines' => $remainingLines,
            'detailsBlocks'  => $detailsBlocks,
        ];
    }

    /**
     * Helper to process the content of a custom block (header, footer, details)
     * @param array<string, WarpDefinitionNode> &$warpDefs
     * @return BlockNode[]
     */
    private static function processCustomBlock(array $lines, int &$i, int $len, ParseContext $ctx, array &$warpDefs): array {
        $innerLines = self::extractWarpLikeBlockContent($lines, $i, $len);
        $nested = self::extractCustomBlocks($innerLines, $ctx);
        
        foreach ($nested['warpDefs'] as $wid => $wdef) {
            $warpDefs[$wid] = $wdef;
        }
        
        $children = self::parseBlocks($nested['remainingLines'], $ctx);
        if (!empty($nested['detailsBlocks'])) {
            $children = self::replaceDetailsPlaceholders($children, $nested['detailsBlocks']);
        }
        
        return $children;
    }

    /**
     * @param BlockNode[] $blocks
     * @param array{startIdx: int, node: DetailsNode}[] $detailsBlocks
     * @return BlockNode[]
     */
    private static function replaceDetailsPlaceholders(array $blocks, array $detailsBlocks): array {
        return array_map(function ($node) use ($detailsBlocks) {
            if ($node instanceof ParagraphNode && count($node->children) === 1 && $node->children[0] instanceof TextNode) {
                $text = $node->children[0]->text;
                if (preg_match('/^__DETAILS_PLACEHOLDER_(\d+)__$/', $text, $pm)) {
                    $idx = (int)$pm[1];
                    if (isset($detailsBlocks[$idx])) {
                        return $detailsBlocks[$idx]['node'];
                    }
                }
            }
            return $node;
        }, $blocks);
    }

    // ============================================================
    // Block parsers
    // ============================================================

    /**
     * @return array{node: BlockNode, nextIndex: int}|null
     */
    private static function tryParseHeading(array $lines, int $i, ParseContext $ctx): ?array {
        $m = self::matchHeading($lines[$i]);
        if (!$m) return null;
        $node = new HeadingNode($m['level'], InlineParser::parseInline($m['text'], $ctx), $m['id']);
        return ['node' => $node, 'nextIndex' => $i + 1];
    }

    private static function tryParseHorizontalRule(array $lines, int $i): ?array {
        if (!self::isHorizontalRule($lines[$i])) return null;
        $node = new HorizontalRuleNode();
        return ['node' => $node, 'nextIndex' => $i + 1];
    }

    private static function tryParseCodeBlock(array $lines, int $i): ?array {
        $fence = self::matchCodeFence($lines[$i]);
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

    private static function tryParseBlockquote(array $lines, int $i, ParseContext $ctx): ?array {
        if (!str_starts_with($lines[$i], '>')) return null;

        $bqLines = [];
        $j = $i;
        $len = count($lines);
        while ($j < $len && str_starts_with($lines[$j], '>')) {
            $bqLines[] = $lines[$j];
            $j++;
        }

        $stripped = self::stripBlockquotePrefix($bqLines);
        $node = new BlockquoteNode(self::parseBlocks($stripped, $ctx));
        return ['node' => $node, 'nextIndex' => $j];
    }

    private static function tryParseList(array $lines, int $i, ParseContext $ctx): ?array {
        $firstItem = self::matchListItem($lines[$i]);
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
            if (self::isBlankLine($lines[$i])) {
                $i++;
                continue;
            }

            $itemInfo = self::matchListItem($lines[$i]);
            if (!$itemInfo || $itemInfo['marker'] !== 'bullet') break;
            if ($itemInfo['indent'] < $depth) break;

            $textLines = [$itemInfo['text']];
            $i++;

            while ($i < $len) {
                if (self::isBlankLine($lines[$i])) break;
                $next = self::matchListItem($lines[$i]);
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
                $next = self::matchListItem($lines[$i]);
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
            if (self::isBlankLine($lines[$i])) {
                $i++;
                continue;
            }

            $itemInfo = self::matchListItem($lines[$i]);
            if (!$itemInfo || $itemInfo['marker'] !== 'ordered') break;
            if ($itemInfo['indent'] < $depth) break;

            if ($firstItem) {
                $start = $itemInfo['start'] ?? 1;
                $firstItem = false;
            }

            $textLines = [$itemInfo['text']];
            $i++;

            while ($i < $len) {
                if (self::isBlankLine($lines[$i])) break;
                $next = self::matchListItem($lines[$i]);
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
                $next = self::matchListItem($lines[$i]);
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

    private static function tryParseTable(array $lines, int $i, ParseContext $ctx): ?array {
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

    /**
     * @param TableCellNode[][] $rows
     */
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

    private static function parseParagraph(array $lines, int $i, ParseContext $ctx): array {
        $textLines = [];
        $j = $i;
        $len = count($lines);

        while ($j < $len) {
            $line = $lines[$j];
            if (self::isBlankLine($line)) break;

            if (
                self::matchHeading($line) ||
                self::isHorizontalRule($line) ||
                self::matchCodeFence($line) ||
                str_starts_with($line, '>') ||
                self::matchListItem($line) ||
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

        $text = implode("\n", $textLines);
        $children = InlineParser::parseInline($text, $ctx);

        // Strip trailing line_break
        while (!empty($children) && end($children) instanceof LineBreakNode) {
            array_pop($children);
        }

        return ['node' => new ParagraphNode($children), 'nextIndex' => $j];
    }

    // ============================================================
    // Public Block Parser
    // ============================================================

    /**
     * @param string[] $lines
     * @return BlockNode[]
     */
    public static function parseBlocks(array $lines, ParseContext $ctx): array {
        $nodes = [];
        $trimmed = self::trimTrailingSpaces($lines);
        $len = count($trimmed);
        $i = 0;

        while ($i < $len) {
            $line = $trimmed[$i];

            if (self::isBlankLine($line)) {
                $i++;
                continue;
            }

            if (preg_match('/^__DETAILS_PLACEHOLDER_\d+__$/', $line)) {
                $nodes[] = new ParagraphNode([new TextNode($line)]);
                $i++;
                continue;
            }

            $result = self::tryParseHeading($trimmed, $i, $ctx)
                   ?? self::tryParseHorizontalRule($trimmed, $i)
                   ?? self::tryParseCodeBlock($trimmed, $i)
                   ?? self::tryParseBlockquote($trimmed, $i, $ctx)
                   ?? self::tryParseList($trimmed, $i, $ctx)
                   ?? self::tryParseTable($trimmed, $i, $ctx)
                   ?? self::parseParagraph($trimmed, $i, $ctx);

            $nodes[] = $result['node'];
            $i = $result['nextIndex'];
        }

        return $nodes;
    }

    public static function parseDocument(string $markdown): Document {
        // Normalize line endings to \n
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $rawLines = explode("\n", $markdown);
        $lines = self::trimTrailingSpaces($rawLines);

        $defs = self::collectDefinitions($lines);
        $cleanLines = self::removeDefinitionLines($lines);

        $ctx = new ParseContext(
            $defs['linkDefs'],
            [], // footnoteDefs
            [], // warpDefs
            [], // footnoteRefs
            0   // inlineFootnoteCount
        );

        foreach ($defs['rawFootnoteDefs'] as $id => $text) {
            $ctx->footnoteDefs[$id] = InlineParser::parseInline($text, $ctx);
        }

        $extracted = self::extractCustomBlocks(array_values($cleanLines), $ctx);
        foreach ($extracted['warpDefs'] as $k => $v) $ctx->warpDefs[$k] = $v;

        $body = self::parseBlocks($extracted['remainingLines'], $ctx);
        if (!empty($extracted['detailsBlocks'])) {
            $body = self::replaceDetailsPlaceholders($body, $extracted['detailsBlocks']);
        }

        return new Document(
            $body,
            $defs['linkDefs'],
            $ctx->footnoteDefs,
            $ctx->footnoteRefs,
            $ctx->warpDefs,
            $extracted['header'],
            $extracted['footer']
        );
    }
}
