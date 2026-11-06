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
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /**
     * Readers: confirmed sign-ups, plus every account with a verified e-mail that has not switched
     * the letter off (owner's decision, D59: accounts get the letter by default, one click to stop).
     * Writers: registered writers who have not opted out.
     */
    public static function recipients(string $audience): Collection
    {
        $out = collect();
        if (in_array($audience, ['readers', 'both'], true)) {
            NewsletterSubscriber::mailable()->get(['id', 'email'])
                ->each(fn ($s) => $out->push(['email' => mb_strtolower(trim($s->email)), 'kind' => 'subscriber', 'id' => $s->id]));
            User::where('mail_opt_out', false)->whereNotNull('email_verified_at')
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('users', 'spam_reason'), fn ($q) => $q->whereNull('spam_reason'))
                ->get(['id', 'email'])
                ->each(fn ($u) => $out->push(['email' => mb_strtolower(trim($u->email)), 'kind' => 'writer', 'id' => $u->id]));
        }
        if (in_array($audience, ['writers', 'both'], true)) {
            User::where('mail_opt_out', false)->whereNotNull('email_verified_at')
                ->where(fn ($q) => $q->whereIn('role', [Role::Writer->value])->orWhereHas('author', fn ($a) => $a->where('published_count', '>', 0)))
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('users', 'spam_reason'), fn ($q) => $q->whereNull('spam_reason'))
                ->get(['id', 'email'])
                ->each(fn ($u) => $out->push(['email' => mb_strtolower(trim($u->email)), 'kind' => 'writer', 'id' => $u->id]));
        }

        return $out->filter(fn ($r) => filter_var($r['email'], FILTER_VALIDATE_EMAIL))->unique('email')->values();
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
        $n = Newsletter::whereIn('status', ['queued', 'sending'])->orderBy('queued_at')->first();
        if (! $n || ! self::realMail()) {
            return 0;
        }
        $n->status === 'queued' && $n->forceFill(['status' => 'sending'])->save();
        $done = 0;
        foreach ($n->deliveries()->whereNull('sent_at')->whereNull('error')->limit($size)->get() as $d) {
            try {
                Mail::to($d->email)->send(new NewsletterMail($n, self::unsubscribeUrl($d->kind, $d->recipient_id)));
                $d->forceFill(['sent_at' => now()])->save();
                $done++;
            } catch (\Throwable $e) {
                $d->forceFill(['error' => mb_substr($e->getMessage(), 0, 200)])->save();
            }
        }
        $n->forceFill(['sent' => $n->deliveries()->whereNotNull('sent_at')->count(), 'failed' => $n->deliveries()->whereNotNull('error')->count()])->save();
        if (! $n->deliveries()->whereNull('sent_at')->whereNull('error')->exists()) {
            $n->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
            AuditLog::record('newsletter.sent', null, ['newsletter' => $n->id, 'sent' => $n->sent, 'failed' => $n->failed]);
        }

        return $done;
    }

    /** "আমাকে একটা পাঠান": the owner sees the real e-mail before sending to everyone. */
    public static function test(Newsletter $n, User $to): void
    {
        Mail::to($to->email)->send(new NewsletterMail($n, url('/').'/'));
    }

    public static function unsubscribeUrl(string $kind, int $id): string
    {
        return $kind === 'writer'
            ? URL::signedRoute('newsletter.unsubscribe.writer', ['user' => $id])
            : URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $id]);
    }
}
