<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\SeoExpert;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D46: the AI SEO expert finishes search & share details after approval; never touches the text. */
class SeoExpertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    private function fake(array $relatedIds = []): void
    {
        Claude::$fake = fn () => ['seo_title' => 'বৃষ্টির দিন — কবিতা', 'seo_description' => 'বর্ষার দুপুরে মনে পড়ে যাওয়া এক মানুষের কবিতা।',
            'keyphrase' => 'বর্ষার কবিতা', 'tags' => ['বৃষ্টি', 'বর্ষা'], 'image_alt' => 'জানলার কাচে বৃষ্টির ফোঁটা',
            'share_text' => 'বৃষ্টি দিনে পড়ার মতো একটি কবিতা।', 'related_ids' => $relatedIds];
    }

    public function test_after_approval_details_are_added_text_untouched_and_page_uses_them(): void
    {
        Tag::create(['slug' => 'বৃষ্টি', 'name' => 'বৃষ্টি']);
        $other = $this->makePost(['slug' => 'onno', 'title' => 'অন্য লেখা']);
        $post = $this->makePost(['slug' => 'brishti', 'title' => 'বৃষ্টির দিন', 'body_html' => '<p>আকাশ ভরা মেঘ</p>']);
        $body = $post->body_html;
        $this->fake([$other->id, 999]);

        $this->assertNull(SeoExpert::run($post));
        $post->refresh();
        $this->assertSame('বৃষ্টির দিন — কবিতা', $post->seo_title);
        $this->assertSame('বর্ষার কবিতা', $post->seo_keyphrase);
        $this->assertSame([$other->id], $post->related_ids, 'Only real candidates');
        $this->assertSame($body, $post->body_html);
        $this->assertSame('বৃষ্টির দিন', $post->title);
        $this->assertSame(1, Tag::where('name', 'বৃষ্টি')->count(), 'Existing tag reused, not duplicated');

        $this->get('/brishti/')->assertSee('বৃষ্টির দিন — কবিতা', false)->assertSee('অন্য লেখা')
            ->assertSee(rawurlencode('বৃষ্টি দিনে পড়ার মতো একটি কবিতা।'), false);
    }

    public function test_old_pieces_only_get_missing_details_and_undo_restores(): void
    {
        $post = $this->makePost(['slug' => 'puron', 'title' => 'পুরনো লেখা']);
        $post->forceFill(['seo_title' => 'সম্পাদকের নিজের শিরোনাম'])->save();
        $this->fake();
        SeoExpert::run($post->fresh(), onlyMissing: true);
        $post->refresh();
        $this->assertSame('সম্পাদকের নিজের শিরোনাম', $post->seo_title, 'Never overwrites what a person wrote on old pieces');
        $this->assertNotNull($post->seo_description);

        $this->assertTrue(SeoExpert::undo($post));
        $this->assertNull($post->fresh()->seo_description);
        $this->assertSame(0, $post->fresh()->tags()->count());
    }

    public function test_ask_first_mode_keeps_a_proposal_and_unapproved_pieces_are_skipped(): void
    {
        Setting::put('ai.seo_auto', '0');
        $post = $this->makePost(['slug' => 'pr']);
        $this->fake();
        SeoExpert::run($post);
        $this->assertNull($post->fresh()->seo_title);
        $this->assertSame('বর্ষার কবিতা', $post->fresh()->seo_proposal['seo_keyphrase']);

        $draft = $this->makePost(['slug' => 'dr', 'status' => 'pending', 'published_at' => null]);
        $this->assertSame('not approved', SeoExpert::run($draft));
    }
}
