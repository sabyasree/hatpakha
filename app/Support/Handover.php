<?php

namespace App\Support;

use App\Ai\EditorPackage;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\EditorTask;
use App\Models\Newsletter;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\Review;
use App\Support\Transliterate;

/**
 * D208 (owner, 2026-11-15, shown the list of an admin's jobs: "Do all these… do all this safe handover change").
 * Six jobs the owner used to click through are done by the AI team, each behind its own switch in
 * /desk → নিজে থেকে চলা কাজ, each written to the audit log, each with a way back:
 *
 *   fixes     spelling, punctuation and spacing fixes go into a sent piece; a change of words never does
 *   covers    the drawn picture becomes the cover when the writer sent none
 *   comments  a comment the AI found clearly fine appears; a doubtful one waits
 *   letter    the readers' letter sends itself LETTER_HOURS after it was drafted, unless stopped
 *   monthly   writer of the month, collections, old pieces' search details, machine-voice readings
 *   publish   a strong, clean piece is selected and given the next free date PUBLISH_HOURS after its report,
 *             unless stopped; anything the AI is unsure about is held for the owner with the reason
 *
 * This is the owner's explicit exception to "the AI prepares, the owner approves" (D42). Declining a piece, asking a
 * writer for changes, the editorial, serials and plagiarism warnings stay with the owner.
 */
final class Handover
{
    public const JOBS = [
        'fixes' => ['ছোট সংশোধন', 'বানান, যতিচিহ্ন আর ফাঁকের সংশোধন পাঠানো লেখায় নিজে থেকে বসে। শব্দ বদল কখনও নয়।'],
        'covers' => ['প্রচ্ছদের ছবি', 'লেখক ছবি না দিলে এআই-এর আঁকা ছবি নিজে থেকে বসে।'],
        'comments' => ['পাঠকের মন্তব্য', 'এআই যে মন্তব্য পরিষ্কার ভালো বলে, তা সঙ্গে সঙ্গে প্রকাশ হয়। সন্দেহ থাকলে আপনার জন্য থাকে।'],
        'letter' => ['পাঠকদের চিঠি', 'খসড়া তৈরির ১২ ঘণ্টা পরে চিঠি নিজে থেকে যায়, আপনি না থামালে।'],
        'monthly' => ['মাসের ছোট কাজ', 'মাসের লেখক, সংকলন, পুরনো লেখার এসইও আর যন্ত্রকণ্ঠের পাঠ নিজে থেকে হয়।'],
        'publish' => ['ভালো লেখা প্রকাশ', 'এআই যে লেখা নিশ্চিন্তে প্রকাশ করতে বলে, তা ২৪ ঘণ্টা পরে নিজে থেকে মনোনীত হয়, আপনি না থামালে।'],
        // D214: the detective series only
        'cases' => ['গোয়েন্দা সিরিজ', 'একটি কেস শেষ হওয়ার আগে পরের কেসের পরিকল্পনা তৈরি হয়; আপনি সমাধান পড়ে শুরু করলে প্রতিটি পর্ব যাচাই হয়ে নিজে থেকে প্রকাশ হয়।'],
    ];

    public const LETTER_HOURS = 12;

    public const PUBLISH_HOURS = 24;

    public const MIN_SCORE = 8;

    /** Never more than this many pieces selected by themselves in one day, whatever the reports say. */
    public const PUBLISH_PER_DAY = 3;

    public const UNREAD_HOURS = 6;

    public const WAITING_DAYS = 3;

    public static function on(string $job): bool
    {
        return isset(self::JOBS[$job]) && Setting::get('handover.'.$job) !== '0';
    }

    public static function set(string $job, bool $on): void
    {
        if (isset(self::JOBS[$job])) {
            Setting::put('handover.'.$job, $on ? '1' : '0');
            AuditLog::record('handover.switch', null, ['job' => $job, 'on' => $on]);
        }
    }

    /** In whose name the desk's own actions are recorded: the owner. */
    public static function owner(): ?User
    {
        return User::where('role', \App\Enums\Role::Admin)->orderBy('id')->first();
    }

