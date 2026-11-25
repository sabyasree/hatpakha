<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\TopicSorter;
use App\Models\Post;
use App\Support\Topics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** D293: topic pages (/bishoy/…), filled by the archivist or the desk, shown only once they hold enough pieces. */
class TopicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    private function ghostStories(int $n): array
    {
        return collect(range(1, $n))->map(fn ($i) => $this->makePost(['slug' => 'bhut-'.$i, 'status' => 'published', 'title' => 'ভূতের গল্প '.$i,
            'published_at' => '2024-01-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' 10:00:00', 'topics' => ['bhuter-golpo'], 'topics_by' => 'ai']))->all();
    }

    public function test_a_topic_page_with_enough_pieces_is_listed_indexed_and_linked(): void
    {
        $posts = $this->ghostStories(Topics::MIN);
        Cache::flush();

        $html = $this->get('/bishoy/bhuter-golpo/')->assertOk()
            ->assertSee('<title>ভূতের গল্প: বাংলা ভৌতিক গল্প (Bhuter Golpo) | হাতপাখা</title>', false)
            ->assertSee('অন্ধকার রাস্তা, পোড়ো বাড়ি')
            ->assertSee('ভূতের গল্প 8')->getContent();
        $this->assertStringNotContainsString('noindex', $html);

        $this->get('/bishoy/')->assertOk()->assertSee('ভূতের গল্প')->assertSee(url('/bishoy/bhuter-golpo').'/', false)->assertDontSee(url('/bishoy/premer-kobita').'/', false);
        $this->get('/sitemap-sections.xml')->assertSee(url('/bishoy/bhuter-golpo').'/', false)->assertDontSee('premer-kobita');
        $this->get($posts[0]->url())->assertSee('এই বিষয়ের আরও লেখা')->assertSee(url('/bishoy/bhuter-golpo').'/', false);
        $this->get('/category/poetry/')->assertDontSee('aria-label="বিষয় ধরে পড়ুন"', false);   // poetry offers poem topics only, none live yet
    }

    public function test_a_thin_topic_stays_out_of_google_and_an_unknown_one_is_not_found(): void
    {
        $this->ghostStories(Topics::MIN - 1);
        Cache::flush();

        $this->get('/bishoy/bhuter-golpo/')->assertOk()->assertSee('noindex', false);
        $this->get('/bishoy/')->assertDontSee(url('/bishoy/bhuter-golpo').'/', false);
        $this->get('/bishoy/no-such-topic/')->assertNotFound();
    }

    public function test_the_archivist_sorts_in_batches_never_overrides_the_desk_and_waits_for_the_owner(): void
    {
        $a = $this->makePost(['slug' => 'a', 'status' => 'published', 'title' => 'পোড়ো বাড়ি']);
        $b = $this->makePost(['slug' => 'b', 'status' => 'published', 'title' => 'হিসাবের খাতা']);
        $desk = $this->makePost(['slug' => 'c', 'status' => 'published', 'title' => 'মা']);
        $desk->update(['topics' => ['maa-ke-niye']]);
        $this->assertSame('desk', $desk->fresh()->topics_by);

        $asked = [];
        Claude::$fake = function ($purpose, $user) use (&$asked, $a, $b) {
            $asked[] = $purpose;

            return ['pieces' => [['id' => $a->id, 'topics' => ['bhuter-golpo', 'not-a-topic']], ['id' => $b->id, 'topics' => []]]];
        };

        $this->assertFalse(TopicSorter::started());
        $this->artisan('hatpakha:ai-shift', ['task' => 'backlog'])->assertExitCode(0);
        $this->assertNull($a->fresh()->topics_by, 'Nothing is sorted before the owner starts it');

        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        TopicSorter::start();
        $this->assertSame(2, TopicSorter::backlog());
        $this->assertSame(['topics'], $asked);
        $this->assertSame(['bhuter-golpo'], $a->fresh()->topics);
        $this->assertSame([], $b->fresh()->topics);
        $this->assertSame('ai', $b->fresh()->topics_by);
        $this->assertSame(['maa-ke-niye'], $desk->fresh()->topics, 'The desk choice is kept');
        $this->assertSame(0, TopicSorter::waiting()->count());
    }

    public function test_saving_an_unsorted_piece_in_the_desk_without_topics_leaves_it_for_the_archivist(): void
    {
        $p = $this->makePost(['slug' => 'd', 'status' => 'published']);
        $p->update(['topics' => [], 'title' => 'নতুন নাম']);
        $this->assertNull($p->fresh()->topics_by);
        $this->assertNull($p->fresh()->topics);
    }

    public function test_the_estimate_is_small_and_counts_only_waiting_pieces(): void
    {
        $this->makePost(['slug' => 'e', 'status' => 'published']);
        $this->makePost(['slug' => 'f', 'status' => 'published', 'topics' => [], 'topics_by' => 'ai']);
        $e = TopicSorter::estimate();
        $this->assertSame(1, $e['pieces']);
        $this->assertLessThan(0.01, $e['usd']);
    }

    public function test_every_topic_has_a_short_title_and_an_introduction(): void
    {
        foreach (Topics::LIST as $slug => [$name, $group, $title, $intro]) {
            $this->assertMatchesRegularExpression('/^[a-z-]+$/', $slug);
            $this->assertArrayHasKey($group, Topics::GROUPS);
            $this->assertLessThanOrEqual(60, mb_strlen($title), $slug);
            $this->assertLessThanOrEqual(155, mb_strlen($intro), $slug);
        }
    }

    public function test_durga_names_in_bengali_are_added_once_above_the_english_list(): void
    {
        $post = $this->makePost(['slug' => 'maa-durga-108-names', 'status' => 'published', 'body_html' => '<h3>Durga 108 Names</h3><p>1) Sati, the daughter of Daksa;</p>']);
        $m = require database_path('migrations/2026_11_25_000500_durga_names_bengali.php');
        $this->assertCount(108, $m::NAMES);
        $m->up();
        $m->up();
        $body = $post->fresh()->body_html;
        $this->assertSame(1, substr_count($body, $m::HEADING));
        $this->assertStringContainsString('১০৮) ব্রহ্মবাদিনী', $body);
        $this->assertStringContainsString('1) Sati, the daughter of Daksa;', $body);
        $this->assertLessThan(strpos($body, 'Durga 108 Names'), strpos($body, $m::HEADING));
        $this->assertStringContainsString('কবিতা কী, কবিতা কাকে বলে', (string) file_get_contents(database_path('migrations/2026_11_25_000400_kobita_ki_title.php')));
    }
}
