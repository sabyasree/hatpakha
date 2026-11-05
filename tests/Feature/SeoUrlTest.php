<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** docs/SEO.md §1–4 — old WordPress URLs must keep working. */
class SeoUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_bengali_wordpress_slug_resolves_with_trailing_slash(): void
    {
        $post = $this->makePost(['slug' => 'কোনজন-তুমি', 'legacy_wp_id' => 20604]);

        $this->get('/'.rawurlencode('কোনজন-তুমি').'/')->assertOk()->assertSee('পরীক্ষার কবিতা');
        // WordPress stored lowercase percent-encoding; both cases must work.
        $this->get('/'.strtolower(rawurlencode('কোনজন-তুমি')).'/')->assertOk();
        $this->assertStringEndsWith('/', $post->url());
    }

    public function test_missing_trailing_slash_is_301_to_slashed_url(): void
    {
        $this->makePost(['slug' => 'kon-jon-tumi']);

        $this->get('/kon-jon-tumi?x=1')->assertStatus(301)->assertRedirect(url('/kon-jon-tumi').'/?x=1');
    }

    public function test_legacy_query_id_and_amp_redirect_to_canonical(): void
    {
        $post = $this->makePost(['slug' => 'old-post', 'legacy_wp_id' => 123]);

        $this->get('/?p=123')->assertStatus(301)->assertRedirect($post->url());
        $this->get('/old-post/amp/')->assertStatus(301)->assertRedirect($post->url());
    }

    public function test_redirect_table_and_gone(): void
    {
        $this->makePost(['slug' => 'new-home']);
        Redirect::create(['from_path' => '/category/fiction/', 'to_path' => '/category/short-stories/', 'status_code' => 301]);
        Redirect::create(['from_path' => '/spam-post/', 'to_path' => null, 'status_code' => 410]);

        $this->get('/category/fiction/')->assertStatus(301)->assertRedirect(url('/category/short-stories').'/');
        $this->get('/spam-post/')->assertStatus(410);
        $this->get('/nothing-here/')->assertNotFound();
    }

    public function test_unpublished_posts_are_not_public(): void
    {
        $this->makePost(['slug' => 'pending-one', 'status' => 'pending', 'published_at' => null]);
        $this->makePost(['slug' => 'future-one', 'published_at' => now()->addDay()]);

        $this->get('/pending-one/')->assertNotFound();
        $this->get('/future-one/')->assertNotFound();
    }

    public function test_post_has_title_description_canonical_and_jsonld(): void
    {
        $post = $this->makePost(['slug' => 'meta-check', 'seo_description' => 'একটি বর্ণনা']);

        $html = $this->get('/meta-check/')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.$post->url().'">', $html);
        $this->assertStringContainsString('<meta name="description" content="একটি বর্ণনা">', $html);
        $this->assertStringContainsString('max-image-preview:large', $html);

        preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
        $graph = json_decode($m[1], true)['@graph'];
        $this->assertSame('CreativeWork', $graph[0]['@type']);
        $this->assertSame('Poetry', $graph[0]['genre']);
        $this->assertSame('BreadcrumbList', $graph[1]['@type']);
    }

    public function test_home_declares_periodical_with_issn(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('"issn":"2321-3035"', $html);
    }

    public function test_thin_author_pages_are_noindex(): void
    {
        $thin = Author::create(['slug' => 'thin', 'display_name' => 'Thin', 'published_count' => 1]);
        $rich = Author::create(['slug' => 'rich', 'display_name' => 'Rich', 'published_count' => 5]);
        $this->makePost([], $thin);
        $this->makePost([], $rich);

        $this->get('/author/thin/')->assertOk()->assertSee('noindex, follow', false);
        $this->get('/author/rich/')->assertOk()->assertSee('index, follow, max-image-preview:large', false);
    }

    public function test_sitemap_lists_only_published_posts_and_robots_points_to_it(): void
    {
        $this->makePost(['slug' => 'in-sitemap']);
        $this->makePost(['slug' => 'not-in-sitemap', 'status' => 'draft']);

        $this->get('/sitemap.xml')->assertOk()->assertSee('sitemap-posts-1.xml');
        $this->get('/sitemap-posts-1.xml')->assertOk()->assertSee(url('/in-sitemap').'/', false)->assertDontSee('not-in-sitemap');
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('/sitemap.xml'), false)->assertDontSee('Disallow: /wp-content');
    }

    public function test_staging_is_never_indexable(): void
    {
        config(['seo.indexable' => false]);
        $this->makePost(['slug' => 'staging-post']);

        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:');
        $this->get('/staging-post/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_live_site_has_no_blanket_noindex_header(): void
    {
        $this->makePost(['slug' => 'live-post']);

        $this->get('/live-post/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }
}