    /** Every ten minutes. One job failing never stops the others. @return array<string, int> */
    public static function run(): array
    {
        $done = [];
        foreach (self::JOBS as $job => [$name]) {
            $done[$job] = 0;
            if (! self::on($job)) {
                self::flag('job.'.$job, null);

                continue;
            }
            try {
                $done[$job] = (int) self::{$job}();
                self::flag('job.'.$job, null);
            } catch (\Throwable $e) {   // a job that broke is said out loud, never left silent
                report($e);
                self::flag('job.'.$job, __('নিজে থেকে চলা কাজ থেমে আছে: ').__($name), [mb_substr($e->getMessage(), 0, 300)]);
            }
        }
        rescue(fn () => self::watch(), null, true);
        rescue(fn () => self::replyDue(), null, true);
        Setting::put('handover.ran_at', now()->toIso8601String());

        return $done;
    }

    /**
     * Owner (2026-11-15): "Make sure the team will do a proper job always. Any confusion, raise a flag." A flag is a
     * warning in /desk → আজকের কাজ and on the AI team page; it goes away by itself when the trouble does.
     */
    public static function flag(string $key, ?string $title, array $lines = []): void
    {
        $open = EditorTask::where('type', 'alert')->where('key', 'handover.'.$key)->where('status', 'open');
        if ($title === null) {
            $open->get()->each->close('dismissed');

            return;
        }
        $task = EditorTask::firstOrCreate(['type' => 'alert', 'key' => 'handover.'.$key, 'status' => 'open'], ['title' => $title, 'payload' => ['errors' => $lines]]);
        if (! $task->wasRecentlyCreated && ($task->title !== $title || ($task->payload['errors'] ?? []) !== $lines)) {
            $task->forceFill(['title' => $title, 'payload' => ['errors' => $lines]])->save();
        }
    }

    /** What nobody would otherwise notice: a piece no AI has read, a piece kept for the owner that keeps waiting. */
    public static function watch(): void
    {
        $sent = Post::where('status', 'pending')->whereNotNull('submitted_at')->with('latestAiReview')->oldest('submitted_at')->limit(80)->get();
        $unread = $sent->filter(fn (Post $p) => $p->submitted_at->lt(now()->subHours(self::UNREAD_HOURS)) && ! EditorPackage::report($p));
        self::flag('unread', $unread->isEmpty() ? null : __('এআই এই লেখাগুলো এখনও পড়েনি; খুলে "এআই দিয়ে পড়ান" টিপুন বা নিজে পড়ুন'), $unread->take(8)->pluck('title')->all());

        $waiting = self::on('publish') ? $sent->filter(fn (Post $p) => ! $p->auto_publish_at && $p->submitted_at->lt(now()->subDays(self::WAITING_DAYS)) && EditorPackage::report($p)) : collect();
        self::flag('waiting', $waiting->isEmpty() ? null : __('এই লেখাগুলো আপনার সিদ্ধান্তের অপেক্ষায় আছে; "নিজে থেকে চলা কাজ" পাতায় কারণ দেখুন'),
            $waiting->take(8)->map(fn (Post $p) => $p->title.': '.(self::hold($p) ?? ''))->values()->all());
    }

    /** Writers are told "usually within 2–3 weeks" (Review::REPLY_TEXT); the desk hears when a piece has waited 14 days. */
    public static function replyDue(): void
    {
        $late = Post::where('status', 'pending')->whereNotNull('submitted_at')->where('submitted_at', '<', now()->subDays(Review::REPLY_WARN_DAYS))->oldest('submitted_at')->limit(8)->get();
        self::flag('reply_due', $late->isEmpty() ? null : __('লেখকদের বলা আছে ২–৩ সপ্তাহের মধ্যে জানাব; এই লেখাগুলো ১৪ দিনের বেশি অপেক্ষায় আছে'),
            $late->map(fn (Post $p) => $p->title.' · '.Bn::num((string) (int) $p->submitted_at->diffInDays(now())).' '.__('দিন'))->all());
    }

