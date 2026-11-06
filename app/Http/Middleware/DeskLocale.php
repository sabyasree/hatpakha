<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** /desk speaks Bengali (default) or English, per user (D45). Filament's own strings follow the locale. */
class DeskLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Each desk user's choice (D45); Bengali by default.
        $locale = in_array($request->user()?->desk_locale, ['bn', 'en'], true) ? $request->user()->desk_locale : 'bn';
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale);
        \App\Support\Bn::$latin = $locale === 'en';
        // Dates in /desk are shown and picked in India time (stored as UTC).
        \Filament\Support\Facades\FilamentTimezone::set('Asia/Kolkata');

        return $next($request);
    }
}
