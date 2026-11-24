<?php

namespace App\Support;

use App\Ai\EditorPackage;
use App\Enums\Role;
use App\Models\Comment;
use App\Models\EditorTask;
use App\Models\Post;
use App\Models\Serial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * D279 (owner: "a new serial proposal and a new episode: I just get a number beside the menu and can't tell which";
 * "as admin I should not spend time finding where things are"; three UX reviews): everything waiting for a person,
 * in one place. আজকের কাজ lists it, one line per thing, most urgent first; the menu's numbers count the same things
 * (the count functions below), so the two never disagree. Nothing here decides anything (D42): every line opens the
 * item or offers the same button the item's own page has.
 */
final class DeskInbox
{
    public const GROUPS = ['today' => 'আজই', 'week' => 'এই সপ্তাহে', 'fyi' => 'জেনে রাখুন'];

    /** Families of work, for the small tag at the start of a line (logo palette only). */
    public const TAGS = [
        'serial' => ['ধারাবাহিক', '#EFC08D'], 'piece' => ['লেখা', '#DDF1FA'], 'question' => ['প্রশ্ন', '#F08D90'],
        'comment' => ['মন্তব্য', '#FBEBD8'], 'reader' => ['পাঠক', '#FBEBD8'], 'issue' => ['সংখ্যা', '#EFC08D'],
        'ai' => ['এআই তৈরি', '#DDF1FA'], 'mail' => ['চিঠি', '#DDF1FA'], 'site' => ['সাইট', '#F08D90'],
    ];

    // ── Counts (the menu's numbers) ─────────────────────────────────────────────────────────────

    /** Writers' waiting pieces that are not serial episodes. */
    public static function newPieces(): int
    {
        return self::waiting()->whereNull('serial_id')->count();
    }

    public static function newEpisodes(): int
    {
        return self::waiting()->whereNotNull('serial_id')->whereHas('serial', fn ($q) => $q->where('status', '!=', 'proposed'))->count();
    }

    public static function serialProposals(): int
    {
        return Serial::where('status', 'proposed')->count();
    }

    public static function objections(): int
    {
        return self::waiting()->where('review_note', 'like', EditorPackage::OBJECTED.'%')->count();
    }

    /** The লেখা menu: everything writers sent and are waiting on. */
    public static function piecesTooltip(): ?string
    {
        return self::join([
            [self::newPieces(), 'টি নতুন লেখা'], [self::newEpisodes(), 'টি ধারাবাহিকের পর্ব'],
            [self::waiting()->whereNotNull('serial_id')->whereHas('serial', fn ($q) => $q->where('status', 'proposed'))->count(), 'টি নতুন ধারাবাহিকের প্রথম পর্ব'],
            [self::objections(), 'টিতে লেখকের আপত্তি'],
        ]);
    }

    /** The ধারাবাহিক menu: proposals and episodes waiting for a decision. */
    public static function serialCount(): int
    {
        return self::serialProposals() + self::newEpisodes();
    }

    public static function serialTooltip(): ?string
    {
        return self::join([[self::serialProposals(), 'টি নতুন প্রস্তাব'], [self::newEpisodes(), 'টি নতুন পর্ব']]);
    }

    /** "২টি নতুন পর্ব · ১টি প্রস্তাব" from [[count, words], …], leaving out the zeros. */
    public static function join(array $parts): ?string
    {
        $s = collect($parts)->filter(fn ($p) => $p[0] > 0)->map(fn ($p) => Bn::num((string) $p[0]).__($p[1]))->implode(' · ');

        return $s !== '' ? $s : null;
    }

    /** A writer's piece that waits for the editor (old WordPress submissions are apart, D16). */
    private static function waiting()
    {
        return Post::where('status', 'pending')->whereNotNull('submitted_by');
    }

    // ── The lines of আজকের কাজ ──────────────────────────────────────────────────────────────────

