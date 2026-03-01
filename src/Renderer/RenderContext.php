<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Renderer;

use Kassyi\LobsterPhp\Core\{InlineNode, WarpDefinitionNode};

class RenderContext {
    /**
     * @param string[] $footnoteRefs
     * @param array<string, int> $footnoteRefCount
     * @param array<string, InlineNode[]> $footnoteDefs
     * @param array<string, WarpDefinitionNode> $warpDefs
     */
    public function __construct(
        public array $footnoteRefs,
        public array $footnoteRefCount,
        public array $footnoteDefs,
        public array $warpDefs
    ) {}
}
