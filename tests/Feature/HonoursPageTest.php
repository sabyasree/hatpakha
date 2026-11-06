<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Enums\Role;
use App\Filament\Pages\Honours;
use App\Models\Author;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D76: one place to honour writers; the honour shows on the site at once. */
class HonoursPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_shortlist_honour_with_drafted_praise_and_it_shows_on_the_site(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $a = Author::create(['display_name' => 'শ্রাবণী দে', 'slug' => 'srabani']);
        foreach (range(1, 3) as $i) {
            $this->makePost(['status' => 'published', 'slug' => 'p'.$i, 'title' => 'কবিতা '.$i, 'published_at' => now()->subYears($i)], $a);
        }
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Claude::$fake = fn () => ['praise' => 'বহু বছর ধরে হাতপাখায় লিখছেন।'];

        Livewire::test(Honours::class)->assertSee('শ্রাবণী দে')->assertSee('এখনও কাউকে সম্মান দেওয়া হয়নি')
            ->callAction('honour', ['label' => 'pick', 'praise' => 'বহু বছর ধরে হাতপাখায় লিখছেন।', 'tell' => false], ['author' => $a->id])->assertHasNoActionErrors();

        $a->refresh();
        $this->assertSame('সম্পাদকের পছন্দের লেখক', $a->honour_label);
        $this->assertSame('বহু বছর ধরে হাতপাখায় লিখছেন।', $a->honour_note);
        $this->assertTrue(\App\Models\Certificate::where('author_id', $a->id)->where('kind', 'honour')->exists());
        $this->get('/')->assertSee('সম্মানিত লেখক')->assertSee('শ্রাবণী দে');
        $this->get($a->url())->assertSee('সম্পাদকের পছন্দের লেখক')->assertSee('বহু বছর ধরে হাতপাখায় লিখছেন।');
        $this->assertSame('বহু বছর ধরে হাতপাখায় লিখছেন।', \App\Ai\Honours::draftPraise($a));

        Livewire::test(Honours::class)->call('unhonour', $a->id);
        $this->assertNull($a->fresh()->honour_label);
    }
}
