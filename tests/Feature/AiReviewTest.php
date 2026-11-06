<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Ai\AnthropicReviewer;
use App\Ai\OpenAiReviewer;
use App\Ai\ReviewOutcome;
use App\Ai\Reviewer;
use App\Ai\ReviewService;
use App\Enums\Role;
use App\Models\AiReview;
use App\Models\AiUsage;
use App\Models\Post;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/** Phase 3d/3e: AI advises editors; cost guards hold; no real API calls in tests. */
class AiReviewTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT = ['quality_score' => 8, 'summary' => 'সুন্দর কবিতা।', 'genre' => 'কবিতা', 'strengths' => ['ছবি'],
        'suggestions' => ['এক', 'দুই', 'তিন', 'চার'], 'spelling' => [], 'suggested_section' => 'poetry', 'tags' => ['বৃষ্টি'],
        'seo_title' => 'বৃষ্টির দিন', 'seo_description' => 'বর্ষার কবিতা', 'policy_flags' => ['hate', 'not-a-flag'], 'fits_magazine' => true, 'fit_reason' => 'ভালো'];

    private function piece(): Post
    {
        return $this->makePost(['slug' => 'p'.mt_rand(), 'title' => 'বৃষ্টির দিন', 'status' => 'pending', 'published_at' => null,
            'body_html' => '<p>'.str_repeat('আকাশ ভরা মেঘ ভিজে গেছে মাঠ ', 10).'</p>']);
    }

    private function enable(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'test-key', 'ai.openai.key' => 'test-key', 'ai.openai.review_model' => 'test-model']);
    }

    public function test_claude_request_uses_structured_output_cached_rubric_and_fallback(): void
    {
        $this->enable();
        $http = new class implements ClientInterface {
            public ?RequestInterface $request = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                    'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
                    'content' => [['type' => 'text', 'text' => json_encode(AiReviewTest::reportFor())]],
                    'stop_reason' => 'end_turn', 'stop_sequence' => null,
                    'usage' => ['input_tokens' => 400, 'output_tokens' => 300, 'cache_read_input_tokens' => 2000, 'cache_creation_input_tokens' => 0],
                ]));
            }
        };
        $client = new Client(apiKey: 'test-key', requestOptions: ['transporter' => $http]);
        $this->app->instance(AnthropicReviewer::class, new AnthropicReviewer($client));

        $review = app(ReviewService::class)->run($this->piece());

        $body = json_decode((string) $http->request->getBody(), true);
        $this->assertSame('claude-sonnet-5-5', $body['model']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame('ephemeral', $body['system'][0]['cache_control']['type']);
        $this->assertStringContainsString('<submission>', $body['messages'][0]['content']);
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $http->request->getHeaderLine('anthropic-beta'));
        $this->assertSame('default', $body['fallbacks']);

        $this->assertSame('done', $review->status);
        $this->assertSame(8, $review->score);
        $this->assertCount(3, $review->report['suggestions'], 'Never more than 3 suggestions');
        $this->assertSame(['hate'], $review->report['policy_flags'], 'Unknown flags dropped');
        $usage = AiUsage::first();
        $this->assertSame(2000, $usage->cache_read_tokens);
        $this->assertEqualsWithDelta((400 * 2 + 300 * 10 + 2000 * 0.2) / 1e6, (float) $usage->cost_usd, 1e-9);
    }

    public static function reportFor(): array
    {
        return self::REPORT;
    }

    public function test_openai_request_and_parsing(): void
    {
        $this->enable();
        Http::fake(['*/responses' => Http::response([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(self::REPORT)]]]],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 200, 'input_tokens_details' => ['cached_tokens' => 600]],
        ])]);

        $review = app(ReviewService::class)->run($this->piece(), 'openai');

        $this->assertSame('done', $review->status);
        Http::assertSent(fn ($r) => $r['text']['format']['strict'] === true && $r['model'] === 'test-model' && str_contains($r['input'], '<submission>'));
        $this->assertSame(400, AiUsage::first()->input_tokens);
    }

    public function test_cost_guards_skip_without_calling_any_provider(): void
    {
        $calls = 0;
        $this->app->instance(AnthropicReviewer::class, new class($calls) implements Reviewer {
            public function __construct(private int &$calls) {}
            public function provider(): string { return 'anthropic'; }
            public function model(): string { return 'claude-sonnet-5-5'; }
            public function review(Post $post, array $sections): ReviewOutcome { $this->calls++; return new ReviewOutcome(AiReviewTest::reportFor()); }
        });
        $svc = app(ReviewService::class);
        $post = $this->piece();

        $this->assertStringContainsString('switched off', $svc->run($post)->error);           // AI_ENABLED=false by default
        $this->enable();
        config(['ai.anthropic.key' => null]);
        $this->assertStringContainsString('No API key', $svc->run($post)->error);
        $this->enable();
        $short = $this->makePost(['slug' => 'short', 'body_html' => '<p>দুটি শব্দ</p>']);
        $this->assertStringContainsString('Too short', $svc->run($short)->error);
        AiUsage::create(['provider' => 'anthropic', 'model' => 'x', 'purpose' => 'review', 'cost_usd' => 999]);
        \App\Models\Setting::put('ai.paused', '1');                                            // no cap; the admin's pause button
        $this->assertStringContainsString('paused', $svc->run($post)->error);

        $this->assertSame(0, $calls, 'No provider call was made while blocked');
        $this->assertSame(4, AiReview::where('status', 'skipped')->count());
    }

    public function test_submitting_triggers_a_review_after_the_response_and_ai_never_changes_status(): void
    {
        $this->enable();
        $this->app->instance(AnthropicReviewer::class, new class implements Reviewer {
            public function provider(): string { return 'anthropic'; }
            public function model(): string { return 'claude-sonnet-5-5'; }
            public function review(Post $post, array $sections): ReviewOutcome { return new ReviewOutcome(array_merge(AiReviewTest::reportFor(), ['quality_score' => 10])); }
        });
        $writer = User::factory()->create(['role' => Role::Reader]);
        $section = \App\Models\Section::firstOrCreate(['slug' => 'poetry'], ['name_bn' => 'কবিতা', 'name_en' => 'Poetry', 'format' => 'poem', 'sort' => 1]);

        $this->actingAs($writer)->post('/submit/', ['title' => 'বৃষ্টি', 'section_id' => $section->id, 'original' => '1', 'action' => 'submit',
            'body' => str_repeat("আকাশ ভরা মেঘ ভিজে গেছে মাঠ\n", 8)]);

        $post = Post::where('title', 'বৃষ্টি')->first();
        $this->assertSame(10, $post->latestAiReview->score);
        $this->assertSame('pending', $post->fresh()->status, 'Even a perfect AI score never publishes anything');
    }

    public function test_blind_test_refuses_without_keys_and_scoring_page_hides_providers(): void
    {
        $this->artisan('hatpakha:ai-blind-test', ['--force' => true])->assertFailed();

        $post = $this->piece();
        foreach (['anthropic' => 'A', 'openai' => 'B'] as $p => $l) {
            AiReview::create(['post_id' => $post->id, 'provider' => $p, 'model' => $p.'-model', 'status' => 'done', 'report' => self::REPORT, 'score' => 8, 'blind_run' => 'r1', 'blind_label' => $l]);
        }
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $page = \Livewire\Livewire::test(\App\Filament\Pages\AiBlindTest::class)->assertOk()->assertSee('রিপোর্ট A')
            ->assertDontSee('anthropic-model')->assertDontSee('openai-model');

        foreach (AiReview::all() as $r) {
            $page->set("scores.{$r->id}", $r->provider === 'anthropic' ? 5 : 3)->call('score', $r->id);
        }
        \Livewire\Livewire::test(\App\Filament\Pages\AiBlindTest::class)->assertSee('ফলাফল')->assertSee('anthropic-model');
    }

    public function test_ai_settings_page_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(\App\Filament\Pages\AiSettings::canAccess());
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $this->assertTrue(\App\Filament\Pages\AiSettings::canAccess());
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\AiSettings::class)->set('data.rubric', 'নতুন মানদণ্ড')->call('save');
        $this->assertSame('নতুন মানদণ্ড', \App\Models\Setting::get('ai.rubric'));
    }

    public function test_admin_pause_button_stops_reports_and_is_logged(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Livewire\Livewire::test(\App\Filament\Pages\AiSettings::class)
            ->assertActionVisible('pause')->callAction('pause')->assertActionVisible('resume');
        $this->assertTrue(\App\Ai\AiSwitch::paused());
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.paused']);

        // Same switch from the inbox; no spending cap blocks anything (D37).
        \Livewire\Livewire::test(\App\Filament\Pages\Dashboard::class)->assertSee('এআই চালু করুন')->call('resumeAi');
        $this->assertFalse(\App\Ai\AiSwitch::paused());
        $this->enable();
        AiUsage::create(['provider' => 'anthropic', 'model' => 'x', 'purpose' => 'review', 'cost_usd' => 9999]);
        $this->assertNull(app(ReviewService::class)->blockedReason($this->piece(), 'anthropic'));

        // Editors can't flip it.
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        \Livewire\Livewire::test(\App\Filament\Pages\Dashboard::class)->call('pauseAi')->assertForbidden();
        $this->assertFalse(\App\Ai\AiSwitch::paused());
    }

    public function test_key_check_uses_free_model_lookup_and_reports_problems(): void
    {
        $this->enable();
        config(['ai.openai.review_model' => 'wrong-model']);
        Http::fake([
            'api.anthropic.com/v1/models/*' => Http::response(['id' => 'claude-sonnet-5-5']),
            '*/models/wrong-model' => Http::response(['error' => 'not found'], 404),
        ]);
        $r = \App\Ai\KeyCheck::run();
        $this->assertTrue($r['anthropic']['ok']);
        $this->assertFalse($r['openai']['ok']);
        $this->assertStringContainsString('মডেলের নাম ভুল', $r['openai']['message']);
        Http::assertSent(fn ($req) => $req->method() === 'GET');   // lookups only: no tokens, no cost
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'messages') || str_contains($req->url(), 'responses'));
    }
}
