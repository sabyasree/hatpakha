<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\Honours;
use App\Enums\Role;
use App\Models\Author;
use App\Models\EditorTask;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D66: the AI proposes the writer of the month; nothing shows until the owner honours them. */
class HonoursTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_proposal_then_owner_honours_and_it_shows_everywhere(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $writer = Author::create(['slug' => 'rina', 'display_name' => 'Rina', 'published_count' => 2]);
        $this->makePost(['status' => 'published', 'slug' => 'a', 'published_at' => now()->subDays(5)], $writer);
        Claude::$fake = fn () => ['author_id' => $writer->id, 'praise' => 'রিনার কবিতায় শহরের আলো আর মানুষের একাকীত্ব একসঙ্গে ধরা পড়ে।', 'why' => 'সবচেয়ে বেশি পছন্দ'];

        $task = Honours::proposeWriterOfMonth();
        $this->assertSame('honour', $task->type);
        $this->get('/')->assertDontSee('সম্মানিত লেখক');

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Claude::$fake = fn () => ['facebook' => 'f', 'whatsapp' => 'w', 'instagram' => 'i'];
        Livewire::test(\App\Filament\Pages\Dashboard::class)->callAction('honour', ['praise' => 'রিনার কবিতায় শহরের আলো।', 'tell' => false], arguments: ['task' => $task->id]);
        $writer->refresh();
        $this->assertStringStartsWith('মাসের লেখক', $writer->honour_label);
        $this->assertSame('done', $task->fresh()->status);
        auth()->logout();
        $this->get('/')->assertSee('সম্মানিত লেখক')->assertSee('রিনার কবিতায় শহরের আলো।');
        $this->get($writer->url())->assertSee('মাসের লেখক')->assertSee('হাতপাখার সম্পাদকমণ্ডলী');
    }
}
