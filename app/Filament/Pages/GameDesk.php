<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Support\WeeklyQuiz;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * D328, D330: the desk side of the new games, for a look when wanted; nothing waits here. (1) পত্রিকা-কুইজ: written,
 * checked and published by itself every Saturday; here it can be read, changed, withdrawn or written again. (2) পাড়ার
 * দল: every team; names are screened by themselves when a team is made, and a team can still be taken off the boards.
 */
class GameDesk extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.game-desk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|\UnitEnum|null $navigationGroup = 'সংখ্যা ও প্রকাশ';

    protected static ?string $navigationLabel = 'খেলা: কুইজ ও দল';

    protected static ?string $title = 'খেলা: পত্রিকা-কুইজ ও পাড়ার দল';

    protected static ?string $slug = 'game-desk';

    protected static ?int $navigationSort = 9;

    /** week => list of questions being edited */
    public array $edit = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    // D330 (owner: "For games, I don't want to spend time as admin"): no badge; nothing here waits for anyone.

    public function mount(): void
    {
        foreach ($this->quizzes() as $q) {
            $this->edit[$q['week']] = $q['questions'] ?: [self::blank()];
        }
    }

    public static function blank(): array
    {
        return ['q' => '', 'options' => ['', '', '', ''], 'answer' => 0, 'post_id' => null, 'why' => ''];
    }

    /** This week's quiz and the next. */
    public function quizzes(): array
    {
        $now = WeeklyQuiz::week();
        $next = Carbon::parse($now)->addDays(7)->toDateString();
        $rows = DB::table('game_quizzes')->whereIn('week', [$now, $next])->get()->keyBy('week');
        $out = [];
        foreach ([$next, $now] as $week) {
            $r = $rows[$week] ?? null;
            $out[] = ['week' => $week, 'current' => $week === $now, 'exists' => (bool) $r, 'status' => $r->status ?? 'none', 'note' => $r->note ?? null,
                'questions' => $r ? array_values((array) json_decode((string) $r->questions, true)) : [], 'approved_at' => $r->approved_at ?? null];
        }

        return $out;
    }

    protected function getViewData(): array
    {
        $weeks = array_column($this->quizzes(), 'week');
        $pieces = [];
        foreach ($weeks as $w) {
            $pieces[$w] = WeeklyQuiz::pieces($w)->mapWithKeys(fn ($p) => [$p->id => $p->title])->all();
        }
        $teams = DB::table('game_teams')->orderByDesc('created_at')->limit(200)->get();
        $counts = DB::table('users')->whereNotNull('game_team_id')->groupBy('game_team_id')->selectRaw('game_team_id, count(*) as n')->pluck('n', 'game_team_id');

        return ['quizzes' => $this->quizzes(), 'pieces' => $pieces, 'teams' => $teams, 'counts' => $counts, 'ranks' => collect(\App\Support\GameSocial::teams())->keyBy('id')];
    }

    public function draft(string $week): void
    {
        $why = WeeklyQuiz::draft($week, true);
        $this->mount();
        $why ? Notification::make()->warning()->title($why)->send() : Notification::make()->success()->title(__('এআই নতুন প্রশ্ন দিয়েছে। পড়ে দেখে অনুমোদন দিন।'))->send();
    }

    public function addQuestion(string $week): void
    {
        if (count($this->edit[$week] ?? []) < WeeklyQuiz::QUESTIONS) {
            $this->edit[$week][] = self::blank();
        }
    }

    public function removeQuestion(string $week, int $i): void
    {
        unset($this->edit[$week][$i]);
        $this->edit[$week] = array_values($this->edit[$week]);
    }

    /** Save what is in the form; approve too if asked. */
    public function save(string $week, bool $approve = false): void
    {
        $qs = array_values(array_filter(array_map(fn ($q) => ['q' => trim((string) ($q['q'] ?? '')), 'options' => array_map(fn ($o) => trim((string) $o), array_pad(array_slice((array) ($q['options'] ?? []), 0, 4), 4, '')),
            'answer' => (int) ($q['answer'] ?? 0), 'post_id' => ($q['post_id'] ?? null) ? (int) $q['post_id'] : null, 'why' => trim((string) ($q['why'] ?? ''))], $this->edit[$week] ?? []),
            fn ($q) => $q['q'] !== '' || array_filter($q['options'])));
        $bad = array_filter($qs, fn ($q) => ! WeeklyQuiz::valid($q));
        if ($approve && ($bad || count($qs) < 3)) {
            Notification::make()->danger()->title(__('অনুমোদন হল না: অন্তত তিনটে প্রশ্ন লাগে, প্রতিটিতে চারটে আলাদা উত্তর।'))->send();

            return;
        }
        $row = DB::table('game_quizzes')->where('week', $week)->first();
        $data = ['questions' => json_encode($qs, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'note' => null]
            + ($approve ? ['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()] : []);
        $row ? DB::table('game_quizzes')->where('id', $row->id)->update($data) : DB::table('game_quizzes')->insert($data + ['week' => $week, 'status' => $approve ? 'approved' : 'draft', 'created_at' => now()]);
        WeeklyQuiz::forget($week);
        AuditLog::record($approve ? 'quiz.approved' : 'quiz.saved', null, ['week' => $week, 'questions' => count($qs)]);
        Notification::make()->success()->title($approve ? __('অনুমোদিত। সপ্তাহ শুরু হলে পাঠকেরা খেলতে পারবেন।') : __('রাখা হয়েছে'))->send();
        $this->mount();
    }

    public function unapprove(string $week): void
    {
        DB::table('game_quizzes')->where('week', $week)->update(['status' => 'draft', 'approved_at' => null, 'approved_by' => null, 'updated_at' => now()]);
        WeeklyQuiz::forget($week);
        AuditLog::record('quiz.unapproved', null, ['week' => $week]);
    }

    public function hideTeam(int $id, bool $hide = true): void
    {
        DB::table('game_teams')->where('id', $id)->update(['hidden_at' => $hide ? now() : null, 'updated_at' => now()]);
        \Illuminate\Support\Facades\Cache::forget('hp.teams.'.now('Asia/Kolkata')->format('Y-m'));
        AuditLog::record($hide ? 'games.team_hidden' : 'games.team_shown', null, ['team' => $id]);
    }
}
