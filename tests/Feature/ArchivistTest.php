<?php

namespace Tests\Feature;

use App\Ai\Archivist;
use App\Ai\Claude;
use App\Models\Collection;
use App\Models\EditorTask;
use App\Models\Redirect;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D55: archivist proposals reach readers only after approval; tag merges keep old URLs working. */
class ArchivistTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_collection_is_proposed_then_shown_on_archive_only_when_approved(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $posts = collect(range(1, 8))->map(fn ($i) => $this->makePost(['status' => 'published', 'slug' => 'old-'.$i, 'title' => 'পুরনো '.$i, 'published_at' => now()->subYears(3)]));
        Claude::$fake = fn () => ['title' => 'বর্ষার লেখা', 'intro' => 'বৃষ্টির দিনের লেখা।', 'ids' => [...$posts->take(5)->pluck('id')->all(), 999999]];
        $c = Archivist::collection('বর্ষা');
        $this->assertSame('proposed', $c->status);
        $this->assertCount(5, $c->post_ids, 'Unknown ids dropped');
        $this->assertSame(1, EditorTask::where('type', 'collection')->count());
        $this->get('/archive/')->assertOk()->assertDontSee('বর্ষার লেখা');
        $c->forceFill(['status' => 'approved', 'approved_at' => now()])->save();
        $this->get('/archive/')->assertOk()->assertSee('বর্ষার লেখা')->assertSee('পুরনো 1');
    }

    public function test_tag_tidy_up_and_merge_redirects_the_old_tag(): void
    {
        $post = $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()]);
        $keep = Tag::create(['slug' => 'kobita', 'name' => 'কবিতা']);
        $drop = Tag::create(['slug' => 'kobita-2', 'name' => 'কবিতা ']);
        $post->tags()->attach([$keep->id]);
        $this->makePost(['status' => 'published', 'slug' => 'q', 'published_at' => now()])->tags()->attach([$drop->id]);
        $task = Archivist::tags();
        $this->assertSame('কবিতা ', $task->payload['pairs'][0]['drop_name']);
        Archivist::mergeTag($keep->id, $drop->id);
        $this->assertNull(Tag::find($drop->id));
        $this->assertSame(2, $keep->posts()->count());
        $this->assertSame('/tag/kobita/', Redirect::where('from_path', '/tag/kobita-2/')->value('to_path'));
        $this->get('/tag/kobita-2/')->assertRedirect();
    }
}
