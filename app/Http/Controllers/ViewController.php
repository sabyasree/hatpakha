<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Read counter beacon (D54). Sent by the reader's browser after the page shows, so most bots never count.
 * Stores only a daily total per piece, the kind of source (search / social / our own site) and the place (country,
 * and city when Cloudflare sends it). Nothing about
 * the reader is kept: no cookie, no IP address, no account.
 */
class ViewController extends Controller
{
    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse/i';

    public function __invoke(Request $request, int $post): Response
    {
        $ua = (string) $request->userAgent();
        // The rate-limit key is a one-way hash kept in the cache for an hour only (no IP is stored).
        if ($ua === '' || preg_match(self::BOTS, $ua)
            || ! RateLimiter::attempt('view:'.hash('sha256', $request->ip().'|'.$post), 1, fn () => true, 1800)) {
            return response()->noContent();
        }
        if (! Post::published()->whereKey($post)->exists()) {
            return response()->noContent();
        }
        $src = in_array($request->input('s'), ['search', 'social', 'internal'], true) ? $request->input('s') : null;
        $day = now('Asia/Kolkata')->toDateString();
        DB::table('post_views')->upsert(
            ['post_id' => $post, 'day' => $day, 'views' => 1, 'search' => (int) ($src === 'search'), 'social' => (int) ($src === 'social'), 'internal' => (int) ($src === 'internal')],
            ['post_id', 'day'],
            ['views' => DB::raw('views + 1'), 'search' => DB::raw('search + '.(int) ($src === 'search')), 'social' => DB::raw('social + '.(int) ($src === 'social')), 'internal' => DB::raw('internal + '.(int) ($src === 'internal'))],
        );

        // Where (writers' numbers, 2026-11-12): the country Cloudflare reports, and the city when its "visitor location"
        // headers are on. Only a total per piece and place; nothing about the reader.
        $cc = strtoupper((string) $request->header('CF-IPCountry'));
        if (preg_match('/^[A-Z]{2}$/', $cc) && $cc !== 'XX' && $cc !== 'T1') {
            $city = mb_substr(trim(preg_replace('/[^\p{L}\p{M}\s.\-]/u', '', (string) $request->header('CF-IPCity'))), 0, 60);
            DB::table('post_view_places')->upsert(['post_id' => $post, 'place' => $city.'|'.$cc, 'views' => 1], ['post_id', 'place'], ['views' => DB::raw('views + 1')]);
        }

        return response()->noContent();
    }
}
