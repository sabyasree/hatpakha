<?php

namespace Tests\Feature;

use App\Ai\Chief;
use App\Ai\EditorJobs;
use App\Models\AiUsage;
use App\Models\Certificate;
use App\Models\Contest;
use App\Models\EditorTask;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D56: deadline watch, monthly team review, publication certificates on the owner's click. */
class ChiefDutiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_deadline_watch_flags_empty_days_and_ended_contests(): void
    {
        Setting::put('ai.heartbeat', now()->toIso8601String());
        $this->makePost(['status' => 'pending', 'slug' => 'w']);
        $contest = Contest::create(['title' => 'গল্প প্রতিযোগিতা', 'status' => 'open', 'ends_at' => now()->subDay()]);
        $this->makePost(['status' => 'pending', 'slug' => 'e', 'contest_id' => $contest->id]);
        $raised = Chief::check();
        $this->assertArrayHasKey('slots', $raised);
        $this->assertArrayHasKey('contest', $raised);
        $contest->update(['status' => 'judging']);
        $this->assertArrayNotHasKey('contest', Chief::check());
    }

    public function test_team_review_once_a_month_with_forecast(): void
    {
        AiUsage::create(['provider' => 'anthropic', 'model' => 'm', 'purpose' => 'review', 'input_tokens' => 1, 'output_tokens' => 1, 'cost_usd' => 0.7, 'created_at' => now()->subDays(2)]);
        $task = Chief::teamReview();
        $this->assertGreaterThan(0, $task->payload['forecast']);
        $this->assertArrayHasKey('editor', $task->payload['agents']);
        $this->assertNull(Chief::teamReview());
    }

    public function test_certificates_are_listed_then_issued_only_on_click(): void
    {
        $writer = User::factory()->create();
        $post = $this->makePost(['status' => 'published', 'slug' => 'c', 'published_at' => now()->subDay(), 'submitted_by' => $writer->id]);
        $post->authors()->sync([\App\Models\Author::create(['user_id' => $writer->id, 'display_name' => 'লেখক', 'slug' => 'lekhok'])->id => ['position' => 0]]);
        $task = EditorJobs::certificates();
        $this->assertArrayHasKey($post->id, $task->payload['posts']);
        $this->assertSame(0, Certificate::count(), 'Nothing before the click');
        $this->assertSame(1, EditorJobs::issueCertificates([$post->id]));
        $this->assertSame(0, EditorJobs::issueCertificates([$post->id]), 'Never twice');
        $this->assertSame(1, EditorTask::where('type', 'certificates')->count());
        $this->actingAs($writer)->get('/account/')->assertOk()->assertSee('আমার শংসাপত্র')->assertSee($post->title);
    }
}
