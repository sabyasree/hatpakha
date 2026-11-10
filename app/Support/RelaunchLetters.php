<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\RelaunchLetter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

/**
 * D104 (owner): after the move to hatpakha.com, every writer whose old submission was never answered gets one
 * letter: an apology, the reason, and a question: do you still want it published? Their answer is recorded per
 * piece. Before that, the AI editor reads every piece and only those worth publishing are asked about (owner);
 * writers whose pieces are all too weak get no letter. Nothing is published by the letter; a "yes" puts the piece in the review queue, a "no" or silence keeps
 * it unpublished. Sending starts by itself on the live site once the owner has approved the letter's text.
 */
final class RelaunchLetters
{
    /** The live site's host names: never sent from staging or a laptop. */
    public const PRODUCTION_HOSTS = ['hatpakha.com', 'www.hatpakha.com'];

    public const DEFAULT_SUBJECT = 'হাতপাখায় পাঠানো আপনার লেখা: অনেক দেরির জন্য ক্ষমা চাইছি';

    public const DEFAULT_BODY = "অনেক আগে আপনি হাতপাখায় লেখা পাঠিয়েছিলেন:\n{লেখা}\n\n"
        ."আমরা এতদিন তার উত্তর দিইনি। সেজন্য আন্তরিকভাবে ক্ষমা চাইছি।\n\n"
        ."কারণটা সোজাসুজি বলি: কয়েক বছর হাতপাখা প্রায় বন্ধ ছিল, তাই জমা পড়া লেখাগুলো কেউ পড়ে উত্তর দিতে পারেনি। এই ভুল আমাদের।\n\n"
        ."হাতপাখা এখন নতুন করে শুরু হয়েছে। প্রতিটি লেখা সম্পাদক নিজে পড়েন, আর এবার আমরা সময়মতো উত্তর দেব।\n\n"
        ."আপনি কি এখনও লেখাটি হাতপাখায় প্রকাশ করতে চান? নিচের বোতাম টিপে জানান। চাইলে সম্পাদক লেখাটি পড়ে সিদ্ধান্ত জানাবেন। "
        .'না চাইলে লেখাটি সরিয়ে রাখা হবে, কখনও প্রকাশ হবে না। উত্তর না দিলেও লেখাটি প্রকাশ হবে না।';

    /** The old unanswered submissions (WordPress era). */
    public static function pieces(): Builder
    {
        return Post::where('status', 'pending')->whereNull('submitted_by')->whereNotNull('legacy_wp_id');
    }

    /** The pieces worth asking about: the AI editor's suggestion, or the owner's own choice (D104). */
    public static function askable(): Builder
    {
        return self::pieces()->where('relaunch_worth', 'ask');
    }

    /** Owner: change the AI's suggestion for one piece. */
    public static function setWorth(Post $post, string $worth): void
    {
        abort_unless(in_array($worth, ['ask', 'skip'], true) && $post->status === 'pending' && $post->submitted_by === null && $post->legacy_wp_id !== null, 422);
        $post->forceFill(['relaunch_worth' => $worth, 'relaunch_worth_by_owner' => true])->saveQuietly();
    }

    /** @return array{subject:string, body:string} */
    public static function letter(): array
    {
        $saved = json_decode((string) Setting::get('relaunch.letter'), true) ?: [];

        return ['subject' => $saved['subject'] ?? self::DEFAULT_SUBJECT, 'body' => $saved['body'] ?? self::DEFAULT_BODY];
    }

    /** Saving new text withdraws the approval: the owner approves the words that are actually sent. */
    public static function save(string $subject, string $body): void
    {
        Setting::put('relaunch.letter', json_encode(['subject' => trim($subject), 'body' => trim($body)], JSON_UNESCAPED_UNICODE));
        Setting::put('relaunch.approved', null);
    }

    public static function approve(User $owner): void
    {
        Setting::put('relaunch.approved', json_encode(['at' => now()->toIso8601String(), 'by' => $owner->id, 'hash' => self::hash()]));
        AuditLog::record('relaunch.letter_approved', null, ['by' => $owner->id]);
    }

