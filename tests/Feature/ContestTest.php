<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\ContestManager;
use App\Enums\Role;
use App\Models\Certificate;
use App\Models\Contest;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D53: contests: entries via the submit form, AI shortlist (advice), owner picks winners. */
class ContestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    private function submit(User $user, array $extra = [])
    {
        $section = Section::firstOrCreate(['slug' => 'kobita'], ['name_bn' => 'কবিতা', 'name_en' => 'Poetry', 'format' => 'poem']);

        return $this->actingAs($user)->post('/submit/', array_merge(['title' => 'বর্ষা', 'body' => str_repeat('মেঘ ভেসে যায় দূরে। ', 5), 'section_id' => $section->id, 'action' => 'draft'], $extra));
    }

    public function test_entry_only_for_open_contest_and_once_per_writer(): void
    {
        $open = Contest::create(['title' => 'বর্ষার কবিতা', 'status' => 'open', 'ends_at' => now()->addWeek()]);
        $closed = Contest::create(['title' => 'পুরনো', 'status' => 'open', 'ends_at' => now()->subDay()]);
        $writer = User::factory()->create(['role' => Role::Writer]);

        $this->submit($writer, ['contest_id' => $closed->id])->assertSessionHasErrors('contest_id');
        $this->submit($writer, ['contest_id' => $open->id])->assertSessionHasNoErrors();
        $this->assertSame(1, $open->entries()->count());
        $this->submit($writer, ['contest_id' => $open->id, 'title' => 'আরেকটি'])->assertSessionHasErrors('contest_id');
        $this->actingAs($writer)->get('/submit/')->assertSee('বর্ষার কবিতা')->assertDontSee('পুরনো');
    }

    public function test_shortlist_is_advice_and_owner_announces_winners_as_a_draft_with_certificates(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'name_en' => 'Editorial', 'format' => 'prose']);
        $contest = Contest::create(['title' => 'বর্ষার কবিতা', 'status' => 'open']);
        $a = $this->makePost(['status' => 'pending', 'contest_id' => $contest->id, 'slug' => 'a', 'title' => 'ক']);
        $b = $this->makePost(['status' => 'pending', 'contest_id' => $contest->id, 'slug' => 'b', 'title' => 'খ']);
        Claude::$fake = fn () => ['picks' => [['id' => $b->id, 'why' => 'ভালো'], ['id' => 99999, 'why' => 'নেই']]];
        $this->assertNull(ContestManager::shortlist($contest));
        $this->assertSame([$b->id], array_column($contest->fresh()->shortlist, 'post_id'), 'Unknown ids are dropped');
        $this->assertSame('pending', $b->fresh()->status, 'Shortlisting changes nothing');

        $owner = User::factory()->create(['role' => Role::Admin]);
        $results = ContestManager::announce($contest->fresh(), $owner, [['post_id' => $b->id, 'place' => 1], ['post_id' => 424242, 'place' => 2]]);
        $this->assertSame('draft', $results->status);
        $this->assertStringContainsString('খ', $results->body_html);
        $this->assertSame(1, Certificate::where('kind', 'contest')->count());
    }

    public function test_desk_contest_pages_render(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $contest = Contest::create(['title' => 'বর্ষার কবিতা', 'status' => 'open', 'theme' => 'বৃষ্টি']);
        $this->makePost(['status' => 'pending', 'contest_id' => $contest->id, 'slug' => 'e', 'title' => 'জমা লেখা']);
        \Livewire\Livewire::test(\App\Filament\Resources\Contests\Pages\ListContests::class)->assertOk()->assertSee('বর্ষার কবিতা');
        \Livewire\Livewire::test(\App\Filament\Resources\Contests\Pages\EditContest::class, ['record' => $contest->id])->assertOk()->assertSee('জমা লেখা');
    }
}
