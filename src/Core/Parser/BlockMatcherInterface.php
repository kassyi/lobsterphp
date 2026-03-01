<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser;

use Kassyi\LobsterPhp\Core\{BlockNode, ParseContext};

interface BlockMatcherInterface {
    /**
     * @param string[] $lines
     * @return array{node: BlockNode, nextIndex: int}|null
     */
    public function tryMatch(array $lines, int $i, ParseContext $ctx): ?array;
}
