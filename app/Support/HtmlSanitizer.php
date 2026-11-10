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

    /** The only classes rich text may carry: paragraph alignment chosen by a writer or editor. */
    public const CLASSES = ['ta-left', 'ta-center', 'ta-right'];

    /** For loading stored HTML into an editor: ta-* classes back to the text-align style editors read. */
    public static function alignmentForEditor(?string $html): string
    {
        return preg_replace('~\sclass="ta-(left|center|right)"~', ' style="text-align: $1"', (string) $html) ?? (string) $html;
    }

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Raw-text elements: remove with their contents first, so CSS/JS never leaks out as visible text.
        // (Byte-wise regex without /u, and never fall back to '': a regex failure must not blank a post.)
        $html = preg_replace('~<(script|style|noscript|template|textarea)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? $html;

        // Alignment from an editor (style="text-align:…") becomes a fixed class; every other style is dropped.
        $html = preg_replace_callback('~<(p|h[2-4])\b([^>]*)>~i', function (array $m) {
            if (! preg_match('~text-align:\s*(left|start|center|right|end)~i', $m[2], $a)) {
                return $m[0];
            }
            $align = ['start' => 'left', 'end' => 'right'][strtolower($a[1])] ?? strtolower($a[1]);

            return '<'.$m[1].' class="ta-'.$align.'">';
        }, $html) ?? $html;

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
                ->allowElement('p', ['class'])->allowElement('br')->allowElement('em')->allowElement('i')
                ->allowElement('strong')->allowElement('b')->allowElement('blockquote')
                ->allowElement('h2', ['class'])->allowElement('h3', ['class'])->allowElement('h4', ['class'])
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
        // class survives only as one of the alignment classes (D40); anything else is removed.
        $clean = preg_replace_callback('~\sclass="([^"]*)"~', function (array $m) {
            $keep = array_intersect(preg_split('/\s+/', $m[1]), self::CLASSES);

            return $keep ? ' class="'.implode(' ', $keep).'"' : '';
        }, $clean) ?? $clean;

        return preg_replace('~<p>(?:\s|&nbsp;|\xC2\xA0)*+</p>~', '', $clean) ?? $clean;
    }
}
