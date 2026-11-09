<?php

namespace App\Support;

use App\Ai\Copywriter;
use App\Models\Newsletter;

/**
 * D117: links in a sent letter go through /chithi/{letter}/{delivery}/{link}/{sig}/ so /desk can show how many
 * readers clicked and which piece. The target is looked up from the letter itself (never taken from the URL),
 * so the link can't be turned into an open redirect; the short signature stops guessing other readers' links.
 */
final class NewsletterLinks
{
    public static function sig(int $newsletter, int $delivery, string $link): string
    {
        return substr(hash_hmac('sha256', "{$newsletter}|{$delivery}|{$link}", (string) config('app.key')), 0, 16);
    }

    public static function tracked(Newsletter $n, ?int $delivery, string $link): string
    {
        if (! $delivery) {
            return self::target($n, $link);   // previews and test mails: the plain link
        }

        return Url::to("/chithi/{$n->id}/{$delivery}/{$link}/".self::sig($n->id, $delivery, $link));
    }

    /** Where a link goes; anything off this site falls back to the home page. */
    public static function target(Newsletter $n, string $link): string
    {
        $home = url('/').'/';
        $url = match (true) {
            $link === 'cta' => Copywriter::ctaUrl($n),
            ctype_digit($link) => (string) (((array) $n->items)[(int) $link]['url'] ?? $home),
            default => $home,
        };

        $url = parse_url($url, PHP_URL_HOST) === parse_url($home, PHP_URL_HOST) ? $url : $home;

        // D119: Google Analytics then counts these visits as coming from the letter.
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['utm_source' => 'hatpakha-chithi', 'utm_medium' => 'email', 'utm_campaign' => 'chithi-'.$n->id]);
    }

    /** @return array{clickers: int, links: array<string, int>} people who clicked anything; clicks per link (people) */
    public static function stats(Newsletter $n): array
    {
        $q = \Illuminate\Support\Facades\DB::table('newsletter_clicks')->where('newsletter_id', $n->id);

        return [
            'clickers' => (clone $q)->distinct()->count('delivery_id'),
            'links' => (clone $q)->selectRaw('link, COUNT(DISTINCT delivery_id) as people')->groupBy('link')->pluck('people', 'link')->map(fn ($v) => (int) $v)->all(),
        ];
    }
}
