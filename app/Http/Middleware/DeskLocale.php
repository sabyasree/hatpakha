<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** /desk speaks Bengali: Filament's own buttons, filters and dates use its bundled `bn` strings. */
class DeskLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('bn');
        \Carbon\Carbon::setLocale('bn');
        // Dates in /desk are shown and picked in India time (stored as UTC).
        \Filament\Support\Facades\FilamentTimezone::set('Asia/Kolkata');

        return $next($request);
    }
}
