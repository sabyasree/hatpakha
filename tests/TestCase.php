<?php

namespace Tests;

use App\Models\Author;
use App\Models\Post;
use App\Models\Section;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Test the live-site behaviour by default; staging (noindex) has its own test.
        config(['seo.indexable' => true]);
    }

    /**
     * Laravel's test client trims trailing slashes; real browsers and crawlers don't.
     * Keep the slash so tests exercise the real URL policy (docs/SEO.md §1).
     */
    protected function prepareUrlForRequest($uri)
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '';
        $url = parent::prepareUrlForRequest($uri);
        if ($path !== '/' && str_ends_with($path, '/')) {
            [$base, $query] = array_pad(explode('?', $url, 2), 2, null);
            $url = rtrim($base, '/').'/'.($query !== null ? '?'.$query : '');
        }

        return $url;
    }

    /** Creates a published post with one author. */
    protected function makePost(array $attrs = [], ?Author $author = null): Post
    {
        $section = Section::firstOrCreate(['slug' => 'poetry'], ['name_bn' => 'কবিতা', 'format' => 'poem']);
        $author ??= Author::create(['slug' => 'writer-'.uniqid(), 'display_name' => 'Test Writer']);

        $post = Post::create(array_merge([
            'slug' => 'test-'.uniqid(),
            'title' => 'পরীক্ষার কবিতা',
            'body_html' => '<p>প্রথম লাইন</p><p>দ্বিতীয় লাইন</p>',
            'format' => 'poem',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'section_id' => $section->id,
        ], $attrs));
        $post->authors()->attach($author->id);

        return $post;
    }
}
