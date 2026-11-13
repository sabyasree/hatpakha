<?php

namespace App\Support;

/**
 * D111 (owner): where AdSense ads may show. Manual placements only (no Auto ads), never on the first screen, never
 * inside a poem (D167: one ad near the middle of a long story or article), at most 3 per phone page; never on staging, children's pages, forms, account, legal or
 * error pages, or a piece the editor marked "no ads". A page type opts in with Ads::allow(); each spot shows only
 * once its slot id from AdSense is set (ADSENSE_SLOT_* in the environment), so the owner decides what goes live.
 */
final class Ads
{
    /** D167: a long story or article gets one ad near its middle (owner): only above this much text (about 8 phone screens). */
    public const MID_MIN_TEXT = 9000;

    /** ...and never before this much text has been read (about 3 phone screens). */
    public const MID_MIN_BEFORE = 3000;

    /** Hatpakha's AdSense publisher id (public: it is in every page and in ads.txt). */
    public const PUBLISHER = 'pub-4627594058272769';

    public const SPOTS = ['home', 'home_2', 'article', 'article_2', 'article_mid', 'sidebar', 'list'];

    public static function client(): string
    {
        return (string) (config('services.adsense.client') ?: 'ca-'.self::PUBLISHER);
    }

    /** Real ads only on the live site (staging and laptops never show them), unless forced for a test. */
    public static function liveSite(): bool
    {
        return (bool) config('services.adsense.force')
            || in_array(mb_strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)), RelaunchLetters::PRODUCTION_HOSTS, true);
    }

    /** A controller says "this page type may carry ads". */
    public static function allow(): void
    {
        request()->attributes->set('hp.ads', true);
    }

    public static function on(): bool
    {
        return self::liveSite() && request()->attributes->get('hp.ads') && ! request()->attributes->get('hp.no_ads');
    }

    /**
     * D167: where to put the one mid-story ad. Returns [before, after] cut at the end of a top-level paragraph near the
     * middle of the text, or null (poem, children's piece, too short, no good paragraph break). The piece's HTML is only
     * cut, never re-written, so the two halves join back to exactly the original.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function splitForMid(string $html, \App\Models\Post $post): ?array
    {
        if (! self::on() || ! self::slot('article_mid') || $post->isPoem() || $post->isKids()) {
            return null;
        }
        $total = self::textLength($html);
        if ($total < self::MID_MIN_TEXT) {
            return null;
        }
        preg_match_all('#<(/?)([a-zA-Z][a-zA-Z0-9]*)\b[^>]*?(/?)>#', $html, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $void = ['br', 'hr', 'img', 'wbr', 'source', 'col', 'embed', 'input', 'meta', 'link', 'track', 'area', 'param'];
        $depth = 0;
        $best = null;
        foreach ($tags as $t) {
            $name = strtolower($t[2][0]);
            if (in_array($name, $void, true) || $t[3][0] === '/') {
                continue;
            }
            if ($t[1][0] === '') {
                $depth++;

                continue;
            }
            $depth = max(0, $depth - 1);
            if ($depth !== 0 || $name !== 'p') {
                continue;   // only after a whole top-level paragraph
            }
            $cut = $t[0][1] + strlen($t[0][0]);
            $before = self::textLength(substr($html, 0, $cut));
            if ($before < self::MID_MIN_BEFORE || $before < $total * 0.35 || $total - $before < $total * 0.25) {
                continue;
            }
            $distance = abs($before - $total / 2);
            if ($best === null || $distance < $best[1]) {
                $best = [$cut, $distance];
            }
        }

        return $best ? [substr($html, 0, $best[0]), substr($html, $best[0])] : null;
    }

    private static function textLength(string $html): int
    {
        return mb_strlen(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    }

    public static function slot(string $spot): ?string
    {
        $id = config('services.adsense.slots.'.$spot);

        return filled($id) ? (string) $id : null;
    }
}
