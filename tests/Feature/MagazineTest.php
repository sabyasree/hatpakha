<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\Issue;
use App\Models\User;
use App\Support\IssueEbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Owner (2026-11-12): the issue as a real magazine (full screen, pages that turn), and its e-book approved before readers get it. */
class MagazineTest extends TestCase
{
    use RefreshDatabase;

    private function issue(): Issue
    {
        $issue = Issue::create(['slug' => '2026-11', 'title' => 'অগ্রহায়ণ সংখ্যা', 'status' => 'published', 'published_at' => now(), 'editor_note' => 'শীতের প্রথম হাওয়া।']);
        $a = Author::create(['slug' => 'mou', 'display_name' => 'মৌ', 'bio' => 'কবিতা লেখেন।']);
        $this->makePost(['status' => 'published', 'slug' => 'nodi', 'title' => 'নদীর ধারে', 'issue_id' => $issue->id, 'published_at' => now()->subDay()], $a);
        $this->makePost(['status' => 'published', 'slug' => 'kuasha', 'title' => 'কুয়াশা', 'issue_id' => $issue->id, 'published_at' => now()->subHour()], $a);

        return $issue;
    }

    public function test_the_magazine_has_cover_contents_sections_writers_and_back_page(): void
    {
        $this->issue();
        $html = $this->get('/sonkha/2026-11/boi/')->assertOk()->getContent();
        foreach (['data-kind="cover"', 'data-kind="inside"', 'data-kind="contents"', 'data-kind="opener"', 'data-kind="writers"', 'data-kind="back"', 'hp-magazine.js', 'data-mag-fullscreen', 'noindex'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
        $this->assertStringContainsString('data-goto="piece-', $html, 'Contents link to the pieces');
        $this->assertStringContainsString('পাতা সাজানো হচ্ছে', $html, 'Something to read while a long issue is laid out');
        $js = file_get_contents(public_path('js/hp-magazine.js'));
        $this->assertStringContainsString("classList.toggle('far'", $js, 'Only the leaves near the open page are drawn (phones crashed on long issues)');
        $this->assertStringContainsString('কবিতা লেখেন।', $html, 'The issue\'s writers with their own bios');
        $this->get('/sonkha/2026-11/')->assertOk()->assertSee('পত্রিকা খুলুন')->assertDontSee('ই-বুক নামান');
    }

    private function editorial(Issue $issue, string $status = 'published'): \App\Models\Post
    {
        $section = \App\Models\Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'format' => 'prose']);
        $post = $this->makePost(['status' => $status, 'slug' => 'sompadokiyo', 'title' => 'শীতের মুখে', 'section_id' => $section->id, 'format' => 'prose',
            'issue_id' => $issue->id, 'published_at' => now()->subMinutes(5)]);
        $issue->forceFill(['editorial_post_id' => $post->id])->save();

        return $post;
    }

