<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core;

class InlineParser {
    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Finds the next `]` that closes a `[` opened before `start`.
     * Returns -1 if not found within the same line.
     */
    private static function findClosingBracket(string $text, int $start): int {
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
    private static function findClosingParen(string $text, int $start): int {
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
    private static function parseLinkContent(string $content): array {
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
    private static function parseImageContent(string $content): array {
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

    /**
     * Code span: `code`, `` `code` `` (n backticks)
     * @return array{node: InlineNode, end: int}|null
     */
    private static function tryMatchCodeSpan(string $text, int $pos): ?array {
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

    /**
     * Image: ![alt](url "title" =WxH)
     * @return array{node: InlineNode, end: int}|null
     */
    private static function tryMatchImage(string $text, int $pos, ParseContext $ctx): ?array {
        if (!isset($text[$pos+1]) || $text[$pos] !== '!' || $text[$pos + 1] !== '[') return null;

        $altEnd = self::findClosingBracket($text, $pos + 2);
        if ($altEnd === -1) return null;

        if (!isset($text[$altEnd + 1]) || $text[$altEnd + 1] !== '(') return null;

        $urlEnd = self::findClosingParen($text, $altEnd + 2);
        if ($urlEnd === -1) return null;

        $alt = substr($text, $pos + 2, $altEnd - ($pos + 2));
        $urlContent = substr($text, $altEnd + 2, $urlEnd - ($altEnd + 2));
        $parsed = self::parseImageContent($urlContent);

        $node = new ImageNode($alt, $parsed['href'], $parsed['title'], $parsed['width'], $parsed['height']);
        return ['node' => $node, 'end' => $urlEnd + 1];
    }

    /**
     * Inline footnote: ^[text]
     * @return array{node: InlineNode, end: int}|null
     */
    private static function tryMatchInlineFootnote(string $text, int $pos, ParseContext $ctx): ?array {
        if (!isset($text[$pos+1]) || $text[$pos] !== '^' || $text[$pos + 1] !== '[') return null;

        $end = self::findClosingBracket($text, $pos + 2);
        if ($end === -1) return null;

        $content = substr($text, $pos + 2, $end - ($pos + 2));
        $ctx->inlineFootnoteCount++;
        $id = "__inline_" . $ctx->inlineFootnoteCount;
        $ctx->footnoteRefs[] = $id;
        
        $children = self::parseInline($content, $ctx);
        $ctx->footnoteDefs[$id] = $children;

        $node = new InlineFootnoteNode($children);
        return ['node' => $node, 'end' => $end + 1];
    }

    /**
     * Footnote reference: [^id]
     * @return array{node: InlineNode, end: int}|null
     */
    private static function tryMatchFootnoteRef(string $text, int $pos, ParseContext $ctx): ?array {
        if (!isset($text[$pos+1]) || $text[$pos] !== '[' || $text[$pos + 1] !== '^') return null;

        $end = strpos($text, ']', $pos + 2);
        if ($end === false) return null;
        
        $id = substr($text, $pos + 2, $end - ($pos + 2));
        if (str_contains($id, ' ')) return null;

        if (!in_array($id, $ctx->footnoteRefs, true)) {
            $ctx->footnoteRefs[] = $id;
        }

        $node = new FootnoteRefNode($id);
        return ['node' => $node, 'end' => $end + 1];
    }

    /**
     * Warp reference: [~id]
     * @return array{node: InlineNode, end: int}|null
     */
    private static function tryMatchWarpRef(string $text, int $pos): ?array {
        if (!isset($text[$pos+1]) || $text[$pos] !== '[' || $text[$pos + 1] !== '~') return null;

        $end = strpos($text, ']', $pos + 2);
        if ($end === false) return null;
        
        $id = substr($text, $pos + 2, $end - ($pos + 2));
        if ($id === '' || str_contains($id, ' ')) return null;

        $node = new WarpRefNode($id);
        return ['node' => $node, 'end' => $end + 1];
    }

    /**
     * Link or inline link starting with [
     * @return array{node: InlineNode, end: int}|null
     */
    private static function tryMatchBracketExpression(string $text, int $pos, ParseContext $ctx): ?array {
        if (!isset($text[$pos]) || $text[$pos] !== '[') return null;

        if (isset($text[$pos + 1])) {
            if ($text[$pos + 1] === '^') return self::tryMatchFootnoteRef($text, $pos, $ctx);
            if ($text[$pos + 1] === '~') return self::tryMatchWarpRef($text, $pos);
        }

        $textEnd = self::findClosingBracket($text, $pos + 1);
        if ($textEnd === -1) return null;

        $linkText = substr($text, $pos + 1, $textEnd - ($pos + 1));
        $afterBracket = $textEnd + 1;

        // Inline link: [text](url)
        if (isset($text[$afterBracket]) && $text[$afterBracket] === '(') {
            $urlEnd = self::findClosingParen($text, $afterBracket + 1);
            if ($urlEnd !== -1) {
                $urlContent = substr($text, $afterBracket + 1, $urlEnd - ($afterBracket + 1));
                $parsedUrl = self::parseLinkContent($urlContent);
                $node = new InlineLinkNode(
                    self::parseInline($linkText, $ctx),
                    $parsedUrl['href'],
                    $parsedUrl['title']
                );
                return ['node' => $node, 'end' => $urlEnd + 1];
            }
        }

        // Reference link: [text][id]
        if (isset($text[$afterBracket]) && $text[$afterBracket] === '[') {
            $idEnd = strpos($text, ']', $afterBracket + 1);
            if ($idEnd !== false) {
                $rawId = substr($text, $afterBracket + 1, $idEnd - ($afterBracket + 1));
                $id = strtolower(trim($rawId) !== '' ? trim($rawId) : trim($linkText));
                if (isset($ctx->linkDefs[$id])) {
                    $def = $ctx->linkDefs[$id];
                    $node = new LinkNode(
                        self::parseInline($linkText, $ctx),
                        $def->href,
                        $def->title
                    );
                    return ['node' => $node, 'end' => $idEnd + 1];
                }
            }
        }

        // Implicit shortcut: [text][]
        if (isset($text[$afterBracket]) && $text[$afterBracket] === '[' && isset($text[$afterBracket+1]) && $text[$afterBracket + 1] === ']') {
            $id = strtolower(trim($linkText));
            if (isset($ctx->linkDefs[$id])) {
                $def = $ctx->linkDefs[$id];
                $node = new LinkNode(
                    self::parseInline($linkText, $ctx),
                    $def->href,
                    $def->title
                );
                return ['node' => $node, 'end' => $afterBracket + 2];
            }
        }

        // Implicit link (no bracket after): [text] if id matches
        $implicitId = strtolower(trim($linkText));
        if (isset($ctx->linkDefs[$implicitId])) {
            $implicitDef = $ctx->linkDefs[$implicitId];
            $node = new LinkNode(
                self::parseInline($linkText, $ctx),
                $implicitDef->href,
                $implicitDef->title
            );
            return ['node' => $node, 'end' => $afterBracket];
        }

        return null;
    }

    /**
     * Strong: **text** or __text__
     * @return array{node: InlineNode, end: int}|null
     */
    private static function tryMatchStrong(string $text, int $pos, ParseContext $ctx): ?array {
        $ch = $text[$pos];
        if (($ch !== '*' && $ch !== '_') || !isset($text[$pos + 1]) || $text[$pos + 1] !== $ch) {
            return null;
        }
        // Don't consume *** (triple) - let the outer loop emit one literal char
        if (isset($text[$pos + 2]) && $text[$pos + 2] === $ch) {
            return null;
        }

        $delim = $ch . $ch;
        $contentStart = $pos + 2;

        $closeIdx = strpos($text, $delim, $contentStart);
        if ($closeIdx === false) return null;

        // Content cannot span newlines
        $content = substr($text, $contentStart, $closeIdx - $contentStart);
        if (str_contains($content, "\n")) return null;

        $node = new StrongNode(self::parseInline($content, $ctx));
        return ['node' => $node, 'end' => $closeIdx + 2];
    }

    /**
     * Emphasis: *text* or _text_
     * @return array{node: InlineNode, end: int}|null
     */
    private static function tryMatchEmphasis(string $text, int $pos, ParseContext $ctx): ?array {
        $ch = $text[$pos];
        if ($ch !== '*' && $ch !== '_') return null;
        // Must be single (not **)
        if (isset($text[$pos + 1]) && $text[$pos + 1] === $ch) return null;

        $contentStart = $pos + 1;
        $searchPos = $contentStart;
        $len = strlen($text);

        while ($searchPos < $len) {
            $closeIdx = strpos($text, $ch, $searchPos);
            if ($closeIdx === false) return null;

            // Skip double occurrences
            if (isset($text[$closeIdx + 1]) && $text[$closeIdx + 1] === $ch) {
                $searchPos = $closeIdx + 2;
                continue;
            }

            $content = substr($text, $contentStart, $closeIdx - $contentStart);
            if (str_contains($content, "\n")) return null;

            $node = new EmphasisNode(self::parseInline($content, $ctx));
            return ['node' => $node, 'end' => $closeIdx + 1];
        }
        return null;
    }

    /**
     * Strikethrough: ~~text~~
     * @return array{node: InlineNode, end: int}|null
     */
    private static function tryMatchStrikethrough(string $text, int $pos, ParseContext $ctx): ?array {
        if (!isset($text[$pos+1]) || $text[$pos] !== '~' || $text[$pos + 1] !== '~') return null;
        // Reject ~~~
        if (isset($text[$pos + 2]) && $text[$pos + 2] === '~') return null;

        $contentStart = $pos + 2;
        $closeIdx = strpos($text, '~~', $contentStart);
        if ($closeIdx === false) return null;

        $content = substr($text, $contentStart, $closeIdx - $contentStart);
        if (str_contains($content, "\n")) return null;

        $node = new StrikethroughNode(self::parseInline($content, $ctx));
        return ['node' => $node, 'end' => $closeIdx + 2];
    }

    // ============================================================
    // Main inline parser
    // ============================================================

    /**
     * @return InlineNode[]
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
            $result = null;

            if ($ch === '`') {
                $result = self::tryMatchCodeSpan($text, $pos);
            } elseif ($ch === '!' && isset($text[$pos + 1]) && $text[$pos + 1] === '[') {
                $result = self::tryMatchImage($text, $pos, $ctx);
            } elseif ($ch === '^' && isset($text[$pos + 1]) && $text[$pos + 1] === '[') {
                $result = self::tryMatchInlineFootnote($text, $pos, $ctx);
            } elseif ($ch === '[') {
                $result = self::tryMatchBracketExpression($text, $pos, $ctx);
            } elseif (
                ($ch === '*' || $ch === '_') &&
                isset($text[$pos + 1]) && $text[$pos + 1] === $ch &&
                (!isset($text[$pos + 2]) || $text[$pos + 2] !== $ch)
            ) {
                // Potential strong (**/**)
                $result = self::tryMatchStrong($text, $pos, $ctx);
            } elseif (
                ($ch === '*' || $ch === '_') &&
                (!isset($text[$pos + 1]) || $text[$pos + 1] !== $ch)
            ) {
                // Potential emphasis
                $result = self::tryMatchEmphasis($text, $pos, $ctx);
            } elseif ($ch === '~' && isset($text[$pos + 1]) && $text[$pos + 1] === '~' && (!isset($text[$pos + 2]) || $text[$pos + 2] !== '~')) {
                $result = self::tryMatchStrikethrough($text, $pos, $ctx);
            } elseif ($ch === "\n") {
                $flushText();
                $nodes[] = new LineBreakNode();
                $pos++;
                $textStart = $pos;
                continue;
            }
            // Triple star/underscore: emit one literal char and let the next iteration handle **
            elseif (
                ($ch === '*' || $ch === '_') &&
                isset($text[$pos + 1]) && $text[$pos + 1] === $ch &&
                isset($text[$pos + 2]) && $text[$pos + 2] === $ch
            ) {
                $pos++;
                continue;
            }

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
}
