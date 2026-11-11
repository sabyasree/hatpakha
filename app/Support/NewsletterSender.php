<?php

namespace App\Support;

use App\Enums\Role;
use App\Mail\NewsletterMail;
use App\Models\AuditLog;
use App\Models\Newsletter;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Sends newsletters (D58). Only after the owner's click; recipients are fixed at that moment; the
 * scheduler sends a small batch every minute (shared hosting limits); every letter carries a personal
 * one-click unsubscribe link.
 */
final class NewsletterSender
{
    public static function realMail(): bool
    {
        return BulkMail::real();
    }

    /**
     * Readers: confirmed sign-ups, plus every account that has not switched the letter off and is not
     * marked as spam (D59; owner, 2026-11-10 (D120): verified or not, every current account is on the list).
     * Writers: registered writers who have not opted out.
     */
    public static function recipients(string $audience): Collection
    {
        $out = collect();
        if (in_array($audience, ['readers', 'both'], true)) {
            NewsletterSubscriber::mailable()->get(['id', 'email'])
                ->each(fn ($s) => $out->push(['email' => mb_strtolower(trim($s->email)), 'kind' => 'subscriber', 'id' => $s->id]));
            User::where('mail_opt_out', false)
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('users', 'spam_reason'), fn ($q) => $q->whereNull('spam_reason'))
                ->where(fn ($q) => $q->whereNull('bot_reasons')->orWhere('bot_review', 'human'))   // D121: suspected bots wait for review
                ->get(['id', 'email'])
                ->each(fn ($u) => $out->push(['email' => mb_strtolower(trim($u->email)), 'kind' => 'writer', 'id' => $u->id]));
        }
        if (in_array($audience, ['writers', 'both'], true)) {
            User::where('mail_opt_out', false)
                ->where(fn ($q) => $q->whereIn('role', [Role::Writer->value, Role::TrustedWriter->value])->orWhereHas('author', fn ($a) => $a->where('published_count', '>', 0)->orWhereHas('posts', fn ($p) => $p->where('status', 'published'))))
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('users', 'spam_reason'), fn ($q) => $q->whereNull('spam_reason'))
                ->where(fn ($q) => $q->whereNull('bot_reasons')->orWhere('bot_review', 'human'))   // D121: suspected bots wait for review
                ->get(['id', 'email'])
                ->each(fn ($u) => $out->push(['email' => mb_strtolower(trim($u->email)), 'kind' => 'writer', 'id' => $u->id]));
        }

        return $out->filter(fn ($r) => filter_var($r['email'], FILTER_VALIDATE_EMAIL))->unique('email')->values();
    }

    /**
     * Why accounts are or aren't on the list (counts only), for /desk → চিঠি.
     *
     * @return array{accounts: int, spam: int, unverified: int, opted_out: int, readers: int, writers: int, signups: int, suspects: int}
     */
    public static function breakdown(): array
    {
        $spam = \Illuminate\Support\Facades\Schema::hasColumn('users', 'spam_reason');
        $clean = fn () => User::query()->when($spam, fn ($q) => $q->whereNull('spam_reason'));

        return [
            'accounts' => User::count(),
            'spam' => $spam ? User::whereNotNull('spam_reason')->count() : 0,
            'unverified' => $clean()->whereNull('email_verified_at')->count(),
            'opted_out' => $clean()->where('mail_opt_out', true)->count(),
            'readers' => self::count('readers'),
            'writers' => self::count('writers'),
            'signups' => NewsletterSubscriber::mailable()->count(),
            'suspects' => BotCheck::pendingCount(),
        ];
    }

    public static function count(string $audience): int
    {
        return self::recipients($audience)->count();
    }

    /** The owner's click: freeze the list and hand it to the scheduler. */
    public static function queue(Newsletter $n, User $owner): int
    {
        abort_unless($n->status === 'draft', 409);
        $list = self::recipients($n->audience);
        foreach ($list->chunk(500) as $chunk) {
            NewsletterDelivery::insertOrIgnore($chunk->map(fn ($r) => ['newsletter_id' => $n->id, 'email' => $r['email'], 'kind' => $r['kind'], 'recipient_id' => $r['id']])->all());
        }
        $n->forceFill(['status' => 'queued', 'approved_by' => $owner->id, 'queued_at' => now(), 'recipients' => $list->count()])->save();
        AuditLog::record('newsletter.queued', null, ['newsletter' => $n->id, 'audience' => $n->audience, 'recipients' => $list->count()]);

        return $list->count();
    }

    /** Every minute from the scheduler: send the next few letters. */
    public static function batch(?int $size = null): int
    {
        $size ??= (int) config('mail.batch', 30);
        if (($left = BulkMail::remainingToday()) !== null) {
            $size = min($size, $left);   // D117: a big letter goes out over several days instead of being refused
        }
        $n = Newsletter::whereIn('status', ['queued', 'sending'])->orderBy('queued_at')->first();
        if (! $n || ! self::realMail() || $size <= 0) {
            return 0;
        }
        $n->status === 'queued' && $n->forceFill(['status' => 'sending'])->save();
        $mailer = BulkMail::mailer();
        $via = BulkMail::provider();
        $done = 0;
        $failedNow = 0;
        $lastError = null;
        foreach ($n->deliveries()->whereNull('sent_at')->whereNull('error')->limit($size)->get() as $d) {
            try {
                Mail::mailer($mailer)->to($d->email)->send(new NewsletterMail($n, self::unsubscribeUrl($d->kind, $d->recipient_id), $d->id));
                $d->forceFill(['sent_at' => now(), 'via' => $via])->save();
                $done++;
            } catch (\Throwable $e) {
                $d->forceFill(['error' => $lastError = mb_substr($e->getMessage(), 0, 200)])->save();
                $failedNow++;
            }
        }
        $n->forceFill(['sent' => $n->deliveries()->whereNotNull('sent_at')->count(), 'failed' => $n->deliveries()->whereNotNull('error')->count()])->save();
        // A whole batch failed: something is wrong with the sending itself (settings, quota, sandbox), not with
        // the addresses. Pause instead of burning through the whole list; the desk shows why and can resend.
        if ($done === 0 && $failedNow >= min(10, $size)) {
            $n->forceFill(['status' => 'cancelled'])->save();
            AuditLog::record('newsletter.paused', null, ['newsletter' => $n->id, 'error' => $lastError]);

            return 0;
        }
        if (! $n->deliveries()->whereNull('sent_at')->whereNull('error')->exists()) {
            $n->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
            AuditLog::record('newsletter.sent', null, ['newsletter' => $n->id, 'sent' => $n->sent, 'failed' => $n->failed]);
        }

        return $done;
    }

    /** Why letters did not go: the most common error messages with how many each. */
    public static function failures(Newsletter $n): array
    {
        // The same cause often names a different address each time ("…failed the check: a@x.com"): group by the
        // message without addresses, so 149 letters with one cause show as one line.
        return $n->deliveries()->whereNotNull('error')->pluck('error')
            ->map(fn ($e) => trim(preg_replace(['/[^\s:;,"<>]+@[^\s:;,"<>]*/u', '/\s+/u'], ['…', ' '], (string) $e)))
            ->countBy()->sortDesc()->take(5)->all();
    }

    /** Plain words for the usual causes (the raw message is shown too). */
    public static function explain(string $error): string
    {
        $e = strtolower($error);

        return match (true) {
            str_contains($e, 'not verified') => __('Amazon SES এখনও “স্যান্ডবক্সে”: শুধু যাচাই করা ঠিকানায় পাঠাতে দেয়। SES কনসোলে Get set up → Request production access করুন (সাধারণত এক দিনের মধ্যে অনুমতি আসে)।'),
            str_contains($e, 'quota') || str_contains($e, 'maximum sending rate') || str_contains($e, 'throttl') => __('Amazon SES-এর দিনের বা সেকেন্ডের সীমা পেরিয়েছে। ENV_FILE-এ MAIL_DAILY_LIMIT সেই সীমায় রাখুন; বাকিগুলো পরদিন যাবে।'),
            str_contains($e, 'authenticat') || str_contains($e, '535') => __('ই-মেল সার্ভারের ইউজারনেম বা পাসওয়ার্ড ভুল (MAIL_BULK_USERNAME / MAIL_BULK_PASSWORD, SES-এর SMTP credentials)।'),
            str_contains($e, 'connection could not be established') || str_contains($e, 'timed out') || str_contains($e, 'getaddrinfo') => __('ই-মেল সার্ভারে পৌঁছানো যায়নি: MAIL_BULK_HOST বা পোর্ট (587) দেখুন।'),
            str_contains($e, 'limit') || str_contains($e, 'too many') || str_contains($e, '550') || str_contains($e, '554') || str_contains($e, '451') => __('ই-মেল সার্ভার এত চিঠি একসঙ্গে নিচ্ছে না (সম্ভবত Hostinger-এর সীমা, কারণ চিঠি SES দিয়ে যাচ্ছে না)। ENV_FILE-এ MAIL_BULK_HOST ইত্যাদি আছে কি না দেখুন।'),
            default => __('নিচের মূল বার্তাটি দেখুন।'),
        };
    }

    /** After fixing the cause: the letters that failed go again, and a paused letter carries on. */
    public static function retry(Newsletter $n): int
    {
        $count = $n->deliveries()->whereNotNull('error')->whereNull('bounce')->update(['error' => null]);
        $n->forceFill(['status' => 'sending', 'failed' => $n->deliveries()->whereNotNull('error')->count()])->save();
        AuditLog::record('newsletter.retry', null, ['newsletter' => $n->id, 'letters' => $count]);

        return $count;
    }

    /** "আমাকে একটা পাঠান": the owner sees the real e-mail before sending to everyone. */
    public static function test(Newsletter $n, User $to): void
    {
        Mail::mailer(BulkMail::mailer())->to($to->email)->send(new NewsletterMail($n, url('/').'/'));
    }

    public static function unsubscribeUrl(string $kind, int $id): string
    {
        return $kind === 'writer'
            ? URL::signedRoute('newsletter.unsubscribe.writer', ['user' => $id])
            : URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $id]);
    }
}
