<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\Knowledge;
use App\Ai\Strategist;
use App\Enums\Role;
use App\Models\EditorTask;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D63: the strategist researches, writes a memo for the owner, and its priorities guide the team. */
class StrategistTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_memo_reaches_the_owner_and_guides_the_team(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        Claude::$fake = function ($purpose, $user) {
            $this->assertStringContainsString('Our last 30 days', $user, 'The strategist sees our own numbers');

            return ['summary' => 'বইমেলার মরসুম আসছে।', 'market' => [['point' => 'ধারাবাহিক উপন্যাস জনপ্রিয়', 'url' => 'https://example.org/a'], ['point' => 'উৎস নেই', 'url' => 'x']],
                'strengths' => ['সম্পাদনা'], 'weaknesses' => ['সার্চ নেই'], 'priorities' => [['what' => 'বইমেলা সংখ্যার পরিকল্পনা', 'who' => 'editor', 'check' => '২০টি লেখা']]];
        };
        $this->assertNull(Strategist::run());
        $task = EditorTask::firstWhere('type', 'strategy');
        $this->assertCount(1, $task->payload['market'], 'Only sourced market points');
        $this->assertStringContainsString('বইমেলা সংখ্যার পরিকল্পনা', Knowledge::forPurpose('issue_plan'));
        $this->assertStringNotContainsString('বইমেলা সংখ্যার পরিকল্পনা', Knowledge::forPurpose('proof'));
        $this->assertNull(Strategist::monthly(), 'Once a month unless asked');
    }

    public function test_owner_can_ask_for_a_new_plan_from_the_team_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Pages\AiTeam::class)->assertSee('এই মাসের কৌশল')->call('newStrategy');
        $this->assertNotNull(Setting::get('strategy.requested'));
    }
}
