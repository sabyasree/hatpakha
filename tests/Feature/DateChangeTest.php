<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\User;
use App\Notifications\TemplateMail;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** D255 (owner: "if I change the date of a scheduled piece, how will the writer know?"): a tick box sends the new date. */
class DateChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_a_scheduled_piece_tells_its_writer_when_ticked(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 06:00:00', 'UTC'));
        Notification::fake();
        $writer = User::factory()->create(['name' => 'মেঘলা', 'email' => 'megha@example.com']);
        $post = $this->makePost(['slug' => 'brishti', 'title' => 'বৃষ্টির দিন', 'status' => 'scheduled', 'published_at' => '2026-12-05 03:30:00']);
        $post->forceFill(['submitted_by' => $writer->id])->save();
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertFormFieldIsHidden('tell_new_date')
            ->fillForm(['published_at' => '2026-12-14 09:00:00'])
            ->assertFormFieldIsVisible('tell_new_date')
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('scheduled', $post->fresh()->status);
        Notification::assertSentTo($writer, TemplateMail::class, fn ($n) => $n->key === 'date_changed'
            && $n->values['{আগের_তারিখ}'] !== $n->values['{নতুন_তারিখ}'] && $n->values['{লেখা}'] === 'বৃষ্টির দিন');
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.date_told']);

        // Saving again without moving the date, or with the box unticked, sends nothing more.
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->call('save');
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['published_at' => '2026-12-15 09:00:00', 'tell_new_date' => false])->call('save')->assertHasNoFormErrors();
        Notification::assertSentToTimes($writer, TemplateMail::class, 1);
        $this->assertStringContainsString('প্রকাশের নতুন তারিখ', \App\Support\MailTemplates::mail('date_changed', [], null)->subject);
    }
}
