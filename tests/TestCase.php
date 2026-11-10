<?php

namespace Tests;

use App\Models\Author;
use App\Models\Post;
use App\Models\Section;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety (D93): tests wipe their database. They may only ever touch the throwaway in-memory SQLite one.
     * With cached config (every deploy runs `optimize`) phpunit.xml is ignored and RefreshDatabase would
     * wipe the site's real database, so refuse loudly before anything runs.
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        $db = $app['config']->get('database.default');
        $name = $app['config']->get("database.connections.{$db}.database");
        if ($app->configurationIsCached() || $db !== 'sqlite' || $name !== ':memory:') {
            throw new \RuntimeException("Refusing to run tests: they would wipe the '{$db}' database '{$name}'. "
                .'Run `php artisan config:clear` first; tests only ever use the in-memory SQLite database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Test the live-site behaviour by default; staging (noindex) has its own test.
        config(['seo.indexable' => true]);
        // The breached-password check (D59) must not call the internet from tests: "not found".
        \Illuminate\Support\Facades\Http::preventStrayRequests(false);
        \Illuminate\Support\Facades\Http::fake(['api.pwnedpasswords.com/*' => \Illuminate\Support\Facades\Http::response('', 200)]);
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
    /** Requests and generated links behave as on https://hatpakha.com (D125: other hosts are redirected there). */
    protected function asLiveSite(): void
    {
        config(['app.url' => 'https://hatpakha.com']);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://hatpakha.com');
        \Illuminate\Support\Facades\URL::forceScheme('https');
        $this->withServerVariables(['HTTP_HOST' => 'hatpakha.com', 'HTTPS' => 'on', 'SERVER_PORT' => '443']);
    }

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
