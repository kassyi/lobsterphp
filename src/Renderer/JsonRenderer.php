<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Renderer;

use Kassyi\LobsterPhp\Core\{BlockParser, Document};
use Kassyi\LobsterPhp\Renderer\RenderContext;

/**
 * Facade for JSON rendering.
 *
 * Converts a Markdown document into a JSON-serializable array structure
 * that can be consumed by SPAs (Vue, React, Inertia.js) or
 * headless CMS pipelines.
 *
 * Usage:
 *   $json = JsonRenderer::render($markdown);  // returns JSON string
 *   $array = JsonRenderer::renderDocument(BlockParser::parseDocument($md));
 */
class JsonRenderer {
    public static function render(string $markdown, int $flags = JSON_UNESCAPED_UNICODE): string {
        $doc = BlockParser::parseDocument($markdown);
        return json_encode(self::renderDocument($doc), $flags | JSON_THROW_ON_ERROR);
    }

    public static function renderDocument(Document $doc): array {
        $ctx = new RenderContext(
            $doc->footnoteRefs,
            [], // footnoteRefCount (unused by JSON visitor)
            $doc->footnoteDefs,
            $doc->warpDefs,
        );

        $visitor = new JsonRendererVisitor($ctx);

        $body = $visitor->renderNodes($doc->body);

        $header = $doc->header !== null ? $doc->header->accept($visitor) : null;
        $footer = $doc->footer !== null ? $doc->footer->accept($visitor) : null;

        // Warps as id=>array map
        $warps = [];
        foreach ($doc->warpDefs as $id => $warpDef) {
            $warps[$id] = $visitor->renderNodes($warpDef->children);
        }

        // Footnotes
        $footnotes = [];
        foreach ($ctx->footnoteRefs as $i => $id) {
            $defNodes = $ctx->footnoteDefs[$id] ?? null;
            $footnotes[] = [
                'id'       => $id,
                'num'      => $i + 1,
                'children' => $defNodes !== null ? $visitor->renderNodes($defNodes) : [],
            ];
        }

        return array_filter([
            'header'    => $header,
            'body'      => $body,
            'footer'    => $footer,
            'warps'     => !empty($warps) ? $warps : null,
            'footnotes' => !empty($footnotes) ? $footnotes : null,
        ], fn($v) => $v !== null);
    }
}
