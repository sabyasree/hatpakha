<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * Staging never e-mails readers or writers (owner, 2026-11-09), whatever is in its ENV_FILE. Off the live
 * site (hatpakha.com), bulk mail is off altogether, and any other e-mail is delivered only when every
 * recipient is desk staff (editor or above), so the team can still get login codes and password resets there.
 * Everything else is dropped and written to the log. MAIL_OFF_LIVE=allow turns this off (tests only).
 */
final class MailGuard
{
    public static function live(): bool
    {
        return RelaunchLetters::onLiveSite() || config('mail.off_live') === 'allow';
    }

    /** MessageSending listener: returning false cancels the e-mail. */
    public static function check(MessageSending $event): bool
    {
        if (self::live()) {
            return true;
        }
        $to = collect(array_merge($event->message->getTo(), $event->message->getCc(), $event->message->getBcc()))
            ->map(fn ($a) => mb_strtolower($a->getAddress()))->unique()->values();
        $staff = $to->isNotEmpty() && User::whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(email)'), $to->all())->get(['email', 'role'])
            ->filter(fn (User $u) => $u->role instanceof Role ? $u->role->atLeast(Role::Editor) : Role::tryFrom((string) $u->role)?->atLeast(Role::Editor))
            ->count() === $to->count();
        if (! $staff) {
            Log::info('Mail blocked off the live site', ['subject' => $event->message->getSubject(), 'recipients' => $to->count()]);
        }

        return $staff;
    }
}
