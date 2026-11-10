<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\Illustrator;
use App\Enums\Role;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** D60: the owner asks for a (new) cover from the post page; it is drawn by the scheduler and put on only by the owner. */
class DrawCoverTest extends TestCase
{
    use RefreshDatabase;

    private ?string $seen = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => 'k', 'ai.openai.image_model' => 'img-model', 'ai.openai.image_price_usd' => 0.04]);
        Claude::$fake = function ($purpose, $user) {
            $this->seen = $user;

            return ['scene' => 'A riverside ghat at dusk with an empty boat'];
        };
        $img = imagecreatetruecolor(1536, 1024);
        ob_start(); imagepng($img); $png = ob_get_clean();
        Http::fake(['*/images/generations' => Http::response(['data' => [['b64_json' => base64_encode($png)]]])]);
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_draw_from_the_post_page_then_put_it_on(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        $post = $this->makePost(['status' => 'published', 'slug' => 'ghat', 'published_at' => now()]);

        Livewire::test(EditPost::class, ['record' => $post->id])->callAction('drawCover', ['hint' => 'বর্ষার সন্ধ্যায় নদীর ঘাট']);
        $this->assertTrue(Illustrator::drawing($post));
        $this->assertNull($post->fresh()->proposed_image_path, 'Nothing drawn inside the web request');

        $this->assertSame(1, Illustrator::drawRequested());
        $this->assertStringContainsString('বর্ষার সন্ধ্যায় নদীর ঘাট', $this->seen, 'The owner\'s idea reaches the designer');
        $this->assertFalse(Illustrator::drawing($post));
        $post->refresh();
        $this->assertNotNull($post->proposed_image_path);
        $this->assertNull($post->featured_image_path, 'Not on the site before the owner says so');

        Livewire::test(EditPost::class, ['record' => $post->id])->callAction('reviewDrawn');
        $post->refresh();
        $this->assertNotNull($post->featured_image_path);
        $this->assertTrue((bool) $post->image_is_ai);
        @unlink(public_path(ltrim($post->featured_image_path, '/')));
    }
}
