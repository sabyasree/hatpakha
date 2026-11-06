<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 2 URL audit: every old WordPress URL shape answers 200 or 301 (docs/SEO.md §1). */
class LegacyUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_wordpress_url_shapes_redirect_to_the_right_place(): void
    {
        $post = $this->makePost(['slug' => 'dabipuran', 'legacy_wp_id' => 205]);
        Section::firstOrCreate(['slug' => 'poetry'], ['name_bn' => 'কবিতা', 'name_en' => 'Poetry', 'format' => 'poem', 'sort' => 1]);
        Page::create(['slug' => 'faqs', 'title' => 'প্রশ্নোত্তর', 'body_html' => '<p>উত্তর</p>', 'legacy_wp_id' => 18209]);
        $home = url('/').'/';

        $this->get('/dabipuran/feed/')->assertRedirect(url('/dabipuran').'/')->assertStatus(301);
        $this->get('/dabipuran/some-image-attachment/')->assertRedirect(url('/dabipuran').'/');
        $this->get('/dabipuran/amp/')->assertRedirect(url('/dabipuran').'/');
        $this->get('/comments/feed/')->assertRedirect(url('/feed').'/');
        $this->get('/page/2/')->assertRedirect($home);
        $this->get('/category/poetry/page/3/')->assertRedirect(url('/category/poetry').'/?page=3');
        $this->get('/2016/')->assertRedirect($home);
        $this->get('/2016/03/')->assertRedirect($home);
        $this->get('/2016/03/15/')->assertRedirect($home);
        $this->get('/?p=205')->assertRedirect(url('/dabipuran').'/');
        $this->get('/?page_id=18209')->assertRedirect(url('/faqs').'/');
        $this->get('/?s=kobita')->assertRedirect($home);
        $this->get('/?cat=7')->assertRedirect($home);
        $this->get('/category/literature/poetry/')->assertRedirect(url('/category/poetry').'/');
        $this->get('/category/poetry/feed/')->assertOk();
        $this->get('/sitemap.html')->assertRedirect($home);
    }

    public function test_unknown_author_and_tag_urls_use_the_redirects_table(): void
    {
        \App\Models\Redirect::create(['from_path' => '/author/hatpakha/', 'to_path' => '/author/hatpakha-desk/', 'status_code' => 301]);
        $this->get('/author/hatpakha/')->assertRedirect(url('/author/hatpakha-desk').'/');
        $this->get('/author/nobody/')->assertNotFound();
        $this->get('/tag/nothing/')->assertNotFound();
    }

    public function test_author_query_never_reveals_user_names(): void
    {
        $this->get('/?author=1')->assertRedirect(url('/').'/');
    }

    public function test_unknown_two_segment_paths_still_404_and_admin_is_not_swallowed(): void
    {
        $this->get('/no-such-post/feed/')->assertNotFound();
        $this->get('/no-such-post/child/')->assertNotFound();
        $this->get('/desk/login')->assertOk();
    }

    public function test_a_post_with_a_year_like_slug_still_opens(): void
    {
        $this->makePost(['slug' => '1971']);
        $this->get('/1971/')->assertOk();
    }
}
