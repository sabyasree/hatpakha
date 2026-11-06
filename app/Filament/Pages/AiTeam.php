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

    /** What each scheduled shift does and who does it (the plan, D61). Every-minute helpers are not listed. */
    public const SHIFTS = [
        'ai-shift inbox' => ['নতুন জমা ও মন্তব্য', 'নতুন লেখার রিপোর্ট, প্রুফ, মৌলিকতা যাচাই, মন্তব্য পড়া, নতুন লেখার ছবি, দিনের মার্কেটিং পোস্ট', ['editor', 'proofreader', 'plagiarism', 'designer', 'marketing']],
        'ai-shift backlog' => ['পুরনো কাজ এগোনো', 'পুরনো জমার রিপোর্ট ও প্রুফ, পুরনো লেখার এসইও আর ছবি (প্রতিবার ১০টি করে)', ['editor', 'proofreader', 'seo', 'designer']],
        'ai-shift daily' => ['দৈনিক কাজ', 'লেখককে মনে করানো, দিনের পোস্ট; ২০ তারিখ থেকে পরের সংখ্যার প্রস্তাব, ২১ থেকে লেখকদের চিঠি', ['editor', 'marketing', 'copywriter']],
        'ai-shift weekly' => ['সাপ্তাহিক কাজ (সোমবার)', 'সপ্তাহের খবর খোঁজা, সম্পাদকের পছন্দ, সাইটের স্বাস্থ্য, কাজের মান যাচাই, সপ্তাহের পোস্ট-পরিকল্পনা, শংসাপত্রের তালিকা', ['scout', 'editor', 'chief', 'marketing']],
        'ai-shift newsletter' => ['পাঠকদের চিঠি (শনিবার)', 'সাপ্তাহিক চিঠির খসড়া; আপনি দেখে পাঠাবেন', ['copywriter']],
        'ai-shift monthly' => ['মাসিক কাজ (১ তারিখ)', 'মাসের হিসাব, পাঠকের হিসাব, সংকলন, দল-পর্যালোচনা, এসইও ঘষামাজা, লেখক পরিচিতি', ['editor', 'analyst', 'archivist', 'chief', 'seo', 'marketing']],
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
            ->modalContent(fn (array $arguments) => view('filament.ai-agent-activity', ['agent' => $arguments['agent'], 'jobs' => $this->recent((string) $arguments['agent']),
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
            'feed' => self::feed(),
            'alerts' => EditorTask::where('type', 'alert')->where('status', 'open')->latest()->get(),
            'enabled' => (bool) config('ai.enabled'),
            'teamPaused' => AiSwitch::paused(),
            'clock' => $heartbeat ? \Illuminate\Support\Carbon::parse($heartbeat) : null,
        ];
    }
}
