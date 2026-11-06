<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fast pages for everyone who isn't signed in (D65). A guest's first visit (no session cookie) to a public
 * page gets no cookies and a short shared-cache lifetime, so Cloudflare can serve the same page from near
 * the reader. Forms on these pages fetch their security token only when used (/csrf-token, never cached).
 * Signed-in readers, writers and editors carry a session cookie, so their pages are never shared.
 * Runs outermost, after the session middleware has added its cookies.
 */
class GuestPageCache
{
    /** Never shared: the desk, accounts, sign-in, writing, search, newsletter links, chat, beacons. */
    private const PRIVATE = ['desk', 'desk/*', 'livewire*', 'filament/*', 'account*', 'login*', 'logout', 'register*', 'forgot-password*',
        'reset-password*', 'email/*', 'submit*', 'search*', 'newsletter/*', 'csrf-token', 'chat', 'v/*', 'like/*', 'save/*', 'follow/*', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200 || $request->is(...self::PRIVATE)
            || $request->cookies->has(config('session.cookie')) || $request->user()
            || str_contains((string) $response->headers->get('Cache-Control'), 'no-store')) {
            return $response;
        }
        foreach ($response->headers->getCookies() as $c) {
            $response->headers->removeCookie($c->getName(), $c->getPath(), $c->getDomain());
        }
        // Browsers re-check every time; Cloudflare may keep it 10 minutes (home and lists 5).
        $edge = $request->is('/', 'category/*', 'tag/*', 'archive*') ? 300 : 600;
        $response->headers->set('Cache-Control', "public, max-age=0, s-maxage={$edge}, stale-while-revalidate=60");
        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }
}
