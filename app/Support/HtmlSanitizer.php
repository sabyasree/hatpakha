<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The ONLY way rich text enters the database (docs/SECURITY.md §4).
 * Allowlist: basic text formatting, links and images. Everything else is stripped.
 */
final class HtmlSanitizer
{
    private static ?SymfonySanitizer $instance = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        self::$instance ??= new SymfonySanitizer(
            (new HtmlSanitizerConfig())
                ->allowElement('p')->allowElement('br')->allowElement('em')->allowElement('i')
                ->allowElement('strong')->allowElement('b')->allowElement('blockquote')
                ->allowElement('h2')->allowElement('h3')->allowElement('h4')
                ->allowElement('ul')->allowElement('ol')->allowElement('li')->allowElement('hr')
                ->allowElement('figure')->allowElement('figcaption')
                ->allowElement('a', ['href', 'title'])
                ->allowElement('img', ['src', 'alt', 'width', 'height'])
                ->allowLinkSchemes(['https', 'http', 'mailto'])
                ->allowMediaSchemes(['https', 'http'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener nofollow ugc')
                ->withMaxInputLength(2_000_000)
        );

        return self::$instance->sanitize($html);
    }
}
