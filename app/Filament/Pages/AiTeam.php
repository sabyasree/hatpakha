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

    /** "দলকে কাজ দিন" (D72): the owner's own words; the editor-in-chief (or the chosen member) does it. */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('ask')->label(__('দলকে কাজ দিন'))->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->modalHeading(__('দলকে কী করতে বলবেন?'))
                ->modalDescription(__('নিজের ভাষায় লিখুন। প্রধান সম্পাদক ঠিক করবে কে করবে; চেনা কাজ হলে সেটাই করবে, নইলে খসড়া, তালিকা বা পরিকল্পনা লিখে দেবে। প্রকাশ, পাঠানো বা মুছে ফেলা সবসময় আপনার হাতে।'))
                ->schema([
                    \Filament\Forms\Components\Select::make('agent')->label(__('কে করবে'))->placeholder(__('প্রধান সম্পাদক ঠিক করুক'))
                        ->options(fn () => collect(\App\Ai\Team::AGENTS)->except('relations')->map(fn ($a) => __($a[0]))->all()),
                    \Filament\Forms\Components\Textarea::make('instruction')->label(__('কাজটি'))->required()->rows(5)->maxLength(3000)
                        ->placeholder(__('যেমন: ভাইফোঁটা নিয়ে একটা ফেসবুক পোস্ট; শীতের সংখ্যার জন্য ১০টি বিষয়ের তালিকা; "শ্রাবণের মেঘ" লেখাটির ছবি আবার আঁকো')),
                ])
                ->modalSubmitActionLabel(__('কাজ দিন'))
                ->action(function (array $data) {
                    \App\Ai\TeamJobs::queue($data['agent'] ?: null, null, (string) $data['instruction']);
                    Notification::make()->title(__('কাজ দেওয়া হয়েছে; এক-দুই মিনিটের মধ্যে শুরু হবে। নিচে "আপনার দেওয়া কাজ"-এ ফল দেখবেন।'))->success()->send();
                }),
        ];
    }

    /** Per member: run one of its jobs now, or again, or anything in words (D72). */
    public function giveAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('give')->label(__('কাজ দিন'))->icon('heroicon-o-play-circle')->size('sm')
            ->modalHeading(fn (array $arguments) => __(Team::AGENTS[$arguments['agent'] ?? '']['0'] ?? '').': '.__('কাজ দিন'))
            ->fillForm(fn (array $arguments) => ['job' => array_key_first(\App\Ai\TeamJobs::JOBS[$arguments['agent'] ?? ''] ?? []) ?? 'words', 'redo' => false])
            ->schema(fn (array $arguments) => [
                \Filament\Forms\Components\Radio::make('job')->label(__('কী করবে'))->required()->live()
                    ->options(collect(\App\Ai\TeamJobs::JOBS[$arguments['agent'] ?? ''] ?? [])->map(fn ($j) => __($j[0]))->put('words', __('অন্য কিছু (নিজের ভাষায় লিখুন)'))->all()),
                \Filament\Forms\Components\Select::make('post')->label(__('লেখা'))->searchable()
                    ->getSearchResultsUsing(fn (string $search) => \App\Models\Post::where('title', 'like', '%'.$search.'%')->latest('id')->limit(30)->pluck('title', 'id'))
                    ->getOptionLabelUsing(fn ($value) => \App\Models\Post::find($value)?->title)
                    ->visible(fn ($get) => (\App\Ai\TeamJobs::JOBS[$arguments['agent'] ?? ''][$get('job')][1] ?? null) === 'post')
                    ->required(fn ($get) => (\App\Ai\TeamJobs::JOBS[$arguments['agent'] ?? ''][$get('job')][1] ?? null) === 'post'),
                \Filament\Forms\Components\Textarea::make('text')->rows(4)->maxLength(3000)
                    ->label(fn ($get) => $get('job') === 'words' ? __('কাজটি') : __('বিষয়'))
                    ->visible(fn ($get) => $get('job') === 'words' || (\App\Ai\TeamJobs::JOBS[$arguments['agent'] ?? ''][$get('job')][1] ?? null) === 'topic')
                    ->required(fn ($get) => $get('job') === 'words' || (\App\Ai\TeamJobs::JOBS[$arguments['agent'] ?? ''][$get('job')][1] ?? null) === 'topic'),
                \Filament\Forms\Components\Toggle::make('redo')->label(__('আগে করা থাকলেও আবার নতুন করে করুক'))
                    ->helperText(__('আগের প্রস্তাবটি সরিয়ে নতুনটি রাখবে।'))->visible(fn ($get) => $get('job') !== 'words'),
            ])
            ->modalSubmitActionLabel(__('এখনই করুক'))
            ->action(function (array $data, array $arguments) {
                $agent = (string) ($arguments['agent'] ?? '');
                abort_unless(isset(Team::AGENTS[$agent]), 404);
                $job = $data['job'] === 'words' ? null : $data['job'];
                \App\Ai\TeamJobs::queue($agent, $job, $job && (\App\Ai\TeamJobs::JOBS[$agent][$job][1] ?? null) === 'post' ? (string) $data['post'] : ($data['text'] ?? null), (bool) ($data['redo'] ?? false));
                Notification::make()->title(__('কাজ দেওয়া হয়েছে; এক-দুই মিনিটের মধ্যে শুরু হবে। নিচে "আপনার দেওয়া কাজ"-এ ফল দেখবেন।'))->success()->send();
            });
    }

    /** Do a request again (same work, as a redo). */
    public function again(int $id): void
    {
        $r = \App\Models\TeamRequest::findOrFail($id);
        \App\Ai\TeamJobs::queue($r->agent, $r->job, $r->instruction, true);
        Notification::make()->title(__('আবার কাজ দেওয়া হয়েছে'))->success()->send();
    }

    /** The owner answers the editor-in-chief's question (owner, 2026-11-11: giving work is a conversation). */
    public function replyRequest(int $id, string $text): void
    {
        $r = \App\Models\TeamRequest::findOrFail($id);
        if ($r->status !== 'asking' || trim($text) === '') {
            return;
        }
        \App\Ai\TeamJobs::reply($r, $text);
        Notification::make()->title(__('উত্তর পাঠানো হল; প্রধান সম্পাদক এক মিনিটের মধ্যে আবার দেখবে'))->success()->send();
    }

    /** A finished job stays marked "new" until the owner opens it. */
    public function markSeen(int $id): void
    {
        \App\Models\TeamRequest::whereKey($id)->whereIn('status', ['done', 'failed'])->whereNull('seen_at')->update(['seen_at' => now()]);
    }

    /** Agents whose work is long writing (2–10 minutes). */
    private const LONG = ['copywriter', 'serial_mystery', 'serial_ghost', 'serial_family', 'strategist', 'analyst', 'archivist', 'editor'];

    /**
     * Owner + UX review (2026-11-11): the four steps of a job, each with its state and a short line.
     * state: done | current | pending | skipped | retry | failed
     *
     * @return list<array{label: string, state: string, sub: ?string, at: ?string}>
     */
    public static function stepsFor(\App\Models\TeamRequest $r): array
    {
        $n = fn ($v) => \App\Support\Bn::num((string) $v);
        $t = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso) : null;
        $took = function ($a, $b) use ($n) {
            $m = (int) floor($a->diffInSeconds($b ?? now()) / 60);

            return $m < 1 ? __('এক মিনিটের কম') : $n($m).' '.__('মিনিট');
        };
        $clock = fn ($c) => $c ? $n($c->copy()->timezone('Asia/Kolkata')->format('H:i')) : null;
        $s = $r->steps ?? [];
        $phase = $r->phase();
        $member = $r->agent ? __(Team::AGENTS[$r->agent][0] ?? $r->agent) : __('দলের সদস্য');

        // 1. Received
        $steps = [[
            'label' => __('পেয়েছি'),
            'state' => $phase === 'waiting' ? 'current' : 'done',
            'sub' => $phase === 'waiting' ? __('শুরু হবে এক মিনিটের মধ্যে').' · '.__('অপেক্ষায়').' '.$took($r->created_at, null) : null,
            'at' => $clock($r->created_at),
        ]];

        // 2. Who does it (only for a request in the owner's own words)
        $route = $s['route'] ?? [];
        $routeFailed = $phase === 'failed' && isset($route['start']) && ! isset($route['end']) && ! ($route['skipped'] ?? false);
        $steps[] = match (true) {
            ($route['skipped'] ?? false) || ($r->job && ! isset($route['start'])) => ['label' => __('কে করবে ঠিক হচ্ছে'), 'state' => 'skipped', 'sub' => __('দরকার হয়নি'), 'at' => null],
            $phase === 'asking' => ['label' => __('প্রধান সম্পাদকের একটি প্রশ্ন'), 'state' => 'ask', 'sub' => __('আপনার উত্তরের অপেক্ষায়'), 'at' => null],
            isset($route['end']) => ['label' => __('কে করবে ঠিক হল'), 'state' => 'done', 'sub' => (filled($route['why'] ?? null) ? $route['why'].' · ' : '').__('ঠিক হল').': '.$route['note'], 'at' => $clock($t($route['end']))],
            $routeFailed => ['label' => __('কে করবে ঠিক হচ্ছে'), 'state' => 'failed', 'sub' => null, 'at' => $clock($t($route['start']))],
            isset($route['start']) && $phase === 'route' => ['label' => __('কে করবে ঠিক হচ্ছে'), 'state' => 'current', 'sub' => __('প্রধান সম্পাদক দেখছে কোন সদস্য করবে'), 'at' => $clock($t($route['start']))],
            default => ['label' => __('কে করবে ঠিক হচ্ছে'), 'state' => 'pending', 'sub' => null, 'at' => null],
        };

        // 3. The member at work
        $work = $s['work'] ?? [];
        $start = $t($work['start'] ?? null);
        $label = $member.' '.__('কাজ করছে');
        $steps[] = match (true) {
            $phase === 'ready' => ['label' => $member.' '.__('কাজ করেছে'), 'state' => 'done', 'sub' => $start ? $took($start, $t($work['end'] ?? null) ?? $r->finished_at).' '.__('লেগেছে') : null, 'at' => $clock($t($work['end'] ?? null) ?? $r->finished_at)],
            $phase === 'retry' => ['label' => $label, 'state' => 'retry', 'sub' => __('আবার চেষ্টা').' ('.$n(($r->attempts + 1).'/'.\App\Ai\TeamJobs::MAX_TRIES).'), '.__('এক মিনিটের মধ্যে'), 'at' => null],
            $phase === 'failed' && $start !== null => ['label' => $member.' '.__('কাজ শেষ করতে পারেনি'), 'state' => 'failed', 'sub' => null, 'at' => $clock($start)],
            $phase === 'work' => ['label' => $label, 'state' => 'current',
                'sub' => $took($start, null).' '.__('ধরে চলছে')
                    .($start && $start->diffInMinutes(now()) > 12 ? ' · '.__('স্বাভাবিকের চেয়ে বেশি সময় লাগছে; চাইলে অপেক্ষা করুন')
                        : (in_array($r->agent, self::LONG, true) ? ' · '.__('লম্বা লেখায় সাধারণত ২–১০ মিনিট লাগে') : '')),
                'at' => $clock($start)],
            default => ['label' => $label, 'state' => 'pending', 'sub' => null, 'at' => null],
        };

        // Owner (2026-11-11): a request split by the editor-in-chief shows each member's step on its own line.
        if (! empty($s['plan']) && is_array($s['plan']) && $phase !== 'retry') {
            array_pop($steps);
            foreach ($s['plan'] as $p) {
                $who = __(Team::AGENTS[$p['agent']][0] ?? $p['agent']);
                $what = __(\App\Ai\TeamJobs::JOBS[$p['agent']][$p['job']][0] ?? 'নিজের কথায় কাজ');
                $pStart = $t($p['start'] ?? null);
                $steps[] = [
                    'label' => $who.': '.$what,
                    'state' => $p['state'] === 'current' && $phase === 'failed' ? 'failed' : ($p['state'] ?? 'pending'),
                    'sub' => match ($p['state'] ?? 'pending') {
                        'current' => $took($pStart, null).' '.__('ধরে চলছে'),
                        'done', 'failed' => \Illuminate\Support\Str::limit((string) ($p['note'] ?? ''), 160),
                        default => null,
                    },
                    'at' => $clock($t($p['end'] ?? null) ?? $pStart),
                ];
            }
        }

        // 4. Ready for you (or could not be done)
        $steps[] = match ($phase) {
            'ready' => ['label' => __('তৈরি, আপনি দেখুন'), 'state' => 'done', 'sub' => __('প্রকাশ হয়নি; প্রকাশ বা পাঠানো আপনার হাতে'), 'at' => $clock($r->finished_at)],
            'failed' => ['label' => __('হল না'), 'state' => 'failed', 'sub' => null, 'at' => $clock($r->finished_at)],
            default => ['label' => __('তৈরি, আপনি দেখুন'), 'state' => 'pending', 'sub' => null, 'at' => null],
        };

        return $steps;
    }

    /** Remove a finished or failed request from the list (owner, 2026-11-09). A queued one is cancelled the same way. */
    public function deleteRequest(int $id): void
    {
        if (! \App\Filament\Support\Deleting::isAdmin()) {
            return;
        }
        $r = \App\Models\TeamRequest::findOrFail($id);
        if ($r->status === 'running') {
            Notification::make()->title(__('কাজ চলছে; শেষ হলে মুছতে পারবেন।'))->warning()->send();

            return;
        }
        \App\Models\AuditLog::record('team_request.deleted', null, ['id' => $r->id, 'agent' => $r->agent, 'job' => $r->job]);
        $r->delete();
        Notification::make()->title(__('মুছে ফেলা হয়েছে'))->success()->send();
    }

    /** Clears every finished or failed request from the list. */
    /**
     * Owner (2026-11-11): choose what to clear. done = finished work you've opened; done_all = all finished work;
     * failed = jobs that couldn't be done; all = every finished or failed job. Waiting and running jobs are never cleared here.
     */
    public function clearRequests(string $which = 'done'): void
    {
        if (! \App\Filament\Support\Deleting::isAdmin()) {
            return;
        }
        $q = \App\Models\TeamRequest::query();
        match ($which) {
            'done' => $q->where('status', 'done')->whereNotNull('seen_at'),
            'done_all' => $q->where('status', 'done'),
            'failed' => $q->where('status', 'failed'),
            'all' => $q->whereIn('status', ['done', 'failed']),
            default => $q->whereRaw('1 = 0'),
        };
        $n = $q->delete();
        \App\Models\AuditLog::record('team_request.cleared', null, ['count' => $n, 'which' => $which]);
        Notification::make()->title(\App\Support\Bn::num((string) $n).__('টি মুছে ফেলা হয়েছে'))->success()->send();
    }

    public function findTrends(): void
    {
        \App\Ai\TeamJobs::queue('scout', 'trends');
        Notification::make()->title(__('ট্রেন্ড-সন্ধানী খুঁজতে শুরু করছে; কয়েক মিনিট পরে দেখুন।'))->success()->send();
    }

    /** Where a request's result is waiting in /desk. */
    /** Every place the results of a job are: the draft piece, and the page of each planned step (marketing, images…). */
    public static function resultLinks(\App\Models\TeamRequest $r): array
    {
        $links = [];
        if ($l = self::resultLink($r)) {
            $links[$l[1]] = $l;
        }
        foreach ((array) (($r->steps ?? [])['plan'] ?? []) as $p) {
            if (($p['state'] ?? '') === 'done' && ($l = self::resultLink(new \App\Models\TeamRequest(['agent' => $p['agent'], 'job' => $p['job']])))) {
                $links[$l[1]] = $l;
            }
        }

        return array_values($links);
    }

    public static function resultLink(\App\Models\TeamRequest $r): ?array
    {
        if (str_starts_with((string) $r->link, 'post:')) {
            return [__('লেখাটি খুলুন'), \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => (int) substr($r->link, 5)])];
        }
        $where = $r->agent === 'designer' && $r->job === 'post' ? 'images' : (\App\Ai\TeamJobs::JOBS[$r->agent][$r->job][2] ?? null);

        return match ($where) {
            'posts' => [__('লেখা'), \App\Filament\Resources\Posts\PostResource::getUrl()],
            'issues' => [__('সংখ্যা'), \App\Filament\Resources\Issues\IssueResource::getUrl()],
            'today' => [__('আজকের কাজ'), Dashboard::getUrl()],
            'images' => [__('ছবি অনুমোদন'), AiImages::getUrl()],
            'marketing' => [__('মার্কেটিং'), Marketing::getUrl()],
            'letters' => [__('চিঠি'), \App\Filament\Resources\Newsletters\NewsletterResource::getUrl()],
            'contests' => [__('প্রতিযোগিতা'), \App\Filament\Resources\Contests\ContestResource::getUrl()],
            'honours' => [__('সম্মানিত লেখক'), Honours::getUrl()],
            'kids' => [__('ছোটদের হাতপাখা'), KidsCorner::getUrl()],
            'relaunch' => [__('পুরনো লেখকদের চিঠি'), RelaunchLetters::getUrl()],
            'serials' => [__('ধারাবাহিক'), \App\Filament\Resources\Serials\SerialResource::getUrl()],
            default => null,
        };
    }

    public function newStrategy(): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        \App\Ai\Strategist::request();
        Notification::make()->title(__('কৌশলবিদ কাজ শুরু করছে; কয়েক মিনিট পরে এই পাতা আবার খুলুন।'))->success()->send();
    }

    public function toggle(string $agent): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        Team::setPaused($agent, ! Team::paused($agent));
        Notification::make()->title(Team::paused($agent) ? __('থামানো হয়েছে') : __('আবার কাজ শুরু'))->success()->send();
    }

    /** What each scheduled shift does and who does it (the plan, D61). Every-minute helpers are not listed. */
    public const SHIFTS = [
        'ai-shift inbox' => ['নতুন জমা ও মন্তব্য', 'নতুন লেখার রিপোর্ট, প্রুফ, মৌলিকতা যাচাই, মন্তব্য পড়া, নতুন লেখার ছবি, দিনের মার্কেটিং পোস্ট', ['editor', 'proofreader', 'plagiarism', 'designer', 'marketing']],
        'ai-shift backlog' => ['পুরনো কাজ এগোনো', 'পুরনো জমার রিপোর্ট ও প্রুফ, পুরনো লেখার এসইও আর ছবি (প্রতিবার ১০টি করে)', ['editor', 'proofreader', 'seo', 'designer']],
        'ai-shift daily' => ['দৈনিক কাজ', 'লেখককে মনে করানো, দিনের পোস্ট; ২০ তারিখ থেকে পরের সংখ্যার প্রস্তাব, ২১ থেকে লেখকদের চিঠি', ['editor', 'marketing', 'copywriter']],
        'ai-shift weekly' => ['সাপ্তাহিক কাজ (সোমবার)', 'সপ্তাহের খবর খোঁজা, সম্পাদকের পছন্দ, সাইটের স্বাস্থ্য, কাজের মান যাচাই, সপ্তাহের পোস্ট-পরিকল্পনা', ['scout', 'editor', 'chief', 'marketing']],
        'ai-shift newsletter' => ['পাঠকদের চিঠি (শনিবার)', 'সাপ্তাহিক চিঠির খসড়া; আপনি দেখে পাঠাবেন', ['copywriter']],
        'ai-shift monthly' => ['মাসিক কাজ (১ তারিখ)', 'মাসের হিসাব, পাঠকের হিসাব, কৌশলবিদের পরিকল্পনা, সংকলন, দল-পর্যালোচনা, এসইও ঘষামাজা, লেখক পরিচিতি', ['editor', 'analyst', 'strategist', 'archivist', 'chief', 'seo', 'marketing']],
        'ai-shift chief' => ['প্রধান সম্পাদকের পরীক্ষা', 'কেউ আটকে আছে কি না, খরচ, খালি প্রকাশের দিন, প্রতিযোগিতার সময়', ['chief']],
        'ai-shift brief' => ['সকালের সারাংশ', 'আপনার জন্য আজকের এক নজর', ['chief']],
    ];

    /** The plan: what runs next, and how much work is still waiting (D61). */
    public static function plan(): array
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        if ($schedule->events() === []) {
            require base_path('routes/console.php');   // web requests don't load the schedule; read it once here
        }
        $events = collect($schedule->events())
            ->filter(fn ($e) => isset(self::SHIFTS[$e->description]))
            ->map(fn ($e) => ['key' => $e->description, 'next' => rescue(fn () => \Illuminate\Support\Carbon::instance($e->nextRunDate()), null, false)] + array_combine(['title', 'what', 'who'], self::SHIFTS[$e->description]))
            ->filter(fn ($e) => $e['next'])->sortBy('next')->values();
        $done = \App\Models\AiReview::where('status', 'done')->whereNull('blind_run')->where('report', 'like', '%"edits"%')->select('post_id');
        $queues = [
            ['পুরনো জমা লেখা, রিপোর্টের অপেক্ষায়', \App\Models\Post::where('status', 'pending')->whereNotIn('id', $done)->count(), 'editor'],
            ['অপ্রকাশিত লেখা, প্রুফের অপেক্ষায়', \App\Models\Post::where('status', 'pending')->whereNull('proof_at')->count(), 'proofreader'],
            ['ছবি ছাড়া লেখা', rescue(fn () => \App\Ai\Illustrator::candidates()->count(), 0, false), 'designer'],
            ['আঁকার জন্য আপনার চাওয়া ছবি', Setting::where('key', 'like', 'draw.%')->count(), 'designer'],
            ['এসইও তথ্য বাকি (প্রকাশিত লেখা)', \App\Models\Post::published()->whereNull('seo_done_at')->count(), 'seo'],
            ['পাঠানো হচ্ছে এমন চিঠি', \App\Models\Newsletter::whereIn('status', ['queued', 'sending'])->count(), 'copywriter'],
        ];
        $today = now('Asia/Kolkata');
        $milestones = collect([
            [$today->copy()->day(20)->startOfDay(), 'পরের মাসের সংখ্যার প্রস্তাব ও সম্পাদকীয় খসড়া', 'editor'],
            [$today->copy()->day(21)->startOfDay(), 'লেখকদের মাসিক চিঠির খসড়া', 'copywriter'],
            [$today->copy()->addMonthNoOverflow()->startOfMonth(), 'মাসের হিসাব, সংকলন, দল-পর্যালোচনা', 'chief'],
        ])->map(fn ($m) => [$m[0]->lt($today->copy()->startOfDay()) ? $m[0]->addMonthNoOverflow() : $m[0], $m[1], $m[2]])->sortBy(fn ($m) => $m[0])->values();

        return ['events' => $events, 'queues' => $queues, 'milestones' => $milestones];
    }

    /** The whole team's latest work, newest first. */
    public static function feed(int $limit = 20): \Illuminate\Support\Collection
    {
        $rows = \App\Models\AiUsage::latest('id')->limit($limit)->get();
        $titles = \App\Models\Post::whereIn('id', $rows->pluck('post_id')->filter())->pluck('title', 'id');

        return $rows->map(fn ($r) => ['at' => $r->created_at, 'agent' => Team::agentFor($r->purpose), 'purpose' => $r->purpose,
            'post' => $r->post_id, 'title' => $titles[$r->post_id] ?? null]);
    }

    public function activityAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('activity')->label(__('কী করেছে'))->icon('heroicon-o-queue-list')->size('sm')->color('gray')
            ->modalHeading(fn (array $arguments) => __(Team::AGENTS[$arguments['agent'] ?? '']['0'] ?? ''))
            ->slideOver()->modalSubmitAction(false)->modalCancelActionLabel(__('বন্ধ'))
            ->modalContent(fn (array $arguments) => \App\Filament\Support\Safe::view('filament.ai-agent-activity', ['agent' => $arguments['agent'], 'jobs' => $this->recent((string) $arguments['agent']),
                'where' => self::whereToSee((string) $arguments['agent'])]));
    }

    public function data(): array
    {
        $heartbeat = Setting::get('ai.heartbeat');
        $stats = Team::stats();
        $week = (float) \App\Models\AiUsage::where('created_at', '>=', now()->subDays(7))->sum('cost_usd');

        return [
            'stats' => $stats,
            'monthCost' => array_sum(array_column($stats, 'cost')),
            'monthJobs' => array_sum(array_column($stats, 'calls')),
            'forecast' => $week / 7 * now()->daysInMonth,
            'plan' => self::plan(),
            'trends' => \App\Ai\Knowledge::trends(),
            'strategy' => \App\Ai\Strategist::current(),
            'strategyPending' => Setting::get('strategy.requested') !== null,
            'feed' => self::feed(),
            // Needs you first (new results), then failed, running, waiting, and older finished work (UX review).
            'requests' => \App\Models\TeamRequest::latest('id')->limit(20)->get()->sortBy(fn ($r) => [match (true) {
                $r->status === 'asking' => -1, $r->status === 'done' && ! $r->seen_at => 0, $r->status === 'failed' && ! $r->seen_at => 1, $r->status === 'running' => 2,
                $r->status === 'queued' => 3, default => 4,
            }, -$r->id])->values(),
            'alerts' => EditorTask::where('type', 'alert')->where('status', 'open')->latest()->get(),
            'enabled' => (bool) config('ai.enabled'),
            'teamPaused' => AiSwitch::paused(),
            'clock' => $heartbeat ? \Illuminate\Support\Carbon::parse($heartbeat) : null,
        ];
    }
}
