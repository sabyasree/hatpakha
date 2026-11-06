<?php

namespace Tests\Feature;

use App\Ai\Chief;
use App\Ai\Claude;
use App\Ai\Team;
use App\Enums\Role;
use App\Models\AiReview;
use App\Models\EditorTask;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D49: the AI team, per-agent pause, and the editor-in-chief's checks. */
class AiTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_pausing_one_agent_stops_only_that_agent(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        Claude::$fake = fn () => ['ok' => true];
        Team::setPaused('seo', true);
        $this->assertSame([null, 'paused: seo'], Claude::json('seo', 's', 'u', []));
        $this->assertSame([['ok' => true], null], Claude::json('picks', 's', 'u', []));
        Claude::$fake = null;
    }

    public function test_team_page_lists_all_seven_and_toggles(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        $page = Livewire::test(\App\Filament\Pages\AiTeam::class)->assertOk();
        foreach (['এআই সম্পাদক', 'প্রুফ রিডার', 'এসইও বিশেষজ্ঞ', 'গ্রাফিক ডিজাইনার', 'মার্কেটিং বিশেষজ্ঞ', 'সম্পর্ক ব্যবস্থাপক', 'প্রধান সম্পাদক (এআই)'] as $name) {
            $page->assertSee($name);
        }
        $page->call('toggle', 'marketing');
        $this->assertTrue(Team::paused('marketing'));
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(\App\Filament\Pages\AiTeam::canAccess());
    }

    public function test_chief_raises_and_clears_alerts(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        Setting::put('ai.heartbeat', now()->toIso8601String());
        $post = $this->makePost(['slug' => 'x']);
        foreach (range(1, 3) as $i) {
            AiReview::create(['post_id' => $post->id, 'provider' => 'anthropic', 'model' => 'm', 'status' => 'failed', 'error' => 'timeout']);
        }
        $this->assertArrayHasKey('failures', Chief::check());
        Chief::check();
        $this->assertSame(1, EditorTask::where('type', 'alert')->where('key', 'failures')->where('status', 'open')->count(), 'One alert, not one per hour');

        AiReview::query()->update(['created_at' => now()->subDays(2)]);
        Chief::check();
        $this->assertSame(0, EditorTask::where('type', 'alert')->where('status', 'open')->where('key', 'failures')->count(), 'Cleared when fixed');
    }
}