    /** How many of the pieces sent through the form need the owner now (for the desk menu). */
    public static function needsOwner(): int
    {
        return (int) cache()->remember('handover.needs', 600, fn () => Post::where('status', 'pending')->whereNotNull('submitted_at')->whereNull('auto_publish_at')
            ->with('latestAiReview')->limit(80)->get()->filter(fn (Post $p) => EditorPackage::report($p) && self::hold($p) !== null)->count());
    }

    private static function note(string $what, $subject = null, array $meta = []): void
    {
        AuditLog::record('handover.'.$what, $subject, $meta + ['auto' => true]);
    }

    public static function fixes(): int
    {
        $n = 0;
        foreach (Post::where('status', 'pending')->whereNotNull('submitted_at')->latest('submitted_at')->limit(30)->get() as $post) {
            $open = EditorPackage::openSafe($post);
            if ($open && ($put = EditorPackage::applyNow($post, $open))) {
                self::note('fixes', $post, ['fixes' => $put]);
                $n++;
            }
        }

        return $n;
    }

    public static function covers(): int
    {
        $n = 0;
        // Pieces sent through the form only: pictures drawn for the old archive still wait in /desk → ছবি অনুমোদন.
        foreach (Post::whereNotNull('proposed_image_path')->whereNull('featured_image_path')->whereNotNull('submitted_at')->whereIn('status', ['pending', 'scheduled', 'published'])->limit(20)->get() as $post) {
            CoverImage::acceptProposed($post);
            self::note('cover', $post);
            $n++;
        }

        return $n;
    }

    public static function comments(): int
    {
        $n = 0;
        foreach (Comment::where('status', 'pending')->where('ai_verdict', 'ok')->limit(50)->get() as $c) {
            $n += (int) self::comment($c);
        }

        return $n;
    }

    /** One comment, right after the AI read it. */
    public static function comment(Comment $c): bool
    {
        if (! self::on('comments') || $c->status !== 'pending' || $c->ai_verdict !== 'ok') {
            return false;
        }
        \App\Filament\Resources\Comments\Tables\CommentsTable::setStatus($c, 'approved');
        self::note('comment', $c);

        return true;
    }

    /** When a new draft of the readers' letter will go by itself. */
    public static function letterDrafted(Newsletter $n): void
    {
        if (self::on('letter')) {
            $n->forceFill(['auto_send_at' => now()->addHours(self::LETTER_HOURS)])->saveQuietly();
        }
    }

    public static function letter(): int
    {
        $owner = self::owner();
        if (! $owner || ! NewsletterSender::realMail()) {
            return 0;
        }
        $n = 0;
        foreach (Newsletter::where('status', 'draft')->where('source', 'ai_weekly')->whereNotNull('auto_send_at')->where('auto_send_at', '<=', now())->get() as $letter) {
            $to = NewsletterSender::queue($letter, $owner);
            $letter->forceFill(['auto_send_at' => null])->saveQuietly();
            self::note('letter', null, ['newsletter' => $letter->id, 'recipients' => $to]);
            $n++;
        }

        return $n;
    }

