<?php

namespace Tests\Feature;

use App\Support\HtmlToMarkdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D126: agents may find and quote pieces (with a link); nobody may train on them. Markdown on request, never cached. */
class AgentReadyTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_says_search_and_answers_yes_training_no(): void
    {
        config(['seo.indexable' => true]);
        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString("User-agent: *\nContent-Signal: search=yes, ai-input=yes, ai-train=no\n", $robots);
        $this->assertStringContainsString("User-agent: GPTBot\n", $robots);
        $this->assertStringContainsString("User-agent: Google-Extended\n", $robots);
        $this->assertMatchesRegularExpression('/User-agent: cohere-training-data-crawler\nDisallow: \/\n/', $robots);
        foreach (['ChatGPT-User', 'OAI-SearchBot', 'PerplexityBot', 'Claude-User', 'Claude-SearchBot', 'Googlebot', 'Bingbot'] as $answers) {
            $this->assertStringNotContainsString('User-agent: '.$answers, $robots, $answers.' follows the * rules');
        }
        $this->assertStringEndsWith('Sitemap: '.url('/sitemap.xml')."\n", $robots);

        config(['seo.indexable' => false]);
        $this->assertStringNotContainsString('Content-Signal', $this->get('/robots.txt')->getContent(), 'Staging stays closed (D88)');
    }

    public function test_llms_txt_lists_real_sections_pages_and_pieces(): void
    {
        $post = $this->makePost(['status' => 'published', 'slug' => 'megh', 'title' => 'মেঘের চিঠি', 'published_at' => now()->subDay()]);
        $this->makePost(['status' => 'draft', 'slug' => 'khasra', 'title' => 'খসড়া']);

        $txt = $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();
        $this->assertStringStartsWith("# হাতপাখা (Hatpakha)\n\n> ", $txt);
        $this->assertStringContainsString('[মেঘের চিঠি]('.$post->url().'): Test Writer', $txt);
        $this->assertStringContainsString('[কবিতা]('.url('/category/poetry').'/)', $txt);
        $this->assertStringNotContainsString('খসড়া', $txt);
        $this->assertStringContainsString('Accept: text/markdown', $txt);
        $this->assertStringContainsString(url('/sitemap.xml'), $txt);
    }

    public function test_pages_point_to_llms_txt_and_give_markdown_only_when_asked(): void
    {
        $post = $this->makePost(['status' => 'published', 'slug' => 'brishti', 'title' => 'বৃষ্টি', 'published_at' => now()->subDay(),
            'body_html' => '<p>প্রথম লাইন<br>দ্বিতীয় লাইন</p><p>আরেক <a href="https://hatpakha.com/megh/">মেঘ</a> <strong>জোর</strong></p><blockquote><p>উদ্ধৃতি</p></blockquote>']);

        $html = $this->get('/brishti/')->assertOk();
        $this->assertStringContainsString('</llms.txt>; rel="describedby"', $html->headers->get('Link'));
        $this->assertStringStartsWith('text/html', $html->headers->get('Content-Type'));
        $this->assertStringContainsString('public', $html->headers->get('Cache-Control'), 'Browsers still get the cached HTML page');

        $md = $this->get('/brishti/', ['Accept' => 'text/markdown'])->assertOk();
        $md->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $this->assertStringContainsString('no-store', $md->headers->get('Cache-Control'), 'Markdown is never kept by Cloudflare');
        $this->assertStringContainsString('<'.$post->url().'>; rel="canonical"', implode(',', $md->headers->all('link')));
        $body = $md->getContent();
        $this->assertStringStartsWith('# ', $body);
        $this->assertStringContainsString('Source: <'.$post->url().'>', $body);
        $this->assertStringContainsString("প্রথম লাইন  \nদ্বিতীয় লাইন", $body, 'A poem keeps its lines');
        $this->assertStringContainsString('[মেঘ](https://hatpakha.com/megh/)', $body);
        $this->assertStringContainsString('**জোর**', $body);
        $this->assertStringContainsString('> উদ্ধৃতি', $body);
        $this->assertStringNotContainsString('<', strtok($body, "\n"));
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('<form', $body);

        // A browser's usual Accept header never gets Markdown; nor do the desk and account pages.
        $this->get('/brishti/', ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'])->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->get('/', ['Accept' => 'text/markdown'])->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $this->get('/login/', ['Accept' => 'text/markdown'])->assertOk()->assertHeaderMissing('Link');
    }

    public function test_converter_drops_scripts_forms_ads_and_hidden_parts(): void
    {
        $md = HtmlToMarkdown::page('<html><head><title>শিরোনাম</title><meta name="description" content="বিবরণ"></head><body><nav>মেনু</nav><main>'
            .'<h1>লেখা</h1><script>alert(1)</script><form><input name="x"><button>পাঠান</button></form><ins class="adsbygoogle">বিজ্ঞাপন</ins>'
            .'<span aria-hidden="true">লুকোনো</span><div data-md-skip>ভালো লেগেছে</div><ul><li>এক</li><li>দুই</li></ul><img src="/a.webp" alt="ছবি"></main><footer>ফুটার</footer></body></html>', 'https://hatpakha.com/x/');

        $this->assertStringStartsWith("# শিরোনাম\n\n> বিবরণ\n>\n> Source: <https://hatpakha.com/x/>", $md);
        $this->assertStringContainsString("## লেখা", $md);
        $this->assertStringContainsString("- এক\n- দুই", $md);
        $this->assertStringContainsString('![ছবি](/a.webp)', $md);
        foreach (['alert', 'পাঠান', 'বিজ্ঞাপন', 'লুকোনো', 'ভালো লেগেছে', 'মেনু', 'ফুটার'] as $gone) {
            $this->assertStringNotContainsString($gone, $md);
        }
        $this->assertNull(HtmlToMarkdown::page('<p>no main</p>', 'https://x.test/'));
    }

    public function test_sitemap_index_dates_and_contact_point(): void
    {
        $this->travelTo('2026-11-01 10:00:00');
        $this->makePost(['status' => 'published', 'published_at' => now()->subDay()]);
        $this->travelBack();

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<loc>[^<]+sitemap-posts-1\.xml</loc><lastmod>2026-11-01T10:00:00\+0\d:\d\d</lastmod>#', $xml);
        $this->assertStringContainsString('sitemap-sections.xml</loc><lastmod>', $xml);

        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $this->get('/')->assertOk()->getContent(), $m);
        $ld = str_replace('\/', '/', implode("\n", $m[1]));
        $this->assertStringContainsString('"contactPoint"', $ld);
        $this->assertStringContainsString('"url":"'.url('/contact-us').'/"', $ld);
        $this->assertStringNotContainsString('"telephone"', $ld);
        $this->assertStringNotContainsString('"email"', $ld, 'D89: no public email address');
    }
}
