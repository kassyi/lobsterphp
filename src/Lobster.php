<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp;

use Kassyi\LobsterPhp\Core\{BlockParser, Document};
use Kassyi\LobsterPhp\Renderer\{HtmlRenderer, RenderedParts};

/**

 * Lobster

 */

class Lobster {
    /**
     * Parses a Markdown string and returns the rendered HTML string.
     */
    /**
     * toHtml
     */
    public static function toHtml(string $markdown): string {
        $doc = BlockParser::parseDocument($markdown);
        return HtmlRenderer::renderDocument($doc);
    }

    /**
     * Parses a Markdown string and returns the rendered parts as an object.
     * Use this for framework integrations (e.g. Laravel Blade).
     */
    /**
     * toHtmlParts
     */
    public static function toHtmlParts(string $markdown): RenderedParts {
        $doc = BlockParser::parseDocument($markdown);
        return HtmlRenderer::renderDocumentParts($doc);
    }

    /**
     * Parses a Markdown string into an AST Document.
     */
    /**
     * parseDocument
     */
    public static function parseDocument(string $markdown): Document {
        return BlockParser::parseDocument($markdown);
    }
}

