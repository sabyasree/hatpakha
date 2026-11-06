<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D44: the AI editor works on a schedule, prepares only, and rests when paused. */
class AiShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_routine_is_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode("\n");
        foreach (['inbox', 'backlog', 'issue', 'brief'] as $task) {
            $this->assertStringContainsString("hatpakha:ai-shift {$task}", $events);
        }
    }

    public function test_inbox_screens_comments_and_leaves_a_heartbeat(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        \App\Ai\Claude::$fake = fn () => ['verdict' => 'ok', 'reason' => 'ভালো'];
        $c = Comment::create(['post_id' => $this->makePost(['slug' => 's1'])->id, 'author_name' => 'ক', 'body' => 'সুন্দর', 'status' => 'pending']);
        $this->artisan('hatpakha:ai-shift inbox')->assertSuccessful();
        $this->assertSame('ok', $c->fresh()->ai_verdict);
        $this->assertSame('pending', $c->fresh()->status, 'Prepares only');
        $this->assertNotNull(Setting::get('ai.heartbeat'));
        \App\Ai\Claude::$fake = null;
    }

    public function test_paused_editor_rests(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        Setting::put('ai.paused', '1');
        \App\Ai\Claude::$fake = fn () => throw new \RuntimeException('must not be called');
        Comment::create(['post_id' => $this->makePost(['slug' => 's2'])->id, 'author_name' => 'ক', 'body' => 'x', 'status' => 'pending']);
        $this->artisan('hatpakha:ai-shift inbox')->expectsOutputToContain('Resting')->assertSuccessful();
        \App\Ai\Claude::$fake = null;
    }

    public function test_morning_brief_is_stored_and_mailed_only_with_real_mail(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \App\Models\User::factory()->create(['role' => \App\Enums\Role::Admin]);
        $this->artisan('hatpakha:ai-shift brief')->assertSuccessful();
        $this->assertArrayHasKey('waiting_pieces', json_decode(Setting::get('ai.brief'), true));
        \Illuminate\Support\Facades\Notification::assertNothingSent();   // MAIL_MAILER=log in tests
    }
}
