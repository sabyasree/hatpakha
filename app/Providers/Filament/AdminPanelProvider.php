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
            // বাংলা / English switch for each desk user (D45).
            ->userMenuItems([
                \Filament\Actions\Action::make('deskLanguage')
                    ->label(fn () => app()->getLocale() === 'en' ? 'বাংলা' : 'English')
                    ->icon('heroicon-o-language')
                    ->url(fn () => route('desk.language', app()->getLocale() === 'en' ? 'bn' : 'en')),
            ])
            ->navigationGroups([
                // D279 (owner: "I should not spend time finding where things are"; three UX reviews): five groups in the
                // order of the work. Daily decisions first; records and settings folded away. Red numbers wait for you.
                NavigationGroup::make('আপনার সিদ্ধান্ত')->label(fn () => __('আপনার সিদ্ধান্ত')),
                NavigationGroup::make('সংখ্যা ও প্রকাশ')->label(fn () => __('সংখ্যা ও প্রকাশ')),
                NavigationGroup::make('চিঠি ও প্রচার')->label(fn () => __('চিঠি ও প্রচার')),
                NavigationGroup::make('হিসাব ও খাতা')->label(fn () => __('হিসাব ও খাতা'))->collapsed(),
                NavigationGroup::make('সেটিং ও এআই')->label(fn () => __('সেটিং ও এআই'))->collapsed(),
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
                'gray' => Color::hex('#4D5A63'),   // D279: grey numbers (for information) in the palette's ink
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
            // Bengali calendar names + Bengali digits on screen (public/js/desk-bn.js).
            // D260 (owner: "these action links take a long row; make them a vertical list"): a row with four or more
            // buttons shows them one under another, so the table keeps its width for the title and the writer.
            ->renderHook(\Filament\View\PanelsRenderHook::STYLES_AFTER, fn () => new \Illuminate\Support\HtmlString(
                '<style>.fi-ta-row td .fi-ta-actions:has(> :nth-child(4)){flex-direction:column;align-items:flex-start;flex-wrap:nowrap;gap:.45rem;min-width:11rem}</style>'))
            ->renderHook(\Filament\View\PanelsRenderHook::BODY_END, fn () => app()->getLocale() === 'bn' ? new \Illuminate\Support\HtmlString('<script src="'.asset('js/desk-bn.js').'?v=3"></script>') : '')
            ->authMiddleware([Authenticate::class])
            ->loginRouteSlug('login')
            ->authGuard('web');
    }
}
