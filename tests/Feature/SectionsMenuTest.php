<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D95: grouped menu, English on its own, new sections, desk drafts that never publish themselves. */
class SectionsMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_groups_sections_shows_english_and_hides_empty_sections(): void
    {
        $poetry = Section::updateOrCreate(['slug' => 'poetry'], ['name_bn' => 'কবিতা', 'format' => 'poem', 'sort' => 10, 'nav_group' => 'literature', 'in_top_bar' => true]);
        $heritage = Section::updateOrCreate(['slug' => 'heritage'], ['name_bn' => 'ঐতিহ্য ও উৎসব', 'format' => 'prose', 'sort' => 45, 'nav_group' => 'life']);
        $kitchen = Section::updateOrCreate(['slug' => 'kitchen'], ['name_bn' => 'রান্নাঘরের গল্প', 'format' => 'prose', 'sort' => 46, 'nav_group' => 'life']);
        $english = Section::updateOrCreate(['slug' => 'english'], ['name_bn' => 'English', 'format' => 'prose', 'sort' => 100, 'nav_group' => 'other']);
        $this->makePost(['status' => 'published', 'section_id' => $poetry->id, 'published_at' => now()->subDay()]);
        $this->makePost(['status' => 'published', 'section_id' => $heritage->id, 'published_at' => now()->subDay()]);
        $this->makePost(['status' => 'published', 'section_id' => $english->id, 'language' => 'en', 'published_at' => now()->subDay()]);
        cache()->flush();

        $m = Section::menu();
        $this->assertSame([['কবিতা', $poetry->url(), 'bn']], $m['top']);
        $this->assertSame(['সাহিত্য', 'জীবন ও সংস্কৃতি', 'আরও'], array_column($m['groups'], 'label'));
        $life = collect($m['groups'])->firstWhere('label', 'জীবন ও সংস্কৃতি')['links'];
        $this->assertSame([['ঐতিহ্য ও উৎসব', $heritage->url(), 'bn'], ['রান্নাঘরের গল্প', $kitchen->url(), 'bn']], array_slice($life, 0, 2), 'New sections show even before their first piece (D96)');
        $this->assertSame([['English', $english->url(), 'en']], collect($m['groups'])->firstWhere('label', 'আরও')['links'], 'English is an ordinary section in আরও');

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('সব বিভাগ', $html);
        $this->assertStringContainsString('lang="en"', $html);
        $this->assertStringNotContainsString('Hatpakha in English', $html);
        $this->assertStringContainsString($heritage->url(), $html);
        $this->assertStringContainsString($kitchen->url(), $html);

        // the empty section invites writers, stays out of Google, and pre-selects itself on the writing page
        $page = $this->get('/category/kitchen/')->assertOk()->assertSee('এই বিভাগে প্রথম লেখাটি আপনার হোক')->assertSee('রেসিপির তালিকা নয়');
        $this->assertStringContainsString('noindex', (string) $page->headers->get('X-Robots-Tag').$page->getContent());
        $this->get('/category/heritage/')->assertOk()->assertDontSee('এই বিভাগে প্রথম লেখাটি');
        $this->actingAs(User::factory()->create(['role' => Role::Writer, 'email_verified_at' => now()]));
        $this->get('/submit/?section=kitchen')->assertOk()->assertSee('value="'.$kitchen->id.'" data-format="prose" class="peer sr-only" checked', false);
    }

    public function test_desk_draft_never_publishes_without_the_owner_checking_it(): void
    {
        $sec = Section::updateOrCreate(['slug' => 'kitchen'], ['name_bn' => 'রান্নাঘরের গল্প', 'format' => 'prose', 'sort' => 46, 'nav_group' => 'life']);
        Author::create(['slug' => 'hatpakha-desk', 'display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]);
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        \App\Ai\Claude::$fake = fn () => ['title' => 'নতুন গুড়', 'excerpt' => 'শীতের গুড়।', 'paragraphs' => ['প্রথম অনুচ্ছেদ।', 'দ্বিতীয় অনুচ্ছেদ।'],
            'facts_to_check' => ['সংক্রান্তির তারিখ'], 'photo_ideas' => ['গুড় জ্বাল দেওয়া']];
        [$post] = \App\Ai\DeskWriter::draft('kitchen', 'নলেন গুড়');
        \App\Ai\Claude::$fake = null;

        $this->assertSame('draft', $post->status);
        $this->assertSame(['hatpakha-desk'], $post->authors->pluck('slug')->all(), 'House byline, never a made-up person');
        $this->assertStringContainsString('সংক্রান্তির তারিখ', $post->ai_note);
        $this->get('/'.$post->slug.'/')->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $page = \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->getRouteKey()]);
        $page->callAction('publishDraft', ['at' => now()->subMinute()->format('Y-m-d H:i'), 'byline' => 'desk', 'checked' => false])->assertHasActionErrors(['checked']);
        $this->assertSame('draft', $post->fresh()->status);
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('publishDraft', ['at' => now()->subMinute()->format('Y-m-d H:i'), 'byline' => 'desk', 'checked' => true])->assertHasNoActionErrors();
        $this->assertSame('published', $post->fresh()->status);
        $this->assertStringStartsNotWith('desk-', $post->fresh()->slug, 'A readable address from the final title');
    }
}
