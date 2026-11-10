<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

/**
 * D121: finds accounts that look like bots, for a PERSON to review (owner: "it should go by manual review").
 * The check only flags; it never deletes, blocks or e-mails anyone. While an account waits for review it gets
 * no letters and its comments are always checked. The owner then marks it "human" (never flagged again) or
 * "bot" (no letters, no sign-in). Runs once over all accounts, and on every new sign-up.
 */
final class BotCheck
{
    /** Throw-away mailbox services. */
    public const DISPOSABLE = ['mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', 'grr.la', '10minutemail.com', 'tempmail.com',
        'temp-mail.org', 'tempmail.net', 'yopmail.com', 'yopmail.net', 'trashmail.com', 'trashmail.de', 'getnada.com', 'nada.email', 'dispostable.com',
        'maildrop.cc', 'mohmal.com', 'fakeinbox.com', 'throwawaymail.com', 'mailnesia.com', 'mintemail.com', 'spamgourmet.com', 'mytemp.email',
        'emailondeck.com', 'tempinbox.com', 'burnermail.io', 'mailcatch.com', 'moakt.com', 'tmail.ws', 'tempr.email', 'discard.email', 'spambox.us',
        'mailpoof.com', 'inboxkitten.com', '33mail.com', 'mail.tm', 'tmpmail.org', 'tmpmail.net', 'dropmail.me', 'emailfake.com', 'fakemail.net'];

    public const SPAM_WORDS = '/\b(casino|betting|bet365|slots?|poker|loans?|crypto|bitcoin|forex|viagra|cialis|escort|porn|sex|xxx|dating|seo|backlinks?|followers|essay|replica|cheap|discount|promo|whatsapp\s*\+?\d)\b/i';

    public const LABELS = [
        'disposable' => 'এককালীন (ফেলে দেওয়ার) ই-মেল ঠিকানা',
        'no_mail_domain' => 'ঠিকানার ডোমেইনে মেল যায় না',
        'spam_words' => 'নামে বা ঠিকানায় স্প্যামের শব্দ',
        'link_in_name' => 'নামে ওয়েবসাইটের লিংক',
        'test_account' => 'ফেসবুকের পরীক্ষামূলক অ্যাকাউন্ট',
        'random_email' => 'ই-মেল ঠিকানাটি এলোমেলো অক্ষর-সংখ্যার মতো',
        'random_name' => 'নামটি এলোমেলো অক্ষরের মতো',
        'digits_in_name' => 'নামে সংখ্যা',
        'dot_trick' => 'জিমেল ঠিকানায় অনেক বিন্দু (একই ঠিকানায় বহু অ্যাকাউন্টের কৌশল)',
    ];

    private const STRONG = ['disposable', 'no_mail_domain', 'spam_words', 'link_in_name', 'test_account'];

    /** For tests: replaces the DNS lookup. fn (string $domain): bool (true = the domain can receive mail). */
    public static ?\Closure $dns = null;

    /** @var array<string, bool> */
    private static array $domainCache = [];

