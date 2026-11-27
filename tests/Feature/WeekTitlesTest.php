<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GameScores;
use App\Support\GameTitles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** D329 (owner: "smaller titles like সপ্তাহের সেরা too, so people will get engaged"). */
class WeekTitlesTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $when): void
    {
        Carbon::setTestNow(Carbon::parse($when, 'Asia/Kolkata'));
        Cache::flush();
    }

    private function played(User $u, string $day, int $points): void
    {
        DB::table('game_scores')->insert(['user_id' => $u->id, 'game' => 'banan', 'day' => $day, 'points' => $points, 'seconds' => 60, 'created_at' => $day.' 10:00:00']);
    }

    public function test_a_week_runs_sunday_to_saturday_and_counts_the_best_six_days(): void
    {
        $this->at('2026-12-12 20:00');   // Saturday
        $u = User::factory()->create();
        foreach (['2026-12-05' => 100, '2026-12-06' => 10, '2026-12-07' => 20, '2026-12-08' => 30, '2026-12-09' => 40, '2026-12-10' => 50, '2026-12-11' => 60, '2026-12-12' => 70] as $day => $p) {
            $this->played($u, $day, $p);
        }
        $row = GameScores::ranking('sweek')[0];
        $this->assertSame(7, $row['days'], 'Sunday 6th to Saturday 12th; Saturday 5th was last week');
        $this->assertSame(270, $row['points'], 'The best six of seven: the 10 of Sunday is left out');
        $this->assertSame('৬ – ১২ ডিসেম্বর', GameTitles::label('week', '2026-12-06'));
        $this->assertSame('২৯ নভেম্বর – ৫ ডিসেম্বর', GameTitles::label('week', '2026-11-29'));
    }

    public function test_sunday_writes_last_weeks_first_three_once_told_on_the_phone_not_by_mail(): void
    {
        Notification::fake();
        $this->at('2026-12-05 12:00');
        $a = User::factory()->create(['name' => 'অমল']);
        $b = User::factory()->create(['name' => 'বিমল']);
        $c = User::factory()->create(['name' => 'কমল']);
        $twoDays = User::factory()->create(['name' => 'দুদিনের']);
        $hidden = User::factory()->create(['name' => 'লুকোনো', 'board_hidden' => true]);
        foreach (['2026-11-30', '2026-12-01', '2026-12-02', '2026-12-03'] as $day) {
            $this->played($a, $day, 300);
            $this->played($b, $day, 200);
            $this->played($c, $day, 100);
            $this->played($hidden, $day, 300);
        }
        $this->played($twoDays, '2026-12-01', 300);
        $this->played($twoDays, '2026-12-02', 300);

        $this->at('2026-12-06 00:20');   // Sunday
        $this->assertGreaterThanOrEqual(3, GameTitles::award());
        $rows = DB::table('game_titles')->where('kind', 'week')->orderBy('place')->get();
        $this->assertSame([$a->id, $b->id, $c->id], $rows->pluck('user_id')->map(fn ($x) => (int) $x)->all(), 'Three days or more, names shown');
        $this->assertSame('2026-11-29', $rows[0]->period);
        $this->assertSame(0, GameTitles::award(), 'Once');
        Notification::assertNothingSent();

        $this->assertSame(['w'], GameTitles::marks()[$a->id] ?? null, 'Last week\'s first wears the mark this week');
        $t = GameTitles::of($a);
        $this->assertSame(['2026-11-29'], $t['weeks']);
        $hall = GameTitles::hall();
        $this->assertCount(3, $hall['lastWeek']);

        $this->get('/khela/board/')->assertOk()->assertSee('এ সপ্তাহে এগিয়ে')->assertSee('গত সপ্তাহের সেরা তিন')->assertSee('অমল')->assertSee('সপ্তাহের সেরা');
        $this->actingAs($a)->get('/account/profile/')->assertOk()->assertSee('সপ্তাহের সেরা');

        $this->at('2026-12-13 00:20');   // a week later the mark passes on (nobody played)
        GameTitles::award();
        $this->assertArrayNotHasKey($a->id, GameTitles::marks());
    }
}