    public static function monthly(): int
    {
        $n = 0;
        // D231 (owner: "it should be automatic... no need to come to me"): the month's readers' choice.
        $n += (int) (bool) rescue(fn () => Feelings::pickMonth(), null, true);
        foreach (EditorTask::where('status', 'open')->whereIn('type', ['honour', 'collection', 'collection_add', 'seo_refresh'])->where('created_at', '>=', now()->subDays(45))->oldest('id')->limit(20)->get() as $task) {
            $ok = rescue(function () use ($task) {
                $s = $task->subject;
                if ($task->type === 'honour' && $s instanceof \App\Models\Author && filled($task->payload['praise'] ?? null)) {
                    \App\Ai\Honours::give($s, $task->payload['label'] ?? 'month', (string) $task->payload['praise'], true);
                    rescue(fn () => \App\Ai\Marketer::spotlight($s), null, false);

                    return true;
                }
                if ($task->type === 'collection' && $s instanceof \App\Models\Collection) {
                    $s->forceFill(['status' => 'approved', 'approved_at' => $s->approved_at ?? now()] + (! $s->cover_path && $s->proposed_cover_path ? ['cover_path' => $s->proposed_cover_path, 'proposed_cover_path' => null] : []))->save();

                    return true;
                }
                if ($task->type === 'collection_add' && $s instanceof \App\Models\Collection) {
                    $s->forceFill(['post_ids' => array_values(array_unique(array_merge(array_map('intval', (array) $s->post_ids), array_map('intval', (array) ($task->payload['add'] ?? [])))))])->save();

                    return true;
                }
                if ($task->type === 'seo_refresh') {
                    foreach (Post::whereKey(array_column((array) ($task->payload['posts'] ?? []), 'post'))->whereNotNull('seo_proposal')->get() as $p) {
                        $new = (array) $p->seo_proposal;
                        \App\Ai\SeoExpert::apply($p, array_intersect_key($new, array_flip(\App\Ai\SeoExpert::FIELDS)), (array) ($new['tags'] ?? []));
                    }

                    return true;
                }

                return false;
            }, false, true);
            if ($ok) {
                $task->close();
                self::note('monthly', $task->subject, ['task' => $task->id, 'type' => $task->type]);
                $n++;
            }
        }
        // The machine voice only; a writer's own recording waits for the owner's ear.
        foreach (Post::where('audio_status', 'pending')->where('audio_ai', true)->whereNotNull('audio_path')->limit(20)->get() as $post) {
            AudioReading::approve($post);
            self::note('monthly', $post, ['type' => 'audio']);
            $n++;
        }

        return $n;
    }

    /**
     * Why this piece is NOT selected by itself, in the owner's words; null when it may be. Checked when the clock is
     * set and again when it runs out, so a piece changed in between is looked at afresh.
     */
    public static function hold(Post $post): ?string
    {
        if (str_starts_with((string) $post->review_note, \App\Ai\EditorPackage::OBJECTED)) {
            return __('লেখক বদলে আপত্তি করেছেন: আপনি দেখবেন');   // D256
        }
        $r = EditorPackage::report($post);
        if (! $r) {
            return __('এআই রিপোর্ট এখনও নেই');
        }
        if ($post->auto_stopped) {
            return __('আপনি থামিয়েছেন');
        }
        if (! array_key_exists('stray_text', $r) || ! array_key_exists('doubts', $r)) {
            return __('রিপোর্টটি পুরনো: "এআই দিয়ে পড়ান" টিপলে নতুন করে দেখা হবে');
        }
        if ($r['stray_text']) {
            return __('লেখার মধ্যে এমন কিছু আছে যা লেখার অংশ নয় বলে মনে হচ্ছে').': “'.mb_substr((string) ($r['stray_text'][0]['text'] ?? ''), 0, 80).'”';
        }
        if ($r['doubts']) {
            return __('এআই নিশ্চিত নয়').': '.mb_substr((string) $r['doubts'][0], 0, 160);
        }
        if (($r['decision'] ?? '') !== 'publish') {
            return __('এআই সরাসরি প্রকাশ করতে বলেনি');
        }
        if ((int) ($r['quality_score'] ?? 0) < self::MIN_SCORE) {
            return __('মান ৮-এর কম');
        }
        if (! ($r['fits_magazine'] ?? false) || ! empty($r['policy_flags'])) {
            return __('রিপোর্টে সতর্কতা আছে');
        }
        if (($post->plagiarism['verdict'] ?? null) !== 'clear') {
            return __('মৌলিকতা যাচাই পরিষ্কার নয় বা হয়নি');
        }
        if (EditorPackage::wordChanges($post) || filled($post->proof['title_fix'] ?? null)) {
            return __('শব্দ বা শিরোনাম বদলের প্রস্তাব আছে: লেখকের সম্মতি লাগবে');
        }
        if ($post->serial_id) {
            return __('ধারাবাহিকের পর্ব: আপনি দেখবেন');
        }
        if ($post->contest_id) {
            return __('প্রতিযোগিতার লেখা: আপনি দেখবেন');
        }
        if (\App\Support\WriterInvites::forIssue($post)) {
            return __('ফিরে আসা সংখ্যার লেখা: সংখ্যার দিনে একসঙ্গে প্রকাশ হবে');   // owner, 2026-11-23: never before the issue
        }
        if (\App\Models\PieceReview::where('post_id', $post->id)->where('status', 'assigned')->exists()) {
            return __('যাচাইকারীর মতামত বাকি');
        }
        BodyFormatter::format((string) $post->body_html, $post->format ?: 'prose', $post->title);
        if (BodyFormatter::$refused) {
            return __('লেখার সাজ ঠিকমতো বসানো যায়নি');
        }

        return null;
    }

