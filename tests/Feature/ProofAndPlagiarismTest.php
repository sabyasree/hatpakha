<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\EditorPackage;
use App\Ai\PlagiarismCheck;
use App\Ai\Proofreader;
use App\Enums\Role;
use App\Models\EditorTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D50: the proofreader and the plagiarism checker report; the owner decides. */
class ProofAndPlagiarismTest extends TestCase
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

    public function test_proofreader_feeds_the_approve_dialog_and_never_touches_published(): void
    {
        Claude::$fake = fn () => ['edits' => [['original' => 'আকাষ', 'replacement' => 'আকাশ', 'kind' => 'spelling']], 'title_fix' => 'মেঘের দিন', 'note' => 'ভাষা পরিষ্কার।'];
        $post = $this->makePost(['status' => 'pending', 'title' => 'মেঘের দিণ', 'body_html' => '<p>নীল আকাষ জুড়ে মেঘ ভেসে যায়, দূরে পাখির ডাক শোনা যায়।</p>']);
        $this->assertNull(Proofreader::run($post));
        $post->refresh();
        $this->assertSame('মেঘের দিন', $post->proof['title_fix']);
        $this->assertSame('আকাশ', EditorPackage::edits($post)[0]['replacement']);

        $owner = User::factory()->create(['role' => Role::Admin]);
        EditorPackage::approve($post, $owner, now(), null, null, [0], false, true);
        $post->refresh();
        $this->assertStringContainsString('আকাশ', $post->body_html);
        $this->assertSame('মেঘের দিন', $post->title);

        $live = $this->makePost(['status' => 'published', 'slug' => 'live']);
        $this->assertSame('published pieces are not proofread', Proofreader::run($live));
        $this->assertNull($live->fresh()->proof);
    }

    public function test_proofreader_never_offers_a_new_title(): void
    {
        Claude::$fake = fn () => ['edits' => [], 'title_fix' => 'সম্পূর্ণ অন্য একটি শিরোনাম', 'note' => ''];
        $post = $this->makePost(['status' => 'pending', 'title' => 'বর্ষা', 'body_html' => '<p>বৃষ্টি পড়ে টাপুর টুপুর, নদে এল বান।</p>']);
        Proofreader::run($post);
        $this->assertSame('', $post->fresh()->proof['title_fix']);
    }

    public function test_plagiarism_found_opens_a_task_and_keeps_only_real_links(): void
    {
        Claude::$fake = fn () => ['verdict' => 'found', 'matches' => [['url' => 'https://example.org/poem', 'line' => 'x'], ['url' => 'javascript:alert(1)', 'line' => 'y']], 'note' => 'একই কবিতা।'];
        $post = $this->makePost(['status' => 'pending', 'body_html' => '<p>এটি একটি বেশ লম্বা লাইন যা খুঁজে দেখা হবে ওয়েবে</p><p>আরও একটি বেশ লম্বা লাইন যা খুঁজে দেখা হবে</p>']);
        $this->assertNull(PlagiarismCheck::run($post));
        $post->refresh();
        $this->assertSame('found', $post->plagiarism['verdict']);
        $this->assertCount(1, $post->plagiarism['matches']);
        $this->assertStringContainsString('example.org', PlagiarismCheck::summary($post));
        $this->assertSame(1, EditorTask::where('type', 'plagiarism')->count());
    }
}
