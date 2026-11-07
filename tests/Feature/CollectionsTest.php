<?php

namespace Tests\Feature;

use App\Ai\Archivist;
use App\Ai\Claude;
use App\Enums\Role;
use App\Filament\Resources\Collections\CollectionResource;
use App\Filament\Resources\Collections\Pages\EditCollection;
use App\Models\Collection;
use App\Models\EditorTask;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D97: themed collections with their own pages; the archivist proposes, the owner orders and approves. */
class CollectionsTest extends TestCase
{
    use RefreshDatabase;

    private array $posts = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        foreach (['শীতের সকাল', 'কুয়াশার গল্প', 'পৌষের রাত', 'শীত এল', 'হেমন্তের শেষে', 'নলেন গুড়', 'বর্ষার দিন'] as $i => $t) {
            $this->posts[] = $this->makePost(['status' => 'published', 'title' => $t, 'slug' => 'p'.$i, 'published_at' => now()->subDays(10 + $i),
                'body_html' => '<p>'.$t.'।'.($i < 6 ? ' শীত আর কুয়াশা।' : ' টিনের চালে জল।').'</p>']);
        }
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_archivist_proposes_from_the_whole_archive_and_nothing_is_public_until_approved(): void
    {
        $ids = array_map(fn ($p) => $p->id, array_slice($this->posts, 0, 5));
        $seen = null;
        Claude::$fake = function ($purpose, $user) use ($ids, &$seen) {
            $seen = $user;

            return ['intro' => 'শীতের লেখা নিয়ে <b>ভূমিকা</b>।', 'ids' => array_merge($ids, [999999])];
        };
        [$c] = Archivist::themed('shiter-lekha');

        $this->assertStringContainsString('শীতের সকাল', $seen);
        $this->assertStringNotContainsString('বর্ষার দিন', $seen, 'Only pieces that match the theme are offered');
        $this->assertSame(['shiter-lekha', 'proposed', [11, 12, 1]], [$c->slug, $c->status, $c->months]);
        $this->assertSame($ids, $c->post_ids, 'Unknown ids are dropped, order kept');
        $this->assertSame('শীতের লেখা নিয়ে ভূমিকা।', $c->intro);
        $this->assertTrue(EditorTask::open('collection')->exists());

        $this->get('/sonkolon/shiter-lekha/')->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->get('/sonkolon/shiter-lekha/')->assertOk()->assertSee('প্রস্তাব: এখনও প্রকাশিত নয়')->assertSee('noindex', false);
    }

    public function test_owner_orders_and_approves_then_readers_find_it_everywhere(): void
    {
        $c = Collection::create(['kind' => 'theme', 'title' => 'শীতের লেখা', 'slug' => 'shiter-lekha', 'intro' => 'শীতের ভূমিকা।', 'months' => [now('Asia/Kolkata')->month],
            'post_ids' => [$this->posts[0]->id, $this->posts[1]->id, $this->posts[2]->id], 'status' => 'proposed']);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(EditCollection::class, ['record' => $c->getRouteKey()])
            ->fillForm(['post_ids' => [['id' => $this->posts[2]->id], ['id' => $this->posts[0]->id], ['id' => $this->posts[1]->id]]])->call('save')->assertHasNoFormErrors()
            ->callAction('approve');
        $c->refresh();
        $this->assertSame('approved', $c->status);
        $this->assertSame([$this->posts[2]->id, $this->posts[0]->id, $this->posts[1]->id], $c->post_ids, 'The owner\'s order');
        auth()->logout();

        $page = $this->get('/sonkolon/shiter-lekha/')->assertOk()->assertSee('শীতের ভূমিকা।')->assertSee('CollectionPage', false);
        $this->assertLessThan(strpos($page->getContent(), 'শীতের সকাল'), strpos($page->getContent(), 'পৌষের রাত'), 'Shown in the chosen order');
        $this->get('/sonkolon/')->assertOk()->assertSee('শীতের লেখা');
        $this->get('/')->assertOk()->assertSee('পুরো সংকলন', false)->assertSee($c->url(), false);
        // at the end of a piece: the next one in the owner's order (2 → 0 → 1), then the whole collection
        $this->get($this->posts[2]->url())->assertOk()->assertSee('“শীতের লেখা” সংকলনের লেখা', false)->assertSee('পরেরটি: শীতের সকাল', false);
        $this->get($this->posts[1]->url())->assertOk()->assertSee('সংকলনের সব লেখা দেখুন', false);
        $this->get($this->posts[5]->url())->assertOk()->assertDontSee('সংকলনের লেখা', false);
        $this->get('/sonkolon/shiter-lekha/')->assertSee('প্রথম লেখা থেকে পড়ুন', false)->assertSee($this->posts[2]->url(), false);
        $this->get('/sitemap-pages.xml')->assertOk()->assertSee($c->url(), false);

        // Re-running the archivist on a published collection only suggests additions; the owner's list stays.
        Claude::$fake = fn () => ['intro' => 'নতুন।', 'ids' => [$this->posts[3]->id, $this->posts[0]->id, $this->posts[1]->id, $this->posts[2]->id]];
        Archivist::themed('shiter-lekha');
        $this->assertSame([$this->posts[2]->id, $this->posts[0]->id, $this->posts[1]->id], $c->fresh()->post_ids);
        $this->assertSame([$this->posts[3]->id], EditorTask::open('collection_add')->sole()->payload['add']);
        $this->assertNotNull(CollectionResource::addTask($c));
    }