    /** @return Collection<int, array> every line, most urgent first */
    public static function items(): Collection
    {
        $admin = auth()->user()?->hasRole(Role::Admin) ?? false;
        $out = collect();
        $add = function (array $row) use ($out) {
            $out->push($row + ['due' => null, 'soon' => false, 'url' => null, 'button' => __('খুলুন'), 'action' => null, 'note' => null, 'when' => now()]);
        };
        // each source is safe on its own: one that fails leaves the rest of the page (D191)
        $safe = fn (callable $f) => rescue($f, null, true);

        $safe(fn () => self::pieceLines($add));
        $safe(fn () => self::proposalLines($add));
        $safe(fn () => self::commentLines($add));
        $safe(fn () => self::questionLines($add));
        if ($admin) {
            $safe(fn () => self::adminLines($add));
        }
        $safe(fn () => self::taskLines($add, $admin));
        $safe(fn () => self::fyiLines($add, $admin));

        $rank = ['today' => 0, 'week' => 1, 'fyi' => 2];

        return $out->sortBy(fn ($r) => sprintf('%d-%d-%015d', $rank[$r['group']], $r['soon'] ? 0 : 1, $r['when']->timestamp))->values();
    }

    /** @return array{today:int, week:int, fyi:int} */
    public static function counts(?Collection $items = null): array
    {
        $items ??= self::items();

        return ['today' => $items->where('group', 'today')->count(), 'week' => $items->where('group', 'week')->count(), 'fyi' => $items->where('group', 'fyi')->count()];
    }

