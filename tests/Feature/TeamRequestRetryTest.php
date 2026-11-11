<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\TeamJobs;
use App\Models\TeamRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner (2026-11-11): "it should auto fix and auto retry; I don't know if it is going on, finished or failed". */
class TeamRequestRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_a_passing_failure_is_tried_again_by_itself_then_explained(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'sk-test']);
        $calls = 0;
        Claude::$fake = function () use (&$calls) {
            $calls++;

            return null;   // no answer: a passing problem
        };
        $r = TeamRequest::create(['agent' => 'copywriter', 'instruction' => 'ভাইফোঁটা নিয়ে একটি গল্প', 'status' => 'queued']);

        TeamJobs::runQueued();
        $r->refresh();
        $this->assertSame('queued', $r->status, 'Tried again by itself');
        $this->assertSame(1, $r->attempts);
        $this->assertStringContainsString('আবার চেষ্টা হচ্ছে', $r->result);

        TeamJobs::runQueued();
        TeamJobs::runQueued();
        $r->refresh();
        $this->assertSame('failed', $r->status, 'After 3 tries it stops and says why');
        $this->assertSame(TeamJobs::MAX_TRIES, $r->attempts);
        TeamJobs::runQueued();
        $this->assertSame('failed', $r->fresh()->status);
    }

    public function test_errors_are_explained_in_plain_words(): void
    {
        $this->assertStringContainsString('ছোট ছোট ভাগে', TeamJobs::explain('করা গেল না: '.Claude::CUT_OFF));
        $this->assertStringContainsString('ব্যস্ত', TeamJobs::explain('Overloaded (529)'));
        $this->assertTrue(TeamJobs::passing(Claude::CUT_OFF));
        $this->assertFalse(TeamJobs::passing('লেখাটি খুঁজে পাইনি।'), 'A problem in the request itself is not retried');
        $this->assertSame('লেখাটি খুঁজে পাইনি।', TeamJobs::explain('লেখাটি খুঁজে পাইনি।'));
    }

    public function test_a_cut_off_answer_is_asked_for_again_with_more_room(): void
    {
        $this->assertSame('the answer was too long and got cut off (max_tokens)', Claude::CUT_OFF);
        $src = file_get_contents(app_path('Ai/Claude.php'));
        $this->assertStringContainsString('min(64000, $maxTokens * 3)', $src);
    }

    /** Owner + UX review: each job shows its steps; what needs the owner comes first. */
    public function test_the_page_shows_each_jobs_steps_and_new_results_first(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => \App\Enums\Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));

        $waiting = TeamRequest::create(['instruction' => 'একটি গল্প', 'status' => 'queued']);
        $working = TeamRequest::create(['agent' => 'copywriter', 'instruction' => 'ভাইফোঁটার গল্প', 'status' => 'running', 'started_at' => now()->subMinutes(3)]);
        $working->step('route', ['start' => now()->subMinutes(3)->toIso8601String(), 'end' => now()->subMinutes(3)->toIso8601String(), 'note' => 'কপিরাইটার, নিজের কথায় কাজ']);
        $working->step('work', ['start' => now()->subMinutes(3)->toIso8601String()]);
        $done = TeamRequest::create(['agent' => 'marketing', 'job' => 'daily', 'status' => 'done', 'result' => 'তিনটি পোস্ট তৈরি', 'finished_at' => now(),
            'steps' => ['route' => ['skipped' => true], 'work' => ['start' => now()->subMinutes(2)->toIso8601String(), 'end' => now()->toIso8601String()]]]);
        $failed = TeamRequest::create(['agent' => 'copywriter', 'instruction' => 'চিঠি', 'status' => 'failed', 'attempts' => 3, 'result' => TeamJobs::explain('Overloaded'), 'finished_at' => now(),
            'steps' => ['route' => ['start' => now()->toIso8601String(), 'end' => now()->toIso8601String(), 'note' => 'কপিরাইটার'], 'work' => ['start' => now()->toIso8601String()]]]);

        $this->assertSame(['done', 'skipped', 'done', 'done'], array_column(\App\Filament\Pages\AiTeam::stepsFor($done), 'state'));
        $this->assertSame(['done', 'done', 'current', 'pending'], array_column(\App\Filament\Pages\AiTeam::stepsFor($working), 'state'));
        $this->assertSame(['current', 'pending', 'pending', 'pending'], array_column(\App\Filament\Pages\AiTeam::stepsFor($waiting), 'state'));
        $this->assertSame(['done', 'done', 'failed', 'failed'], array_column(\App\Filament\Pages\AiTeam::stepsFor($failed), 'state'));

        $html = \Livewire\Livewire::test(\App\Filament\Pages\AiTeam::class)->html();
        $this->assertStringContainsString('hp-req-pill--ready', $html);
        $this->assertStringContainsString('ধরে চলছে', $html);
        $this->assertStringContainsString('দরকার হয়নি', $html);
        $this->assertLessThan(strpos($html, 'wire:key="req-'.$working->id.'"'), strpos($html, 'wire:key="req-'.$done->id.'"'), 'A new result comes before running work');

        \Livewire\Livewire::test(\App\Filament\Pages\AiTeam::class)->call('markSeen', $done->id);
        $this->assertNotNull($done->fresh()->seen_at);
    }
}
