<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
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

        // Raw-text elements: remove with their contents first, so CSS/JS never leaks out as visible text.
        // (Byte-wise regex without /u, and never fall back to '': a regex failure must not blank a post.)
        $html = preg_replace('~<(script|style|noscript|template|textarea)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? $html;

        self::$instance ??= new SymfonySanitizer(
            (new HtmlSanitizerConfig())
                // Unknown wrappers (span, div, font, o:p from Word/Mac pastes…) are unwrapped and their
                // TEXT IS KEPT. Dropping them would silently delete poems (346 imported posts were affected).
                ->defaultAction(HtmlSanitizerAction::Block)
                // …but active or invisible content is removed together with everything inside it.
                ->dropElement('script')->dropElement('style')->dropElement('noscript')->dropElement('template')
                ->dropElement('iframe')->dropElement('object')->dropElement('embed')->dropElement('applet')
                ->dropElement('svg')->dropElement('math')->dropElement('form')->dropElement('input')
                ->dropElement('button')->dropElement('select')->dropElement('textarea')->dropElement('option')
                ->dropElement('head')->dropElement('title')->dropElement('meta')->dropElement('link')
                ->dropElement('base')->dropElement('frame')->dropElement('frameset')
                ->allowElement('u')->allowElement('s')->allowElement('del')->allowElement('sup')->allowElement('sub')
                ->allowElement('small')->allowElement('pre')->allowElement('code')
                ->allowElement('table')->allowElement('thead')->allowElement('tbody')->allowElement('tr')
                ->allowElement('th')->allowElement('td')
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

        // Removed elements (forms, inputs…) can leave empty paragraphs behind; parsers differ by PHP version.
        $clean = self::$instance->sanitize($html);

        return preg_replace('~<p>(?:\s|&nbsp;|\xC2\xA0)*+</p>~', '', $clean) ?? $clean;
    }
}
