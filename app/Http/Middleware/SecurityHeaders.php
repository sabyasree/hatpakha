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
            : "script-src 'self' 'nonce-{$nonce}' https://pagead2.googlesyndication.com https://*.googlesyndication.com https://*.adtrafficquality.google https://fundingchoicesmessages.google.com https://challenges.cloudflare.com https://www.googletagmanager.com";   // D111: AdSense + its consent message

        $csp = implode('; ', [
            "default-src 'self'",
            $script,
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            "img-src 'self' data: https:",
            "connect-src 'self' https://*.google.com https://*.googlesyndication.com https://*.doubleclick.net https://*.adtrafficquality.google https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com",   // D119: GA4
            'frame-src https://googleads.g.doubleclick.net https://*.doubleclick.net https://*.googlesyndication.com https://*.google.com https://*.adtrafficquality.google https://challenges.cloudflare.com https://www.youtube-nocookie.com',
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
        // D63: pages seen by editors carry the "edit in desk" button: never let any cache keep them.
        if (! $isAdmin && $request->user()?->hasRole(\App\Enums\Role::Editor)) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        if (! config('seo.indexable')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