    private static function pieceLines(callable $add): void
    {
        $posts = self::waiting()->with(['authors', 'section', 'serial', 'latestAiReview'])->orderBy('submitted_at')->limit(40)->get();
        foreach ($posts as $p) {
            if ($p->serial_id && $p->serial?->status === 'proposed') {
                continue;   // a new serial's first episode is one line with its proposal (below)
            }
            $who = $p->authors->first()?->publicName() ?? '';
            $arrived = $p->submitted_at ?? $p->created_at;
            $ai = self::aiLine($p);
            $url = \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $p]);
            if (str_starts_with((string) $p->review_note, EditorPackage::OBJECTED)) {
                $add(['key' => 'p'.$p->id, 'group' => 'today', 'soon' => true, 'tag' => 'piece', 'title' => __('লেখক বদলে আপত্তি করেছেন').': ‘'.$p->title.'’', 'who' => $who,
                    'sub' => '“'.\Illuminate\Support\Str::limit(\Illuminate\Support\Str::after($p->review_note, EditorPackage::OBJECTED), 120).'” · '.self::ago($arrived),
                    'url' => $url, 'button' => __('আবার দেখুন'), 'when' => $arrived]);

                continue;
            }
            if ($p->serial_id && $p->serial) {
                $due = rescue(fn () => $p->serial->nextEpisodeAt($p->id), null, false);
                $soon = $due && $due->lte(now()->addDays(3));   // due by Saturday when Wednesday's reminder goes
                $add(['key' => 'p'.$p->id, 'group' => $soon ? 'today' : 'week', 'tag' => 'serial',
                    'title' => __('নতুন পর্ব').': ‘'.$p->serial->title.'’ '.__('পর্ব').' '.Bn::num((string) $p->episode_no), 'who' => $who,
                    'sub' => trim(__('এসেছে').' '.self::ago($arrived).($ai ? ' · '.$ai : '')),
                    'due' => $due ? self::day($due).' '.__('প্রকাশের দিন') : null, 'soon' => $soon,
                    'url' => $url, 'button' => __('পর্বটি দেখুন'), 'when' => $arrived]);

                continue;
            }
            $old = $arrived && $arrived->lte(now()->subDays(3));
            $add(['key' => 'p'.$p->id, 'group' => $old ? 'today' : 'week', 'tag' => 'piece',
                'title' => __('নতুন লেখা').': ‘'.$p->title.'’', 'who' => $who,
                'sub' => trim(($p->section?->name_bn ? $p->section->name_bn.' · ' : '').__('এসেছে').' '.self::ago($arrived).($ai ? ' · '.$ai : '')),
                'url' => $url, 'button' => __('দেখুন ও সিদ্ধান্ত'), 'when' => $arrived]);
        }
    }

    /** A new serial: the writer's proposal and its first episode, decided together on the episode's page. */
    private static function proposalLines(callable $add): void
    {
        foreach (Serial::where('status', 'proposed')->with('user')->latest()->limit(20)->get() as $s) {
            $first = $s->episodes()->whereIn('status', ['pending', 'draft'])->orderBy('episode_no')->first();
            $writer = $s->user_id ? (string) $s->user?->name : __('এআই দল');
            $plan = $s->planned_count ? ' · '.Bn::num((string) $s->planned_count).__(' পর্বের পরিকল্পনা') : '';
            $add(['key' => 's'.$s->id, 'group' => 'week', 'tag' => 'serial',
                'title' => __('নতুন ধারাবাহিকের প্রস্তাব').': ‘'.$s->title.'’', 'who' => $writer,
                'sub' => trim(($first ? __('পর্ব ১ সহ').' · ' : '').__('এসেছে').' '.self::ago($s->created_at).$plan),
                'url' => $first ? \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $first]) : \App\Filament\Resources\Serials\SerialResource::getUrl('edit', ['record' => $s]),
                'button' => $first ? __('প্রস্তাব ও পর্ব ১ পড়ুন') : __('প্রস্তাব দেখুন'), 'when' => $s->created_at]);
        }
    }

    private static function commentLines(callable $add): void
    {
        $ok = Comment::where('status', 'pending')->where('ai_verdict', 'ok')->count();
        if ($ok) {
            $add(['key' => 'c-ok', 'group' => 'week', 'tag' => 'comment', 'title' => __('এআই-এর মতে ঠিক আছে এমন').' '.Bn::num((string) $ok).__('টি মন্তব্য'), 'who' => '',
                'sub' => __('পড়ে নিতে চাইলে “মন্তব্য” পাতায়; নইলে একসঙ্গে প্রকাশ করুন'), 'action' => ['approveAiOkComments'], 'button' => __('একসঙ্গে প্রকাশ'),
                'url' => \App\Filament\Resources\Comments\CommentResource::getUrl('index'), 'when' => now()]);
        }
        foreach (Comment::with('post')->where('status', 'pending')->where(fn ($q) => $q->whereNull('ai_verdict')->orWhere('ai_verdict', '!=', 'ok'))->oldest()->limit(10)->get() as $c) {
            $add(['key' => 'c'.$c->id, 'group' => 'week', 'tag' => 'comment', 'title' => '“'.\Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', (string) $c->body), 110).'”',
                'who' => (string) $c->author_name, 'sub' => '‘'.($c->post?->title ?? '').'’ · '.self::ago($c->created_at)
                    .($c->ai_verdict ? ' · '.__('এআই').': '.__(['spam' => 'স্প্যাম মনে হচ্ছে', 'check' => 'দেখে নিন'][$c->ai_verdict] ?? '') : ''),
                'action' => ['comment', $c->id], 'button' => __('প্রকাশ'), 'when' => $c->created_at]);
        }
    }

    private static function questionLines(callable $add): void
    {
        foreach (EditorTask::open('question')->oldest()->limit(15)->get() as $t) {
            $old = $t->created_at->lte(now()->subDays(2));
            $add(['key' => 't'.$t->id, 'group' => $old ? 'today' : 'week', 'tag' => 'question',
                'title' => __('লেখকের প্রশ্ন').': “'.\Illuminate\Support\Str::limit((string) ($t->payload['question'] ?? $t->title), 110).'”',
                'who' => (string) ($t->payload['name'] ?? ''), 'sub' => self::ago($t->created_at), 'action' => ['replyQuestion', $t->id], 'button' => __('উত্তর দিন'), 'when' => $t->created_at]);
        }
    }

    private static function adminLines(callable $add): void
    {
        // the issue: the AI editor's proposal, or a reminder to make one
        foreach (\App\Models\Issue::where('status', 'proposed')->latest('updated_at')->limit(2)->get() as $i) {
            $add(['key' => 'i'.$i->id, 'group' => now('Asia/Kolkata')->day >= 25 ? 'today' : 'week', 'tag' => 'issue', 'title' => __('সংখ্যার প্রস্তাব').': '.$i->title, 'who' => __('এআই সম্পাদক'),
                'sub' => Bn::num((string) count((array) ($i->ai_plan['post_ids'] ?? []))).__('টি লেখা বাছা হয়েছে · দেখে অনুমোদন দিন'),
                'url' => \App\Filament\Resources\Issues\IssueResource::getUrl('edit', ['record' => $i]), 'button' => __('সংখ্যা দেখুন'), 'when' => $i->updated_at]);
        }
        foreach (rescue(fn () => \App\Ai\IssueWatch::current()->filter(fn ($i) => \App\Ai\IssueWatch::needs($i)), collect(), false) as $i) {
            $st = \App\Ai\IssueWatch::status($i);
            $what = ['missing' => __('সম্পাদকীয় এখনও নেই'), 'draft' => __('সম্পাদকীয় খসড়া, প্রকাশ বাকি')][$st['editorial']] ?? __('কিছু কাজ বাকি');
            $add(['key' => 'iw'.$i->id, 'group' => 'week', 'tag' => 'issue', 'title' => $i->title.': '.$what, 'who' => '',
                'sub' => $i->published_at ? __('প্রকাশ').' '.self::day($i->published_at) : '', 'url' => \App\Filament\Pages\Editorials::getUrl(), 'button' => __('সম্পাদকীয় দেখুন'), 'when' => $i->updated_at ?? now()]);
        }
        if ($n = \App\Filament\Pages\AiImages::waiting()->count()) {
            $add(['key' => 'img', 'group' => 'week', 'tag' => 'ai', 'title' => Bn::num((string) $n).__('টি লেখার জন্য এআই-এর আঁকা ছবি'), 'who' => '',
                'sub' => __('পছন্দ হলে বসান, না হলে বাদ দিন'), 'url' => \App\Filament\Pages\AiImages::getUrl(), 'button' => __('ছবিগুলো দেখুন')]);
        }
        foreach (\App\Models\ContactMessage::where('status', 'new')->oldest()->limit(10)->get() as $m) {
            $add(['key' => 'm'.$m->id, 'group' => $m->created_at->lte(now()->subDays(2)) ? 'today' : 'week', 'tag' => 'reader',
                'title' => ($m->kind === 'advertise' ? __('বিজ্ঞাপনের বার্তা') : __('পাঠকের বার্তা')).': “'.\Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', (string) $m->body), 90).'”',
                'who' => (string) $m->name, 'sub' => self::ago($m->created_at), 'url' => \App\Filament\Resources\ContactMessages\ContactMessageResource::getUrl('index'), 'button' => __('পড়ুন'), 'when' => $m->created_at]);
        }
        if ($n = \App\Models\PieceReview::where('status', 'done')->whereNull('desk_read_at')->count()) {
            $add(['key' => 'pr', 'group' => 'week', 'tag' => 'piece', 'title' => __('যাচাইকারীর মত এসেছে').': '.Bn::num((string) $n).__('টি লেখায়'), 'who' => '',
                'sub' => __('বাইরের পাঠকের মতামত'), 'url' => \App\Filament\Resources\PieceReviews\PieceReviewResource::getUrl('index'), 'button' => __('পড়ুন')]);
        }
        if ($n = \App\Models\Newsletter::where('status', 'draft')->count()) {
            $add(['key' => 'nl', 'group' => 'week', 'tag' => 'mail', 'title' => __('চিঠির খসড়া তৈরি').': '.Bn::num((string) $n).__('টি'), 'who' => __('এআই'),
                'sub' => __('পড়ে পাঠান, বা যেমন আছে রেখে দিন'), 'url' => \App\Filament\Resources\Newsletters\NewsletterResource::getUrl('index'), 'button' => __('দেখুন ও পাঠান')]);
        }
        if ($n = \App\Models\WriterInvite::where('status', 'drafted')->count()) {
            $add(['key' => 'wi', 'group' => 'week', 'tag' => 'mail', 'title' => __('লেখকদের আমন্ত্রণ, পাঠানোর অপেক্ষায়').': '.Bn::num((string) $n).__('টি'), 'who' => __('এআই'),
                'sub' => __('প্রতিটি চিঠি পড়ে নিতে পারেন'), 'url' => \App\Filament\Resources\WriterInvites\WriterInviteResource::getUrl('index'), 'button' => __('দেখুন')]);
        }
        if ($n = Post::where('status', 'pending')->whereNull('submitted_by')->where('relaunch_status', 'yes')->count()) {
            $add(['key' => 'rl', 'group' => 'week', 'tag' => 'piece', 'title' => __('পুরনো লেখক রাজি হয়েছেন').': '.Bn::num((string) $n).__('টি পুরনো লেখা'), 'who' => '',
                'sub' => __('পড়ে সিদ্ধান্ত দিন'), 'url' => \App\Filament\Resources\Posts\PostResource::getUrl('index', ['tab' => 'backlog_yes']), 'button' => __('দেখুন')]);
        }
        if (\App\Models\ReaderPrompt::where('status', 'closed')->whereHas('entries', fn ($q) => $q->where('status', 'picked'))->exists()) {
            $add(['key' => 'kolom', 'group' => 'week', 'tag' => 'reader', 'title' => __('পাঠকের কলম: এ সপ্তাহের বাছাই তৈরি'), 'who' => __('এআই'),
                'sub' => __('দেখে প্রকাশ করুন'), 'url' => \App\Filament\Resources\ReaderEntries\ReaderEntryResource::getUrl('index'), 'button' => __('বাছাই দেখুন')]);
        }
        $kids = collect(rescue(fn () => \App\Support\KidsAlphabet::state(), [], false))->filter(fn ($s) => (! empty($s['pic']) && empty($s['pic_ok'])) || (! empty($s['audio']) && empty($s['audio_ok'])))->count();
        if ($kids) {
            $add(['key' => 'kids', 'group' => 'week', 'tag' => 'ai', 'title' => __('ছোটদের বর্ণমালা').': '.Bn::num((string) $kids).__('টি ছবি বা পাঠ দেখার অপেক্ষায়'), 'who' => '',
                'sub' => '', 'url' => \App\Filament\Pages\KidsCorner::getUrl(), 'button' => __('দেখুন')]);
        }
        if ($n = \App\Support\SiteErrors::unseen()) {
            $add(['key' => 'err', 'group' => 'today', 'tag' => 'site', 'title' => __('সাইটে নতুন ত্রুটি').': '.Bn::num((string) $n).__('টি (গত সাত দিনে)'), 'who' => '',
                'sub' => __('কী হয়েছে দেখে নিন; বেশিরভাগ নিজে থেকেই সেরে যায়'), 'url' => \App\Filament\Resources\SiteErrors\SiteErrorResource::getUrl('index'), 'button' => __('দেখুন')]);
        }
        if (rescue(fn () => cache()->remember('desk.health.badge', 600, fn () => \App\Ai\Report::build()['overall'] === \App\Ai\Report::URGENT ? '!' : null), null, false)) {
            $add(['key' => 'health', 'group' => 'today', 'tag' => 'site', 'title' => __('সাইটের স্বাস্থ্যে জরুরি কিছু আছে'), 'who' => '', 'sub' => '',
                'url' => \App\Filament\Pages\HealthReport::getUrl(), 'button' => __('দেখুন')]);
        }
    }

    /** The AI editor's other prepared jobs (D48), each with the button it always had. */
    private static function taskLines(callable $add, bool $admin): void
    {
        $skip = ['question', 'kolom', 'serial'];   // questions and new serials are their own lines; the কলম line is above
        $tasks = EditorTask::where('status', 'open')->whereNotIn('type', $skip)
            ->when(! $admin, fn ($q) => $q->whereIn('type', ['followup', 'welcome']))->latest()->limit(30)->get();
        $info = ['report', 'health', 'quality', 'insights', 'seo_refresh', 'team_review', 'strategy'];
        foreach ($tasks as $t) {
            $group = $t->type === 'alert' ? 'today' : (in_array($t->type, $info, true) ? 'fyi' : 'week');
            $add(['key' => 't'.$t->id, 'group' => $group, 'tag' => in_array($t->type, ['serial', 'serial_episode'], true) ? 'serial' : 'ai',
                'title' => __(self::TASK_LABELS[$t->type] ?? $t->type).': '.$t->title, 'who' => '', 'sub' => self::ago($t->created_at),
                'action' => ['task', $t->id], 'button' => '', 'when' => $t->created_at]);
        }
    }

    private static function fyiLines(callable $add, bool $admin): void
    {
        if ($n = Post::where('status', 'confirm')->count()) {
            $add(['key' => 'fc', 'group' => 'fyi', 'tag' => 'piece', 'title' => __('লেখকের সম্মতির অপেক্ষায়').': '.Bn::num((string) $n).__('টি লেখা'), 'who' => '', 'sub' => __('তিন দিনে উত্তর না এলে লেখককে একবার মনে করানো হয়'),
                'url' => \App\Filament\Resources\Posts\PostResource::getUrl('index', ['tab' => 'confirm']), 'button' => __('দেখুন')]);
        }
        if ($n = Post::where('status', 'changes')->count()) {
            $add(['key' => 'fx', 'group' => 'fyi', 'tag' => 'piece', 'title' => __('বদল চেয়ে লেখকের কাছে').': '.Bn::num((string) $n).__('টি লেখা'), 'who' => '', 'sub' => '',
                'url' => \App\Filament\Resources\Posts\PostResource::getUrl('index', ['tab' => 'changes']), 'button' => __('দেখুন')]);
        }
        if (! $admin) {
            return;
        }
        // the AI desk's own drafts (classics, kids' tales…): one line, never one each (UX review)
        if ($n = Post::where('status', 'draft')->whereNull('submitted_by')->whereNotNull('ai_drafted_at')->whereDoesntHave('section', fn ($q) => $q->where('slug', 'editorial'))->count()) {
            $add(['key' => 'drafts', 'group' => 'fyi', 'tag' => 'ai', 'title' => __('এআই-এর তৈরি খসড়া').': '.Bn::num((string) $n).__('টি'), 'who' => '', 'sub' => __('সময় পেলে পড়ে প্রকাশ করুন'),
                'url' => \App\Filament\Resources\Posts\PostResource::getUrl('index', ['tab' => 'drafts']), 'button' => __('দেখুন')]);
        }
        if ($n = \App\Models\SocialPost::where('status', 'ready')->where('kind', '!=', 'weekly')->count()) {
            $add(['key' => 'soc', 'group' => 'fyi', 'tag' => 'mail', 'title' => __('মার্কেটিং পোস্ট তৈরি').': '.Bn::num((string) $n).__('টি'), 'who' => '', 'sub' => '',
                'url' => \App\Filament\Pages\Marketing::getUrl(), 'button' => __('দেখুন')]);
        }
        if ($n = \App\Support\BotCheck::pendingCount()) {
            $add(['key' => 'bot', 'group' => 'fyi', 'tag' => 'site', 'title' => __('সন্দেহজনক নতুন সদস্য').': '.Bn::num((string) $n).__('টি'), 'who' => '', 'sub' => '',
                'url' => \App\Filament\Pages\NewsletterList::getUrl(), 'button' => __('দেখুন')]);
        }
        if ($n = \App\Models\WordReport::where('status', 'new')->count()) {
            $add(['key' => 'word', 'group' => 'fyi', 'tag' => 'reader', 'title' => __('খেলার শব্দ নিয়ে অভিযোগ').': '.Bn::num((string) $n).__('টি'), 'who' => '', 'sub' => '',
                'url' => \App\Filament\Resources\WordReports\WordReportResource::getUrl('index'), 'button' => __('দেখুন')]);
        }
        $packaged = \App\Models\AiReview::where('status', 'done')->whereNull('blind_run')->where('report', 'like', '%"edits"%')->select('post_id');
        if ($n = Post::where('status', 'pending')->whereNull('submitted_by')->whereIn('id', $packaged)->count()) {
            $add(['key' => 'bl', 'group' => 'fyi', 'tag' => 'piece', 'title' => __('ওয়ার্ডপ্রেসের পুরনো জমা, এআই পড়ে রেখেছে').': '.Bn::num((string) $n).__('টি'), 'who' => '', 'sub' => __('সময় পেলে দেখুন'),
                'url' => \App\Filament\Resources\Posts\PostResource::getUrl('index', ['tab' => 'backlog']), 'button' => __('দেখুন')]);
        }
        if ($n = Post::whereIn('audit_label', ['merge', 'noindex', 'remove'])->whereNull('audit_decision')->count()) {
            $add(['key' => 'audit', 'group' => 'fyi', 'tag' => 'site', 'title' => __('পুরনো লেখার অডিট সুপারিশ, সিদ্ধান্ত বাকি').': '.Bn::num((string) $n).__('টি'), 'who' => '', 'sub' => '',
                'url' => \App\Filament\Resources\Posts\PostResource::getUrl('index', ['tab' => 'all']), 'button' => __('দেখুন')]);
        }
    }

    public const TASK_LABELS = ['followup' => 'লেখককে মনে করানো', 'welcome' => 'প্রথম লেখার শুভেচ্ছা', 'pick' => 'সম্পাদকের পছন্দ ও এই দিনে', 'newsletter' => 'সাপ্তাহিক চিঠি',
        'report' => 'মাসের হিসাব', 'health' => 'সাইটের স্বাস্থ্য', 'alert' => 'প্রধান সম্পাদকের সতর্কতা', 'quality' => 'এআই সম্পাদকের কাজের মান', 'plagiarism' => 'মৌলিকতা নিয়ে সন্দেহ',
        'insights' => 'পাঠকের হিসাব', 'seo_refresh' => 'এসইও ঘষামাজা', 'collection' => 'সংগ্রাহকের প্রস্তাব', 'collection_add' => 'সংকলনে নতুন লেখা', 'kids_alphabet' => 'ছোটদের বর্ণমালা',
        'serial' => 'নতুন ধারাবাহিক', 'serial_episode' => 'ধারাবাহিকের পর্ব', 'tags' => 'ট্যাগ গোছানো', 'certificates' => 'শংসাপত্র', 'certificate_request' => 'লেখকের শংসাপত্রের অনুরোধ',
        'team_review' => 'এআই দলের মাসিক পর্যালোচনা', 'strategy' => 'কৌশলবিদের পরিকল্পনা', 'honour' => 'মাসের লেখক', 'audio' => 'লেখকের পাঠ (অডিও)'];

    private static function aiLine(Post $p): ?string
    {
        $r = EditorPackage::report($p);
        if (! $r) {
            return null;
        }
        $d = ['publish' => 'প্রকাশযোগ্য', 'changes' => 'বদল দরকার', 'decline' => 'এবার নয়'][$r['decision'] ?? ''] ?? null;

        return trim(__('এআই').': '.($d ? __($d).' ' : '').Bn::num((string) ($r['quality_score'] ?? '')).'/১০');
    }

    public static function ago(?\DateTimeInterface $at): string
    {
        return $at ? Bn::num((string) Carbon::instance($at)->locale(app()->getLocale())->diffForHumans()) : '';
    }

    public static function day(\DateTimeInterface $at): string
    {
        return Bn::num(Carbon::instance($at)->setTimezone('Asia/Kolkata')->locale(app()->getLocale())->isoFormat('dddd, D MMM'));
    }
}
