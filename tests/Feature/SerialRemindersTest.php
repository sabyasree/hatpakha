<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Serial;
use App\Models\User;
use App\Notifications\SubmissionDecision;
use App\Notifications\TemplateMail;
use App\Support\SerialReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** D268 (owner): a serial writer is reminded of the next episode, Saturday evening and Wednesday, when none is in. */
class SerialRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $time): int
    {
        Carbon::setTestNow(Carbon::parse($time, 'Asia/Kolkata'));

        return SerialReminders::send();
    }

    private function serial(User $writer): Serial
    {
        $s = Serial::create(['slug' => 'nodir-opare', 'title' => 'নদীর ওপারে', 'status' => 'active', 'day' => 6, 'user_id' => $writer->id, 'planned_count' => 8]);
        $this->makePost(['slug' => 'porbo-1', 'title' => 'পর্ব ১', 'status' => 'published', 'published_at' => '2026-11-28 03:30:00'])
            ->forceFill(['serial_id' => $s->id, 'episode_no' => 1])->save();

        return $s;
    }

    public function test_a_writer_is_reminded_twice_a_week_while_no_episode_is_in(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-28 15:00', 'Asia/Kolkata'));
        $writer = User::factory()->create(['email_verified_at' => now()]);
        $s = $this->serial($writer);
        Serial::create(['slug' => 'desk', 'title' => 'ডেস্কের', 'status' => 'active', 'day' => 6, 'writer' => 'mystery']);   // the desk's own: never

        $this->assertSame(0, $this->at('2026-11-28 15:00'), 'Saturday afternoon: not yet');
        $this->assertSame(1, $this->at('2026-11-28 18:30'), 'Saturday evening: the next one is due');
        $this->assertSame(0, $this->at('2026-11-29 10:00'), 'Once');
        Notification::assertSentTo($writer, TemplateMail::class, fn ($n) => $n->key === 'serial_next' && $n->values['{পর্ব}'] === '২'
            && str_contains($n->values['{তারিখ}'], '৫ ডিসেম্বর'));
        $this->assertSame(0, $this->at('2026-12-01 10:00'), 'Tuesday: nothing');
        $this->assertSame(1, $this->at('2026-12-02 11:00'), 'Wednesday: still none in');
        Notification::assertSentTo($writer, TemplateMail::class, fn ($n) => $n->key === 'serial_next_soon');

        // The episode comes in: no more reminders
        $this->makePost(['slug' => 'porbo-2', 'title' => 'পর্ব ২', 'status' => 'pending', 'published_at' => null])->forceFill(['serial_id' => $s->id, 'episode_no' => 2])->save();
        $this->assertSame(0, $this->at('2026-12-05 19:00'));
        Notification::assertSentToTimes($writer, TemplateMail::class, 2);
    }

    public function test_the_first_episode_approval_carries_the_weekly_rhythm(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-28 15:00', 'Asia/Kolkata'));
        $writer = User::factory()->create();
        $s = $this->serial($writer);
        $first = Post::where('slug', 'porbo-1')->first();
        $this->assertStringContainsString('প্রতি শনিবার প্রকাশ হবে', implode(' ', array_map('strval', (new SubmissionDecision($first, 'approved'))->toMail($writer)->outroLines)));
        $second = $this->makePost(['slug' => 'porbo-2', 'title' => 'পর্ব ২'])->forceFill(['serial_id' => $s->id, 'episode_no' => 2]);
        $second->save();
        $this->assertStringNotContainsString('প্রতি শনিবার প্রকাশ হবে', implode(' ', array_map('strval', (new SubmissionDecision($second, 'approved'))->toMail($writer)->outroLines)), 'Only with the first episode');
    }
}
