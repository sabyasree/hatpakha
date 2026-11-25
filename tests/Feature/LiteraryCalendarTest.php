<?php

namespace Tests\Feature;

use App\Ai\CalendarDesk;
use App\Ai\Claude;
use App\Enums\Role;
use App\Models\Author;
use App\Models\Post;
use App\Models\Section;
use App\Models\User;
use App\Support\Handover;
use App\Support\LiteraryCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D294: calendar and reading pieces, written, fact-checked and published by the desk itself; off-topic desk pieces hidden. */
class LiteraryCalendarTest extends TestCase
{
    use RefreshDatabase;

    private array $problems = [];

    private array $asked = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => null]);
        User::factory()->create(['role' => Role::Admin]);
        Author::firstOrCreate(['slug' => 'hatpakha-desk'], ['display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]);
        Section::firstOrCreate(['slug' => 'phire-pora'], ['name_bn' => 'ফিরে পড়া, ফিরে দেখা', 'format' => 'prose']);
        Section::firstOrCreate(['slug' => 'heritage'], ['name_bn' => 'ঐতিহ্য ও উৎসব', 'format' => 'prose']);
        Claude::$fake = function ($purpose, $user) {
            $this->asked[] = $purpose;

            return match ($purpose) {
                'copy' => str_contains($user, '<problems>')
                    ? ['paragraphs' => ['শুধরানো: সেই ভোরে পায়রাবন্দের আকাশে আলো ফুটছিল, আর একটি মেয়ে জানলার ধারে বসে চুপি চুপি অক্ষর চিনছিল।', 'অনেক বছর পরে সেই অক্ষরই হয়ে উঠল একটা গোটা স্বপ্ন, যেখানে মেয়েরা পড়ে, লেখে, আর নিজের পথ নিজে বানায়।']]
                    : ['plan' => ['moment' => 'ভোর', 'feeling' => 'মায়া', 'details' => ['আলো'], 'closing_image' => 'জানলা'],
                        'title' => 'রোকেয়ার স্বপ্ন', 'excerpt' => 'এক লাইনে।', 'paragraphs' => ['সেই ভোরে পায়রাবন্দের আকাশে আলো ফুটছিল, আর একটি মেয়ে জানলার ধারে বসে চুপি চুপি অক্ষর চিনছিল।', 'অনেক বছর পরে সেই অক্ষরই হয়ে উঠল একটা গোটা স্বপ্ন, যেখানে মেয়েরা পড়ে, লেখে, আর নিজের পথ নিজে বানায়।'],
                        'facts_to_check' => ['বেগম রোকেয়ার জন্ম ৯ ডিসেম্বর ১৮৮০'], 'photo_ideas' => []],
                'factcheck' => ['problems' => array_shift($this->problems) ?? []],
                'plagiarism' => ['verdict' => 'clear', 'matches' => [], 'note' => ''],
                default => null,
            };
        };
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_the_calendar_dates_and_publish_day(): void
    {
        $days = LiteraryCalendar::between(CarbonImmutable::parse('2026-12-01', 'Asia/Kolkata'), CarbonImmutable::parse('2027-02-28', 'Asia/Kolkata'));
        $this->assertSame(['rokeya-2026', 'poushmela-2026', 'jasimuddin-2027', 'ashapurna-2027', 'mahasweta-2027', 'madhusudan-2027', 'boimela-2027', 'jibanananda-2027', 'bhasha-2027', 'leela-2027'], array_column($days, 'key'));
        $this->assertSame('2026-12-06 09:00', LiteraryCalendar::publishAt($days[0]['date'], CarbonImmutable::parse('2026-12-01 08:00', 'Asia/Kolkata'))->format('Y-m-d H:i'));
        $this->assertSame('2026-12-09 09:00', LiteraryCalendar::publishAt($days[0]['date'], CarbonImmutable::parse('2026-12-08 12:00', 'Asia/Kolkata'))->format('Y-m-d H:i'), 'Late: the next morning');
        $this->assertCount(12, LiteraryCalendar::WORKS);
    }

    public function test_a_checked_day_piece_is_drafted_and_scheduled_under_the_desk_byline_once(): void
    {
        $now = CarbonImmutable::parse('2026-12-01 08:00', 'Asia/Kolkata');
        $this->assertSame(1, CalendarDesk::run($now));
        $post = Post::where('title', 'রোকেয়ার স্বপ্ন')->firstOrFail();
        $this->assertSame('scheduled', $post->status);
        $this->assertSame('2026-12-06 09:00', $post->published_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'));
        $this->assertSame('phire-pora', $post->section->slug);
        $this->assertSame(['hatpakha-desk'], $post->authors->pluck('slug')->all());
        $this->assertStringStartsNotWith('desk-', $post->slug);
        $this->assertContains('factcheck', $this->asked);

        // The same day is never written twice, and one draft a day at most.
        CalendarDesk::run($now->addHours(2));
        $this->assertSame(1, Post::count());
        CalendarDesk::run($now->addDay());   // the next day: no other calendar day near, so this week's reading
        $this->assertSame(2, Post::count());
    }

    public function test_a_wrong_fact_is_mended_once_and_a_piece_that_still_fails_is_never_published(): void
    {
        $now = CarbonImmutable::parse('2026-12-01 08:00', 'Asia/Kolkata');
        $this->problems = [[['claim' => '১৮৮১ সালে', 'why' => 'ভুল সাল', 'fix' => '১৮৮০']]];   // first check: one problem
        CalendarDesk::run($now);
        $post = Post::where('slug', 'like', 'desk-cal-%')->firstOrFail();
        $this->assertSame('draft', $post->status);
        $this->assertStringContainsString('শুধরানো', $post->body_html, 'Mended');
        CalendarDesk::run($now->addMinutes(10));   // second check: clean
        $this->assertSame('scheduled', $post->fresh()->status);

        // A piece whose facts fail again after the one repair stays a draft for good.
        Post::query()->delete();
        \App\Models\Setting::put('calendar.tried', null);
        \App\Models\Setting::put('calendar.day.poushmela-2026', null);
        $this->problems = [[['claim' => 'ক', 'why' => 'অজানা', 'fix' => '']], [['claim' => 'খ', 'why' => 'অজানা', 'fix' => '']]];
        $later = CarbonImmutable::parse('2026-12-14 08:00', 'Asia/Kolkata');
        CalendarDesk::run($later);
        CalendarDesk::run($later->addMinutes(10));
        CalendarDesk::run($later->addMinutes(20));
        $held = Post::where('slug', 'like', 'desk-cal-%')->firstOrFail();
        $this->assertSame('draft', $held->status);
        $this->assertSame('failed', CalendarDesk::check($held)['state']);
    }

    public function test_a_week_with_no_day_gets_a_reading_of_a_famous_work_and_the_switch_stops_everything(): void
    {
        $now = CarbonImmutable::parse('2027-03-01 08:00', 'Asia/Kolkata');   // no calendar day in the next 12 days
        CalendarDesk::run($now);
        $post = Post::where('status', 'scheduled')->firstOrFail();
        $this->assertSame(CarbonImmutable::WEDNESDAY, $post->published_at->timezone('Asia/Kolkata')->dayOfWeek);
        $this->assertSame('1', \App\Models\Setting::get('calendar.study.next'));

        // Owner ("reduce the cost"): the reading every second week, not every week.
        \App\Models\Setting::put('calendar.tried', null);
        CalendarDesk::run($now->addDays(7));
        $this->assertSame(1, Post::count());
        \App\Models\Setting::put('calendar.tried', null);
        CalendarDesk::run($now->addDays(14));
        $this->assertSame(2, Post::count());
        $this->assertSame('2', \App\Models\Setting::get('calendar.study.next'));

        Handover::set('calendar', false);
        Post::query()->delete();
        \App\Models\Setting::put('calendar.tried', null);
        \App\Models\Setting::put('calendar.study.week', null);
        Handover::run();
        $this->assertSame(0, Post::count());
    }

    public function test_off_topic_desk_pieces_leave_google_but_a_writers_piece_is_never_hidden(): void
    {
        $desk = Author::where('slug', 'hatpakha-desk')->first();
        $filler = $this->makePost(['slug' => 'monthly-horoscope-june-2014', 'status' => 'published'], $desk);
        $writer = $this->makePost(['slug' => 'sleep-like-baby-tips-help', 'status' => 'published']);   // a writer's, same address on the list
        $poem = $this->makePost(['slug' => 'peyaj', 'status' => 'published'], $desk);

        (require database_path('migrations/2026_11_25_000600_hide_off_topic_desk_pieces.php'))->up();

        $this->assertTrue($filler->fresh()->noindex);
        $this->assertFalse($writer->fresh()->noindex);
        $this->assertFalse($poem->fresh()->noindex);
        $this->get($filler->url())->assertOk()->assertSee('noindex', false);
        $this->get('/sitemap-posts-1.xml')->assertDontSee('monthly-horoscope-june-2014');
    }
}
