<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D273 (owner: "make sure the AI is not doing unnecessary work"): the spend audit's fixes. */
class AiSpendTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_copy_that_is_not_the_live_site_spends_nothing(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'app.url' => 'https://new.hatpakha.com']);
        $this->app['env'] = 'production';
        $this->assertSame('Not the live site: AI spends nothing here', Claude::blocked());
        config(['app.url' => 'https://hatpakha.com']);
        $this->assertNull(Claude::blocked());
        $this->app['env'] = 'testing';
        $this->assertGreaterThan(0, config('ai.openai.image_price_usd'), 'Pictures are never counted as free');
    }

    public function test_a_failing_proofread_stops_after_three_tries_and_a_short_piece_leaves_the_queue(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        Claude::$fake = fn () => null;   // every answer fails
        $p = $this->makePost(['slug' => 'long', 'status' => 'pending', 'published_at' => null, 'body_html' => '<p>'.str_repeat('একটি বাক্য। ', 20).'</p>']);
        foreach (range(1, 3) as $i) {
            \App\Ai\Proofreader::run($p->fresh());
        }
        $this->assertNotNull($p->fresh()->proof_at, 'Not retried every ten minutes for ever');
        $short = $this->makePost(['slug' => 'short', 'status' => 'pending', 'published_at' => null, 'body_html' => '<p>ছোট</p>']);
        \App\Ai\Proofreader::run($short);
        $this->assertNotNull($short->fresh()->proof_at);
        Claude::$fake = null;
    }

    public function test_a_discarded_or_old_unanswered_piece_gets_no_automatic_picture(): void
    {
        $old = $this->makePost(['slug' => 'old', 'status' => 'pending', 'published_at' => null]);   // an old WordPress submission, no answer
        $live = $this->makePost(['slug' => 'live']);
        $ids = \App\Ai\Illustrator::candidates()->pluck('id')->all();
        $this->assertContains($live->id, $ids);
        $this->assertNotContains($old->id, $ids);
        $live->forceFill(['proposed_image_path' => 'media/covers/x.webp'])->save();
        \App\Support\CoverImage::discardProposed($live->fresh());
        $this->assertSame('discarded', $live->fresh()->proposed_image_error, 'Not drawn again by itself within the hour');
    }
}
