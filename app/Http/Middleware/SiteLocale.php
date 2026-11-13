<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * D169 (owner): the sign-up, sign-in and account pages and the menus come in বাংলা or English, each person's choice.
 * A signed-in person's choice is on their account (`site_locale`); before signing in it is the `hp_lang` cookie.
 * Guests' public pages stay বাংলা: they are cached and shared (D65), so they must look the same for everyone. Writing
 * itself (pieces, sections' names, pages) is never translated. /desk has its own switch (D45).
 */
class SiteLocale
{
    public const COOKIE = 'hp_lang';

    /** Pages that are never shared from a cache, so they can follow the visitor's choice. */
    public const OWN_PAGES = ['account*', 'login*', 'register*', 'forgot-password*', 'reset-password*', 'email/*', 'language/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('desk', 'desk/*', 'livewire*', 'filament/*')) {
            return $next($request);   // DeskLocale decides there
        }
        $locale = 'bn';
        if ($request->user() || $request->is(...self::OWN_PAGES)) {
            $locale = self::chosen($request);
        }
        app()->setLocale($locale);
        \App\Support\Bn::$latin = $locale === 'en';

        return $next($request);
    }

    public static function chosen(Request $request): string
    {
        $pick = $request->user()?->site_locale ?? $request->cookies->get(self::COOKIE);

        return $pick === 'en' ? 'en' : 'bn';
    }

    public static function isEnglish(): bool
    {
        return app()->getLocale() === 'en';
    }
}
