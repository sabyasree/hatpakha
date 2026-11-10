<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\Knowledge;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D62: one shared brief for the team, plus the trend scout's weekly news with sources. */
class KnowledgeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_brief_goes_to_writing_jobs_but_not_the_chat_helper(): void
    {
        $this->assertStringContainsString('জীবনানন্দ দাশ', Knowledge::forPurpose('copy'));
        $this->assertStringContainsString('শারদীয়া', Knowledge::forPurpose('review'));
        $this->assertSame('', Knowledge::forPurpose('chat'));
        $this->assertSame('', Knowledge::forPurpose('comment_screen'));
    }

    public function test_trend_scout_keeps_only_sourced_items_and_writing_jobs_see_them(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        Claude::$fake = fn () => ['items' => [
            ['title' => 'কলকাতা বইমেলা ২০২৭', 'why' => '৫০তম বইমেলা', 'when' => '২২ জানু – ৩ ফেব্রু', 'url' => 'https://example.org/boimela'],
            ['title' => 'উৎস ছাড়া খবর', 'why' => 'x', 'when' => '', 'url' => 'not-a-url'],
        ]];
        $this->assertNull(Knowledge::refreshTrends());
        $t = Knowledge::trends();
        $this->assertCount(1, $t['items']);
        $this->assertStringContainsString('কলকাতা বইমেলা ২০২৭', Knowledge::forPurpose('marketing'));
        $this->assertStringNotContainsString('কলকাতা বইমেলা ২০২৭', Knowledge::forPurpose('proof'), 'Proofreading needs no news');
        $this->assertNotNull(Setting::get('knowledge.trends'));
    }
}
