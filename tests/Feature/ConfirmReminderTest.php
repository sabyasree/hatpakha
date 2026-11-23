<?php

namespace Tests\Feature;

use App\Ai\EditorPackage;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\TemplateMail;
use App\Support\ConfirmReminder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** D256 (owner: "yes, build both"): one reminder after three silent days; an objection shows first on আজকের কাজ. */
class ConfirmReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_silent_writer_is_reminded_once_after_three_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 06:00:00', 'UTC'));
        Notification::fake();
        $writer = User::factory()->create(['email' => 'asmita@example.com']);
        $post = $this->makePost(['slug' => 'ovoyar', 'title' => 'অভয়ার মহামায়া', 'status' => 'confirm', 'published_at' => null]);
        $post->forceFill(['submitted_by' => $writer->id, 'reviewed_at' => now(), 'pending_edits' => ['edits' => [], 'at' => '2026-11-25T03:30:00Z']])->save();

        Carbon::setTestNow(Carbon::parse('2026-11-25 06:00:00', 'UTC'));
        $this->assertSame(0, ConfirmReminder::send(), 'Two days: not yet');
        Carbon::setTestNow(Carbon::parse('2026-11-26 07:00:00', 'UTC'));
        $this->assertSame(1, ConfirmReminder::send());
        $this->assertSame(0, ConfirmReminder::send(), 'Only once');
        Notification::assertSentToTimes($writer, TemplateMail::class, 1);
        Notification::assertSentTo($writer, TemplateMail::class, fn ($n) => $n->key === 'confirm_reminder' && str_ends_with((string) $n->url, '/submit/'.$post->id.'/'));
        $this->assertSame('confirm', $post->fresh()->status, 'A reminder changes nothing');
    }

    public function test_an_objection_comes_first_on_the_desk_and_is_never_selected_by_itself(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 06:00:00', 'UTC'));
        $writer = User::factory()->create();
        $older = $this->makePost(['slug' => 'puronoo', 'title' => 'পুরনো জমা', 'status' => 'pending', 'published_at' => null, 'submitted_at' => '2026-11-01 00:00:00']);
        $older->forceFill(['submitted_by' => $writer->id])->save();
        $post = $this->makePost(['slug' => 'ovoyar', 'title' => 'অভয়ার মহামায়া', 'status' => 'confirm', 'published_at' => null, 'submitted_at' => '2026-11-20 00:00:00']);
        $post->forceFill(['submitted_by' => $writer->id, 'pending_edits' => ['edits' => []]])->save();
        EditorPackage::writerObjects($post, 'শেষ লাইনটা যেমন ছিল তেমন থাকুক');

        $this->assertStringContainsString('আপত্তি', (string) \App\Support\Handover::hold($post->fresh()));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $html = Livewire::test(\App\Filament\Pages\Dashboard::class)->assertOk()
            ->assertSee('লেখক বদলে আপত্তি করেছেন')->assertSee('শেষ লাইনটা যেমন ছিল তেমন থাকুক')->html();
        $this->assertLessThan(strpos($html, 'পুরনো জমা'), strpos($html, 'অভয়ার মহামায়া'), 'The objection is first');
    }
}
