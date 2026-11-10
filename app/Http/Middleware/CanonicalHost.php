<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One address per page (docs/SEO.md §1; found on production 2026-11-10). On the live site every page is served only
 * at https://hatpakha.com/…: "www.", the hosting folders "/site/…" and "/new/…" (which otherwise showed the whole site
 * again) are sent there with a permanent redirect. Staging asked under hatpakha.com (the /new/ folder) sends the visitor to
 * the real site. Local development is left alone.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $home = (string) config('app.url');
        $host = mb_strtolower((string) parse_url($home, PHP_URL_HOST));
        $asked = mb_strtolower($request->getHost());
        $live = \App\Support\RelaunchLetters::PRODUCTION_HOSTS;
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }
        $qs = $request->getQueryString();
        $rest = $request->getPathInfo().($qs ? '?'.$qs : '');
        // The live site, asked under another name or through a hosting folder.
        if (in_array($host, $live, true) && ($asked !== $host || $request->getBaseUrl() !== '')) {
            return redirect()->to(rtrim($home, '/').$rest, 301);
        }
        // Staging reached through the main domain (hatpakha.com/new/… points at staging's folder): send the visitor
        // to the same page on the real site; staging is only ever new.hatpakha.com.
        if (! in_array($host, $live, true) && in_array($asked, $live, true)) {
            return redirect()->to('https://hatpakha.com'.$rest, 301);
        }

        return $next($request);
    }
}
