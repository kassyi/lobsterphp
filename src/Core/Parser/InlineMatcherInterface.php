<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser;

use Kassyi\LobsterPhp\Core\{InlineNode, ParseContext};

interface InlineMatcherInterface {
    /**
     * @return array{node: InlineNode, end: int}|null
     */
    public function tryMatch(string $text, int $pos, ParseContext $ctx): ?array;
}
