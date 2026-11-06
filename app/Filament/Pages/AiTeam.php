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

    /** Which member's recent work is open below the cards. */
    public ?string $open = null;

    public function show(string $agent): void
    {
        $this->open = $this->open === $agent ? null : (isset(Team::AGENTS[$agent]) ? $agent : null);
    }

    /** Where each member's results are waiting for the owner (D57). */
    public static function whereToSee(string $agent): array
    {
        $u = fn (string $class) => $class::getUrl();
        $today = Dashboard::getUrl();

        return match ($agent) {
            'designer' => [__('ছবি অনুমোদন'), $u(AiImages::class)],
            'marketing' => [__('মার্কেটিং'), $u(Marketing::class)],
            'contest' => [__('প্রতিযোগিতা'), \App\Filament\Resources\Contests\ContestResource::getUrl()],
            'editor', 'proofreader', 'plagiarism' => [__('লেখা → অপেক্ষায় (প্রতিটি লেখার মনোনীত বোতামে)'), \App\Filament\Resources\Posts\PostResource::getUrl()],
            'seo' => [__('প্রতিটি লেখার পাতায় "এসইও"'), \App\Filament\Resources\Posts\PostResource::getUrl()],
            default => [__('আজকের কাজ'), $today],
        };
    }

    /** The member's last 25 jobs: when, what, which piece. */
    public function recent(string $agent): \Illuminate\Support\Collection
    {
        $purposes = Team::AGENTS[$agent][2] ?? [];

        return \App\Models\AiUsage::whereIn('purpose', $purposes)->latest('id')->limit(25)->get()
            ->map(fn ($r) => ['at' => $r->created_at, 'purpose' => $r->purpose, 'cost' => (float) $r->cost_usd,
                'post' => $r->post_id ? \App\Models\Post::find($r->post_id, ['id', 'title']) : null]);
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