    /** @return list<string> reason keys (empty = looks human) */
    public static function reasons(User $u, ?bool $active = null): array
    {
        $email = mb_strtolower(trim((string) $u->email));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $name = trim((string) $u->name);
        $r = [];

        if (str_ends_with($domain, 'tfbnw.net')) {
            $r[] = 'test_account';
        }
        if (in_array($domain, self::DISPOSABLE, true)) {
            $r[] = 'disposable';
        } elseif ($domain !== '' && ! self::domainReceivesMail($domain)) {
            $r[] = 'no_mail_domain';
        }
        if (preg_match(self::SPAM_WORDS, $name.' '.str_replace(['.', '_', '-'], ' ', $local))) {
            $r[] = 'spam_words';
        }
        if (preg_match('~(https?://|www\.|\.(com|net|org|xyz|ru|top|info|shop|online|site)\b)~i', $name)) {
            $r[] = 'link_in_name';
        }

        // Weak signals: only count together, and only for accounts that never did anything here.
        $weak = [];
        if (preg_match('/^[a-z]{3,}\d{4,}$/', $local) || preg_match('/[bcdfghjklmnpqrstvwxz]{6,}/', $local)
            || (strlen($local) >= 16 && preg_match_all('/\d/', $local) >= 4 && preg_match_all('/[a-z]/', $local) >= 6)) {
            $weak[] = 'random_email';
        }
        $latin = preg_replace('/[^A-Za-z]/', '', $name);
        if ($latin !== '' && $latin === preg_replace('/\s+/', '', $name) && (preg_match('/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]{5,}/', $latin)
            || preg_match('/[a-z][A-Z][a-z][A-Z]/', $latin) || (strlen($latin) >= 8 && ! preg_match('/[aeiouyAEIOUY]/', $latin)))) {
            $weak[] = 'random_name';
        }
        if (preg_match('/\d{3,}/', $name)) {
            $weak[] = 'digits_in_name';
        }
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true) && substr_count($local, '.') >= 3) {
            $weak[] = 'dot_trick';
        }
        $active ??= self::active($u);
        if (! $active && count($weak) >= 2) {
            array_push($r, ...$weak);
        }

        return array_values(array_unique($r));
    }

    /** Wrote a piece, commented, or signed in on the new site: real activity. */
    public static function active(User $u): bool
    {
        return $u->last_login_at !== null
            || \App\Models\Comment::where('user_id', $u->id)->exists()
            || \App\Models\Post::withTrashed()->where('submitted_by', $u->id)->exists()
            || ($u->author && $u->author->posts()->withTrashed()->exists());
    }

    public static function domainReceivesMail(string $domain): bool
    {
        if (isset(self::$domainCache[$domain])) {
            return self::$domainCache[$domain];
        }
        if (self::$dns) {
            return self::$domainCache[$domain] = (bool) (self::$dns)($domain);
        }
        if (in_array($domain, ['gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.in', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'rediffmail.com', 'protonmail.com', 'aol.com', 'ymail.com'], true)) {
            return self::$domainCache[$domain] = true;
        }

        // A failed lookup (no network) counts as "receives mail": never flag on our own error.
        return self::$domainCache[$domain] = (bool) rescue(fn () => checkdnsrr($domain.'.', 'MX') || checkdnsrr($domain.'.', 'A'), true, false);
    }

    /** Checks one account and stores the result. Reviewed accounts are never re-flagged. Returns the reasons. */
    public static function check(User $u): array
    {
        if ($u->bot_review !== null) {
            return [];
        }
        $reasons = $u->role && $u->role->atLeast(\App\Enums\Role::Editor) ? [] : self::reasons($u);
        $u->forceFill(['bot_reasons' => $reasons ?: null, 'bot_checked_at' => now()])->saveQuietly();

        return $reasons;
    }

    /** @return array{checked: int, flagged: int} */
    public static function scan(bool $all = true): array
    {
        $checked = $flagged = 0;
        User::with('author')->whereNull('bot_review')->when(! $all, fn ($q) => $q->whereNull('bot_checked_at'))
            ->chunkById(200, function ($users) use (&$checked, &$flagged) {
                foreach ($users as $u) {
                    $checked++;
                    $flagged += self::check($u) ? 1 : 0;
                }
            });
        AuditLog::record('users.bot_scan', null, ['checked' => $checked, 'flagged' => $flagged]);

        return ['checked' => $checked, 'flagged' => $flagged];
    }

    /** The owner's decision. "bot": no letters, no sign-in, comments always checked. "human": cleared for good. */
    public static function decide(User $u, string $verdict, ?User $by): void
    {
        abort_unless(in_array($verdict, ['human', 'bot'], true), 422);
        $u->forceFill([
            'bot_review' => $verdict, 'bot_reviewed_by' => $by?->id,
            'spam_reason' => $verdict === 'bot' ? 'বট (দেখে নিশ্চিত)' : ($u->spam_reason === 'বট (দেখে নিশ্চিত)' ? null : $u->spam_reason),
            'mail_opt_out' => $verdict === 'bot' ? true : $u->mail_opt_out,
        ])->saveQuietly();
        AuditLog::record('user.bot_review', null, ['user' => $u->id, 'verdict' => $verdict]);
    }

    public static function pendingCount(): int
    {
        return User::whereNotNull('bot_reasons')->whereNull('bot_review')->count();
    }

    /** @return list<string> Bengali labels */
    public static function labels(?array $reasons): array
    {
        return array_map(fn ($k) => self::LABELS[$k] ?? $k, (array) $reasons);
    }
}
