<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core;

/**

 * BlockParser

 */

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

    /**

     * isBlankLine

     */

    public static function isBlankLine(string $line): bool {
        return trim($line) === '';
    }

    /** Returns true if line is a horizontal rule (---, ***, with optional spaces) */
    /**
     * isHorizontalRule
     */
    public static function isHorizontalRule(string $line): bool {
        return preg_match('/^\s*(-\s*){3,}$/', $line) || preg_match('/^\s*(\*\s*){3,}$/', $line);
    }

    /** Returns heading level, content, and optional anchor id, or null */
    /**
     * matchHeading
     */
    public static function matchHeading(string $line): ?array {
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
    /**
     * matchCodeFence
     */
    public static function matchCodeFence(string $line): ?array {
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
    /**
     * stripBlockquotePrefix
     */
    public static function stripBlockquotePrefix(array $lines): array {
        return array_map(fn($l) => preg_replace('/^>\s?/', '', $l), $lines);
    }

    /** Returns list item info if line starts a list item */
    /**
     * matchListItem
     */
    public static function matchListItem(string $line): ?array {
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

    // Removed splitTableRow, parseAlignment, isAlignmentRow, buildTableCells

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

    /** @var \Kassyi\LobsterPhp\Core\Parser\BlockMatcherInterface[] */
    private static array $matchers = [];

    private static function getMatchers(): array {
        if (empty(self::$matchers)) {
            self::$matchers = [
                new \Kassyi\LobsterPhp\Core\Parser\Block\HeadingMatcher(),
                new \Kassyi\LobsterPhp\Core\Parser\Block\HorizontalRuleMatcher(),
                new \Kassyi\LobsterPhp\Core\Parser\Block\CodeBlockMatcher(),
                new \Kassyi\LobsterPhp\Core\Parser\Block\BlockquoteMatcher(),
                new \Kassyi\LobsterPhp\Core\Parser\Block\ListMatcher(),
                new \Kassyi\LobsterPhp\Core\Parser\Block\TableMatcher(),
                new \Kassyi\LobsterPhp\Core\Parser\Block\ParagraphMatcher(),
            ];
        }
        return self::$matchers;
    }

    /**
     * @param string[] $lines
     * @return BlockNode[]
     */
    /**
     * parseBlocks
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

            $matched = false;
            foreach (self::getMatchers() as $matcher) {
                $result = $matcher->tryMatch($trimmed, $i, $ctx);
                if ($result !== null) {
                    $nodes[] = $result['node'];
                    $i = $result['nextIndex'];
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                // Should never happen since ParagraphMatcher matches everything else
                $i++;
            }
        }

        return $nodes;
    }

    /**

     * parseDocument

     */

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

