<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Renderer;

/**

 * RenderedParts

 */

class RenderedParts {
    /**
     * @param string $header
     * @param string $body
     * @param string $footer
     * @param string $footnotes
     * @param array<string, string> $warps
     * @param string $full
     */
    /**
     * __construct
     */
    public function __construct(
        public readonly string $header,
        public readonly string $body,
        public readonly string $footer,
        public readonly string $footnotes,
        public readonly array $warps,
        public readonly string $full
    ) {}
}

