<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\WriterQuestions;
use App\Models\EditorTask;
use App\Models\User;
use App\Notifications\WriterNote;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** D257 (owner: "a separate section in the menu, where I can see all the questions and answer"). */
class WriterQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_question_is_listed_waiting_first_and_answered_from_there(): void
    {
        Notification::fake();
        $writer = User::factory()->create(['name' => 'অস্মিতা', 'email' => 'asmita@example.com']);
        $ask = fn (string $q, string $status) => EditorTask::create(['type' => 'question', 'status' => $status, 'subject_type' => $writer->getMorphClass(), 'subject_id' => $writer->id,
            'title' => 'লেখকের প্রশ্ন: '.$q, 'payload' => ['question' => $q, 'email' => $writer->email, 'name' => $writer->name] + ($status === 'done' ? ['reply' => 'আগের উত্তর'] : [])]);
        $old = $ask('পুরনো প্রশ্ন', 'done');
        $new = $ask('ডিসেম্বর সংখ্যার শেষ তারিখ কবে?', 'open');
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));

        $this->assertSame('১', WriterQuestions::getNavigationBadge());
        Livewire::test(WriterQuestions::class)->assertOk()
            ->assertCanSeeTableRecords([$new, $old], inOrder: true)->assertSee('আগের উত্তর')
            ->callTableAction('answer', $new, ['message' => '৭ ডিসেম্বরের মধ্যে পাঠান।'])->assertHasNoTableActionErrors();

        Notification::assertSentTo($writer, WriterNote::class, fn ($n) => $n->message === '৭ ডিসেম্বরের মধ্যে পাঠান।');
        $this->assertSame(['done', '৭ ডিসেম্বরের মধ্যে পাঠান।'], [$new->fresh()->status, $new->fresh()->payload['reply']]);
        $this->assertNull(WriterQuestions::getNavigationBadge());
    }
}
