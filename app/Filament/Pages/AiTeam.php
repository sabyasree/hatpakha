<?php

namespace App\Filament\Pages;

use App\Ai\AiSwitch;
use App\Ai\Team;
use App\Enums\Role;
use App\Models\EditorTask;
use App\Models\Setting;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** The AI team at a glance (D49): who does what, how much, at what cost; pause any one of them. */
class AiTeam extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.ai-team';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'প্রশাসন';

    protected static ?string $navigationLabel = 'এআই দল';

    protected static ?string $title = 'এআই দল';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return ($n = EditorTask::where('type', 'alert')->where('status', 'open')->count()) ? (string) $n : null;
    }

    public function toggle(string $agent): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        Team::setPaused($agent, ! Team::paused($agent));
        Notification::make()->title(Team::paused($agent) ? __('থামানো হয়েছে') : __('আবার কাজ শুরু'))->success()->send();
    }

    public function data(): array
    {
        $heartbeat = Setting::get('ai.heartbeat');

        return [
            'stats' => Team::stats(),
            'alerts' => EditorTask::where('type', 'alert')->where('status', 'open')->latest()->get(),
            'enabled' => (bool) config('ai.enabled'),
            'teamPaused' => AiSwitch::paused(),
            'clock' => $heartbeat ? \Illuminate\Support\Carbon::parse($heartbeat) : null,
        ];
    }
}
