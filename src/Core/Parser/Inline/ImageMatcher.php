<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser\Inline;

use Kassyi\LobsterPhp\Core\{ImageNode, InlineNode, InlineParser, ParseContext};
use Kassyi\LobsterPhp\Core\Parser\InlineMatcherInterface;

class ImageMatcher implements InlineMatcherInterface {
    /**
     * インライン要素のパースを試みる
     *
     * @param string $text パース対象のテキスト
     * @param int $pos 現在確認中の開始位置（インデックス）
     * @param ParseContext $ctx パースコンテキスト（リンク定義や脚注情報）
     * @return array{node: \Kassyi\LobsterPhp\Core\InlineNode, end: int}|null マッチ成功時は生成されたノードとパース終了位置、失敗時はnull
     */
    public function tryMatch(string $text, int $pos, ParseContext $ctx): ?array {
        if (!isset($text[$pos+1]) || $text[$pos] !== '!' || $text[$pos + 1] !== '[') return null;

        $altEnd = InlineParser::findClosingBracket($text, $pos + 2);
        if ($altEnd === -1) return null;

        if (!isset($text[$altEnd + 1]) || $text[$altEnd + 1] !== '(') return null;

        $urlEnd = InlineParser::findClosingParen($text, $altEnd + 2);
        if ($urlEnd === -1) return null;

        $alt = substr($text, $pos + 2, $altEnd - ($pos + 2));
        $urlContent = substr($text, $altEnd + 2, $urlEnd - ($altEnd + 2));
        $parsed = InlineParser::parseImageContent($urlContent);

        $node = new ImageNode($alt, $parsed['href'], $parsed['title'], $parsed['width'], $parsed['height']);
        return ['node' => $node, 'end' => $urlEnd + 1];
    }
}
