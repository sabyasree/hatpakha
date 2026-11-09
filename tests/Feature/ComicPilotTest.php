<?php

namespace Tests\Feature;

use App\Ai\Claude;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComicPilotTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_panels_are_drawn_with_the_character_sheet_attached_and_only_files_are_written(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => 'k', 'ai.openai.image_model' => 'img']);
        $panel = ['scene' => 'A boy on a marsh path at night', 'caption' => '', 'bubbles' => [['speaker' => 'খোকা', 'text' => 'ওটা কী আলো?', 'side' => 'left']]];
        Claude::$fake = fn () => ['title' => 'আলেয়া', 'characters' => [['name' => 'খোকা', 'look' => 'small boy, red gamchha']], 'panels' => array_fill(0, 4, $panel)];
        $png = base64_encode('PNGDATA');
        Http::fake(['*/images/*' => Http::response(['data' => [['b64_json' => $png]]])]);
        $out = sys_get_temp_dir().'/comic-test-'.uniqid();

        $this->artisan('hatpakha:comic-pilot', ['--out' => $out])->assertSuccessful();

        foreach (['script.json', 'sheet.png', 'panel-1.png', 'panel-4.png', 'control-1.png'] as $f) {
            $this->assertFileExists("{$out}/{$f}");
        }
        Http::assertSentCount(6);   // sheet + 4 panels + 1 text-only control
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/images/edits') && str_contains($r->body(), 'PNGDATA'));
        $this->assertDatabaseCount('posts', 0);   // nothing reaches the site
        array_map('unlink', glob("{$out}/*"));
        rmdir($out);
    }

    public function test_refuses_without_keys(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => null]);
        $this->artisan('hatpakha:comic-pilot')->assertFailed();
    }
}
