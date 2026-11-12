<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * All public HTML URLs end with "/" exactly as on WordPress (docs/SEO.md §1).
 * A GET without the slash is 301-redirected. Files (.xml, .txt, ...) and the admin are exempt.
 */
class EnforceTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        $qs = $request->getQueryString();

        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            // Owner (2026-11-12, Search Console 404s): "//tag/…/" from badly built links → the one-slash address.
            if (str_contains($path, '//')) {
                return redirect()->to($request->getSchemeAndHttpHost().$request->getBaseUrl().preg_replace('#/{2,}#', '/', $path).($qs ? '?'.$qs : ''), 301);
            }
            // Old share links "/page/34/=1&fbclid=…" → the clean list page.
            if (preg_match('#^(.*/page/\d+)/=[^/]*/?$#', $path, $m)) {
                return redirect()->to($request->getSchemeAndHttpHost().$request->getBaseUrl().$m[1].'/', 301);
            }
        }

        if (in_array($request->method(), ['GET', 'HEAD'], true)
            && $path !== '/'
            && ! str_ends_with($path, '/')
            && ! preg_match('/\.[a-z0-9]{2,5}$/i', $path)
            && ! $request->is('desk', 'desk/*', 'livewire*', 'filament/*', 'up', 'storage/*', 'build/*', 'wp-json*', 'wp-admin*', 'wp-includes*', 'wp-content/*', 'email/verify/*', 'reset-password/*', 'desk-language/*', 'newsletter/unsubscribe/*', 'newsletter/confirm/*', 'lekha-uttor/*', 'csrf-token')) {
            return redirect()->to($request->getSchemeAndHttpHost().$request->getBaseUrl().$path.'/'.($qs ? '?'.$qs : ''), 301);
        }

        return $next($request);
    }
}
