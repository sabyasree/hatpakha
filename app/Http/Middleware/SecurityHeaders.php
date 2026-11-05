<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/** Security headers on every response (docs/SECURITY.md §1). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $isAdmin = $request->is('desk', 'desk/*', 'livewire*', 'filament/*');

        // The admin panel (Livewire/Alpine) needs eval; the public site gets the strict, nonce-based policy.
        $script = $isAdmin
            ? "script-src 'self' 'unsafe-inline' 'unsafe-eval'"
            : "script-src 'self' 'nonce-{$nonce}' https://pagead2.googlesyndication.com https://*.googlesyndication.com https://challenges.cloudflare.com https://www.googletagmanager.com";

        $csp = implode('; ', [
            "default-src 'self'",
            $script,
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self' https://*.google.com https://*.googlesyndication.com",
            'frame-src https://googleads.g.doubleclick.net https://*.googlesyndication.com https://challenges.cloudflare.com https://www.youtube-nocookie.com',
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]);

        // Don't advertise the PHP version (docs/SECURITY.md §1).
        $response->headers->remove('X-Powered-By');
        if (! app()->runningUnitTests() && function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        if (! config('seo.indexable')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