    public function test_collection_without_a_slug_gets_one_when_approved(): void
    {
        $c = Collection::create(['kind' => 'theme', 'title' => 'নদীর কবিতা', 'post_ids' => [$this->posts[0]->id], 'status' => 'proposed']);
        $this->assertNull($c->slug);
        $c->forceFill(['status' => 'approved'])->save();
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', (string) $c->fresh()->slug);
        $this->get($c->fresh()->url())->assertOk();
    }

    /** D98: each collection is prepared a month before its season starts; nothing is published by itself. */
    public function test_seasonal_calendar_prepares_each_collection_a_month_ahead(): void
    {
        $ids = array_map(fn ($p) => $p->id, array_slice($this->posts, 0, 5));
        Claude::$fake = fn () => ['intro' => 'ভূমিকা।', 'ids' => $ids];
        $at = fn (int $month) => \Illuminate\Support\Carbon::create(2027, $month, 1, 9, 0, 0, 'Asia/Kolkata');

        $this->assertSame(['shiter-lekha'], Archivist::due($at(10)), 'October prepares winter (starts in November)');
        $this->assertSame(['premer-kobita'], Archivist::due($at(1)), 'January prepares love poems (February)');
        $this->assertSame(['mayer-jonno-kobita'], Archivist::due($at(4)));
        $this->assertSame(['babake-mone-pore', 'barshar-kobita-o-golpo'], Archivist::due($at(5)));
        $this->assertSame(['pujor-golpo'], Archivist::due($at(8)));
        $this->assertSame(['bhooter-golpo'], Archivist::due($at(9)));
        $this->assertSame(['chhelebela-o-smriti'], Archivist::due($at(3)), 'Year-round ones in March');
        $this->assertSame([], Archivist::due($at(7)));

        // end to end for October: winter is proposed, and only proposed
        $this->assertSame(['shiter-lekha'], Archivist::seasonal($at(10)));
        $this->assertSame('proposed', Collection::where('slug', 'shiter-lekha')->value('status'));
        $this->assertSame(0, Collection::where('status', 'approved')->count(), 'Only proposals; the owner publishes');
    }

    /** D99 (owner): the archive page shows collections as compact cards, not every piece. */
    public function test_archive_page_shows_collections_as_compact_cards(): void
    {
        Collection::create(['kind' => 'theme', 'title' => 'শীতের লেখা', 'slug' => 'shiter-lekha', 'intro' => 'শীতের ভূমিকা।', 'status' => 'approved', 'approved_at' => now(),
            'post_ids' => array_map(fn ($p) => $p->id, array_slice($this->posts, 0, 5))]);
        $this->get('/archive/')->assertOk()->assertSee('শীতের লেখা')->assertSee('সব সংকলন')->assertSee('৫টি লেখা')
            ->assertSee('শীতের সকাল')->assertDontSee('শীত এল')->assertDontSee('হেমন্তের শেষে');
        $this->get('/sonkolon/')->assertOk()->assertSee('৫টি লেখা')->assertDontSee('হেমন্তের শেষে');
    }
}
