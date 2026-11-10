<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One address per page (docs/SEO.md §1; found on production 2026-11-10). On the live site every page is served only
 * at https://hatpakha.com/…: "www.", the hosting folders "/site/…" and "/new/…" (which otherwise showed the whole site
 * again) are sent there with a permanent redirect. Staging and local development are left alone.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $home = (string) config('app.url');
        $host = mb_strtolower((string) parse_url($home, PHP_URL_HOST));
        if (in_array($host, \App\Support\RelaunchLetters::PRODUCTION_HOSTS, true) && in_array($request->method(), ['GET', 'HEAD'], true)
            && (mb_strtolower($request->getHost()) !== $host || $request->getBaseUrl() !== '')) {
            $qs = $request->getQueryString();

            return redirect()->to(rtrim($home, '/').$request->getPathInfo().($qs ? '?'.$qs : ''), 301);
        }

        return $next($request);
    }
}
