<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Newsletter;
use App\Models\Post;
use App\Support\Handover;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * D208: the jobs the owner handed to the AI team, in one place: a switch for each, what is about to go by itself
 * (with a stop button), what was held for the owner and why, and what was done.
 */
class HandoverPage extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.handover';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'নিজে থেকে চলা কাজ';

    protected static ?string $title = 'নিজে থেকে চলা কাজ';

    protected static ?string $slug = 'handover';
    protected static string|\UnitEnum|null $navigationGroup = 'সেটিং ও এআই';   // D279

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return rescue(fn () => ($n = Handover::needsOwner()) ? (string) $n : null, null, false);   // pieces kept for the owner
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';   // D279: red = waiting for you, grey = for information
    }

    public function toggle(string $job): void
    {
        abort_unless(self::canAccess() && isset(Handover::JOBS[$job]), 403);
        Handover::set($job, ! Handover::on($job));
        Notification::make()->title(Handover::on($job) ? __('চালু হলো') : __('বন্ধ হলো'))->success()->send();
    }

    public function stopPiece(int $id): void
    {
        abort_unless(self::canAccess(), 403);
        Handover::stop(Post::findOrFail($id));
        Notification::make()->title(__('এই লেখা নিজে থেকে প্রকাশ হবে না; আপনি দেখে ঠিক করবেন'))->success()->send();
    }

    public function stopLetter(int $id): void
    {
        abort_unless(self::canAccess(), 403);
        Newsletter::findOrFail($id)->forceFill(['auto_send_at' => null])->saveQuietly();
        AuditLog::record('handover.letter_stopped', null, ['newsletter' => $id]);
        Notification::make()->title(__('চিঠিটি নিজে থেকে যাবে না; খসড়া হিসেবে থাকল'))->success()->send();
    }

    public function getViewData(): array
    {
        $pending = Post::where('status', 'pending')->whereNotNull('submitted_at')->with('latestAiReview', 'authors')->latest('submitted_at')->limit(60)->get();

        return [
            'jobs' => collect(Handover::JOBS)->map(fn ($j, $k) => ['name' => $j[0], 'what' => $j[1], 'on' => Handover::on($k)])->all(),
            'going' => $pending->filter(fn (Post $p) => $p->auto_publish_at)->concat(Post::where('status', 'draft')->whereNotNull('auto_publish_at')->whereNotNull('serial_id')->with('authors')->get())->sortBy('auto_publish_at')->values(),
            'held' => Handover::on('publish') ? $pending->reject(fn (Post $p) => $p->auto_publish_at)
                ->map(fn (Post $p) => ['post' => $p, 'why' => rescue(fn () => Handover::hold($p), __('দেখা যায়নি'), false)])->filter(fn ($r) => $r['why'] !== null)->values() : collect(),
            'ranAt' => rescue(fn () => ($t = \App\Models\Setting::get('handover.ran_at')) ? \Illuminate\Support\Carbon::parse($t) : null, null, false),
            'flags' => \App\Models\EditorTask::where('type', 'alert')->where('status', 'open')->where('key', 'like', 'handover.%')->get(),
            'letters' => Newsletter::where('status', 'draft')->whereNotNull('auto_send_at')->orderBy('auto_send_at')->get(),
            'log' => AuditLog::where('action', 'like', 'handover.%')->whereNotIn('action', ['handover.publish_planned', 'handover.episode_checked'])->latest('id')->limit(40)->get(),
        ];
    }
}