    public function test_the_editorial_is_its_own_section_even_without_a_link(): void
    {
        $issue = $this->issue();
        // Made by hand or imported from WordPress: the editorial is a piece of the issue in the সম্পাদকীয় section, not linked.
        $section = \App\Models\Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'format' => 'prose']);
        $ed = $this->makePost(['status' => 'published', 'slug' => 'ed', 'title' => 'হেমন্তের কথা', 'section_id' => $section->id, 'format' => 'prose',
            'issue_id' => $issue->id, 'published_at' => now()->subDays(2)]);
        $this->assertSame($ed->id, $issue->liveEditorial()?->id);

        $html = $this->get('/sonkha/2026-11/boi/')->assertOk()->getContent();
        $this->assertStringContainsString('<h2>সম্পাদকীয়</h2>', $html, 'An opener page of its own');
        $this->assertStringContainsString('<li><h3>সম্পাদকীয়</h3></li>', $html, 'Its own heading in the contents');
        $this->assertSame(1, substr_count($html, 'data-anchor="piece-'.$ed->id.'"'), 'Shown once');
        $this->assertLessThan(strpos($html, 'নদীর ধারে</h2>'), strpos($html, 'হেমন্তের কথা</h2>'), 'Before the other pieces');
        $this->get('/sonkha/2026-11/')->assertSeeInOrder(['সম্পাদকীয়', 'হেমন্তের কথা', 'এই সংখ্যায়', 'সম্পাদকীয়', 'হেমন্তের কথা']);
        $this->assertSame('live', \App\Ai\IssueWatch::status($issue)['editorial']);
        $this->assertSame($ed->id, \App\Support\IssueBook::pieces($issue)->first()->id, 'First in the e-book too');
    }

    public function test_the_ebook_has_the_editorial_and_a_cover_and_stays_with_the_owner(): void
    {
        Storage::fake('local');
        $issue = $this->issue();
        $this->assertSame(0, IssueEbook::buildDue(), 'No editorial yet: no e-book');
        $editorial = $this->editorial($issue, 'draft');
        $this->assertSame(0, IssueEbook::buildDue(), 'Editorial still a draft: no e-book');
        $editorial->update(['status' => 'published']);
        $this->assertSame(1, IssueEbook::buildDue());
        $this->assertSame(0, IssueEbook::buildDue(), 'Nothing changed: not built again');
        $issue->refresh();
        Storage::disk('local')->assertExists($issue->ebook_path);

        // Readers read on the site only.
        $this->get('/sonkha/2026-11/ebook.epub')->assertNotFound();
        $this->get('/sonkha/2026-11/')->assertDontSee('ই-বুক নামান')->assertDontSee('ebook.epub');

        $zip = new \ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'e');
        file_put_contents($tmp, Storage::disk('local')->get($issue->ebook_path));
        $zip->open($tmp);
        foreach (['OEBPS/writers.xhtml', 'OEBPS/back.xhtml', 'OEBPS/cover.xhtml', 'OEBPS/images/cover.svg'] as $f) {
            $this->assertNotFalse($zip->locateName($f), $f);
        }
        $cover = $zip->getFromName('OEBPS/images/cover.svg');
        $this->assertStringContainsString('অগ্রহায়ণ সংখ্যা', $cover);
        $this->assertStringContainsString('হাতপাখা', $cover);
        $this->assertStringContainsString('properties="cover-image"', $zip->getFromName('OEBPS/content.opf'));
        $this->assertNotFalse(simplexml_load_string($cover), 'The cover is valid XML');
        $this->assertTrue(collect(range(0, $zip->numFiles - 1))->contains(fn ($k) => str_contains((string) $zip->getFromIndex($k), 'শীতের মুখে')), 'The editorial is inside');
        $zip->close();
        @unlink($tmp);

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\Ebooks::class)->assertSee('শীতের মুখে')->assertSee('ই-বুক (শুধু আপনার জন্য)')->assertDontSee('অনুমোদন দিন');
    }

    public function test_the_month_never_misses_its_editorial_or_cover(): void
    {
        Storage::fake('local');
        \Illuminate\Support\Facades\Cache::flush();
        $this->travelTo(now('Asia/Kolkata')->startOfMonth()->addDays(24)->setTime(9, 0));   // the 25th
        $owner = User::factory()->create(['role' => Role::Admin]);
        \App\Models\Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'format' => 'prose']);
        $next = Issue::create(['slug' => now('Asia/Kolkata')->addMonthNoOverflow()->format('Y-m'), 'title' => 'পরের সংখ্যা', 'status' => 'proposed']);

        // AI off: nothing can be made, but the owner sees what is missing.
        $this->assertSame(['সম্পাদকীয় এখনও লেখা হয়নি', 'প্রচ্ছদ এখনও আঁকা হয়নি'], \App\Ai\IssueWatch::needs($next));
        $this->assertSame(0, \App\Ai\IssueWatch::run()['editorials']);

        // AI on: the editorial draft is written (once a day at most), and the owner is reminded on the 25th.
        \Illuminate\Support\Facades\Cache::flush();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        \App\Ai\Claude::$fake = fn ($purpose, $user, $schema) => ['title' => 'সম্পাদকীয়', 'paragraphs' => ['প্রিয় পাঠক,']];
        $done = \App\Ai\IssueWatch::run();
        $this->assertSame(1, $done['editorials']);
        $this->assertSame(1, $done['reminders']);
        $this->assertSame('draft', \App\Ai\IssueWatch::status($next->fresh())['editorial']);
        $this->assertSame(0, \App\Ai\IssueWatch::run()['reminders'], 'One reminder a day');
        \App\Ai\Claude::$fake = null;

        $this->actingAs($owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $this->assertSame('১', \App\Filament\Pages\Ebooks::getNavigationBadge());
        \Livewire\Livewire::test(\App\Filament\Pages\Ebooks::class)->assertSee('পরের সংখ্যা')->assertSee('খসড়া খুলুন')->assertSee('এখনই আঁকান');
        $this->assertContains('মাসের পত্রিকায় কাজ বাকি (প্রচ্ছদ বা সম্পাদকীয়)', collect(\App\Support\HealthReport::build()['todo'])->pluck('label')->all());

        // The designer's proposed cover becomes the cover when the owner picks it.
        $next->forceFill(['proposed_cover_path' => '/media/covers/x.jpg'])->save();
        \Livewire\Livewire::test(\App\Filament\Pages\Ebooks::class)->assertSee('এই প্রচ্ছদ নিন')->call('useCover', $next->id);
        $this->assertSame('/media/covers/x.jpg', $next->fresh()->cover_path);
        $this->assertSame('set', \App\Ai\IssueWatch::status($next->fresh())['cover']);
    }

    public function test_the_desk_has_an_editorial_section(): void
    {
        $issue = $this->issue();
        \App\Models\Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'format' => 'prose']);
        $admin = User::factory()->create(['role' => Role::Admin, 'name' => 'সম্পাদক']);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));

        $this->assertTrue(\App\Filament\Pages\Editorials::canAccess());
        $this->assertSame('১', \App\Filament\Pages\Editorials::getNavigationBadge(), 'This month\'s issue has no editorial yet');
        \Livewire\Livewire::test(\App\Filament\Pages\Editorials::class)->assertSee('অগ্রহায়ণ সংখ্যা')->assertSee('এখনও লেখা হয়নি')
            ->assertSee('নিজে লিখুন')->call('write', $issue->id)->assertRedirect();
        $post = $issue->fresh()->editorialPost();
        $this->assertSame('draft', $post->status);
        $this->assertSame($issue->id, $post->issue_id);
        $this->assertSame('editorial', $post->section->slug);
        $this->assertSame($admin->id, $post->authors->first()->user_id, 'Under my name');
        $this->get('/sonkha/2026-11/boi/')->assertDontSee('সম্পাদকীয়: অগ্রহায়ণ', false);   // a draft is never shown to readers

        \Livewire\Livewire::test(\App\Filament\Pages\Editorials::class)->assertSee('খসড়া খুলুন')->call('write', $issue->id)->assertRedirect();
        $this->assertSame(1, \App\Models\Post::whereHas('section', fn ($q) => $q->where('slug', 'editorial'))->count(), 'Never two');
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\ListPosts::class, ['activeTab' => 'editorial'])->assertCanSeeTableRecords([$post]);
        \Livewire\Livewire::test(\App\Filament\Resources\Issues\Pages\EditIssue::class, ['record' => $issue->id])->assertFormFieldExists('editorial_post_id');
    }

    public function test_readers_find_the_months_issue_from_the_home_page_and_the_menu(): void
    {
        $this->issue();
        $home = $this->get('/')->assertOk();
        $home->assertSee('এই মাসের সংখ্যা')->assertSee('অগ্রহায়ণ সংখ্যা')->assertSee('পত্রিকা খুলুন')->assertSee('/sonkha/2026-11/boi/', false);
        $this->get('/archive/')->assertSee('এই মাসের সংখ্যা');   // the menu, on every page
        Issue::create(['slug' => '2026-12', 'title' => 'পৌষ সংখ্যা', 'status' => 'proposed']);
        $this->get('/')->assertDontSee('পৌষ সংখ্যা', false);   // a proposal is never shown
    }

    public function test_the_desk_previews_an_unpublished_issue_and_cover_paths_are_absolute(): void
    {
        $section = \App\Models\Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'format' => 'prose']);
        $piece = $this->makePost(['status' => 'scheduled', 'slug' => 'kak', 'title' => 'পাঁচিলের ওপর কাক', 'published_at' => now()->addDays(10)]);
        $ed = $this->makePost(['status' => 'draft', 'slug' => 'ed-draft', 'title' => 'হেমন্তের আলো', 'section_id' => $section->id, 'format' => 'prose', 'published_at' => null]);
        $issue = Issue::create(['slug' => '2026-12', 'title' => 'হেমন্ত সংখ্যা', 'status' => 'proposed', 'editorial_post_id' => $ed->id,
            'proposed_cover_path' => 'media/covers/2026/11/x.jpg', 'ai_plan' => ['post_ids' => [$piece->id]]]);

        $this->get('/issue-preview/'.$issue->id.'/')->assertRedirect();   // not for readers
        $this->actingAs(User::factory()->create())->get('/issue-preview/'.$issue->id.'/')->assertForbidden();   // nor writers
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $res = $this->get('/issue-preview/'.$issue->id.'/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $res->assertSee('খসড়া দেখছেন')->assertSee('পাঁচিলের ওপর কাক')->assertSee('হেমন্তের আলো')->assertSee('src="'.url('/media/covers/2026/11/x.jpg').'"', false);
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->get('/sonkha/2026-12/boi/')->assertNotFound();   // the public magazine still waits for publishing

        // The desk's own pages print cover paths from the site root, never relative to /desk/.
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\Ebooks::class)->assertSee('src="'.url('/media/covers/2026/11/x.jpg').'"', false)->assertSee('পত্রিকা কেমন দেখাবে');
    }

    public function test_issue_news_for_readers_and_writers_on_a_click_and_once(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        config(['mail.default' => 'smtp']);
        [$pub, $priv] = \App\Support\WebPush::newKeys();
        config(['services.webpush.public_key' => $pub, 'services.webpush.private_key' => $priv, 'services.webpush.subject' => 'mailto:x@example.com']);
        $issue = $this->issue();
        $writer = User::factory()->create(['email_verified_at' => now()]);
        $mine = Author::create(['slug' => 'lekhok', 'display_name' => 'রিনা দাস', 'user_id' => $writer->id]);
        $this->makePost(['status' => 'published', 'slug' => 'rina', 'title' => 'শীতের নদী', 'issue_id' => $issue->id, 'published_at' => now()->subHour()], $mine);

        $this->assertSame(['রিনা দাস'], \App\Support\IssueNews::writers($issue)->pluck('name')->all(), 'Only writers with an account');
        $this->assertContains('পাঠক আর লেখকদের খবর দিন', \App\Ai\IssueWatch::needs($issue));

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\Ebooks::class)->assertSee('পাঠকদের ফোনে জানান')->assertSee('লেখকদের জানান')
            ->call('announce', $issue->id)->call('tellWriters', $issue->id);
        $a = \App\Models\PushAnnouncement::sole();
        $this->assertSame('নতুন সংখ্যা: অগ্রহায়ণ সংখ্যা', $a->title);
        $this->assertSame($issue->url(), $a->url);
        if (\App\Support\NewsletterSender::realMail()) {
            \Illuminate\Support\Facades\Notification::assertSentTo($writer, \App\Notifications\TemplateMail::class, fn ($n) => $n->key === 'issue_piece');
        }
        // Once only.
        $this->assertNull(\App\Support\IssueNews::announce($issue->fresh(), $admin));
        $this->assertSame(0, \App\Support\IssueNews::tellWriters($issue->fresh(), $admin));
        $this->assertSame(1, \App\Models\PushAnnouncement::count());
        $this->assertNotContains('পাঠক আর লেখকদের খবর দিন', \App\Ai\IssueWatch::needs($issue->fresh()));
        \Livewire\Livewire::test(\App\Filament\Pages\Ebooks::class)->assertSee('পাঠকদের ফোনে পাঠানো হয়েছে')->assertSee('লেখকদের জানানো হয়েছে');
    }

    public function test_approval_warns_when_the_editorial_or_cover_is_not_ready_and_sonkha_lists_issues(): void
    {
        $this->get('/sonkha/')->assertOk()->assertSee('সব সংখ্যা')->assertSee('এখনও কোনও সংখ্যা প্রকাশিত হয়নি')->assertSee('noindex', false);   // its own page since 2026-11-12
        $issue = Issue::create(['slug' => '2026-12', 'title' => 'পৌষ সংখ্যা', 'status' => 'proposed', 'ai_plan' => ['post_ids' => []]]);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\Issues\Pages\EditIssue::class, ['record' => $issue->id])
            ->mountAction('approveIssue')->assertActionMounted('approveIssue')
            ->assertSchemaComponentExists('not_ready', 'mountedActionSchema0', function ($c) {
                $html = (string) $c->getContent();
                $this->assertStringContainsString('সম্পাদকীয় এখনও লেখা হয়নি', $html);
                $this->assertStringContainsString('/issue-preview/', $html);

                return $c->isVisible();
            });
    }
}
