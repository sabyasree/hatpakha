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

    /** Owner (2026-11-11): "why ready to copy? It should be a draft post, with all details right." */
    public function test_a_piece_asked_for_in_words_becomes_a_complete_draft_post(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'sk-test']);
        \App\Models\Author::firstOrCreate(['slug' => 'hatpakha-desk'], ['display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]);
        $stories = \App\Models\Section::firstOrCreate(['slug' => 'short-stories'], ['name_bn' => 'গল্প', 'format' => 'prose', 'is_active' => true]);
        \App\Models\Section::firstOrCreate(['slug' => 'poetry'], ['name_bn' => 'কবিতা', 'format' => 'poem', 'is_active' => true]);
        $tag = \App\Models\Tag::create(['name' => 'ভাইফোঁটা', 'slug' => 'bhaiphota']);
        Claude::$fake = fn (string $purpose, string $user, array $schema) => match ($purpose) {
            'chief' => ['steps' => [
                ['agent' => 'copywriter', 'job' => 'piece', 'post' => '', 'topic' => 'ভাইফোঁটা নিয়ে একটি গল্প'],
                ['agent' => 'designer', 'job' => 'post', 'post' => '@piece', 'topic' => ''],
                ['agent' => 'marketing', 'job' => 'post', 'post' => '@piece', 'topic' => ''],
            ]],
            'marketing' => ['facebook' => 'ভাইফোঁটার গল্প', 'whatsapp' => 'পড়ুন', 'instagram' => 'ক্যাপশন #ভাইফোঁটা', 'quote' => 'চন্দনের বাটি হাতে বসে আছে মিনু।'],
            default => ['form' => 'story', 'section' => 'short-stories', 'title' => 'ফোঁটার দিন', 'excerpt' => 'এক বোনের অপেক্ষা।',
                'paragraphs' => ['সকাল থেকে উঠোনে আলো।', 'চন্দনের বাটি হাতে বসে আছে মিনু।'], 'tags' => ['ভাইফোঁটা', 'বানানো-ট্যাগ'],
                'seo_title' => 'ফোঁটার দিন — ভাইফোঁটার গল্প', 'seo_description' => 'ভাইফোঁটার সকালে এক বোনের অপেক্ষার গল্প।', 'keyphrase' => 'ভাইফোঁটার গল্প',
                'facts_to_check' => [], 'photo_ideas' => ['চন্দনের বাটি']],
        };
        $r = TeamRequest::create(['instruction' => 'We need to write a story on bhai-phota. And publish today. Also, need post to post in social media.', 'status' => 'queued']);
        TeamJobs::runQueued();
        $r->refresh();

        $this->assertSame('done', $r->status, (string) $r->result);
        // The editor-in-chief split the ask: writer → designer → marketing, each a step of its own.
        $this->assertSame(['copywriter', 'designer', 'marketing'], array_column($r->steps['plan'], 'agent'));
        // No image key in tests: the designer's step fails and says why; the others still happen.
        $this->assertSame(['done', 'failed', 'done'], array_column($r->steps['plan'], 'state'), (string) $r->result);
        $this->assertStringStartsWith('post:', (string) $r->link, 'The result opens the draft');
        $post = \App\Models\Post::findOrFail((int) substr($r->link, 5));
        $this->assertSame('draft', $post->status, 'Never published by the team (D42)');
        $this->assertSame($stories->id, $post->section_id);
        $this->assertSame('ফোঁটার দিন', $post->title);
        $this->assertSame('এক বোনের অপেক্ষা।', $post->excerpt);
        $this->assertStringContainsString('চন্দনের বাটি', $post->body_html);
        $this->assertSame('ভাইফোঁটার গল্প', $post->seo_keyphrase);
        $this->assertNotNull($post->seo_description);
        $this->assertSame([$tag->id], $post->tags()->pluck('tags.id')->all(), 'Only tags the site already has');
        $this->assertSame('hatpakha-desk', $post->authors()->first()->slug);
        $this->assertNotNull($post->ai_drafted_at);
        $this->assertSame(['done', 'done', 'done', 'failed', 'done', 'done'], array_column(\App\Filament\Pages\AiTeam::stepsFor($r), 'state'), 'Received, plan, three members, ready');

        // The social posts are on the marketing page, for the draft, before it is published.
        $social = \App\Models\SocialPost::where('subject_type', $post->getMorphClass())->where('subject_id', $post->id)->first();
        $this->assertNotNull($social);
        $this->assertStringContainsString('এখনও প্রকাশ হয়নি', $social->title);
        $this->assertStringContainsString($post->url(), $social->texts['facebook']);
        $links = array_column(\App\Filament\Pages\AiTeam::resultLinks($r), 0);
        $this->assertContains(__('লেখাটি খুলুন'), $links);
        $this->assertContains(__('মার্কেটিং'), $links);
    }

    /** Owner (2026-11-11): "I cleared completed tasks and it cleared the failed ones too; I need to choose." */
    public function test_the_owner_chooses_what_to_clear(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['role' => \App\Enums\Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $seen = TeamRequest::create(['status' => 'done', 'seen_at' => now()]);
        $new = TeamRequest::create(['status' => 'done']);
        $failed = TeamRequest::create(['status' => 'failed']);
        $waiting = TeamRequest::create(['status' => 'queued']);
        $running = TeamRequest::create(['status' => 'running']);
        $page = \Livewire\Livewire::test(\App\Filament\Pages\AiTeam::class);

        $page->call('clearRequests', 'done');
        $this->assertModelMissing($seen);
        $this->assertModelExists($new);
        $this->assertModelExists($failed, 'Clearing finished work keeps the failed jobs');

        $page->call('clearRequests', 'failed');
        $this->assertModelMissing($failed);
        $this->assertModelExists($new);

        $page->call('clearRequests', 'all');
        $this->assertModelMissing($new);
        $this->assertModelExists($waiting);
        $this->assertModelExists($running);
    }

    /** Owner (2026-11-11): the chief answered a story request with text to copy. Never again: it becomes a draft piece. */
    public function test_a_writing_request_answered_as_text_is_turned_into_a_draft_piece(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'sk-test']);
        \App\Models\Author::firstOrCreate(['slug' => 'hatpakha-desk'], ['display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]);
        \App\Models\Section::firstOrCreate(['slug' => 'short-stories'], ['name_bn' => 'গল্প', 'format' => 'prose', 'is_active' => true]);
        Claude::$fake = fn (string $purpose) => match ($purpose) {
            'chief' => ['steps' => [['agent' => 'copywriter', 'job' => 'answer', 'post' => '', 'topic' => 'ভাইফোঁটার গল্প']]],
            default => ['form' => 'story', 'section' => 'short-stories', 'title' => 'পর্দার ওপারে কপাল', 'excerpt' => 'ভাই আসতে পারেনি।',
                'paragraphs' => ['সকাল থেকে মিনুর পেটে এক ফোঁটা জল পড়েনি।'], 'tags' => [], 'seo_title' => 'পর্দার ওপারে কপাল', 'seo_description' => 'ভাইফোঁটার গল্প',
                'keyphrase' => 'ভাইফোঁটার গল্প', 'facts_to_check' => [], 'photo_ideas' => []],
        };
        $r = TeamRequest::create(['instruction' => 'Bhai-phota is going on. So write a story for that topic and publish.', 'status' => 'queued']);
        TeamJobs::runQueued();
        $r->refresh();
        $this->assertSame(['copywriter'], array_column($r->steps['plan'], 'agent'), 'Only the answer becomes a piece; code adds nothing else');
        $this->assertSame('piece', $r->steps['plan'][0]['job']);
        $this->assertStringStartsWith('post:', (string) $r->link);
        $this->assertSame('draft', \App\Models\Post::find((int) substr($r->link, 5))->status);
    }

    /** Owner (2026-11-11): "when only a poster is asked, only a poster": the planner decides per request. */
    public function test_only_a_poster_makes_a_poster_on_the_marketing_page_and_no_piece(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'sk-test', 'ai.openai.key' => 'sk-img', 'ai.openai.image_model' => 'gpt-image-1', 'ai.openai.base_url' => 'https://api.openai.com/v1']);
        $img = imagecreatetruecolor(1024, 1024);
        imagefill($img, 0, 0, imagecolorallocate($img, 203, 27, 50));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        \Illuminate\Support\Facades\Http::fake(['api.openai.com/*' => \Illuminate\Support\Facades\Http::response(['data' => [['b64_json' => base64_encode($png)]]])]);
        Claude::$fake = fn (string $purpose) => match ($purpose) {
            'chief' => ['why' => 'ভাইফোঁটার শুভেচ্ছার একটি পোস্টার পাবেন।', 'steps' => [['agent' => 'designer', 'job' => 'poster', 'post' => '', 'topic' => 'ভাইফোঁটার শুভেচ্ছা']]],
            default => ['scene' => 'A brass plate with sandal paste and rice, morning light.', 'headline' => 'ভাইফোঁটার শুভেচ্ছা', 'line' => 'কপালে ফোঁটা, মনে অনেক গল্প',
                'facebook' => 'ভাইফোঁটার শুভেচ্ছা সবাইকে।', 'whatsapp' => 'ভাইফোঁটার শুভেচ্ছা।', 'instagram' => 'শুভেচ্ছা #ভাইফোঁটা'],
        };
        $r = TeamRequest::create(['instruction' => 'ভাইফোঁটার শুভেচ্ছার একটা পোস্টার বানাও, শুধু পোস্টার', 'status' => 'queued']);
        TeamJobs::runQueued();
        $r->refresh();

        $this->assertSame('done', $r->status, (string) $r->result);
        $this->assertSame(0, \App\Models\Post::count(), 'No piece was asked for, none was made');
        $poster = \App\Models\SocialPost::where('kind', 'poster')->firstOrFail();
        $this->assertSame('ভাইফোঁটার শুভেচ্ছা', $poster->title);
        $this->assertFileExists(public_path(ltrim($poster->texts['image'], '/')));
        $card = \App\Filament\Pages\Marketing::card($poster);
        $this->assertSame('কপালে ফোঁটা, মনে অনেক গল্প', $card['quote']);
        $this->assertStringContainsString('ভাইফোঁটার শুভেচ্ছার একটি পোস্টার পাবেন', \App\Filament\Pages\AiTeam::stepsFor($r)[1]['sub'], 'The planner says what you will get');
        $this->assertContains(__('মার্কেটিং'), array_column(\App\Filament\Pages\AiTeam::resultLinks($r), 0));
        @unlink(public_path(ltrim($poster->texts['image'], '/')));
    }
}
