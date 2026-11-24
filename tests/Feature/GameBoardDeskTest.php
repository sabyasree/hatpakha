<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** D282 (owner: "there is no option to see the game leaderboard details from the desk"). */
class GameBoardDeskTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_desk_sees_the_board_hidden_players_and_one_players_games(): void
    {
        Carbon::setTestNow('2026-12-15 06:00:00');
        $a = User::factory()->create(['name' => 'রিনা']);
        $b = User::factory()->create(['name' => 'সুমন', 'board_hidden' => true]);
        DB::table('game_scores')->insert([
            ['user_id' => $a->id, 'game' => 'word', 'day' => '2026-12-15', 'points' => 90, 'seconds' => 120],
            ['user_id' => $a->id, 'game' => 'riddle', 'day' => '2026-12-14', 'points' => 70, 'seconds' => 60],
            ['user_id' => $b->id, 'game' => 'word', 'day' => '2026-12-15', 'points' => 100, 'seconds' => 90],
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(\App\Filament\Pages\GameBoard::canAccess(), 'Players\' details are for the owner');

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Pages\GameBoard::class)->assertOk()
            ->assertSee('রিনা')->assertSee('সুমন')->assertSee('নিজে নাম লুকিয়েছেন')->assertSee('আজ খেলেছেন')
            ->call('toggle', $a->id)->assertSee('শব্দ-খেলা ৯০')
            ->call('setRange', 'today')->assertSee('রিনা');
    }
}