    public static function publish(): int
    {
        $owner = self::owner();
        if (! $owner) {
            return 0;
        }
        $n = 0;
        foreach (Post::where('status', 'pending')->whereNotNull('submitted_at')->where('auto_stopped', false)->with('latestAiReview')->latest('submitted_at')->limit(60)->get() as $post) {
            $why = self::hold($post);
            if ($why !== null) {
                if ($post->auto_publish_at) {
                    $post->forceFill(['auto_publish_at' => null])->saveQuietly();   // something changed since the clock was set
                }

                continue;
            }
            if (! $post->auto_publish_at) {
                $post->forceFill(['auto_publish_at' => now()->addHours(self::PUBLISH_HOURS)])->saveQuietly();
                self::note('publish_planned', $post, ['at' => $post->auto_publish_at->toIso8601String()]);

                continue;
            }
            if ($post->auto_publish_at->isFuture()) {
                continue;
            }
            if (AuditLog::where('action', 'handover.published')->where('created_at', '>=', now('Asia/Kolkata')->startOfDay()->utc())->count() >= self::PUBLISH_PER_DAY) {
                break;   // the rest go tomorrow
            }
            $r = EditorPackage::report($post);
            $at = Schedule::nextSlot();
            EditorPackage::approve($post, $owner, $at, null, filled($r['note_to_writer'] ?? null) ? (string) $r['note_to_writer'] : null, EditorPackage::openSafe($post), true, false);
            $post->forceFill(['auto_publish_at' => null])->saveQuietly();
            self::note('published', $post, ['at' => $at->toIso8601String(), 'score' => (int) $r['quality_score']]);
            $n++;
        }

        return $n;
    }

