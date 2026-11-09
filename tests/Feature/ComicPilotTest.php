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

    public function test_claude_draws_the_same_script_as_vector_panels_without_active_content(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => null]);
        $dir = sys_get_temp_dir().'/comic-test-'.uniqid();
        mkdir($dir);
        $panel = ['scene' => 'A boy on a marsh path', 'caption' => '', 'bubbles' => []];
        file_put_contents("{$dir}/in.json", json_encode(['title' => 'আলেয়া', 'characters' => [['name' => 'খোকা', 'look' => 'boy']], 'panels' => array_fill(0, 4, $panel)]));
        Claude::$fake = fn ($purpose) => $purpose === 'design'
            ? ['panels' => array_fill(0, 4, '<svg viewBox="0 0 1024 1024"><script>alert(1)</script><g onclick="x()"><circle r="5"/></g></svg>')] : null;
        Http::fake();

        $this->artisan('hatpakha:comic-pilot', ['--engine' => 'claude', '--script' => "{$dir}/in.json", '--out' => $dir])->assertSuccessful();

        $svg = file_get_contents("{$dir}/claude-panel-4.svg");
        $this->assertStringContainsString('<circle', $svg);
        $this->assertStringNotContainsString('script', $svg);
        $this->assertStringNotContainsString('onclick', $svg);
        Http::assertNothingSent();   // no OpenAI call at all
        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }

    public function test_refuses_without_keys(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => null]);
        $this->artisan('hatpakha:comic-pilot')->assertFailed();
    }
}
