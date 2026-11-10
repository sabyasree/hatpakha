<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\EditorJobs;
use App\Enums\Role;
use App\Models\EditorTask;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** D48: the AI editor's other jobs only prepare; the owner sends or approves. */
class EditorJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        Filament::setCurrentPanel(Filament::getPanel('desk'));
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_follow_up_is_drafted_and_sent_only_by_the_owner(): void
    {
        Notification::fake();
        $writer = User::factory()->create(['name' => 'রিনা']);
        $post = $this->makePost(['slug' => 'ch', 'title' => 'নদী', 'status' => 'changes', 'published_at' => null]);
        $post->forceFill(['submitted_by' => $writer->id, 'reviewed_at' => now()->subDays(20), 'review_note' => 'শেষটা ভাবুন'])->save();
        Claude::$fake = fn () => ['message' => 'নমস্কার রিনা, "নদী" লেখাটির কথা মনে করিয়ে দিচ্ছি।'];

        $this->assertSame(1, EditorJobs::followups());
        $this->assertSame(0, EditorJobs::followups(), 'Not twice');
        Notification::assertNothingSent();

        $task = EditorTask::sole();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Livewire::test(\App\Filament\Pages\Dashboard::class)->assertSee('লেখককে মনে করানো')
            ->callAction('sendNote', ['message' => 'নমস্কার, একটু মনে করিয়ে দিচ্ছি।'], arguments: ['task' => $task->id]);
        Notification::assertSentTo($writer, \App\Notifications\WriterNote::class, fn ($n) => str_contains($n->message, 'মনে করিয়ে'));
        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_weekly_pick_goes_on_the_home_page_after_approval(): void
    {
        $pick = $this->makePost(['slug' => 'pick', 'title' => 'বাছাই করা কবিতা', 'published_at' => now()->subDays(3)]);
        Claude::$fake = fn () => ['pick_id' => $pick->id, 'note' => 'এ সপ্তাহে না পড়লেই নয়', 'on_this_day' => []];
        $task = EditorJobs::picks();
        $this->get('/')->assertDontSee('এ সপ্তাহে না পড়লেই নয়');

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Livewire::test(\App\Filament\Pages\Dashboard::class)->callAction('approvePick', ['note' => 'এ সপ্তাহে না পড়লেই নয়'], arguments: ['task' => $task->id]);
        $this->assertSame($pick->id, json_decode(Setting::get('home.pick'), true)['post_id']);
        $this->get('/')->assertSee('এ সপ্তাহে না পড়লেই নয়')->assertSee('বাছাই করা কবিতা');
    }

    public function test_monthly_report_and_site_health(): void
    {
        $month = now('Asia/Kolkata')->subMonthNoOverflow()->startOfMonth();
        $this->makePost(['slug' => 'last-month', 'published_at' => $month->copy()->addDays(3)->utc(),
            'body_html' => '<p>দেখুন <a href="/nei-emon-lekha/">এটা</a> আর <img src="/wp-content/uploads/missing.jpg"></p>']);
        $report = EditorJobs::report($month);
        $this->assertSame(1, $report->payload['published']);
        $health = EditorJobs::health();
        $this->assertCount(2, $health->payload['problems']);
    }

    public function test_first_publication_gets_a_welcome_note_to_send(): void
    {
        $writer = User::factory()->create();
        $post = $this->makePost(['slug' => 'draft-w', 'status' => 'pending', 'published_at' => null]);
        $post->forceFill(['submitted_by' => $writer->id])->save();
        \App\Support\Review::approve($post, User::factory()->create(['role' => Role::Admin]), now());
        $this->assertSame(1, EditorTask::where('type', 'welcome')->count());
    }
}
