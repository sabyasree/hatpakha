<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** "হাতপাখা ডেস্ক" — editors and admins only, 2FA mandatory (docs/SECURITY.md §2–3). */
class AdminPanelProvider extends PanelProvider
{
    /**
     * 2FA methods (docs/SECURITY.md §2, D20). Email codes are offered only when the site can
     * really send email: with MAIL_MAILER=log (staging) nobody would receive the code.
     */
    public static function twoFactorProviders(?string $mailer = null): array
    {
        $providers = [AppAuthentication::make()->recoverable()];

        if (! in_array($mailer ?? config('mail.default'), ['log', null, ''], true)) {
            $providers[] = EmailAuthentication::make()->codeExpiryMinutes(5);
        }

        return $providers;
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('desk')
            ->path('desk')
            ->login()
            ->passwordReset()
            // Everyone can change their name, password and 2FA (app / recovery codes) here.
            ->profile(isSimple: false)
            ->navigationGroups([
                // Editors' daily work is the top three items; everything else is folded away.
                NavigationGroup::make('আরও')->collapsed(),
                NavigationGroup::make('প্রশাসন')->collapsed(),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications(false)
            ->multiFactorAuthentication(self::twoFactorProviders(), isRequired: true)
            ->brandName('হাতপাখা ডেস্ক')
            ->brandLogo(asset('images/logo-wordmark.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/logo.png'))
            ->colors([
                'primary' => Color::hex('#CB1B32'),
                'info' => Color::hex('#2DAAE2'),
                'warning' => Color::hex('#E39A6A'),
                // Logo palette only (docs/DESIGN.md): no stock green/red in the desk either.
                'success' => Color::hex('#1A86C2'),
                'danger' => Color::hex('#B0162B'),
            ])
            ->font('Anek Bangla', url: asset('fonts/fonts.css').'?v=1', provider: LocalFontProvider::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([\App\Http\Middleware\DeskLocale::class], isPersistent: true)
            ->authMiddleware([Authenticate::class])
            ->loginRouteSlug('login')
            ->authGuard('web');
    }
}
