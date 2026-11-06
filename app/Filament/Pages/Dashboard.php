<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Filament\Resources\Comments\Tables\CommentsTable;
use App\Filament\Resources\Posts\PostResource;
use App\Ai\AiSwitch;
use App\Ai\Claude;
use App\Ai\IssuePlanner;
use App\Filament\Pages\AiImages as AiImagesPage;
use App\Models\AiReview;
use App\Models\AiUsage;
use App\Models\Issue;
use App\Support\Schedule;
use Illuminate\Support\Facades\Cache;
use App\Models\Comment;
use App\Models\Post;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * "আজকের কাজ": the one page an editor needs. Everything waiting for a person is listed here,
 * oldest first, with the action next to it. Nothing is decided automatically (CLAUDE.md §2).
 */
class Dashboard extends BaseDashboard
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected static ?string $title = 'আজকের কাজ';

    protected static ?string $navigationLabel = 'আজকের কাজ';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected string $view = 'filament.pages.today';

    public function getWidgets(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function today(): array
    {
        $isAdmin = auth()->user()?->hasRole(Role::Admin) ?? false;
        $submissions = Post::with(['authors', 'section', 'latestAiReview'])
            ->where('status', 'pending')->whereNotNull('submitted_by')->orderBy('submitted_at')->limit(20)->get();
        $packaged = AiReview::where('status', 'done')->whereNull('blind_run')->where('report', 'like', '%"edits"%')->select('post_id');
        $issue = $isAdmin ? Issue::where('status', 'proposed')->latest('updated_at')->first() : null;
        if ($isAdmin) {
            $this->maybePlanIssue();
        }
        $tz = 'Asia/Kolkata';

        return [
            'isAdmin' => $isAdmin,
            'name' => auth()->user()?->name,
            'submissions' => $submissions,
            'confirms' => Post::where('status', 'confirm')->count(),
            'withWriter' => Post::where('status', 'changes')->count(),
            'backlogReady' => Post::where('status', 'pending')->whereNull('submitted_by')->whereIn('id', $packaged)->count(),
            'backlog' => Post::where('status', 'pending')->whereNull('submitted_by')->count(),
            'issue' => $issue,
            'issueDue' => $isAdmin && ! $issue && IssuePlanner::due(),
            'issuePlanning' => Cache::has('ai:issue-planning'),
            'nextMonth' => IssuePlanner::targetMonth(),
            'editorials' => $isAdmin ? Post::whereNotNull('ai_drafted_at')->where('status', 'draft')->get() : collect(),
            'images' => $isAdmin ? AiImagesPage::waiting()->count() : 0,
            'comments' => Comment::with('post')->where('status', 'pending')->oldest()->limit(15)->get(),
            'commentsTotal' => Comment::where('status', 'pending')->count(),
            'commentsOk' => Comment::where('status', 'pending')->where('ai_verdict', 'ok')->count(),
            'week' => collect(range(0, 13))->map(fn ($i) => now($tz)->startOfDay()->addDays($i))
                ->map(fn ($d) => ['day' => $d, 'slot' => in_array($d->dayOfWeek, Schedule::DAYS, true),
                    'posts' => Post::whereIn('status', ['scheduled', 'published'])->whereBetween('published_at', [$d->copy()->utc(), $d->copy()->endOfDay()->utc()])->pluck('title')]),
            'publishedWeek' => Post::published()->where('published_at', '>=', now()->startOfWeek())->count(),
            'aiEnabled' => (bool) config('ai.enabled'),
            'aiPaused' => AiSwitch::paused(),
            'aiSpent' => $isAdmin ? AiUsage::spentThisMonthUsd() : 0,
            // The AI editor's shift (D44): when it last worked (cron heartbeat) and what it did in 24 hours.
            'heartbeat' => ($h = \App\Models\Setting::get('ai.heartbeat')) ? \Illuminate\Support\Carbon::parse($h) : null,
            'brief' => \App\Ai\Brief::today(),
            'auditOpen' => $isAdmin ? Post::whereIn('audit_label', ['merge', 'noindex', 'remove'])->whereNull('audit_decision')->count() : 0,
        ];
    }

    /**
     * From the 20th, the AI editor prepares next month's issue by itself (D42), once, in the background
     * after this page has been sent. The owner then approves it here.
     */
    private function maybePlanIssue(): void
    {
        if (now('Asia/Kolkata')->day < 20 || ! IssuePlanner::due() || Claude::blocked() || Cache::has('ai:issue-tried:'.now()->toDateString())) {
            return;
        }
        $this->planIssueInBackground();
    }

    public function proposeIssue(): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        if ($why = Claude::blocked()) {
            Notification::make()->title(__('এআই এখন কাজ করতে পারছে না'))->body($why)->warning()->send();

            return;
        }
        $this->planIssueInBackground();
        Notification::make()->title(__('এআই সম্পাদক সংখ্যা সাজাচ্ছে'))->body(__('এক-দুই মিনিট পরে পাতাটি নতুন করে খুলুন।'))->success()->send();
    }

    private function planIssueInBackground(): void
    {
        $owner = auth()->user();
        if (! Cache::add('ai:issue-planning', true, 600)) {
            return;   // already running
        }
        Cache::put('ai:issue-tried:'.now()->toDateString(), true, 86400);
        dispatch(function () use ($owner) {
            try {
                IssuePlanner::propose($owner);
            } finally {
                Cache::forget('ai:issue-planning');
            }
        })->afterResponse();
    }

    /** Approve every waiting comment the AI editor marked as fine (D42). */
    public function approveAiOkComments(): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Editor), 403);
        $n = 0;
        foreach (Comment::where('status', 'pending')->where('ai_verdict', 'ok')->get() as $c) {
            CommentsTable::setStatus($c, 'approved');
            $n++;
        }
        Notification::make()->title(\App\Support\Bn::num($n).__('টি মন্তব্য প্রকাশিত'))->success()->send();
    }

    public function editUrl(Post $post): string
    {
        return PostResource::getUrl('edit', ['record' => $post]);
    }

    public function pauseAi(): void
    {
        AiSwitch::set(true);
        Notification::make()->title(__('এআই থামানো হয়েছে'))->success()->send();
    }

    public function resumeAi(): void
    {
        AiSwitch::set(false);
        Notification::make()->title(__('এআই আবার চালু'))->success()->send();
    }

    public function approveComment(int $id): void
    {
        $this->moderate($id, 'approved', __('মন্তব্য প্রকাশিত'));
    }

    public function spamComment(int $id): void
    {
        $this->moderate($id, 'spam', __('স্প্যামে সরানো হয়েছে'));
    }

    private function moderate(int $id, string $status, string $message): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Editor), 403);
        $comment = Comment::where('status', 'pending')->find($id);
        if ($comment) {
            CommentsTable::setStatus($comment, $status);
            Notification::make()->title($message)->success()->send();
        }
    }
}
