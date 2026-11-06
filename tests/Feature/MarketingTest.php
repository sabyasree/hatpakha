<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\Marketer;
use App\Enums\Role;
use App\Filament\Pages\Marketing;
use App\Models\SocialPost;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D51: ready-to-post marketing text; nothing is posted automatically. */
class MarketingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_piece_gets_ready_to_post_text_with_link_and_only_a_real_quote(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $post = $this->makePost(['status' => 'published', 'slug' => 'megh', 'published_at' => now()->subHour(), 'body_html' => '<p>নীল আকাশ জুড়ে মেঘ ভেসে যায়।</p>']);
        Claude::$fake = fn () => ['facebook' => 'পড়ুন নতুন কবিতা', 'whatsapp' => 'নতুন কবিতা', 'instagram' => '#কবিতা', 'quote' => 'নীল আকাশ জুড়ে মেঘ ভেসে যায়'];
        $s = Marketer::forPiece($post);
        $this->assertStringContainsString($post->url(), $s->texts['facebook']);
        $this->assertStringNotContainsString('http', $s->texts['instagram']);
        $this->assertSame('নীল আকাশ জুড়ে মেঘ ভেসে যায়', $s->texts['quote']);
        $this->assertNull(Marketer::forPiece($post->fresh()), 'Once per piece');

        $other = $this->makePost(['status' => 'published', 'slug' => 'other', 'published_at' => now()->subHour(), 'body_html' => '<p>অন্য কিছু।</p>']);
        Claude::$fake = fn () => ['facebook' => 'f', 'whatsapp' => 'w', 'instagram' => 'i', 'quote' => 'এই লাইনটা লেখায় নেই'];
        $this->assertSame('', Marketer::forPiece($other)->texts['quote'], 'An invented line never reaches a card');
    }

    public function test_marketing_page_shows_drafts_and_marks_posted(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        $post = $this->makePost(['status' => 'published', 'slug' => 'x', 'published_at' => now()]);
        $s = SocialPost::create(['kind' => 'piece', 'subject_type' => $post->getMorphClass(), 'subject_id' => $post->id, 'title' => $post->title,
            'texts' => ['facebook' => 'ফেসবুকের লেখা <b>', 'whatsapp' => 'w', 'instagram' => 'i', 'quote' => 'একটি লাইন']]);
        Livewire::test(Marketing::class)->assertOk()->assertSee('ফেসবুকের লেখা &lt;b&gt;', false)->assertSee('ISSN 2321-3035')
            ->call('posted', $s->id);
        $this->assertSame('posted', $s->fresh()->status);
    }

    public function test_daily_always_offers_something_from_the_archive(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $old = $this->makePost(['status' => 'published', 'slug' => 'old', 'published_at' => now()->subYears(2), 'body_html' => '<p>পুরনো লেখা।</p>']);
        Claude::$fake = fn () => ['facebook' => 'f', 'whatsapp' => 'w', 'instagram' => 'i', 'quote' => ''];
        $s = Marketer::daily();
        $this->assertSame('archive', $s->kind);
        $this->assertSame($old->id, $s->subject_id);
        $this->assertNull(Marketer::daily(), 'One a day');
    }

    public function test_owner_can_ask_for_a_post_and_see_team_work(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Claude::$fake = fn () => ['facebook' => 'দীপাবলির শুভেচ্ছা', 'whatsapp' => 'w', 'instagram' => 'i'];
        Livewire::test(Marketing::class)->callAction('create', ['kind' => 'custom', 'topic' => 'দীপাবলির শুভেচ্ছা'])->assertHasNoActionErrors();
        $this->assertSame(1, SocialPost::where('kind', 'custom')->count());
        \App\Models\AiUsage::create(['provider' => 'anthropic', 'model' => 'm', 'purpose' => 'marketing', 'input_tokens' => 1, 'output_tokens' => 1, 'cost_usd' => 0.001]);
        Livewire::test(\App\Filament\Pages\AiTeam::class)->assertSee('পরের কাজ')->assertSee('পোস্ট তৈরি')->mountAction('activity', ['agent' => 'marketing'])->assertMountedActionModalSee('ফল কোথায় দেখবেন');
    }
}
