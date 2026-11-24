<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\Certificate;
use App\Models\Contest;
use App\Models\Issue;
use App\Models\Page;
use App\Models\Post;
use App\Models\ReaderPrompt;
use App\Models\SentLetter;
use App\Models\User;
use App\Notifications\SubmissionDecision;
use App\Support\Bn;
use App\Support\Calls;
use App\Support\Debut;
use App\Support\Review;
use App\Support\WriterJourney;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Writer features approved by the owner on 2026-11-24 (docs/RESEARCH-2026-11.md §B). */
class WriterJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function writer(string $name = 'মেঘলা দাস'): array
    {
        $user = User::factory()->create(['name' => $name, 'role' => Role::Writer, 'email_verified_at' => now()]);
        $author = Author::create(['slug' => 'writer-'.$user->id, 'display_name' => $name, 'user_id' => $user->id]);

        return [$user, $author];
    }

    private function sent(Author $author, User $user, array $attrs = []): Post
    {
        $post = $this->makePost(array_merge(['status' => 'pending', 'published_at' => null, 'submitted_at' => now()->subDays(3), 'title' => 'নদীর গান'], $attrs), $author);
        $post->forceFill(['submitted_by' => $user->id])->save();

        return $post->fresh();
    }

    // 1. Reply time: "২–৩ সপ্তাহ" everywhere
    public function test_the_reply_time_says_two_to_three_weeks_everywhere(): void
    {
        $this->assertSame('সাধারণত ২–৩ সপ্তাহের মধ্যে', Review::REPLY_TEXT);
        $this->assertSame([14, 21, 14], [Review::REPLY_DAYS_MIN, Review::REPLY_DAYS_MAX, Review::REPLY_WARN_DAYS]);
        [$user] = $this->writer();
        $this->actingAs($user)->get('/submit/')->assertOk()->assertSee('২–৩ সপ্তাহের মধ্যে')->assertDontSee('১৪ দিনের মধ্যে');

        foreach (['submissions', 'faqs'] as $slug) {
            $page = \App\Console\Commands\ImportWordPress::revisedPage($slug);
            $this->assertStringContainsString('২–৩ সপ্তাহের মধ্যে', $page['body'], $slug);
        }
        $this->assertStringContainsString('২–৩ সপ্তাহের মধ্যে', \App\Support\MailTemplates::text('submission_received')['body']);
        $this->assertStringNotContainsString('within a week', (string) file_get_contents(base_path('docs/STRATEGY.md')));

        // The desk is warned after 14 days (not 10): a piece sent 12 days ago is not late yet
        $this->sent(Author::first(), $user, ['submitted_at' => now()->subDays(12)]);
        \App\Support\Handover::replyDue();
        $this->assertFalse(\App\Models\EditorTask::where('key', 'handover.reply_due')->where('status', 'open')->exists());
        $this->sent(Author::first(), $user, ['submitted_at' => now()->subDays(15)]);
        \App\Support\Handover::replyDue();
        $this->assertStringContainsString('২–৩ সপ্তাহের মধ্যে', (string) \App\Models\EditorTask::where('key', 'handover.reply_due')->where('status', 'open')->value('title'));
    }

    public function test_the_migration_refreshes_the_rules_pages_unless_edited_in_the_desk(): void
    {
        Page::create(['slug' => 'submissions', 'title' => 'লেখা পাঠানোর নিয়ম', 'body_html' => '<p>পুরনো</p>', 'status' => 'published']);
        Page::create(['slug' => 'faqs', 'title' => 'প্রশ্নোত্তর', 'body_html' => '<p>নিজের লেখা</p>', 'status' => 'published', 'edited_in_desk_at' => now()]);
        (require database_path('migrations/2026_11_24_000300_refresh_reply_time_pages.php'))->up();
        $this->assertStringContainsString('২–৩ সপ্তাহের মধ্যে', Page::where('slug', 'submissions')->value('body_html'));
        $this->assertSame('<p>নিজের লেখা</p>', Page::where('slug', 'faqs')->value('body_html'));
    }

    // 2. The editor's letter on a declined piece, with "what next"
    public function test_a_declined_piece_shows_the_letter_as_sent_and_what_next(): void
    {
        [$user, $author] = $this->writer();
        $post = $this->sent($author, $user);
        $post->forceFill(['status' => 'rejected', 'review_note' => 'ভাবনাটি সুন্দর, কিন্তু শেষটা তাড়াহুড়োয় লেখা মনে হল।', 'reviewed_at' => now()])->save();
        $html = (string) (new SubmissionDecision($post, 'rejected'))->toMail($user)->render();
        SentLetter::create(['kind' => 'decision_declined', 'to_email' => $user->email, 'user_id' => $user->id, 'post_id' => $post->id, 'subject' => 'হাতপাখা: “নদীর গান” সম্পর্কে', 'html' => $html, 'sent_at' => now()]);
        ReaderPrompt::create(['slug' => 'bristi', 'kind' => 'six', 'title' => 'প্রথম বৃষ্টি', 'ask' => 'ছয় শব্দে লিখুন।', 'status' => 'open', 'closes_at' => now()->addDays(4)]);

        $letter = WriterJourney::declineLetter($post);
        $this->assertNotNull($letter);
        $this->assertTrue(collect($letter['paras'])->contains(fn ($p) => $p['kind'] === 'note' && str_contains($p['text'], 'শেষটা তাড়াহুড়োয়')));
        $this->assertFalse(collect($letter['paras'])->contains(fn ($p) => str_contains($p['text'], 'ISSN')), 'the footer is left out');

        $this->actingAs($user)->get('/submit/'.$post->id.'/')->assertOk()
            ->assertSee('সম্পাদকের চিঠি')->assertSee('শেষটা তাড়াহুড়োয় লেখা মনে হল')->assertSee(Bn::date(now()))
            ->assertSee('এরপর কী করবেন')->assertSee(Bn::date(now()->addDays(30)))
            ->assertSee('প্রথম বৃষ্টি')->assertSee('/lekhar-dak/', false)->assertSee('/kolom/', false);
        // A month later the button to send a new piece is there
        Carbon::setTestNow(now()->addDays(31));
        $this->get('/submit/'.$post->id.'/')->assertSee('এখন নতুন লেখা হিসেবে পাঠাতে পারেন');
    }

    public function test_without_a_kept_copy_the_editors_note_is_shown_and_others_never_see_it(): void
    {
        [$user, $author] = $this->writer();
        $post = $this->sent($author, $user);
        $post->forceFill(['status' => 'rejected', 'review_note' => 'এবার নয়, আবার লিখবেন।', 'reviewed_at' => now()])->save();
        // A letter kept for someone else is never shown here
        SentLetter::create(['kind' => 'decision_declined', 'to_email' => 'other@example.com', 'user_id' => null, 'post_id' => $post->id, 'subject' => 's', 'html' => '<td class="content-cell"><p>গোপন</p></td>', 'sent_at' => now()]);

        $this->actingAs($user)->get('/submit/'.$post->id.'/')->assertOk()->assertSee('এবার নয়, আবার লিখবেন।')->assertDontSee('গোপন');
        [$other] = $this->writer('অন্য লেখক');
        $this->actingAs($other)->get('/submit/'.$post->id.'/')->assertNotFound();
    }

    // 3. The public reply-time figure
    public function test_the_reply_time_figure_needs_five_decisions_and_uses_the_median(): void
    {
        [$user, $author] = $this->writer();
        Page::create(['slug' => 'submissions', 'title' => 'লেখা পাঠানোর নিয়ম', 'body_html' => '<p>নিয়ম</p>', 'status' => 'published']);
        foreach ([2, 4, 6, 8] as $d) {
            $this->sent($author, $user, ['status' => 'rejected', 'submitted_at' => now()->subDays(20), 'reviewed_at' => now()->subDays(20 - $d)]);
        }
        $this->sent($author, $user, ['status' => 'rejected', 'submitted_at' => now()->subDays(200), 'reviewed_at' => now()->subDays(100)]);   // outside 90 days
        $this->assertNull(WriterJourney::replyDays());
        $this->get('/submissions/')->assertOk()->assertSee('২–৩ সপ্তাহের মধ্যে')->assertDontSee('গড়ে');

        $this->sent($author, $user, ['status' => 'published', 'published_at' => now()->subDay(), 'submitted_at' => now()->subDays(30), 'reviewed_at' => now()->subDays(20)]);   // 10 days
        WriterJourney::forget();
        $this->assertSame(6, WriterJourney::replyDays());
        $this->get('/submissions/')->assertSee('গত ৯০ দিনে লেখকেরা গড়ে ৬ দিনে উত্তর পেয়েছেন');
        $this->actingAs($user)->get('/submit/')->assertSee('গড়ে ৬ দিনে');
        // Cached as a number: a new decision shows after the cache time
        $this->sent($author, $user, ['status' => 'rejected', 'submitted_at' => now()->subDays(40), 'reviewed_at' => now()->subDays(10)]);
        $this->sent($author, $user, ['status' => 'rejected', 'submitted_at' => now()->subDays(40), 'reviewed_at' => now()->subDays(10)]);
        $this->assertSame(6, WriterJourney::replyDays());
        WriterJourney::forget();
        $this->assertSame(8, WriterJourney::replyDays());
    }

    // 4. A real "the editor opened it" step
    public function test_the_tracker_says_the_editor_is_reading_only_after_an_editor_opened_it(): void
    {
        [$user, $author] = $this->writer();
        $post = $this->sent($author, $user);
        $this->actingAs($user)->get('/account/')->assertOk()->assertSee('সম্পাদকের কাছে পৌঁছেছে')->assertDontSee('সম্পাদক পড়ছেন')->assertSee(Bn::date(now()->subDays(3)));
        $this->get('/submit/'.$post->id.'/')->assertSee('সম্পাদকের কাছে পৌঁছেছে');

        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Carbon::setTestNow(now()->addDay());
        $editor = User::factory()->create(['role' => Role::Editor]);
        $this->actingAs($editor);
        Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->getRouteKey()])->assertOk();
        $opened = $post->fresh()->desk_opened_at;
        $this->assertNotNull($opened);
        // Set once: opening it again later does not move the date
        Carbon::setTestNow(now()->addDays(2));
        Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->getRouteKey()])->assertOk();
        $this->assertTrue($opened->eq($post->fresh()->desk_opened_at));

        $this->actingAs($user)->get('/account/')->assertSee('সম্পাদক পড়ছেন')->assertSee(Bn::date($opened))->assertDontSee('সম্পাদকের কাছে পৌঁছেছে');
    }

    public function test_the_opened_step_is_never_set_by_the_writer_or_for_pieces_not_waiting(): void
    {
        $editorWriter = User::factory()->create(['role' => Role::Editor]);
        $author = Author::create(['slug' => 'ed-writer', 'display_name' => 'Ed', 'user_id' => $editorWriter->id]);
        $own = $this->sent($author, $editorWriter);
        WriterJourney::markOpened($own, $editorWriter);
        $this->assertNull($own->fresh()->desk_opened_at, 'not by the writer themself');

        [$user, $a2] = $this->writer();
        $draft = $this->sent($a2, $user, ['status' => 'draft']);
        WriterJourney::markOpened($draft, $editorWriter);
        $this->assertNull($draft->fresh()->desk_opened_at, 'only for a sent piece');
        $desk = $this->makePost(['status' => 'pending', 'published_at' => null]);
        WriterJourney::markOpened($desk, $editorWriter);
        $this->assertNull($desk->fresh()->desk_opened_at, 'not for the desk\'s own pieces');
        WriterJourney::markOpened($this->sent($a2, $user), $user);
        $this->assertSame(0, Post::whereNotNull('desk_opened_at')->count(), 'a writer is not an editor');
    }

    // 5. লেখার ডাক
    public function test_the_calls_page_shows_only_real_calls(): void
    {
        Issue::create(['slug' => '2027-01', 'title' => 'বইমেলা সংখ্যা', 'status' => 'planning', 'published_at' => '2027-01-25 03:30:00']);
        Issue::create(['slug' => '2027-02', 'title' => 'এআই-এর প্রস্তাব সংখ্যা', 'status' => 'proposed', 'published_at' => '2027-02-01 03:30:00']);
        Issue::create(['slug' => 'phire-asha', 'title' => 'ফিরে আসা সংখ্যা', 'kind' => 'special', 'status' => 'planning']);
        Issue::create(['slug' => '2026-10', 'title' => 'পুরনো সংখ্যা', 'status' => 'published', 'published_at' => '2026-10-01 03:30:00']);
        ReaderPrompt::create(['slug' => 'chithi', 'kind' => 'letter', 'title' => 'মাকে চিঠি', 'ask' => 'না-পাঠানো চিঠি লিখুন।', 'status' => 'open', 'closes_at' => now()->addDays(4)]);
        Contest::create(['title' => 'শীতের গল্প প্রতিযোগিতা', 'theme' => 'শীত', 'status' => 'open', 'ends_at' => now()->addDays(20)]);
        Contest::create(['title' => 'খসড়া প্রতিযোগিতা', 'status' => 'draft']);

        $page = $this->get('/lekhar-dak/')->assertOk()
            ->assertSee('<link rel="canonical" href="'.\App\Support\Url::to('/lekhar-dak').'">', false)
            ->assertSee('লেখার ডাক')->assertSee('বইমেলা সংখ্যা')->assertSee(Bn::date(Carbon::parse('2027-01-25 03:30:00')))
            ->assertDontSee('এআই-এর প্রস্তাব সংখ্যা')->assertDontSee('পুরনো সংখ্যা')
            ->assertSee('ফিরে আসা সংখ্যা')->assertSee('লেখা পাঠানোর শেষ দিন: '.Bn::date(Carbon::parse(\App\Support\WriterInvites::LAST_DATE, 'Asia/Kolkata')))
            ->assertSee('মাকে চিঠি')->assertSee('শীতের গল্প প্রতিযোগিতা')->assertDontSee('খসড়া প্রতিযোগিতা')
            ->assertDontSee('data-wishlist', false);
        $this->assertStringNotContainsString('noindex', (string) $page->headers->get('X-Robots-Tag'));
        // Only the ফিরে আসা সংখ্যা has a real last date; no deadline is invented for the others
        $this->assertSame(1, substr_count($page->getContent(), 'লেখা পাঠানোর শেষ দিন'));

        // After its last date the ফিরে আসা call is gone
        Carbon::setTestNow('2026-12-10 10:00:00');
        $this->get('/lekhar-dak/')->assertDontSee('ফিরে আসা সংখ্যা');
    }

    public function test_the_wish_list_is_written_in_the_desk_and_the_page_is_linked_and_in_the_sitemap(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        $this->actingAs($admin);
        Livewire::test(\App\Filament\Resources\Contests\Pages\ListContests::class)->assertOk()
            ->callAction('wishlist', ['items' => "- পাহাড়ের ভ্রমণকথা\n\n<b>শীতের কবিতা</b>\n"]);
        $this->assertSame(['পাহাড়ের ভ্রমণকথা', 'শীতের কবিতা'], Calls::wishlist());
        auth()->logout();
        $this->get('/lekhar-dak/')->assertSee('সম্পাদক যা খুঁজছেন')->assertSee('পাহাড়ের ভ্রমণকথা')->assertDontSee('<b>শীতের', false);

        $this->get('/sitemap-pages.xml')->assertOk()->assertSee(\App\Support\Url::to('/lekhar-dak'), false);
        Page::create(['slug' => 'submissions', 'title' => 'লেখা পাঠানোর নিয়ম', 'body_html' => '<p>নিয়ম</p>', 'status' => 'published']);
        $this->get('/submissions/')->assertSee('/lekhar-dak/', false);
        [$user] = $this->writer();
        $this->actingAs($user)->get('/submit/')->assertSee('/lekhar-dak/', false);
        $this->sent(Author::where('user_id', $user->id)->first(), $user);   // a writer: the account home
        $this->get('/account/')->assertOk()->assertSee('/lekhar-dak/', false);
    }

    // 6. নতুন কলম
    public function test_a_writers_first_piece_is_labelled_and_shown_on_the_home_page(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        [$user, $new] = $this->writer('নতুন কবি');
        $first = $this->makePost(['title' => 'প্রথম কবিতা', 'published_at' => now()->subDays(3)], $new);
        [$u2, $old] = $this->writer('পুরনো কবি');
        $this->makePost(['title' => 'আগের লেখা', 'published_at' => now()->subYears(2)], $old);
        $second = $this->makePost(['title' => 'দ্বিতীয় লেখা', 'published_at' => now()->subDays(2)], $old);
        [$u3, $longAgo] = $this->writer('আগের কবি');
        $this->makePost(['title' => 'দুমাস আগের প্রথম লেখা', 'published_at' => now()->subDays(45)], $longAgo);
        $desk = Author::create(['slug' => 'hatpakha-desk', 'display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]);
        $this->makePost(['title' => 'ডেস্কের লেখা', 'published_at' => now()->subDay()], $desk);

        $this->assertSame([$first->id], Debut::ids());
        $this->get($first->url())->assertOk()->assertSee('data-debut', false)->assertSee('নতুন কলম');
        $this->get($second->url())->assertOk()->assertDontSee('data-debut', false);
        $this->get('/')->assertOk()->assertSee('নতুন কলম: প্রথম লেখা')->assertSee('প্রথম কবিতা');
        \Illuminate\Support\Facades\Notification::assertNothingSent();   // a label and a strip only: no new automatic message

        // Desk list: a submission from a writer with nothing published yet
        [$u4, $fresh] = $this->writer('একদম নতুন');
        $this->assertTrue(Debut::firstFromWriter($this->sent($fresh, $u4)));
        $this->assertFalse(Debut::firstFromWriter($this->sent($old, $u2)));
    }

    public function test_the_home_strip_is_hidden_without_first_pieces(): void
    {
        [, $old] = $this->writer();
        $this->makePost(['published_at' => now()->subYears(2)], $old);
        $this->makePost(['published_at' => now()->subDay()], $old);
        Debut::forget();
        $this->get('/')->assertOk()->assertDontSee('data-debut-strip', false);
    }

    // 7. Portfolio
    public function test_a_writer_pins_up_to_three_of_their_own_pieces(): void
    {
        [$user, $author] = $this->writer();
        $a = $this->makePost(['title' => 'প্রথম পছন্দ', 'published_at' => now()->subDays(30)], $author);
        $b = $this->makePost(['title' => 'দ্বিতীয় পছন্দ', 'published_at' => now()->subDays(20)], $author);
        $c = $this->makePost(['title' => 'নতুন লেখা', 'published_at' => now()->subDays(1)], $author);
        $draft = $this->makePost(['title' => 'খসড়া', 'status' => 'draft', 'published_at' => null], $author);
        $foreign = $this->makePost(['title' => 'অন্যের লেখা']);
        $author->forceFill(['published_count' => 3])->save();

        $this->actingAs($user)->get('/account/profile/')->assertOk()->assertSee('নির্বাচিত লেখা')->assertSee('প্রথম পছন্দ')->assertDontSee('অন্যের লেখা');
        $this->post('/account/pins/', ['pins' => [$a->id, $b->id, $c->id, $foreign->id]])->assertSessionHasErrors('pins');
        $this->post('/account/pins/', ['pins' => [$b->id, $foreign->id, $draft->id, $a->id]])->assertSessionHasErrors('pins');
        $this->post('/account/pins/', ['pins' => [$b->id, $foreign->id, $a->id]])->assertRedirect();
        $this->assertSame([$b->id, $a->id], $author->fresh()->pinned_post_ids);

        $html = $this->get($author->url())->assertOk()->assertSee('নির্বাচিত লেখা')->assertSee('/lekhok-porichiti/'.$author->slug.'/', false)->getContent();
        $pinned = substr($html, strpos($html, 'data-pinned'));
        $this->assertLessThan(strpos($pinned, 'প্রথম পছন্দ'), strpos($pinned, 'দ্বিতীয় পছন্দ'), 'in the order chosen');
        $this->assertStringNotContainsString('নতুন লেখা', substr($pinned, 0, strpos($pinned, '</section>')));
    }

    public function test_the_printable_writer_profile(): void
    {
        [$user, $author] = $this->writer();
        $author->forceFill(['bio' => 'কলকাতার কবি।', 'honour_label' => 'মাসের লেখক', 'honoured_at' => now()->subMonth()])->save();
        $post = $this->makePost(['title' => 'শীতের সকাল', 'published_at' => now()->subDays(10)], $author);
        $cert = Certificate::create(['author_id' => $author->id, 'post_id' => $post->id, 'kind' => 'publication', 'title_snapshot' => 'শীতের সকাল', 'author_name_snapshot' => 'মেঘলা দাস']);

        $this->get('/lekhok-porichiti/'.$author->slug.'/')->assertOk()
            ->assertSee('<meta name="robots" content="noindex', false)
            ->assertSee('লেখক-পরিচিতি')->assertSee('মেঘলা দাস')->assertSee('কলকাতার কবি।')->assertSee('শীতের সকাল')->assertSee(Bn::date($post->published_at))
            ->assertSee('ISSN 2321-3035')->assertSee($cert->verifyUrl(), false)->assertSee('মাসের লেখক')
            ->assertSee('<svg', false)->assertSee('PDF হিসেবে রাখুন')->assertSee('@page { size: A4', false)->assertSee($author->url(), false);
        $this->actingAs($user)->get('/account/')->assertSee('/lekhok-porichiti/'.$author->slug.'/', false);

        $desk = Author::create(['slug' => 'hatpakha-desk', 'display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]);
        $this->makePost([], $desk);
        $this->get('/lekhok-porichiti/hatpakha-desk/')->assertNotFound();
        [, $empty] = $this->writer('খালি');
        $this->get('/lekhok-porichiti/'.$empty->slug.'/')->assertNotFound();
        $this->get('/lekhok-porichiti/nobody/')->assertNotFound();
    }
}
