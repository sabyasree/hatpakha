<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Support\HealthReport as Report;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Owner (2026-11-12): the weekly / monthly health report lives here, not in an email. One verdict on top,
 * "waiting for you" next, then each part of the site with its numbers and a link to where to act.
 */
class HealthReport extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.health-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'সাইটের স্বাস্থ্য';

    protected static ?string $title = 'সাইটের স্বাস্থ্য';

    protected static ?string $slug = 'health';

    protected static ?int $navigationSort = 2;

    #[Url]
    public string $period = 'week';

    #[Url]
    public int $back = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return rescue(fn () => cache()->remember('desk.health.badge', 600, fn () => Report::build()['overall'] === Report::URGENT ? '!' : null), null, false);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function show(string $period, int $back = 0): void
    {
        $this->period = $period === 'month' ? 'month' : 'week';
        $this->back = max(0, min(24, $back));
    }

    public function getViewData(): array
    {
        return ['r' => Report::build($this->period, $this->back)];
    }
}