    /**
     * D214 (owner, offered the two: "do both automatic"): the detective series (D212) runs itself.
     *   - A case whose last planned episode is out is marked finished.
     *   - One episode before a case ends, the next case is planned (once a day at most if it fails). It waits as a
     *     proposal: the owner reads its solution and starts it. Nothing of a case goes out before that.
     *   - In a started case, each drafted episode waits PUBLISH_HOURS, is checked against the case's plan by a second
     *     reader, and is then given its day (the serial's weekday, 9am Kolkata, a week after the one before). An
     *     episode with a problem, or one that could not be checked, waits for the owner with the reason in a flag.
     */
    public static function cases(): int
    {
        $owner = self::owner();
        $n = 0;
        $series = fn () => \App\Models\Serial::where('writer', 'serial_detective')->whereNull('user_id');
        foreach ($series()->where('status', 'active')->get() as $case) {
            $planned = $case->plannedEpisodes();
            $out = (int) $case->liveEpisodes()->max('episode_no');
            if ($out >= $planned) {
                $case->forceFill(['status' => 'finished'])->save();
                self::note('case_finished', null, ['serial' => $case->id]);

                continue;
            }
            foreach ($case->episodes()->where('status', 'draft')->whereNotNull('ai_drafted_at')->where('auto_stopped', false)->get() as $ep) {
                if (! $ep->auto_publish_at) {
                    $ep->forceFill(['auto_publish_at' => $ep->created_at->copy()->addHours(self::PUBLISH_HOURS)])->saveQuietly();
                }
                if ($ep->auto_publish_at->isFuture() || ! $owner) {
                    continue;
                }
                $problems = self::episodeProblems($case, $ep);
                // D226: a problem is first given back to the writer; the owner hears of it only if two rewrites did not cure it.
                if ($problems && self::repairEpisode($case, $ep, $problems)) {
                    self::flag('episode.'.$ep->id, null);

                    continue;   // the new text is checked on the next round
                }
                if ($problems !== []) {
                    self::flag('episode.'.$ep->id, __('গোয়েন্দা সিরিজের এই পর্বটি নিজে থেকে প্রকাশ হয়নি; পড়ে ঠিক করুন, তারপর নিজে প্রকাশ করুন: ').$ep->title, $problems ?? [__('পর্বটি যাচাই করা যায়নি; একটু পরে আবার চেষ্টা হবে।')]);

                    continue;
                }
                self::flag('episode.'.$ep->id, null);
                $at = self::episodeDay($case);
                if ($ep->proposed_image_path && ! $ep->featured_image_path) {
                    CoverImage::acceptProposed($ep);
                }
                $ep->forceFill(['slug' => str_starts_with((string) $ep->slug, 'desk-') ? Review::uniqueSlug(Transliterate::slug($ep->title) ?: 'lekha-'.$ep->id, $ep->id) : $ep->slug,
                    'status' => $at->isFuture() ? 'scheduled' : 'published', 'published_at' => $at, 'reviewed_by' => $owner->id, 'reviewed_at' => now(), 'auto_publish_at' => null])->save();
                EditorTask::where('type', 'serial_episode')->where('status', 'open')->where('subject_id', $ep->id)->get()->each->close();
                self::note('episode', $ep, ['at' => $at->toIso8601String()]);
                $n++;
            }
            // The next case is planned while the last episode of this one is still to come, so there is no empty week.
            if ($out >= $planned - 1 && ! $series()->where('status', 'proposed')->exists()) {
                $n += (int) self::planNextCase();
            }
        }
        // No case running or waiting: plan one. This also begins the series the first time (owner, 2026-11-16: "don't
        // know how to start, and when the detective series will start").
        if (! $series()->whereIn('status', ['active', 'proposed', 'paused'])->exists()) {
            $n += (int) self::planNextCase();
        }
        // An episode the owner has dealt with by hand no longer needs its flag.
        foreach (EditorTask::where('type', 'alert')->where('status', 'open')->where('key', 'like', 'handover.episode.%')->get() as $flag) {
            if (Post::whereKey((int) substr($flag->key, strlen('handover.episode.')))->where('status', 'draft')->doesntExist()) {
                $flag->close('dismissed');
            }
        }
        $waiting = $series()->where('status', 'proposed')->whereHas('episodes')->pluck('title')->all();
        self::flag('case_waiting', $waiting ? __('গোয়েন্দা সিরিজের নতুন কেস তৈরি: ডেস্ক → ধারাবাহিক-এ খুলে লাল বাক্সে সমাধান পড়ুন, তারপর "সমাধান পড়েছি, কেস শুরু করুন" টিপুন') : null, $waiting);

        return $n;
    }

    private static function planNextCase(): bool
    {
        if (! config('ai.enabled') || blank(config('ai.anthropic.key')) || \App\Ai\AiSwitch::paused()) {
            return false;   // the AI is off or resting: nothing to try, nothing to warn about
        }
        $today = now('Asia/Kolkata')->toDateString();
        if (Setting::get('handover.case_tried') === $today) {
            return false;   // one try a day: a plan is a long, costly piece of writing
        }
        Setting::put('handover.case_tried', $today);
        [$case, $why] = \App\Ai\SerialWriter::start('serial_detective');
        self::flag('case_plan', $case ? null : __('পরের কেসের পরিকল্পনা তৈরি হয়নি; কাল আবার চেষ্টা হবে'), $case ? [] : [(string) $why]);
        if ($case) {
            self::note('case_planned', null, ['serial' => $case->id]);
        }

        return (bool) $case;
    }

    public const REPAIR_TRIES = 3;

