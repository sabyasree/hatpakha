<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\Serial;
use App\Models\User;
use App\Support\DeskInbox;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * D279 (owner: "a new serial proposal and a new episode: I just get a number beside the menu and can't tell which";
 * "as admin I should not spend time finding where things are"): one inbox, and numbers that say what they count.
 */
class DeskInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function scene(): array
    {
        Carbon::setTestNow('2026-11-25 10:00:00');   // a Wednesday
        $writer = User::factory()->create(['role' => Role::Writer, 'name' => 'অনিতা দাস']);
        $author = Author::create(['user_id' => $writer->id, 'slug' => 'anita', 'display_name' => 'অনিতা দাস']);
        $proposal = Serial::create(['title' => 'নদীর ওপারের বাড়ি', 'user_id' => $writer->id, 'status' => 'proposed', 'day' => 6, 'plan' => 'তিন প্রজন্মের গল্প, দশ পর্বে।', 'planned_count' => 10]);
        $first = $this->makePost(['slug' => 'nodi-1', 'title' => 'নদীর ওপারের বাড়ি (পর্ব ১)', 'status' => 'pending', 'submitted_by' => $writer->id, 'submitted_at' => '2026-11-25 04:00:00',
            'serial_id' => $proposal->id, 'episode_no' => 1], $author);
        $running = Serial::create(['title' => 'কুয়াশার শহর', 'user_id' => $writer->id, 'status' => 'active', 'slug' => 'kuasha', 'day' => 6]);
        $this->makePost(['slug' => 'kuasha-2', 'title' => 'কুয়াশার শহর (পর্ব ২)', 'status' => 'published', 'published_at' => '2026-11-21 03:30:00', 'serial_id' => $running->id, 'episode_no' => 2], $author);
        $ep = $this->makePost(['slug' => 'kuasha-3', 'title' => 'কুয়াশার শহর (পর্ব ৩)', 'status' => 'pending', 'submitted_by' => $writer->id, 'submitted_at' => '2026-11-24 04:00:00',
            'serial_id' => $running->id, 'episode_no' => 3], $author);
        $piece = $this->makePost(['slug' => 'brishti', 'title' => 'বৃষ্টির দুপুর', 'status' => 'pending', 'submitted_by' => $writer->id, 'submitted_at' => '2026-11-20 04:00:00'], $author);

        return compact('writer', 'proposal', 'first', 'running', 'ep', 'piece');
    }

    public function test_a_proposal_and_a_new_episode_are_each_named_and_counted(): void
    {
        $s = $this->scene();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $items = DeskInbox::items()->keyBy('key');

        $this->assertStringContainsString('নতুন ধারাবাহিকের প্রস্তাব: ‘নদীর ওপারের বাড়ি’', $items['s'.$s['proposal']->id]['title']);
        $this->assertStringEndsWith('/desk/posts/'.$s['first']->id.'/edit', $items['s'.$s['proposal']->id]['url'], 'The proposal opens its first episode, where both are decided');
        $this->assertArrayNotHasKey('p'.$s['first']->id, $items->all(), 'A proposal and its first episode are one line');
        $this->assertStringContainsString('নতুন পর্ব: ‘কুয়াশার শহর’ পর্ব ৩', $items['p'.$s['ep']->id]['title']);
        $this->assertTrue($items['p'.$s['ep']->id]['soon'], 'Saturday is within three days: today');
        $this->assertSame('today', $items['p'.$s['piece']->id]['group'], 'A piece waiting five days moves up to আজই');

        // the menu's numbers say what they count, and agree with the inbox
        $this->assertSame('২', \App\Filament\Resources\Serials\SerialResource::getNavigationBadge());
        $this->assertSame('১টি নতুন প্রস্তাব · ১টি নতুন পর্ব', \App\Filament\Resources\Serials\SerialResource::getNavigationBadgeTooltip());
        $this->assertSame('১টি নতুন লেখা · ১টি ধারাবাহিকের পর্ব · ১টি নতুন ধারাবাহিকের প্রথম পর্ব', DeskInbox::piecesTooltip());

        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Pages\Dashboard::class)->assertSee('নতুন ধারাবাহিকের প্রস্তাব')->assertSee('প্রস্তাব ও পর্ব ১ পড়ুন')->assertSee('পর্বটি দেখুন');
        Livewire::test(\App\Filament\Resources\Posts\Pages\ListPosts::class)->assertSee('ধারাবাহিক ‘কুয়াশার শহর’ · পর্ব ৩')->assertSee('নতুন ধারাবাহিকের প্রস্তাব ‘নদীর ওপারের বাড়ি’ · পর্ব ১');
        Livewire::test(\App\Filament\Resources\Serials\Pages\ListSerials::class)->assertSee('প্রস্তাব ও পর্ব ১ পড়ুন →')->assertSee('নতুন পর্ব ৩ দেখুন →');
        // the episode's page carries the writer's whole proposal
        Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $s['first']->getRouteKey()])
            ->assertSee('নতুন ধারাবাহিকের প্রস্তাব')->assertSee('তিন প্রজন্মের গল্প, দশ পর্বে।');
        Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $s['ep']->getRouteKey()])
            ->assertSee('ধারাবাহিক ‘কুয়াশার শহর’ · পর্ব ৩')->assertSee('এটির প্রকাশের দিন');
    }

    public function test_the_menu_is_in_five_groups_with_daily_work_first(): void
    {
        $this->assertSame('আপনার সিদ্ধান্ত', \App\Filament\Resources\Serials\SerialResource::getNavigationGroup());
        $this->assertSame('আপনার সিদ্ধান্ত', \App\Filament\Pages\AiImages::getNavigationGroup(), 'Pictures to approve were hidden under প্রশাসন');
        $this->assertSame('হিসাব ও খাতা', \App\Filament\Resources\SiteErrors\SiteErrorResource::getNavigationGroup());
        $this->assertSame('gray', \App\Filament\Resources\SiteErrors\SiteErrorResource::getNavigationBadgeColor(), 'Information is grey, not red');
    }

    public function test_an_empty_desk_says_so(): void
    {
        \App\Models\Newsletter::query()->delete();   // the seeded welcome letter and post
        \App\Models\SocialPost::query()->delete();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Pages\Dashboard::class)->assertSee('আজ কিছু বাকি নেই।');
    }

    /** UX audit: an AI job whose piece was published on its own page leaves; titles never say the same thing twice. */
    public function test_finished_jobs_leave_and_titles_are_not_doubled(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $serial = Serial::create(['title' => 'লাল কাচ', 'status' => 'active', 'slug' => 'lal-kach', 'day' => 6]);
        $ep = $this->makePost(['slug' => 'lal-4', 'title' => 'লাল কাচ (পর্ব ৪)', 'status' => 'published', 'serial_id' => $serial->id, 'episode_no' => 4, 'ai_drafted_at' => now()]);
        $done = \App\Models\EditorTask::propose('serial_episode', 'ধারাবাহিকের নতুন পর্ব: লাল কাচ (পর্ব ৪)', [], $ep);
        $tags = \App\Models\EditorTask::propose('tags', 'ট্যাগ গোছানো: ৫টি জোড়া');
        $lines = DeskInbox::items()->keyBy('key');
        $this->assertArrayNotHasKey('t'.$done->id, $lines->all());
        $this->assertSame('done', $done->fresh()->status, 'Published on its page: the job is closed');
        $this->assertSame('ট্যাগ গোছানো: ৫টি জোড়া', $lines['t'.$tags->id]['title']);
    }

    /** Every menu number and its tooltip can be worked out (a wrong class name once broke the whole desk). */
    public function test_every_menu_number_and_tooltip_runs(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        $n = 0;
        foreach (array_merge(Filament::getCurrentPanel()->getResources(), Filament::getCurrentPanel()->getPages()) as $class) {
            foreach (['getNavigationBadge', 'getNavigationBadgeTooltip', 'getNavigationBadgeColor'] as $m) {
                if (method_exists($class, $m)) {
                    $class::$m();
                    $n++;
                }
            }
        }
        $this->assertGreaterThan(40, $n);
    }

    /** D280: the magazine answers a reader under their comment, and a contact message by e-mail, from the desk. */
    public function test_replies_to_a_comment_and_a_message_from_the_desk(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $post = $this->makePost(['slug' => 'brishti', 'title' => 'বৃষ্টির দুপুর']);
        $c = \App\Models\Comment::create(['post_id' => $post->id, 'author_name' => 'মিতা', 'body' => 'খুব ভালো লাগল, পরের লেখা কবে?', 'status' => 'pending']);
        $r = \App\Filament\Resources\Comments\Tables\CommentsTable::reply($c, 'শিগগিরই, ধন্যবাদ!');
        $this->assertSame(['approved', 'approved', $c->id, 'হাতপাখা'], [$c->fresh()->status, $r->status, $r->parent_id, $r->author_name]);

        $m = \App\Models\ContactMessage::create(['kind' => 'contact', 'topic' => 'other', 'name' => 'সুমন', 'email' => 'suman@example.com', 'body' => 'বিজ্ঞাপন নিয়ে কথা বলতে চাই।', 'status' => 'new']);
        $this->assertSame('উত্তর লিখুন', DeskInbox::items()->firstWhere('key', 'm'.$m->id)['button']);
        \App\Filament\Resources\ContactMessages\ContactMessageResource::reply($m, 'নিশ্চয়ই, ফোনে কথা বলি।');
        $this->assertSame('replied', $m->fresh()->status);
        \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\ContactReply::class);
        $this->assertNull(DeskInbox::items()->firstWhere('key', 'm'.$m->id), 'Answered: the line leaves');
    }
}
