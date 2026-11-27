<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\LearnStrokes;
use App\Http\Controllers\LearnController;
use App\Models\LearnStroke;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D325: stroke order: a machine draft for every letter, checked at the desk; children see only approved drawings. */
class LearnStrokesTest extends TestCase
{
    use RefreshDatabase;

    private function desk(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
    }

    public function test_the_first_set_arrives_as_drafts_for_every_letter(): void
    {
        $letters = array_diff(array_column(\App\Ai\KidsAlphabet::letters(), 0), ['ং', 'ঃ', 'ঁ']);
        $this->assertSame(count($letters), LearnStroke::count());
        $this->assertSame(0, LearnStroke::where('status', 'approved')->count(), 'nothing reaches children before the desk approves it');
        foreach (LearnStroke::all() as $s) {
            $this->assertNotNull(LearnStroke::clean($s->strokes), $s->glyph);
        }
    }

    public function test_only_approved_drawings_reach_lessons_and_worksheets(): void
    {
        $this->assertSame([], LearnController::lessonData('first-letters', 1)['strokes']);
        $this->get('/janun/learn-bengali/first-letters/1/print/')->assertDontSee('data-stroke-glyph', false);
        LearnStroke::where('glyph', 'ম')->update(['status' => 'approved']);
        $this->assertArrayHasKey('ম', LearnController::lessonData('first-letters', 1)['strokes']);
        $this->get('/janun/learn-bengali/first-letters/1/print/')->assertSee('data-stroke-glyph="ম"', false);
    }

    public function test_the_desk_approves_redraws_adds_and_deletes(): void
    {
        $this->desk();
        $ka = LearnStroke::where('glyph', 'ক')->first();
        $page = Livewire::test(LearnStrokes::class)->assertSee('ক')->assertSee('মেশিনের খসড়া');
        $page->call('approve', $ka->id);
        $this->assertSame('approved', $ka->fresh()->status);
        $page->call('unapprove', $ka->id);
        $this->assertSame('draft', $ka->fresh()->status);
        $page->call('save', 'ক', json_encode([[[20, 30], [50, 60]], [[10, 20], [90, 20]]]));
        $this->assertSame(['approved', 'desk'], [$ka->fresh()->status, $ka->fresh()->source], 'drawn by a person: approved');
        $this->assertCount(2, $ka->fresh()->strokes);
        $page->call('saveDraft', 'কা', json_encode([[[10, 10], [20, 20]]]));
        $this->assertSame('draft', LearnStroke::where('glyph', 'কা')->value('status'), 'a new letter or sign can be added any time');
        $page->call('save', 'abc', json_encode([[[1, 1], [2, 2]]]));
        $this->assertNull(LearnStroke::where('glyph', 'abc')->first(), 'Bengali only');
        $page->call('save', 'খ', json_encode([]));
        $this->assertSame('machine', LearnStroke::where('glyph', 'খ')->value('source'), 'an empty drawing changes nothing');
        $this->assertSame([[[0.0, 100.0], [50.1, 0.0]]], LearnStroke::clean([[[-5, 300], [50.123, -1]]]), 'kept inside the box');
        $page->call('remove', $ka->id);
        $this->assertNull(LearnStroke::find($ka->id));
    }

    public function test_writers_cannot_open_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Writer]));
        $this->assertFalse(LearnStrokes::canAccess());
    }
}
