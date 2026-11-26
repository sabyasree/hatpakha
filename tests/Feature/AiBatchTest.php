<?php

namespace Tests\Feature;

use App\Ai\Batch;
use App\Ai\Claude;
use App\Ai\TopicSorter;
use App\Models\AiUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D296: work nobody waits for goes to the half-price batch, and its answers do exactly what a direct answer does. */
class AiBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
    }

    protected function tearDown(): void
    {
        Batch::$fake = null;
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_overnight_work_is_queued_sent_answered_and_logged_at_half_price_once(): void
    {
        $old = $this->makePost(['slug' => 'old', 'status' => 'pending', 'submitted_by' => null, 'relaunch_status' => 'yes',
            'body_html' => '<p>পুরনো লেখাটিতে একটা বানান ভুল আছে, তাই প্রুফ দরকার।</p>']);
        $live = $this->makePost(['slug' => 'live', 'status' => 'published', 'title' => 'নদীর গল্প', 'body_html' => '<p>নদীর ধারে একটা গল্প।</p>']);
        TopicSorter::start();
        Claude::$fake = fn () => throw new \RuntimeException('nothing may be asked directly');

        $this->artisan('hatpakha:ai-shift', ['task' => 'backlog'])->assertExitCode(0);
        $this->assertSame(['proof', 'seo_missing', 'topics'], DB::table('ai_batch_items')->orderBy('job')->pluck('job')->unique()->values()->all());
        $this->assertSame('queued', $live->fresh()->topics_by);

        // Nothing is queued twice while it waits.
        $this->artisan('hatpakha:ai-shift', ['task' => 'backlog'])->assertExitCode(0);
        $this->assertSame(3, DB::table('ai_batch_items')->count());

        $sent = [];
        Batch::$fake = function (array $requests) use (&$sent, $old, $live) {
            $out = [];
            foreach ($requests as $r) {
                $sent[] = $r['params']['model'];
                $user = $r['params']['messages'][0]['content'];
                $data = match (true) {
                    str_contains($user, '<pieces>') => ['pieces' => [['id' => $live->id, 'topics' => ['premer-golpo']]]],
                    str_contains($user, 'Related candidates') => ['seo_title' => 'নদীর গল্প: একটি বাংলা গল্প', 'seo_description' => 'নদীর ধারে', 'keyphrase' => 'নদীর গল্প',
                        'tags' => [], 'image_alt' => '', 'share_text' => 'পড়ুন', 'related_ids' => []],
                    default => ['edits' => [['original' => 'বানান ভুল', 'replacement' => 'বানান-ভুল', 'kind' => 'punctuation']], 'title_fix' => '', 'note' => 'ভালো'],
                };
                $out[$r['customID']] = ['data' => $data, 'usage' => ['in' => 1_000_000, 'out' => 0, 'cache_read' => 0, 'cache_write' => 0]];
            }

            return $out;
        };
        $this->assertSame(['done' => 0, 'sent' => 3], Batch::run());
        $this->assertSame(['done' => 3, 'sent' => 0], Batch::run());

        $this->assertSame(['premer-golpo'], $live->fresh()->topics);
        $this->assertSame('ai', $live->fresh()->topics_by);
        $this->assertSame('নদীর গল্প: একটি বাংলা গল্প', $live->fresh()->seo_title);
        $this->assertNotEmpty($old->fresh()->proof['edits']);
        // Half price: a million input tokens at the topic model's price, halved.
        $topics = AiUsage::where('purpose', 'topics')->sole();
        $this->assertEqualsWithDelta(config('ai.prices.'.config('ai.anthropic.tags_model').'.in') / 2, (float) $topics->cost_usd, 0.0001);
        $this->assertSame(0, DB::table('ai_batch_items')->whereIn('status', ['queued', 'sent'])->count());
    }

    public function test_an_unanswered_topic_request_goes_back_to_waiting(): void
    {
        $live = $this->makePost(['slug' => 'live2', 'status' => 'published']);
        TopicSorter::start();
        Batch::queueTopics();
        Batch::$fake = fn (array $requests) => collect($requests)->mapWithKeys(fn ($r) => [$r['customID'] => ['data' => null, 'error' => 'expired']])->all();
        Batch::run();
        Batch::run();
        $this->assertNull($live->fresh()->topics_by, 'Sorted again in a later batch');
        $this->assertSame('failed', DB::table('ai_batch_items')->value('status'));
    }
}
