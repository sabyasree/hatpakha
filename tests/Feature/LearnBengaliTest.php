<?php

namespace Tests\Feature;

use App\Http\Controllers\LearnController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D317: বাংলা শিখি: four lessons after the বর্ণমালা, worksheets to print, no ads (children's pages, D101). */
class LearnBengaliTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_lessons_and_worksheets_open(): void
    {
        $this->get('/janun/learn-bengali/')->assertOk()->assertSee('বাংলা শিখি')->assertSee('/bornomala/', false)->assertSee('/janun/learn-bengali/kar/', false);
        $this->get('/janun/learn-bengali/kar/')->assertOk()->assertSee('আ-কার')->assertSee('data-barokhori', false)->assertSee('"LearningResource"', false)
            ->assertSee('/janun/learn-bengali/phola/', false)->assertSee('/janun/learn-bengali/kar/print/', false);
        $this->get('/janun/learn-bengali/juktakkhor/')->assertOk()->assertSee('ক্ষ')->assertSee('ক + ষ');
        $this->get('/janun/learn-bengali/first-words/')->assertOk()->assertSee('অনেক মুসলমান পরিবারে পানি')->assertSee('Thank you.');
        $this->get('/janun/learn-bengali/kar/print/')->assertOk()->assertSee('noindex, follow', false)->assertSee('trace', false);
        $this->get('/janun/learn-bengali/nothing/')->assertNotFound();
        $this->assertStringEndsWith('/janun/learn-bengali/kar/', (string) $this->get('/janun/learn-bengali/kar')->assertStatus(301)->headers->get('Location'));
        $this->assertStringContainsString('/janun/learn-bengali/juktakkhor/', $this->get('/sitemap-pages.xml')->getContent());
    }

    public function test_no_ads_and_no_analytics_on_childrens_lessons(): void
    {
        config(['app.url' => 'https://hatpakha.com', 'services.adsense.client' => 'ca-pub-1234567890123456', 'services.google.analytics_id' => 'G-TEST1234']);
        foreach (['', 'kar/', 'first-words/'] as $u) {
            $this->get('https://hatpakha.com/janun/learn-bengali/'.$u)->assertOk()->assertDontSee('googlesyndication', false)->assertDontSee('googletagmanager', false);
        }
    }

    public function test_every_example_is_complete(): void
    {
        $all = LearnController::lessons();
        $this->assertSame(LearnController::LESSONS, array_keys($all));
        foreach ($all as $k => $l) {
            $this->assertNotEmpty($l['title'].$l['en'].$l['intro'], $k);
        }
        foreach ($all['kar']['rows'] as $r) {
            $this->assertCount(3, $r[5]);
            foreach ($r[5] as $w) {
                $this->assertStringContainsString($r[1], $w[0], "{$w[0]} shows {$r[2]}");
            }
        }
        foreach ($all['juktakkhor']['rows'] as [$c, $parts, $w]) {
            $this->assertStringContainsString($c, $w[0], "{$w[0]} has {$c}");
            $this->assertSame(\Normalizer::normalize(str_replace([' + ', ' '], ['্', ''], $parts)), \Normalizer::normalize($c), "{$c} is {$parts}");
        }
        foreach ($all['kar']['rows'] as $r) {
            foreach ($r[5] as $w) {
                $this->assertNotContains($w[1], ['mom'], 'no example that reads as an English word');
            }
        }
    }
}
