<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * D184, "হাতপাখার বন্ধু" (docs/MARKETING.md, play 6): every member has an invite link. A friend who opens it, signs up
 * and then reads a piece or plays a game counts; friends earn the বন্ধু medal (1, 5, 15). No money and no prizes, so
 * there is nothing worth cheating for. The link only sets a cookie that remembers who invited, for 30 days.
 */
final class Invites
{
    public const COOKIE = 'hp_inv';

    public static function code(User $u): string
    {
        if (! $u->invite_code) {
            do {
                $code = Str::lower(Str::random(8));
            } while (User::where('invite_code', $code)->exists());
            $u->forceFill(['invite_code' => $code])->saveQuietly();
        }

        return $u->invite_code;
    }

    public static function url(User $u): string
    {
        return Url::to('/bondhu/'.self::code($u));
    }

    /** At sign-up: remember who invited this person (never themselves). */
    public static function claim(User $new, Request $request): void
    {
        $code = (string) $request->cookie(self::COOKIE);
        $by = preg_match('/^[a-z0-9]{8}$/', $code) ? User::where('invite_code', $code)->first() : null;
        if ($by && $by->id !== $new->id) {
            $new->forceFill(['invited_by' => $by->id])->saveQuietly();
        }
    }

    /** Friends who came and have read a piece or played a game (accounts flagged as spam do not count). */
    public static function count(User $u): int
    {
        return (int) DB::table('users')->where('invited_by', $u->id)->whereNull('spam_reason')
            ->where(fn ($q) => $q->whereExists(fn ($s) => $s->from('reading_history')->whereColumn('reading_history.user_id', 'users.id'))
                ->orWhereExists(fn ($s) => $s->from('game_scores')->whereColumn('game_scores.user_id', 'users.id')))->count();
    }

    /** "আমার সপ্তাহ": what this member did in the last seven days, to share with the invite link. @return array{read: int, days: int, streak: int, text: string} */
    public static function week(User $u): array
    {
        $read = (int) DB::table('reading_history')->where('user_id', $u->id)->where('read_at', '>=', now()->subDays(7))->count();
        $days = (int) DB::table('game_scores')->where('user_id', $u->id)->where('day', '>=', now('Asia/Kolkata')->subDays(6)->toDateString())->distinct()->count('day');
        $streak = GameScores::streak($u)['now'];
        $did = array_filter([
            $read ? Bn::num((string) $read).'টি লেখা পড়েছি' : null,
            $days ? Bn::num((string) $days).' দিন শব্দের খেলা খেলেছি'.($streak >= 3 ? ' (টানা '.Bn::num((string) $streak).' দিন)' : '') : null,
        ]);
        $text = ($did ? 'এ সপ্তাহে হাতপাখায় আমি '.implode(', ', $did).'।' : 'আমি হাতপাখা পড়ি: বাংলা গল্প, কবিতা, আর রোজের শব্দের খেলা।').' তুমিও এসো: '.self::url($u);

        return ['read' => $read, 'days' => $days, 'streak' => $streak, 'text' => $text];
    }
}
