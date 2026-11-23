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
        // D256: a piece whose writer objected to the word changes comes first, with the writer's words
        $submissions = Post::with(['authors', 'section', 'latestAiReview'])
            ->where('status', 'pending')->whereNotNull('submitted_by')
            ->orderByRaw('CASE WHEN review_note LIKE ? THEN 0 ELSE 1 END', [\App\Ai\EditorPackage::OBJECTED.'%'])->orderBy('submitted_at')->limit(20)->get();
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
                // D224: the answer is kept with the question, so it can be read (and sent again) afterwards
                $task->forceFill(['payload' => ['reply' => $data['message'], 'replied_at' => now()->toIso8601String(), 'replied_by' => auth()->id()] + (array) $task->payload])->save();
                $task->close();
                Notification::make()->title(__('পাঠানো হয়েছে'))->success()->send();
            });
    }

    /** D224 (owner: "I sent this reply from there. Then I could not find any details"): the questions already answered. */
    public function answeredQuestions(): \Illuminate\Support\Collection
    {
        return \App\Models\EditorTask::with('subject')->where('type', 'question')->where('status', 'done')->latest('done_at')->limit(15)->get();
    }

    /** Answer an already answered question again (the first e-mail came back, or there is more to say). */
    public function replyAgainAction(): \Filament\Actions\Action
    {
        $find = fn (array $arguments) => \App\Models\EditorTask::where('type', 'question')->where('status', 'done')->findOrFail((int) ($arguments['task'] ?? 0));

        return \Filament\Actions\Action::make('replyAgain')->label(__('আবার উত্তর পাঠান'))->icon('heroicon-o-arrow-path')->size('xs')->color('gray')
            ->modalHeading(__('লেখকের প্রশ্ন'))
            ->fillForm(fn (array $arguments) => ['message' => $find($arguments)->payload['reply'] ?? ''])
            ->schema(fn (array $arguments) => [
                \Filament\Forms\Components\Placeholder::make('q')->label(fn () => ($find($arguments)->payload['name'] ?? '').' · '.($find($arguments)->payload['email'] ?? ''))
                    ->content($find($arguments)->payload['question'] ?? ''),
                \Filament\Forms\Components\Textarea::make('message')->label(__('উত্তর'))->required()->rows(8)->maxLength(2000),
            ])
            ->modalSubmitActionLabel(__('পাঠান'))
            ->action(function (array $data, array $arguments) use ($find) {
                $task = $find($arguments);
                abort_unless($task->subject instanceof \App\Models\User, 404);
                $task->subject->notify(new \App\Notifications\WriterNote('হাতপাখা: আপনার প্রশ্নের উত্তর', $data['message'], \App\Support\Url::to('/account')));
                $task->forceFill(['payload' => ['reply' => $data['message'], 'replied_at' => now()->toIso8601String(), 'replied_by' => auth()->id()] + (array) $task->payload])->save();
                \App\Models\AuditLog::record('task.question.resent', $task->subject, ['task' => $task->id]);
                Notification::make()->title(__('আবার পাঠানো হয়েছে'))->success()->send();
            });
    }

    /** The archivist's collection (D55): approve to show it on /archive/. */
    public function approveCollectionAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('approveCollection')->label(__('দেখুন ও অনুমোদন'))->icon('heroicon-o-check')->size('sm')
            ->modalHeading(fn (array $arguments) => $this->task($arguments)->subject?->title)
            ->fillForm(fn (array $arguments) => ['title' => $this->task($arguments)->subject?->title, 'intro' => $this->task($arguments)->subject?->intro])
            ->schema(fn (array $arguments) => [
                \Filament\Forms\Components\TextInput::make('title')->label(__('নাম'))->required()->maxLength(150),
                \Filament\Forms\Components\Textarea::make('intro')->label(__('ভূমিকা'))->rows(3)->maxLength(1200),
                \Filament\Forms\Components\Placeholder::make('list')->label(__('লেখা'))->content(fn () => $this->task($arguments)->subject?->posts()
                    ->map(fn ($p) => $p->title.' — '.($p->authors->first()?->publicName() ?? ''))->implode(' · ')),
            ])
            ->modalSubmitActionLabel(__('অনুমোদন'))
            ->action(function (array $data, array $arguments) {
                $task = $this->task($arguments);
                $c = $task->subject;
                abort_unless($c instanceof \App\Models\Collection && auth()->user()->hasRole(\App\Enums\Role::Admin), 403);
                $c->forceFill(['title' => strip_tags($data['title']), 'intro' => strip_tags((string) $data['intro']), 'status' => 'approved', 'approved_at' => now()])->save();
                \App\Models\AuditLog::record('collection.approved', null, ['collection' => $c->id]);
                $task->close();
                Notification::make()->title($c->kind === 'theme' ? __('আর্কাইভ পাতায় দেখা যাবে') : __('অনুমোদিত'))->success()->send();
            });
    }

    /** Tag tidy-up (D55): the owner ticks which duplicates to merge (old tag pages 301 to the kept tag). */
    public function mergeTagsAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('mergeTags')->label(__('মেলান'))->icon('heroicon-o-arrows-pointing-in')->size('sm')
            ->modalHeading(__('একই রকম ট্যাগ মেলানো'))
            ->schema(fn (array $arguments) => [
                \Filament\Forms\Components\CheckboxList::make('pairs')->label(__('কোনগুলো মেলাবেন?'))->columns(1)->bulkToggleable()
                    ->options(collect($this->task($arguments)->payload['pairs'] ?? [])->mapWithKeys(fn ($p, $i) => [$i => '"'.$p['drop_name'].'" → "'.$p['keep_name'].'" ('.\App\Support\Bn::num((string) $p['posts']).')'])->all()),
            ])
            ->modalSubmitActionLabel(__('মেলান'))
            ->action(function (array $data, array $arguments) {
                abort_unless(auth()->user()->hasRole(\App\Enums\Role::Admin), 403);
                $task = $this->task($arguments);
                $pairs = $task->payload['pairs'] ?? [];
                foreach ((array) ($data['pairs'] ?? []) as $i) {
                    if (isset($pairs[$i]) && \App\Models\Tag::whereKey([$pairs[$i]['keep'], $pairs[$i]['drop']])->count() === 2) {
                        \App\Ai\Archivist::mergeTag($pairs[$i]['keep'], $pairs[$i]['drop']);
                    }
                }
                $task->close();
                Notification::make()->title(__('ট্যাগ মেলানো হয়েছে'))->success()->send();
            });
    }

    /** Publication certificates (D56): the owner ticks which to issue. */
    public function issueCertificatesAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('issueCertificates')->label(__('শংসাপত্র দিন'))->icon('heroicon-o-academic-cap')->size('sm')
            ->modalHeading(__('প্রকাশের শংসাপত্র'))
            ->fillForm(fn (array $arguments) => ['posts' => array_keys((array) ($this->task($arguments)->payload['posts'] ?? []))])
            ->schema(fn (array $arguments) => [
                \Filament\Forms\Components\CheckboxList::make('posts')->label(__('লেখা'))->columns(1)->bulkToggleable()
                    ->options((array) ($this->task($arguments)->payload['posts'] ?? []))
                    ->helperText(__('প্রতিটি শংসাপত্রের যাচাইয়ের পাতা থাকবে; লেখক নিজের পাতা থেকে দেখতে পাবেন।')),
            ])
            ->modalSubmitActionLabel(__('শংসাপত্র দিন'))
            ->action(function (array $data, array $arguments) {
                abort_unless(auth()->user()->hasRole(\App\Enums\Role::Admin), 403);
                $task = $this->task($arguments);
                $n = \App\Ai\EditorJobs::issueCertificates(array_map('intval', array_intersect((array) ($data['posts'] ?? []), array_keys((array) ($task->payload['posts'] ?? [])))));
                $task->close();
                Notification::make()->title(\App\Support\Bn::num((string) $n).' '.__('টি শংসাপত্র তৈরি হয়েছে'))->success()->send();
            });
    }

    /** D66: the AI editor's writer of the month: edit the praise, then honour (and tell the writer). */
    public function honourAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('honour')->label(__('দেখুন ও সম্মান দিন'))->icon('heroicon-o-star')->size('sm')
            ->modalHeading(fn (array $arguments) => $this->task($arguments)->title)
            ->modalDescription(fn (array $arguments) => $this->task($arguments)->payload['why'] ?? null)
            ->fillForm(fn (array $arguments) => ['praise' => $this->task($arguments)->payload['praise'] ?? '', 'tell' => true])
            ->schema([
                \Filament\Forms\Components\Textarea::make('praise')->label(__('সম্পাদকের কথা (লেখক-পাতায় দেখা যাবে)'))->required()->rows(4)->maxLength(800),
                \Filament\Forms\Components\Toggle::make('tell')->label(__('লেখককে অভিনন্দনের ই-মেল পাঠান')),
            ])
            ->modalSubmitActionLabel(__('সম্মান দিন'))
            ->action(function (array $data, array $arguments) {
                abort_unless(auth()->user()->hasRole(Role::Admin), 403);
                $task = $this->task($arguments);
                abort_unless($task->subject instanceof \App\Models\Author, 404);
                \App\Ai\Honours::give($task->subject, $task->payload['label'] ?? 'month', $data['praise'], (bool) $data['tell']);
                \App\Ai\Marketer::spotlight($task->subject);
                $task->close();
                Notification::make()->title(__('সম্মান দেওয়া হয়েছে; মার্কেটিং পাতায় পোস্টও তৈরি হচ্ছে'))->success()->send();
            });
    }

    /** Any published piece, found by typing part of its title. */
    private static function pickSelect(): \Filament\Forms\Components\Select
    {
        return \Filament\Forms\Components\Select::make('post_id')->label(__('সম্পাদকের পছন্দের লেখা'))->required()->searchable()
            // Owner (2026-11-16): "when I change the post, the short note for the home page is not changing."
            ->live()->afterStateUpdated(fn ($state, $set) => $set('note', self::pickNote((int) $state)))
            ->getSearchResultsUsing(fn (string $search) => Post::published()->where('title', 'like', '%'.str_replace(['%', '_'], '', $search).'%')->latest('published_at')->limit(30)->get()
                ->mapWithKeys(fn (Post $p) => [$p->id => $p->title.' — '.($p->authors->first()?->publicName() ?? '')])->all())
            ->getOptionLabelUsing(fn ($value) => ($p = Post::find($value)) ? $p->title.' — '.($p->authors->first()?->publicName() ?? '') : null)
            ->options(fn () => Post::published()->with('authors')->latest('published_at')->limit(30)->get()
                ->mapWithKeys(fn (Post $p) => [$p->id => $p->title.' — '.($p->authors->first()?->publicName() ?? '')])->all());
    }

    /**
     * The one-line note for a piece chosen by hand: the teaser already written for that piece (the AI editor writes one
     * with every report), so it appears at once and costs nothing. The owner can still change it.
     */
    public static function pickNote(int $postId): string
    {
        $p = Post::with('latestAiReview')->find($postId);
        $line = trim(strip_tags((string) ($p?->excerpt ?: ($p?->latestAiReview?->report['excerpt'] ?? ''))));

        return mb_strlen($line) > 160 ? rtrim(mb_substr($line, 0, 157)).'…' : $line;
    }

    /** What is on the home page now as the editor's pick. @return array{post: ?Post, note: ?string} */
    public function currentPick(): array
    {
        $pick = json_decode((string) \App\Models\Setting::get('home.pick'), true) ?: [];

        return ['post' => isset($pick['post_id']) ? Post::find($pick['post_id']) : null, 'note' => $pick['note'] ?? null];
    }

    /**
     * Owner (2026-11-16: "editor's pick: the AI is selecting, but if I drop it there is no place to see, and select
     * another one"): the pick can be seen and changed at any time, with or without a proposal from the AI.
     */
    public function changePickAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('changePick')->label(fn () => $this->currentPick()['post'] ? __('বদলান') : __('একটি লেখা বেছে নিন'))->icon('heroicon-o-star')->size('sm')->color('gray')
            ->modalHeading(__('প্রথম পাতায় সম্পাদকের পছন্দ'))
            ->fillForm(fn () => ['post_id' => $this->currentPick()['post']?->id, 'note' => $this->currentPick()['note']])
            ->schema([
                self::pickSelect()->helperText(__('প্রকাশিত যে-কোনো লেখা। নাম লিখে খুঁজুন।')),
                \Filament\Forms\Components\TextInput::make('note')->label(__('প্রথম পাতার ছোট লেখা'))->maxLength(160)->helperText(__('লেখা বাছলে তার ছোট পরিচয় এখানে নিজে থেকে বসে। চাইলে বদলে নিন; ফাঁকা রাখলেও চলে।')),
            ])
            ->modalSubmitActionLabel(__('প্রথম পাতায় বসান'))
            ->action(function (array $data) {
                abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
                $post = Post::published()->findOrFail((int) $data['post_id']);
                \App\Models\Setting::put('home.pick', json_encode(['post_id' => $post->id, 'note' => filled($data['note'] ?? null) ? strip_tags((string) $data['note']) : null], JSON_UNESCAPED_UNICODE));
                \App\Models\AuditLog::record('home.pick_set', $post, ['by_hand' => true]);
                \App\Models\EditorTask::open('pick')->get()->each->close('dismissed');   // the AI's waiting proposal is answered
                Cache::forget('home:v2:'.now()->toDateString());
                Notification::make()->title(__('প্রথম পাতায় বসানো হয়েছে'))->success()->send();
            });
    }

    public function removePick(): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        \App\Models\Setting::put('home.pick', '');
        \App\Models\AuditLog::record('home.pick_removed');
        Cache::forget('home:v2:'.now()->toDateString());
        Notification::make()->title(__('প্রথম পাতা থেকে সরানো হয়েছে'))->success()->send();
    }

    /** Weekly picks: edit the note, then they go on the home page. */
    public function approvePickAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('approvePick')->label(__('অনুমোদন'))->icon('heroicon-o-check')->size('sm')
            ->modalHeading(__('সম্পাদকের পছন্দ ও এই দিনে'))
            ->fillForm(fn (array $arguments) => ['post_id' => $this->task($arguments)->payload['pick_id'] ?? null, 'note' => $this->task($arguments)->payload['note'] ?? ''])
            ->schema(fn (array $arguments) => [
                self::pickSelect()->helperText(__('এআই এটি বেছেছে। অন্য লেখা চাইলে এখানে নাম লিখে খুঁজে বেছে নিন।')),
                \Filament\Forms\Components\TextInput::make('note')->label(__('প্রথম পাতার ছোট লেখা'))->maxLength(160)
                    ->helperText(__('অন্য লেখা বাছলে সেই লেখার ছোট পরিচয় এখানে নিজে থেকে বসে। চাইলে বদলে নিন।')),
                \Filament\Forms\Components\Placeholder::make('otd')->label(__('এই দিনে (সাত দিন)'))->content(fn () => collect($this->task($arguments)->payload['on_this_day'] ?? [])
                    ->map(fn ($id, $date) => \App\Support\Bn::num($date).': '.(Post::find($id)?->title ?? '—'))->implode(' · ') ?: '—'),
            ])
            ->modalSubmitActionLabel(__('প্রথম পাতায় বসান'))
            ->action(function (array $data, array $arguments) {
                abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
                $task = $this->task($arguments);
                $id = Post::published()->whereKey((int) ($data['post_id'] ?? 0))->value('id') ?? $task->payload['pick_id'];
                \App\Models\Setting::put('home.pick', json_encode(['post_id' => $id, 'note' => $data['note'] ?? null], JSON_UNESCAPED_UNICODE));
                \App\Models\AuditLog::record('home.pick_set', Post::find($id), ['ai_pick' => $task->payload['pick_id'], 'changed' => (int) $id !== (int) $task->payload['pick_id']]);
                \App\Models\Setting::put('home.on_this_day', json_encode($task->payload['on_this_day'] ?? []));
                Cache::forget('home:v2:'.now()->toDateString());
                $task->close();
                Notification::make()->title(__('প্রথম পাতায় বসানো হয়েছে'))->success()->send();
            });
    }

    /** Monthly report / site health: read, then mark as seen. */
    public function readTaskAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('readTask')->label(__('পড়ুন'))->icon('heroicon-o-document-text')->size('sm')->color('gray')
            ->modalHeading(fn (array $arguments) => $this->task($arguments)->title)
            ->modalContent(fn (array $arguments) => \App\Filament\Support\Safe::view('filament.editor-task', ['task' => $this->task($arguments)]))
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
