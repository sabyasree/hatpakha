<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Post;
use App\Models\SocialPost;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The marketing desk (D51): the marketing expert's ready-to-post text for Facebook, WhatsApp and
 * Instagram, with the graphic designer's cards to download as pictures. The owner copies, posts by
 * hand, and ticks "posted". Nothing is posted automatically.
 */
class Marketing extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;
    use \Livewire\WithFileUploads;

    protected string $view = 'filament.pages.marketing';

    /** The card the browser drew, as a JPEG, on its way to Instagram (owner, 2026-11-12). */
    public $cardJpeg = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'মার্কেটিং';

    protected static ?string $title = 'মার্কেটিং';

    protected static ?int $navigationSort = 3;

    public string $filter = 'ready';

    /** "নতুন পোস্ট তৈরি করুন" (D57): ask the marketing expert for a post right now. */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('create')->label(__('নতুন পোস্ট তৈরি করুন'))->icon('heroicon-o-sparkles')
                ->modalHeading(__('মার্কেটিং বিশেষজ্ঞকে কী লিখতে বলবেন?'))
                ->schema([
                    \Filament\Forms\Components\Radio::make('kind')->label(__('কী নিয়ে'))->required()->default('archive')->live()->options([
                        'archive' => __('আর্কাইভ থেকে একটি ভালো লেখা (এআই বাছবে)'),
                        'piece' => __('আমার বাছা একটি লেখা'),
                        'issue' => __('একটি সংখ্যা'),
                        'weekly' => __('এই সপ্তাহের পরিকল্পনা'),
                        'next_week' => __('পরের সপ্তাহের পরিকল্পনা'),
                        'newsletter' => __('সাপ্তাহিক চিঠি (ই-মেল নিউজলেটার)'),
                        'custom' => __('নিজের বিষয় (শুভেচ্ছা, ঘোষণা, প্রশ্ন…)'),
                    ]),
                    \Filament\Forms\Components\Select::make('post_id')->label(__('লেখা'))->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Post::published()->where('title', 'like', '%'.$search.'%')->latest('published_at')->limit(30)->pluck('title', 'id'))
                        ->getOptionLabelUsing(fn ($value) => Post::find($value)?->title)
                        ->visible(fn ($get) => $get('kind') === 'piece')->required(fn ($get) => $get('kind') === 'piece'),
                    \Filament\Forms\Components\Select::make('issue_id')->label(__('সংখ্যা'))
                        ->options(fn () => Issue::where('status', 'published')->orderByDesc('slug')->pluck('title', 'id'))
                        ->visible(fn ($get) => $get('kind') === 'issue')->required(fn ($get) => $get('kind') === 'issue'),
                    \Filament\Forms\Components\Textarea::make('topic')->label(__('বিষয়'))->rows(3)->maxLength(1000)
                        ->placeholder(__('যেমন: দীপাবলির শুভেচ্ছা; পুজো সংখ্যার জন্য লেখা পাঠানোর শেষ তারিখ ২০ সেপ্টেম্বর'))
                        ->visible(fn ($get) => $get('kind') === 'custom')->required(fn ($get) => $get('kind') === 'custom'),
                ])
                ->modalSubmitActionLabel(__('তৈরি করুন'))
                ->action(function (array $data) {
                    $made = match ($data['kind']) {
                        'piece' => ($p = Post::published()->find($data['post_id'] ?? 0)) ? \App\Ai\Marketer::forPiece($p, force: true) : null,
                        'issue' => ($i = Issue::find($data['issue_id'] ?? 0)) ? \App\Ai\Marketer::forIssue($i, force: true) : null,
                        'weekly' => \App\Ai\Marketer::weekly(force: true),
                        'next_week' => \App\Ai\Marketer::weekly(force: true, next: true),
                        'newsletter' => \App\Ai\EditorJobs::newsletter(force: true),
                        'custom' => \App\Ai\Marketer::custom((string) $data['topic']),
                        default => self::archivePick(),
                    };
                    $this->filter = 'ready';
                    Notification::make()->title($made ? __('তৈরি হয়েছে, নিচে দেখুন') : __('এখন হলো না; এআই থামানো আছে কি না দেখুন'))->{$made ? 'success' : 'warning'}()->send();
                }),
        ];
    }

    private static function archivePick(): ?SocialPost
    {
        $p = Post::published()->whereNotIn('id', SocialPost::where('created_at', '>=', now()->subMonths(6))->whereNotNull('subject_id')->select('subject_id'))
            ->where('published_at', '<', now()->subMonths(3))->whereHas('section', fn ($q) => $q->where('slug', '!=', \App\Models\Section::KIDS))->inRandomOrder()->first();

        return $p ? \App\Ai\Marketer::forPiece($p, 'archive', true) : null;
    }

    public static function getNavigationBadge(): ?string
    {
        return ($n = SocialPost::where('status', 'ready')->where('kind', '!=', 'weekly')->count()) ? (string) $n : null;
    }

    /** "পরের সপ্তাহের পরিকল্পনা" (D70): plan ahead without waiting for Monday. */
    public function nextWeek(): void
    {
        $made = \App\Ai\Marketer::weekly(force: true, next: true);
        Notification::make()->title($made ? __('পরের সপ্তাহের পরিকল্পনা তৈরি হয়েছে') : __('এখন হলো না; এআই থামানো আছে কি না দেখুন'))->{$made ? 'success' : 'warning'}()->send();
    }

    public function posted(int $id): void
    {
        $s = SocialPost::findOrFail($id);
        $s->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()])->save();
        AuditLog::record('marketing.posted', null, ['social_post' => $s->id]);
        self::undoable($id, __('"পোস্ট করা / বাদ"-এ সরানো হলো'));
    }

    /** Facebook Page (owner, 2026-11-12): post this one now. */
    public function facebookNow(int $id): void
    {
        [$ok, $msg] = \App\Support\FacebookPage::publish(SocialPost::findOrFail($id), auth()->id());
        $ok ? Notification::make()->title(__('ফেসবুক পেজে পোস্ট হয়েছে'))->success()->send()
            : Notification::make()->title(__('ফেসবুকে যায়নি'))->body($msg)->danger()->send();
    }

    /** Instagram: keep the card the browser just uploaded with the post (also done ahead of time for automatic posts). */
    public function storeCard(int $id): bool
    {
        $s = SocialPost::findOrFail($id);
        $ok = false;
        if ($this->cardJpeg instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $ok = \App\Support\InstagramPage::saveCard($s, $this->cardJpeg->getRealPath()) !== null;
            $this->cardJpeg->delete();
        }
        $this->cardJpeg = null;

        return $ok;
    }

    /**
     * D170 (owner): post one particular card (share, quote, or a new quote card) to Facebook or Instagram. The browser
     * has just drawn it and uploaded it as `cardJpeg`.
     */
    public function cardPost(int $id, string $key, string $net): void
    {
        $s = SocialPost::findOrFail($id);
        if (! preg_match('/^(share|q\d{1,2})$/', $key) || ! in_array($net, ['facebook', 'instagram'], true)) {
            return;
        }
        $path = null;
        if ($this->cardJpeg instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $path = \App\Support\InstagramPage::saveCard($s, $this->cardJpeg->getRealPath(), $key);
            $this->cardJpeg->delete();
        }
        $this->cardJpeg = null;
        if (! $path) {
            Notification::make()->title(__('কার্ডের ছবি রাখা গেল না'))->danger()->send();

            return;
        }
        [$ok, $msg] = $net === 'facebook'
            ? \App\Support\FacebookPage::publishCard($s->fresh(), $path, $key, auth()->id())
            : \App\Support\InstagramPage::publishCard($s->fresh(), $path, $key, auth()->id());
        $ok ? Notification::make()->title($net === 'facebook' ? __('ছবিসহ ফেসবুক পেজে পোস্ট হয়েছে') : __('ইনস্টাগ্রামে পোস্ট হয়েছে'))->success()->send()
            : Notification::make()->title($net === 'facebook' ? __('ফেসবুকে যায়নি') : __('ইনস্টাগ্রামে যায়নি'))->body($msg)->danger()->send();
    }

    /** D170: more quote cards from the same piece (lines copied exactly from it). */
    public function moreQuotes(int $id): void
    {
        $n = \App\Ai\Marketer::moreQuotes(SocialPost::findOrFail($id));
        Notification::make()->title($n ? __(':n টি নতুন উদ্ধৃতি কার্ড তৈরি হয়েছে', ['n' => \App\Support\Bn::num((string) $n)]) : __('লেখা থেকে আর নতুন লাইন পাওয়া গেল না'))
            ->{$n ? 'success' : 'warning'}()->send();
    }

    /** D170: drop an extra quote card the owner doesn't want. */
    public function removeQuote(int $id, int $i): void
    {
        $s = SocialPost::findOrFail($id);
        $texts = (array) $s->texts;
        $quotes = array_values((array) ($texts['quotes'] ?? []));
        if (isset($quotes[$i])) {
            array_splice($quotes, $i, 1);
            $texts['quotes'] = $quotes;
            $s->forceFill(['texts' => $texts])->save();
        }
    }

    /** Instagram: post it now, with the card just uploaded (or the piece's own picture). */
    public function instagramNow(int $id, bool $withCard = false): void
    {
        if ($withCard && ! $this->storeCard($id)) {
            Notification::make()->title(__('কার্ডের ছবি রাখা গেল না'))->danger()->send();

            return;
        }
        [$ok, $msg] = \App\Support\InstagramPage::publish(SocialPost::findOrFail($id), auth()->id());
        $ok ? Notification::make()->title(__('ইনস্টাগ্রামে পোস্ট হয়েছে'))->success()->send()
            : Notification::make()->title(__('ইনস্টাগ্রামে যায়নি'))->body($msg)->danger()->send();
    }

    /** Post it at the owner's chosen time (Kolkata); the scheduler checks every 5 minutes. */
    public function facebookSchedule(int $id, string $at): void
    {
        $s = SocialPost::findOrFail($id);
        $when = rescue(fn () => \Illuminate\Support\Carbon::parse($at, 'Asia/Kolkata'), null, false);
        if (! $when || $when->isPast()) {
            Notification::make()->title(__('ভবিষ্যতের একটি সময় বেছে নিন'))->warning()->send();

            return;
        }
        $s->forceFill(['fb_scheduled_at' => $when->utc(), 'fb_error' => null])->save();
        AuditLog::record('marketing.facebook_scheduled', null, ['social_post' => $s->id, 'at' => $when->toIso8601String()]);
        Notification::make()->title(__('সময়মতো ফেসবুকে যাবে'))->success()->send();
    }

    public function facebookUnschedule(int $id): void
    {
        SocialPost::findOrFail($id)->forceFill(['fb_scheduled_at' => null])->save();
        Notification::make()->title(__('সময় বাতিল হয়েছে'))->send();
    }

    /** Automatic mode: the owner's switch (D42), admins only. */
    public function toggleFacebookAuto(): void
    {
        if (! \App\Filament\Support\Deleting::isAdmin()) {
            return;
        }
        $on = ! \App\Support\FacebookPage::auto();
        \App\Models\Setting::put(\App\Support\FacebookPage::AUTO, $on ? '1' : '0');
        AuditLog::record('marketing.facebook_auto', null, ['on' => $on]);
        Notification::make()->title($on ? __('নিজে থেকে পোস্ট চালু হয়েছে') : __('নিজে থেকে পোস্ট বন্ধ হয়েছে'))->success()->send();
    }

    /** Owner (2026-11-14): "I can't see the weekly report": make it now, without waiting for Sunday night. Admins only. */
    public function reviewNow(): void
    {
        if (! \App\Filament\Support\Deleting::isAdmin()) {
            return;
        }
        \App\Support\MarketingStats::collect();
        \App\Support\MarketingAnalyst::review();
        Notification::make()->title(__('সপ্তাহের হিসাব তৈরি হয়েছে'))->success()->send();
    }

    /** Owner: "how to check you can comment on Facebook?" Asks Facebook what the Page's key may do. */
    public array $fbCheck = [];

    public function checkFacebook(): void
    {
        $this->fbCheck = \App\Support\FacebookPage::permissions();
    }

    public function dismiss(int $id): void
    {
        SocialPost::findOrFail($id)->forceFill(['status' => 'dismissed'])->save();
        self::undoable($id, __('বাদ দেওয়া হলো'));
    }

    /** Delete a weekly plan or a post for good (owner, 2026-11-09). Admins only, like every delete in the desk. */
    public function deleteSocial(int $id): void
    {
        if (! \App\Filament\Support\Deleting::isAdmin()) {
            return;
        }
        $s = SocialPost::findOrFail($id);
        AuditLog::record('marketing.deleted', null, ['social_post' => $s->id, 'kind' => $s->kind, 'title' => mb_substr((string) $s->title, 0, 120)]);
        $s->delete();
        Notification::make()->title(__('মুছে ফেলা হয়েছে'))->success()->send();
    }

    /** Clears the "পোস্ট করা / বাদ" list in one go. */
    public function clearDone(): void
    {
        if (! \App\Filament\Support\Deleting::isAdmin()) {
            return;
        }
        $n = SocialPost::where('kind', '!=', 'weekly')->where('status', '!=', 'ready')->delete();
        AuditLog::record('marketing.cleared', null, ['count' => $n]);
        Notification::make()->title(\App\Support\Bn::num((string) $n).__('টি মুছে ফেলা হয়েছে'))->success()->send();
    }

    /** Undo (D70): back to "পোস্টের জন্য তৈরি". From the notification, or from the "পোস্ট করা / বাদ" list. */
    #[\Livewire\Attributes\On('mk-restore')]
    public function restore(int $id): void
    {
        SocialPost::findOrFail($id)->forceFill(['status' => 'ready', 'posted_at' => null, 'posted_by' => null])->save();
        Notification::make()->title(__('ফিরিয়ে আনা হয়েছে'))->success()->send();
    }

    private static function undoable(int $id, string $title): void
    {
        Notification::make()->title($title)->success()->actions([
            \Filament\Actions\Action::make('undo')->label(__('ফিরিয়ে আনুন'))->button()->close()->dispatch('mk-restore', ['id' => $id]),
        ])->send();
    }

    public function data(): array
    {
        // The weekly plan is a guide, pinned on top whatever is ticked (D70); the list below holds the posts.
        $plans = SocialPost::where('kind', 'weekly')->latest()->limit(2)->get();
        $items = SocialPost::with('subject')->where('kind', '!=', 'weekly')->where('status', $this->filter === 'done' ? '!=' : '=', 'ready')->latest()->limit(40)->get();

        $newsletter = \App\Models\Newsletter::where('status', 'draft')->latest()->first();
        $readers = \App\Models\NewsletterSubscriber::whereNotNull('consented_at')->where('suppressed', false)->whereNotIn('status', ['unsubscribed', 'bounced'])->count();

        // D182: what goes out today and tomorrow, with what readers did
        $from = now('Asia/Kolkata')->startOfDay()->utc();
        $schedule = SocialPost::whereBetween('slot_at', [$from, $from->copy()->addDays(2)])->orderBy('slot_at')->limit(30)->get()
            ->map(fn (SocialPost $s) => ['s' => $s, 'sum' => \App\Support\MarketingStats::sum($s), 'where' => self::where($s)]);

        return ['schedule' => $schedule, 'whatsapp' => \App\Support\MarketingPlan::whatsapp(), 'lessons' => \App\Support\MarketingStats::saved(), 'review' => \App\Support\MarketingAnalyst::saved(), 'plans' => $plans, 'newsletter' => $newsletter, 'readers' => $readers, 'items' => $items->map(fn (SocialPost $s) => ['s' => $s, 'card' => self::card($s), 'link' => self::link($s)])];
    }

    /** The page a post is about (D87): the piece, the issue, else the home page. */
    public static function link(SocialPost $s): string
    {
        $subject = $s->subject;
        if (filled($s->texts['link'] ?? null) && str_starts_with((string) $s->texts['link'], url('/'))) {
            return (string) $s->texts['link'];   // D175: পাঠকের কলম posts point at the week's page
        }

        return $subject instanceof Post || $subject instanceof Issue ? $subject->url() : \App\Support\Url::to('/');
    }

    /** What the designer's card shows: only real data (title, writer, exact quote, picture). */
    public static function card(SocialPost $s): ?array
    {
        $subject = $s->subject;
        if ($s->kind === 'kolom') {   // D175: the week's theme, or a reader's line, as a quote card
            return ['title' => $s->title, 'by' => 'পাঠকের কলম', 'section' => 'পাঠকের কলম', 'quote' => (string) ($s->texts['quote'] ?? ''), 'image' => null];
        }
        if ($subject instanceof Post) {
            return [
                'title' => $subject->title,
                'by' => $subject->authors->first()?->publicName() ?? '',
                'section' => $subject->section?->name_bn ?? '',
                'quote' => $s->texts['quote'] ?? '',
                'image' => $subject->featured_image_path ? asset(ltrim($subject->featured_image_path, '/')) : null,
            ];
        }
        if ($s->kind === 'poster' && filled($s->texts['image'] ?? null)) {   // the designer's poster: picture + headline + line
            return ['title' => $s->title, 'by' => 'হাতপাখা', 'section' => '', 'quote' => $s->texts['quote'] ?? '', 'image' => asset(ltrim($s->texts['image'], '/'))];
        }
                if ($subject instanceof Issue) {
            return ['title' => $subject->title, 'by' => 'সংখ্যা', 'section' => '', 'quote' => '', 'image' => $subject->cover_path ? asset(ltrim($subject->cover_path, '/')) : null];
        }

        return null;
    }

    /**
     * Owner (2026-11-14: "it shows posted but I can't see it on Facebook or Instagram. How to see details? Is it really
     * posted? What is the error?"): for one post, what happened on each network, in plain words, with the address
     * where it can be seen, when it went and whether a person or the plan sent it.
     *
     * @return list<array{net: string, text: string, link: ?string, bad: bool}>
     */
    public static function where(SocialPost $s): array
    {
        $log = AuditLog::whereIn('action', ['marketing.facebook', 'marketing.instagram'])->where('meta->social_post', $s->id)->latest('id')->get()->keyBy('action');
        $when = function (string $action) use ($log): string {
            $l = $log[$action] ?? null;

            return $l ? ' ('.\App\Support\Bn::num($l->created_at->timezone('Asia/Kolkata')->format('d/m H:i')).', '.($l->user_id ? __('হাতে') : __('নিজে থেকে')).')' : '';
        };
        $waiting = $s->status === 'ready' ? ($s->format !== 'link' && ! $s->media ? __('ছবি তৈরি হচ্ছে') : __('সময়ের অপেক্ষায়')) : __('যায়নি');
        $out = [];
        $fbState = $s->texts['fb_state'] ?? null;
        $out[] = ['net' => 'Facebook'] + match (true) {
            blank($s->texts['facebook'] ?? null) => ['text' => __('এখানে যাবে না'), 'link' => null, 'bad' => false],
            filled($s->fb_error) => ['text' => __('যায়নি').': '.mb_substr((string) $s->fb_error, 0, 160), 'link' => null, 'bad' => true],
            (bool) $s->fb_post_id && $s->format === 'reel' && $s->media && $fbState !== 'ready' => ['text' => __('ফেসবুক নিয়েছে, ভিডিও তৈরি করছে').$when('marketing.facebook'), 'link' => \App\Support\FacebookPage::linkTo($s), 'bad' => false],
            (bool) $s->fb_post_id => ['text' => __('পোস্ট হয়েছে').$when('marketing.facebook'), 'link' => \App\Support\FacebookPage::linkTo($s), 'bad' => false],
            default => ['text' => $waiting, 'link' => null, 'bad' => false],
        };
        $out[] = ['net' => 'Instagram'] + match (true) {
            blank($s->texts['instagram'] ?? null) => ['text' => __('এখানে যাবে না'), 'link' => null, 'bad' => false],
            filled($s->ig_error) => ['text' => __('যায়নি').': '.mb_substr((string) $s->ig_error, 0, 160), 'link' => null, 'bad' => true],
            (bool) $s->ig_media_id => ['text' => __('পোস্ট হয়েছে').$when('marketing.instagram'), 'link' => $s->ig_permalink, 'bad' => false],
            (bool) $s->ig_container => ['text' => __('ইনস্টাগ্রাম ভিডিও তৈরি করছে'), 'link' => null, 'bad' => false],
            default => ['text' => $waiting, 'link' => null, 'bad' => false],
        };

        return $out;
    }
}
