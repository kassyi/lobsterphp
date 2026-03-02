<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core\Parser;

use Kassyi\LobsterPhp\Core\{BlockNode, ParseContext};

/**

 * BlockMatcherInterface

 */

interface BlockMatcherInterface {
    /**
     * @param string[] $lines
     * @return array{node: BlockNode, nextIndex: int}|null
     */
    /**
     * tryMatch
     */
    public function tryMatch(array $lines, int $i, ParseContext $ctx): ?array;
}

