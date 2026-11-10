<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Read counter beacon (D54). Sent by the reader's browser after the page shows, so most bots never count.
 * Stores only a daily total per piece and the kind of source (search / social / our own site). Nothing about
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

        return response()->noContent();
    }
}