    public static function approved(): bool
    {
        $a = json_decode((string) Setting::get('relaunch.approved'), true);

        return is_array($a) && ($a['hash'] ?? null) === self::hash();
    }

    private static function hash(): string
    {
        return sha1(json_encode(self::letter()));
    }

    public static function onLiveSite(): bool
    {
        return in_array(mb_strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)), self::PRODUCTION_HOSTS, true);
    }

    /** Why nothing is being sent right now, or null when sending runs. */
    public static function waitingFor(): ?string
    {
        return match (true) {
            self::pieces()->whereNull('relaunch_worth')->exists() => 'এআই সম্পাদকের সব পুরনো লেখা পড়া শেষ হওয়ার অপেক্ষায়',
            ! self::approved() => 'চিঠির লেখা অনুমোদনের অপেক্ষায়',
            ! self::onLiveSite() => 'সাইট hatpakha.com-এ চালু হওয়ার অপেক্ষায় (স্টেজিং থেকে পাঠানো হয় না)',
            ! NewsletterSender::realMail() => 'আসল ই-মেল (SMTP) চালু হওয়ার অপেক্ষায়',
            default => null,
        };
    }

    /** Writers still to receive the letter: newest submissions first (their addresses are likeliest to work). */
    public static function due(): Builder
    {
        $ids = self::askable()->whereNull('relaunch_status')->select('id');

        return Author::whereNull('relaunch_letter_at')->whereNotNull('user_id')
            ->whereHas('posts', fn ($q) => $q->whereIn('posts.id', $ids))
            ->whereHas('user', fn ($u) => $u->whereNotNull('email')->where('mail_opt_out', false)
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('users', 'spam_reason'), fn ($q) => $q->whereNull('spam_reason')))
            ->withMax(['posts as last_submitted' => fn ($q) => $q->whereIn('posts.id', $ids)], 'submitted_at')
            ->orderByDesc('last_submitted');
    }

    /** Hourly from the scheduler: a small batch, so old addresses don't hurt the site's mail reputation. */
    public static function send(?int $max = null): int
    {
        if (self::waitingFor()) {
            return 0;
        }
        $sent = 0;
        foreach (self::due()->with('user')->limit($max ?? (int) config('mail.relaunch_per_hour', 20))->get() as $author) {
            $titles = $author->posts()->whereIn('posts.id', self::askable()->select('id'))->orderBy('submitted_at')->pluck('title');
            $ok = rescue(function () use ($author, $titles) {
                $author->user->notify(new RelaunchLetter(self::letter(), $titles->all(), self::replyUrl($author)));

                return true;
            }, false, false);
            // Marked either way, so a broken address is never retried every hour.
            $author->forceFill(['relaunch_letter_at' => now()])->saveQuietly();
            $author->posts()->whereIn('posts.id', self::askable()->whereNull('relaunch_status')->select('id'))->update(['relaunch_status' => 'asked']);
            $sent += $ok ? 1 : 0;
        }
        if ($sent) {
            AuditLog::record('relaunch.letters_sent', null, ['count' => $sent]);
        }

        return $sent;
    }

    public static function replyUrl(Author $author): string
    {
        return URL::temporarySignedRoute('relaunch.reply', now()->addDays(180), ['author' => $author->id]);
    }

    /** @return array<string,int> */
    public static function stats(): array
    {
        $p = self::pieces();

        return [
            'writers' => Author::whereHas('posts', fn ($q) => $q->whereIn('posts.id', self::askable()->select('id')))->count(),
            'pieces' => (clone $p)->count(),
            'unread' => (clone $p)->whereNull('relaunch_worth')->count(),
            'ask' => (clone $p)->where('relaunch_worth', 'ask')->count(),
            'skip' => (clone $p)->where('relaunch_worth', 'skip')->count(),
            'sent' => Author::whereNotNull('relaunch_letter_at')->count(),
            'due' => self::due()->count(),
            'yes' => (clone $p)->where('relaunch_status', 'yes')->count(),
            'no' => (clone $p)->where('relaunch_status', 'no')->count(),
            'asked' => (clone $p)->where('relaunch_status', 'asked')->count(),
        ];
    }
}
