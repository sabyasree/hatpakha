<?php

namespace App\Support;

/**
 * D111 (owner): where AdSense ads may show. Manual placements only (no Auto ads), never on the first screen, never
 * inside a poem or story, at most 3 per phone page; never on staging, children's pages, forms, account, legal or
 * error pages, or a piece the editor marked "no ads". A page type opts in with Ads::allow(); each spot shows only
 * once its slot id from AdSense is set (ADSENSE_SLOT_* in the environment), so the owner decides what goes live.
 */
final class Ads
{
    /** Hatpakha's AdSense publisher id (public: it is in every page and in ads.txt). */
    public const PUBLISHER = 'pub-4627594058272769';

    public const SPOTS = ['home', 'home_2', 'article', 'article_2', 'sidebar', 'list'];

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

    public static function slot(string $spot): ?string
    {
        $id = config('services.adsense.slots.'.$spot);

        return filled($id) ? (string) $id : null;
    }
}
