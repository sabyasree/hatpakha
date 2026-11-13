<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Enums\Role;
use App\Models\Author;
use App\Models\Setting;
use App\Models\User;
use App\Models\WriterInvite;
use App\Notifications\WriterInviteLetter;
use App\Support\WriterInvites;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** D188: personal invitations to the regular writers for the ফিরে আসা সংখ্যা, written and sent by the team itself. */
class WriterInvitesTest extends TestCase
{
    use RefreshDatabase;

    private const LINE = 'মা বলতেন, যে চিঠি পাঠানো হয় না, সে চিঠিই সবচেয়ে বেশি দিন মনে থাকে।';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'mail.default' => 'smtp', 'mail.off_live' => 'allow']);
        Carbon::setTestNow(Carbon::parse('2026-11-16 11:20', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function writer(string $slug, int $pieces, ?float $quality = null, array $user = []): Author
    {
        $u = User::factory()->create(['role' => Role::Writer] + $user);
        $a = Author::create(['slug' => $slug, 'display_name' => $slug, 'display_name_bn' => 'অনির্বাণ দাস', 'user_id' => $u->id, 'quality_score' => $quality]);
        foreach (range(1, $pieces) as $i) {
            $this->makePost(['slug' => $slug.'-'.$i, 'title' => 'মায়ের চিঠি '.$i, 'body_html' => '<p>'.self::LINE.'</p><p>আর কিছু কথা।</p>', 'published_at' => now()->subYears(8)], $a);
        }
        $a->refreshCount();

        return $a->fresh();
    }

    private function letter(array $over = []): array
    {
        return $over + ['subject' => 'মায়ের চিঠির সেই লাইনটা', 'quoted_line' => self::LINE,
            'paragraphs' => ['“'.self::LINE.'” আপনার লেখার এই লাইনটা এখনও হাতপাখায় আছে।', 'মাঝের কয়েক বছর হাতপাখা প্রায় চুপ ছিল। সেজন্য আমরা দুঃখিত।', 'আবার শুরু হয়েছে। ফেরার পর প্রথম সংখ্যা ফিরে আসা সংখ্যা।', 'একটা লেখা পাঠাবেন? শেষ দিন ৭ ডিসেম্বর।']];
    }

    public function test_regular_writers_and_fine_writers_from_any_year_are_chosen_and_nobody_twice(): void
    {
        $regular = $this->writer('regular', 3);
        $fine = $this->writer('fine', 1, 8.5);          // one piece, eight years old, but rated high
        $this->writer('once', 1, 6.0);
        $this->writer('silent', 4, null, ['mail_opt_out' => true]);
        $this->assertEqualsCanonicalizing([$regular->id, $fine->id], WriterInvites::eligible()->pluck('id')->all());
        $this->assertSame($fine->id, WriterInvites::eligible()->first()->id, 'The best-rated first');

        Claude::$fake = fn () => $this->letter();
        $this->assertSame(2, WriterInvites::draft());
        $this->assertSame(0, WriterInvites::draft(), 'One letter a writer');
        $inv = WriterInvite::where('author_id', $regular->id)->firstOrFail();
        $this->assertSame('drafted', $inv->status);
        $this->assertStringContainsString(self::LINE, $inv->body);
        $this->assertTrue($inv->send_after->gt(now()->addHours(11)), 'It waits, so the owner can read or stop it');
    }

    public function test_a_letter_with_an_invented_line_a_dash_or_a_link_is_refused(): void
    {
        $a = $this->writer('regular', 3);
        foreach ([
            ['quoted_line' => 'এই লাইনটা লেখায় নেই।'],
            ['paragraphs' => ['“এই লম্বা লাইনটা লেখকের লেখায় কোথাও নেই কিন্তু উদ্ধৃতির ভেতরে আছে।”', 'দুই', 'তিন'], 'quoted_line' => ''],
            ['paragraphs' => ['আপনার লেখা — মনে আছে।', 'দুই', 'তিন'], 'quoted_line' => ''],
            ['paragraphs' => ['দেখুন https://example.com', 'দুই', 'তিন'], 'quoted_line' => ''],
            ['paragraphs' => ['একটাই অনুচ্ছেদ'], 'quoted_line' => ''],
        ] as $bad) {
            Claude::$fake = fn () => $this->letter($bad);
            $this->assertNull(WriterInvites::write($a), json_encode($bad, JSON_UNESCAPED_UNICODE));
        }
        Claude::$fake = fn () => $this->letter(['quoted_line' => '', 'paragraphs' => ['আপনার লেখাটা এখনও হাতপাখায় আছে।', 'দুই', 'তিন']]);
        $this->assertNotNull(WriterInvites::write($a), 'A letter that quotes nothing is fine');
    }

    public function test_letters_go_by_themselves_in_the_kolkata_day_after_the_wait_and_can_be_stopped(): void
    {
        Notification::fake();
        $a = $this->writer('regular', 3);
        $b = $this->writer('second', 3);
        Claude::$fake = fn () => $this->letter();
        WriterInvites::draft();
        $this->assertSame(0, WriterInvites::send(), 'Still inside the twelve hours');
        WriterInvite::where('author_id', $b->id)->update(['status' => 'held']);

        Carbon::setTestNow(Carbon::parse('2026-11-17 03:00', 'Asia/Kolkata'));
        $this->assertSame(0, WriterInvites::send(), 'Not at night');
        Carbon::setTestNow(Carbon::parse('2026-11-17 10:15', 'Asia/Kolkata'));
        $this->assertSame(1, WriterInvites::send());
        Notification::assertSentTo($a->user, WriterInviteLetter::class, fn ($n) => ! $n->reminder);
        Notification::assertNotSentTo($b->user, WriterInviteLetter::class);
        $this->assertSame('phire-asha', \App\Models\Issue::firstOrFail()->slug);
        $this->assertSame(0, WriterInvites::send(), 'Never twice');

        // The switch stops writing and sending.
        Setting::put(WriterInvites::AUTO, '0');
        WriterInvite::where('author_id', $b->id)->update(['status' => 'drafted']);
        $this->assertSame(0, WriterInvites::send());
        $this->assertSame(0, WriterInvites::draft());
    }

    public function test_the_letter_reads_as_a_letter_and_the_answer_is_kept_only_on_the_button(): void
    {
        $a = $this->writer('regular', 3);
        Setting::put('certificates.signer_name', 'সব্যসাচী মণ্ডল');
        $inv = WriterInvite::create(['author_id' => $a->id, 'user_id' => $a->user_id, 'subject' => 'মায়ের চিঠির সেই লাইনটা', 'body' => implode("\n\n", $this->letter()['paragraphs']), 'status' => 'sent', 'sent_at' => now()]);
        $html = (string) (new WriterInviteLetter($inv))->toMail($a->user)->render();
        $this->assertStringContainsString('প্রিয় অনির্বাণ দাস,', $html);
        $this->assertStringContainsString(e(self::LINE), $html);
        $this->assertStringContainsString('সব্যসাচী মণ্ডল', $html);
        $this->assertStringContainsString('সম্পাদক, হাতপাখা', $html);
        $this->assertStringContainsString('হ্যাঁ, লিখব', $html);

        $yes = WriterInvites::link($inv, 'yes');
        $this->get($yes)->assertOk()->assertSee('ফিরে আসা সংখ্যার জন্য একটা লেখা পাঠাবেন?');
        $this->assertNull($inv->fresh()->answer, 'Opening the link alone answers nothing (mail programs open links)');
        $this->post($yes)->assertRedirect();
        $this->assertSame('yes', $inv->fresh()->answer);
        $this->get($yes)->assertOk();
        $this->get(url('/amontron/'.$inv->id.'/yes'))->assertForbidden();   // an unsigned address is refused
    }

    public function test_one_reminder_after_nine_days_and_a_piece_sent_is_marked_for_the_issue(): void
    {
        Notification::fake();
        $quiet = $this->writer('quiet', 3);
        $answered = $this->writer('answered', 3);
        $wrote = $this->writer('wrote', 3);
        foreach ([$quiet, $answered, $wrote] as $a) {
            WriterInvite::create(['author_id' => $a->id, 'user_id' => $a->user_id, 'subject' => 'ক', 'body' => 'খ', 'status' => 'sent', 'sent_at' => now()]);
        }
        WriterInvite::where('author_id', $answered->id)->update(['answer' => 'later']);
        $piece = $this->makePost(['slug' => 'notun', 'title' => 'ফেরা', 'status' => 'pending', 'published_at' => null], $wrote);
        $piece->forceFill(['submitted_by' => $wrote->user_id, 'submitted_at' => now()->addDays(2)])->saveQuietly();

        Carbon::setTestNow(Carbon::parse('2026-11-18 10:15', 'Asia/Kolkata'));
        $this->assertSame(1, WriterInvites::match());
        $this->assertSame($piece->id, WriterInvite::where('author_id', $wrote->id)->first()->piece_id);
        $this->assertSame('phire-asha', $piece->fresh()->issue->slug ?? \App\Models\Issue::find($piece->fresh()->issue_id)->slug);
        $this->assertSame('pending', $piece->fresh()->status, 'Marked for the issue, not published');
        $this->assertSame(0, WriterInvites::remind(), 'Too early');

        Carbon::setTestNow(Carbon::parse('2026-11-26 11:15', 'Asia/Kolkata'));
        $this->assertSame(1, WriterInvites::remind());
        Notification::assertSentTo($quiet->user, WriterInviteLetter::class, fn ($n) => $n->reminder);
        Notification::assertNotSentTo($answered->user, WriterInviteLetter::class);
        Notification::assertNotSentTo($wrote->user, WriterInviteLetter::class);
        $this->assertSame(0, WriterInvites::remind(), 'Only once');
    }

    public function test_the_desk_page_shows_letters_and_lets_the_owner_change_or_stop_one(): void
    {
        $a = $this->writer('regular', 3);
        $inv = WriterInvite::create(['author_id' => $a->id, 'user_id' => $a->user_id, 'subject' => 'মায়ের চিঠির সেই লাইনটা', 'body' => 'চিঠির লেখা', 'status' => 'drafted', 'send_after' => now()->addHours(12)]);
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\WriterInvites\Pages\ListWriterInvites::class)
            ->assertSee('অনির্বাণ দাস')->assertSee('মায়ের চিঠির সেই লাইনটা')->assertSee('পাঠানোর অপেক্ষায়')
            ->callTableAction('read', $inv, ['subject' => 'নতুন বিষয়', 'body' => 'বদলানো চিঠি'])
            ->callTableAction('hold', $inv);
        $this->assertSame(['নতুন বিষয়', 'বদলানো চিঠি', 'held'], [$inv->fresh()->subject, $inv->fresh()->body, $inv->fresh()->status]);
    }
}
