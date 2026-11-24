<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\User;
use App\Support\GameScores;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * D282 (owner: "there is no option to see the game leaderboard details from the desk"): the games as the editor needs
 * them. How many play, the board for today / the week / the month / the year exactly as readers see it, plus the
 * players readers don't see (hidden by their own choice, or kept off by the desk), each player's games in the
 * period, and the titles given each month and year. Only reading: keeping a player off is on their user row (D245).
 */
class GameBoard extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.game-board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'খেলার তালিকা';

    protected static ?string $title = 'খেলার তালিকা';

    protected static string|\UnitEnum|null $navigationGroup = 'হিসাব ও খাতা';   // D279

    protected static ?int $navigationSort = 2;

    public const RANGES = ['today' => 'আজ', 'week' => 'সাত দিন', 'month' => 'এই মাস', 'year' => 'এই বছর'];

    public string $range = 'month';

    public ?int $open = null;   // the player whose games are shown

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public function setRange(string $range): void
    {
        $this->range = isset(self::RANGES[$range]) ? $range : 'month';
        $this->open = null;
    }

    public function toggle(int $userId): void
    {
        $this->open = $this->open === $userId ? null : $userId;
    }

    /** Numbers at the top: players and games today, this week and this month (Kolkata days). */
    public function summary(): array
    {
        $today = now('Asia/Kolkata')->toDateString();
        $week = now('Asia/Kolkata')->subDays(6)->toDateString();
        $month = now('Asia/Kolkata')->startOfMonth()->toDateString();
        $q = fn (string $from) => DB::table('game_scores')->where('day', '>=', $from);

        return [
            'today' => (int) $q($today)->distinct()->count('user_id'), 'todayGames' => (int) $q($today)->count(),
            'week' => (int) $q($week)->distinct()->count('user_id'), 'month' => (int) $q($month)->distinct()->count('user_id'),
            'new' => (int) DB::table('game_scores')->select('user_id')->groupBy('user_id')->havingRaw('min(day) >= ?', [$week])->get()->count(),
            'byGame' => $q($today)->groupBy('game')->selectRaw('game, count(*) as n')->pluck('n', 'game')->map(fn ($n) => (int) $n)->all(),
        ];
    }

    /** Everyone in the period, as the board ranks them; hidden and kept-off players included and marked. */
    public function rows(): array
    {
        $ranking = GameScores::ranking($this->range);
        $users = User::whereIn('id', array_column($ranking, 'user_id'))->get(['id', 'name', 'email', 'board_hidden', 'game_blocked_at'])->keyBy('id');
        $levels = GameScores::levelNames(array_column($ranking, 'user_id'));
        $rank = 0;

        return array_map(function ($r) use ($users, $levels, &$rank) {
            $u = $users[$r['user_id']] ?? null;
            $why = $u?->game_blocked_at ? 'ডেস্ক বাদ দিয়েছে' : ($u?->board_hidden ? 'নিজে নাম লুকিয়েছেন' : null);
            $rank += $why ? 0 : 1;

            return $r + ['rank' => $why ? null : $rank, 'why' => $why, 'email' => (string) $u?->email, 'level' => $levels[$r['user_id']] ?? null];
        }, $ranking);
    }

    /** One player's games in the period: day by day, each game's points. */
    public function games(int $userId): array
    {
        [$from, $to] = match ($this->range) {
            'today' => [now('Asia/Kolkata')->toDateString(), now('Asia/Kolkata')->toDateString()],
            'week' => [now('Asia/Kolkata')->subDays(6)->toDateString(), now('Asia/Kolkata')->toDateString()],
            'month' => [now('Asia/Kolkata')->startOfMonth()->toDateString(), now('Asia/Kolkata')->endOfMonth()->toDateString()],
            default => [now('Asia/Kolkata')->startOfYear()->toDateString(), now('Asia/Kolkata')->endOfYear()->toDateString()],
        };

        return DB::table('game_scores')->where('user_id', $userId)->whereBetween('day', [$from, $to])->orderByDesc('day')->limit(400)
            ->get(['day', 'game', 'points', 'seconds'])->groupBy(fn ($r) => substr((string) $r->day, 0, 10))
            ->map(fn ($day) => $day->mapWithKeys(fn ($r) => [$r->game => [(int) $r->points, (int) $r->seconds]])->all())->all();
    }

    public function userUrl(int $id): string
    {
        return \App\Filament\Resources\Users\UserResource::getUrl('index').'?tableSearch='.rawurlencode((string) User::find($id)?->email);
    }
}