    /**
     * D226 (owner: "why is it asking for this, it should fix by itself"): an episode of the detective series that fails
     * its check is written again by the same writer, with the problems named, up to twice. Never an episode a person
     * has changed by hand: that text is theirs.
     */
    private static function repairEpisode(\App\Models\Serial $case, Post $ep, array $problems): bool
    {
        if (! config('ai.enabled') || blank(config('ai.anthropic.key')) || \App\Ai\AiSwitch::paused()
            || \App\Models\PostVersion::where('post_id', $ep->id)->where('kind', 'desk_edit')->exists()
            || AuditLog::where('action', 'handover.episode_mended')->where('subject_type', $ep->getMorphClass())->where('subject_id', $ep->id)->count() >= self::REPAIR_TRIES) {
            return false;
        }
        self::note('episode_mended', $ep, ['problems' => $problems]);   // counted before the call: a try that fails is still a try

        // D229: first the smallest cure (only the paragraphs at fault); a whole rewrite only when that cannot be done.
        return (bool) rescue(fn () => \App\Ai\SerialWriter::mend($case, $ep, $problems) || \App\Ai\SerialWriter::repair($case, $ep, $problems), false, true);
    }

    /** Checked once for the text as it stands; again only if the episode was changed. @return ?list<string> */
    private static function episodeProblems(\App\Models\Serial $case, Post $ep): ?array
    {
        $log = AuditLog::where('action', 'handover.episode_checked')->where('subject_type', $ep->getMorphClass())->where('subject_id', $ep->id)->latest('id')->first();
        if ($log && ($log->meta['hash'] ?? '') === md5((string) $ep->body_html)) {
            return (array) ($log->meta['problems'] ?? []);
        }
        $problems = rescue(fn () => \App\Ai\Casebook::check($case, $ep), null, true);
        if ($problems !== null) {
            self::note('episode_checked', $ep, ['hash' => md5((string) $ep->body_html), 'problems' => $problems, 'doubts' => \App\Ai\Casebook::$doubts]);
        }

        return $problems;
    }

    /** The serial's weekday at 9am Kolkata: the next one, and never less than a week after the episode before. */
    /**
     * The owner has read the solution: the case starts. Its first episode gets the serial's next day at 9am Kolkata
     * (with its picture), and from then on the episodes go by themselves.
     */
    public static function startCase(\App\Models\Serial $case, User $by): ?\Illuminate\Support\Carbon
    {
        $first = $case->episodes()->where('status', 'draft')->orderBy('episode_no')->first();
        if ($case->status !== 'proposed' || ! $first) {
            return null;
        }
        $at = self::episodeDay($case);
        if ($first->proposed_image_path && ! $first->featured_image_path) {
            CoverImage::acceptProposed($first);
        }
        $first->forceFill(['slug' => str_starts_with((string) $first->slug, 'desk-') ? Review::uniqueSlug(Transliterate::slug($first->title) ?: 'lekha-'.$first->id, $first->id) : $first->slug,
            'status' => 'scheduled', 'published_at' => $at, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'auto_publish_at' => null])->save();   // the Post model starts the serial
        EditorTask::where('type', 'serial_episode')->where('status', 'open')->where('subject_id', $first->id)->get()->each->close();
        AuditLog::record('handover.case_started', $first, ['serial' => $case->id, 'at' => $at->toIso8601String(), 'by' => $by->id]);

        return $at;
    }

    private static function episodeDay(\App\Models\Serial $case): \Illuminate\Support\Carbon
    {
        return $case->nextEpisodeAt();   // D269: one rule for every serial
    }

    /** The owner: "not this one by itself". */
    public static function stop(Post $post): void
    {
        $post->forceFill(['auto_publish_at' => null, 'auto_stopped' => true])->saveQuietly();
        AuditLog::record('handover.publish_stopped', $post);
    }

    /** A piece sent again (or taken back) starts from nothing. */
    public static function reset(Post $post): void
    {
        if ($post->auto_publish_at || $post->auto_stopped) {
            $post->forceFill(['auto_publish_at' => null, 'auto_stopped' => false])->saveQuietly();
        }
    }
}
