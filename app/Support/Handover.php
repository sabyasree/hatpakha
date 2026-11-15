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
    ];

    public const LETTER_HOURS = 12;

    public const PUBLISH_HOURS = 24;

    public const MIN_SCORE = 8;

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
        foreach (array_keys(self::JOBS) as $job) {
            $done[$job] = self::on($job) ? (int) rescue(fn () => self::{$job}(), 0, true) : 0;
        }

        return $done;
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
            $r = EditorPackage::report($post);
            $at = Schedule::nextSlot();
            EditorPackage::approve($post, $owner, $at, null, filled($r['note_to_writer'] ?? null) ? (string) $r['note_to_writer'] : null, EditorPackage::openSafe($post), true, false);
            $post->forceFill(['auto_publish_at' => null])->saveQuietly();
            self::note('published', $post, ['at' => $at->toIso8601String(), 'score' => (int) $r['quality_score']]);
            $n++;
        }

        return $n;
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
