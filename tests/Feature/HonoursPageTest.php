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

    /** D77: the writing leads; a prolific writer does not outrank a better one just by count. */
    public function test_writing_quality_outranks_number_of_pieces(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $many = Author::create(['display_name' => 'অনেক লেখা', 'slug' => 'many']);
        $good = Author::create(['display_name' => 'ভালো লেখা', 'slug' => 'good']);
        foreach (range(1, 40) as $i) {
            $this->makePost(['status' => 'published', 'slug' => 'm'.$i, 'published_at' => now()->subMonths($i), 'body_html' => '<p>সাধারণ লেখা '.$i.'</p>'], $many);
        }
        foreach (range(1, 4) as $i) {
            $this->makePost(['status' => 'published', 'slug' => 'g'.$i, 'published_at' => now()->subMonths($i), 'body_html' => '<p>চমৎকার লেখা '.$i.'</p>'], $good);
        }
        $this->assertSame('অনেক লেখা', \App\Ai\Honours::candidates()->first()['author']->display_name, 'Before reading: numbers only');

        $this->assertGreaterThan(0, \App\Ai\Honours::estimate(\App\Ai\Honours::toRead()));
        Claude::$fake = fn ($purpose, $user) => str_contains($user, 'চমৎকার') ? ['score' => 9, 'note' => 'ছবির মতো স্পষ্ট ভাষা।'] : ['score' => 5, 'note' => 'চলনসই।'];
        $this->assertSame(2, \App\Ai\Honours::readSamples());
        $this->assertSame(0, \App\Ai\Honours::readSamples(), 'Not read again within 90 days');

        $top = \App\Ai\Honours::candidates()->first();
        $this->assertSame('ভালো লেখা', $top['author']->display_name, 'The better writing leads, whatever the count');
        $this->assertSame(9.0, $top['quality']);

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(Honours::class)->assertSee('লেখার মান')->assertSee('ছবির মতো স্পষ্ট ভাষা।')
            ->callAction('read', ['redo' => true])->assertHasNoActionErrors();
        $this->assertSame(1, \App\Models\TeamRequest::where('job', 'read_writers')->count());
    }
}
