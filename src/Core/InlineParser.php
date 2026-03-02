<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core;

/**

 * InlineParser

 */

class InlineParser {
    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Finds the next `]` that closes a `[` opened before `start`.
     * Returns -1 if not found within the same line.
     */
    /**
     * findClosingBracket
     */
    public static function findClosingBracket(string $text, int $start): int {
        $depth = 1;
        $len = strlen($text);
        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            } elseif ($ch === "\n") {
                return -1;
            }
        }
        return -1;
    }

    /**
     * Finds the next `)` that closes a `(` opened before `start`.
     * Returns -1 if not found.
     */
    /**
     * findClosingParen
     */
    public static function findClosingParen(string $text, int $start): int {
        $depth = 1;
        $len = strlen($text);
        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return -1;
    }

    /**
     * Parses `url "title"`, `url 'title'`, `url (title)`, or just `url`.
     * @return array{href: string, title?: string|null}
     */
    /**
     * parseLinkContent
     */
    public static function parseLinkContent(string $content): array {
        $content = trim($content);
        
        if (preg_match('/^(\S+)\s+"([^"]*)"$/', $content, $m)) {
            return ['href' => $m[1], 'title' => $m[2]];
        }
        if (preg_match("/^(\S+)\s+'([^']*)'$/", $content, $m)) {
            return ['href' => $m[1], 'title' => $m[2]];
        }
        if (preg_match('/^(\S+)\s+\(([^)]*)\)$/', $content, $m)) {
            return ['href' => $m[1], 'title' => $m[2]];
        }
        
        return ['href' => $content, 'title' => null];
    }

    /**
     * Parses `url "title" =WxH` for images.
     * @return array{href: string, title?: string|null, width?: int|null, height?: int|null}
     */
    /**
     * parseImageContent
     */
    public static function parseImageContent(string $content): array {
        $content = trim($content);
        $width = null;
        $height = null;
        
        if (preg_match('/\s+=(\d*)x(\d*)\s*$/', $content, $sizeMatch, PREG_OFFSET_CAPTURE)) {
            if ($sizeMatch[1][0] !== '') {
                $width = (int)$sizeMatch[1][0];
            }
            if ($sizeMatch[2][0] !== '') {
                $height = (int)$sizeMatch[2][0];
            }
            $content = trim(substr($content, 0, (int)$sizeMatch[0][1]));
        }
        
        $linkContent = self::parseLinkContent($content);
        return [
            'href' => $linkContent['href'],
            'title' => $linkContent['title'],
            'width' => $width,
            'height' => $height,
        ];
    }

    // ============================================================
    // Individual inline matchers
    // ============================================================

    /** @var \Kassyi\LobsterPhp\Core\Parser\InlineMatcherInterface[] */
    private static array $matchers = [];

    private static function getMatchers(): array {
        if (empty(self::$matchers)) {
            self::$matchers = [
                new \Kassyi\LobsterPhp\Core\Parser\Inline\CodeSpanMatcher(),
                new \Kassyi\LobsterPhp\Core\Parser\Inline\ImageMatcher(),
                new \Kassyi\LobsterPhp\Core\Parser\Inline\LinkMatcher(),
                new \Kassyi\LobsterPhp\Core\Parser\Inline\StrikethroughMatcher(),
                new \Kassyi\LobsterPhp\Core\Parser\Inline\EmphasisMatcher(),
            ];
        }
        return self::$matchers;
    }

    /**
     * @return InlineNode[]
     */
    /**
     * parseInline
     */
    public static function parseInline(string $text, ParseContext $ctx): array {
        $nodes = [];
        $pos = 0;
        $textStart = 0;
        $len = strlen($text);

        $flushText = function() use (&$pos, &$textStart, &$nodes, $text) {
            if ($pos > $textStart) {
                $nodes[] = new TextNode(substr($text, $textStart, $pos - $textStart));
            }
            $textStart = $pos;
        };

        while ($pos < $len) {
            $ch = $text[$pos];

            if ($ch === "\n") {
                $flushText();
                $nodes[] = new LineBreakNode();
                $pos++;
                $textStart = $pos;
                continue;
            }

            // Triple star/underscore: emit one literal char and let the next iteration handle **
            if (
                ($ch === '*' || $ch === '_') &&
                isset($text[$pos + 1]) && $text[$pos + 1] === $ch &&
                isset($text[$pos + 2]) && $text[$pos + 2] === $ch
            ) {
                $pos++;
                continue;
            }

            $result = self::tryMatchAny($text, $pos, $ctx);

            if ($result !== null) {
                $flushText();
                $nodes[] = $result['node'];
                $pos = $result['end'];
                $textStart = $pos;
            } else {
                $pos++;
            }
        }

        $flushText();
        return $nodes;
    }

    private static function tryMatchAny(string $text, int $pos, ParseContext $ctx): ?array {
        foreach (self::getMatchers() as $matcher) {
            $result = $matcher->tryMatch($text, $pos, $ctx);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }
}

