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
            'tasks' => \App\Models\EditorTask::where('status', 'open')
                ->when(! $isAdmin, fn ($q) => $q->whereIn('type', ['followup', 'welcome']))->latest()->limit(30)->get(),
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

    // ── The AI editor's other jobs (D48): each needs the owner's click ──────────────────────────

    private function task(array $arguments): \App\Models\EditorTask
    {
        return \App\Models\EditorTask::where('status', 'open')->findOrFail((int) ($arguments['task'] ?? 0));
    }

    /** Follow-up / welcome: edit the note, then one e-mail to that writer. */
    public function sendNoteAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('sendNote')->label(__('পাঠান'))->icon('heroicon-o-paper-airplane')->size('sm')
            ->modalHeading(fn (array $arguments) => __('লেখককে বার্তা').': '.$this->task($arguments)->title)
            ->fillForm(fn (array $arguments) => ['message' => $this->task($arguments)->payload['message'] ?? ''])
            ->schema([\Filament\Forms\Components\Textarea::make('message')->label(__('বার্তা'))->required()->rows(6)->maxLength(2000)])
            ->modalSubmitActionLabel(__('পাঠান'))
            ->action(function (array $data, array $arguments) {
                $task = $this->task($arguments);
                $post = $task->subject;
                $writer = $post?->submittedBy;
                abort_unless($writer, 404);
                $writer->notify(new \App\Notifications\WriterNote(
                    $task->type === 'welcome' ? 'হাতপাখা: আপনার প্রথম লেখা প্রকাশিত' : 'হাতপাখা: "'.$post->title.'"',
                    $data['message'], \App\Support\Url::to('/submit/'.$post->id)));
                $task->close();
                Notification::make()->title(__('পাঠানো হয়েছে'))->success()->send();
            });
    }

    /** A writer's chat question the helper could not answer (D52): the owner replies by e-mail. */
    public function replyQuestionAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('replyQuestion')->label(__('উত্তর দিন'))->icon('heroicon-o-chat-bubble-left-right')->size('sm')
            ->modalHeading(fn (array $arguments) => __('লেখকের প্রশ্ন'))
            ->schema(fn (array $arguments) => [
                \Filament\Forms\Components\Placeholder::make('q')->label(fn () => ($this->task($arguments)->payload['name'] ?? '').' · '.($this->task($arguments)->payload['email'] ?? ''))
                    ->content($this->task($arguments)->payload['question'] ?? ''),
                \Filament\Forms\Components\Textarea::make('message')->label(__('উত্তর'))->required()->rows(5)->maxLength(2000),
            ])
            ->modalSubmitActionLabel(__('পাঠান'))
            ->action(function (array $data, array $arguments) {
                $task = $this->task($arguments);
                $writer = $task->subject;
                abort_unless($writer instanceof \App\Models\User, 404);
                $writer->notify(new \App\Notifications\WriterNote('হাতপাখা: আপনার প্রশ্নের উত্তর', $data['message'], \App\Support\Url::to('/account')));
                $task->close();
                Notification::make()->title(__('পাঠানো হয়েছে'))->success()->send();
            });
    }

    /** Weekly picks: edit the note, then they go on the home page. */
    public function approvePickAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('approvePick')->label(__('অনুমোদন'))->icon('heroicon-o-check')->size('sm')
            ->modalHeading(__('সম্পাদকের পছন্দ ও এই দিনে'))
            ->fillForm(fn (array $arguments) => ['note' => $this->task($arguments)->payload['note'] ?? ''])
            ->schema(fn (array $arguments) => [
                \Filament\Forms\Components\Placeholder::make('pick')->label(__('এই সপ্তাহের পছন্দ'))->content($this->task($arguments)->title),
                \Filament\Forms\Components\TextInput::make('note')->label(__('প্রথম পাতার ছোট লেখা'))->maxLength(160),
                \Filament\Forms\Components\Placeholder::make('otd')->label(__('এই দিনে (সাত দিন)'))->content(fn () => collect($this->task($arguments)->payload['on_this_day'] ?? [])
                    ->map(fn ($id, $date) => \App\Support\Bn::num($date).': '.(Post::find($id)?->title ?? '—'))->implode(' · ') ?: '—'),
            ])
            ->modalSubmitActionLabel(__('প্রথম পাতায় বসান'))
            ->action(function (array $data, array $arguments) {
                abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
                $task = $this->task($arguments);
                \App\Models\Setting::put('home.pick', json_encode(['post_id' => $task->payload['pick_id'], 'note' => $data['note'] ?? null], JSON_UNESCAPED_UNICODE));
                \App\Models\Setting::put('home.on_this_day', json_encode($task->payload['on_this_day'] ?? []));
                Cache::forget('home:v2:'.now()->toDateString());
                $task->close();
                Notification::make()->title(__('প্রথম পাতায় বসানো হয়েছে'))->success()->send();
            });
    }

    /** Newsletter: preview, edit the opening, send to confirmed readers only. */
    public function sendNewsletterAction(): \Filament\Actions\Action
    {
        $recipients = fn () => \App\Models\NewsletterSubscriber::whereNotNull('consented_at')->where('suppressed', false)->whereNotIn('status', ['unsubscribed', 'bounced']);

        return \Filament\Actions\Action::make('sendNewsletter')->label(__('দেখুন ও পাঠান'))->icon('heroicon-o-envelope')->size('sm')
            ->modalHeading(fn (array $arguments) => $this->task($arguments)->payload['subject'] ?? '')
            ->fillForm(fn (array $arguments) => ['subject' => $this->task($arguments)->payload['subject'] ?? '', 'intro' => $this->task($arguments)->payload['intro'] ?? ''])
            ->schema(fn (array $arguments) => [
                \Filament\Forms\Components\TextInput::make('subject')->label(__('বিষয়'))->required()->maxLength(90),
                \Filament\Forms\Components\Textarea::make('intro')->label(__('শুরুর কথা'))->required()->rows(4)->maxLength(800),
                \Filament\Forms\Components\Placeholder::make('items')->label(__('এই সপ্তাহের লেখা'))->content(new \Illuminate\Support\HtmlString(collect($this->task($arguments)->payload['items'] ?? [])
                    ->map(fn ($it) => '<p style="margin:.3rem 0"><strong>'.e($it['title']).'</strong> — '.e($it['author']).'<br>'.e($it['line']).'</p>')->implode(''))),
                \Filament\Forms\Components\Placeholder::make('who')->label(__('কারা পাবেন'))->content(fn () => \App\Support\Bn::num($recipients()->count()).' '.__('জন (যাঁরা নিজে সম্মতি দিয়েছেন)')
                    .(in_array(config('mail.default'), ['log', 'array'], true) ? ' · '.__('এই সার্ভারে আসল ই-মেল চালু নেই, তাই পাঠানো যাবে না') : '')),
            ])
            ->modalSubmitActionLabel(__('এখন পাঠান'))
            ->action(function (array $data, array $arguments) use ($recipients) {
                abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
                if (in_array(config('mail.default'), ['log', 'array'], true)) {
                    Notification::make()->title(__('আসল ই-মেল চালু হলে পাঠানো যাবে'))->warning()->send();

                    return;
                }
                $task = $this->task($arguments);
                $issue = ['subject' => $data['subject'], 'intro' => $data['intro'], 'items' => $task->payload['items'] ?? []];
                $n = 0;
                foreach ($recipients()->get() as $sub) {
                    \Illuminate\Support\Facades\Notification::route('mail', $sub->email)->notify(new \App\Notifications\NewsletterIssue($issue,
                        \Illuminate\Support\Facades\URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $sub->id])));
                    $sub->forceFill(['last_sent_at' => now()])->save();
                    $n++;
                }
                $task->forceFill(['payload' => $issue + ['sent_to' => $n]])->save();
                $task->close();
                Notification::make()->title(\App\Support\Bn::num($n).' '.__('জনকে পাঠানো হয়েছে'))->success()->send();
            });
    }

    /** Monthly report / site health: read, then mark as seen. */
    public function readTaskAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('readTask')->label(__('পড়ুন'))->icon('heroicon-o-document-text')->size('sm')->color('gray')
            ->modalHeading(fn (array $arguments) => $this->task($arguments)->title)
            ->modalContent(fn (array $arguments) => view('filament.editor-task', ['task' => $this->task($arguments)]))
            ->modalSubmitActionLabel(__('পড়েছি'))
            ->action(fn (array $arguments) => $this->task($arguments)->close());
    }

    public function dismissTaskAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('dismissTask')->label(__('বাদ দিন'))->icon('heroicon-o-x-mark')->size('sm')->color('gray')
            ->requiresConfirmation()
            ->action(fn (array $arguments) => $this->task($arguments)->close('dismissed'));
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
