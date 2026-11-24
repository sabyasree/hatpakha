<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\SavedLine;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reader changes from the owner-approved research (docs/RESEARCH-2026-11.md): C1 the phone home for returning readers,
 * C2 text size and the night page, C3 the desktop side rail, A5 a tidier end of a piece, A7 title cards instead of a
 * lone letter, C6 saved lines.
 */
class ReaderExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function reader(array $attrs = []): User
    {
        return User::factory()->create(['email_verified_at' => now()] + $attrs);
    }

    // ---- C1 ----

    public function test_the_home_page_is_the_same_for_every_guest_and_a_returning_phone_gets_the_slim_band(): void
    {
        $this->makePost(['slug' => 'notun', 'title' => 'আজকের নতুন লেখা', 'published_at' => now()->subHour()]);
        $res = $this->get('/')->assertOk();
        $html = $res->getContent();
        // one page for everyone (Cloudflare may share it, D65): the choice is made in the browser, from a flag only it keeps
        $this->assertStringContainsString('public', (string) $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('class="hp-first-visit"', $html);
        $this->assertStringContainsString('data-home-slim', $html);
        $this->assertStringContainsString('আজকের লেখা', $html);
        $slim = substr($html, strpos($html, 'data-home-slim'));
        $this->assertStringContainsString('আজকের নতুন লেখা', substr($slim, 0, strpos($slim, '</section>')));
        $this->assertSame(1, substr_count($html, '<h1'), 'The slim band does not add a second main heading');
        // the flag is read before the page draws, by a script that carries the CSP nonce
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+">[^<]*hp\.back[^<]*<\/script>/', $html);
        $this->assertStringContainsString('html[data-hp-back] .hp-first-visit', file_get_contents(resource_path('css/app.css')));
        $this->assertStringNotContainsString('যেখানে থেমেছিলেন', $html);
    }

    public function test_a_signed_in_reader_with_a_history_finds_where_they_stopped_at_the_top_of_the_home_page(): void
    {
        $post = $this->makePost(['slug' => 'adhek', 'title' => 'অর্ধেক পড়া গল্প']);
        $me = $this->reader(['reading_history' => true]);
        DB::table('reading_history')->insert(['user_id' => $me->id, 'post_id' => $post->id, 'progress' => 40, 'read_at' => now()]);

        $html = $this->actingAs($me)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('যেখানে থেমেছিলেন', $html);
        $this->assertStringContainsString($post->url().'#resume-40', $html);
        $this->assertLessThan(strpos($html, 'data-hero'), strpos($html, 'যেখানে থেমেছিলেন'), 'Above the poster');

        $me->forceFill(['reading_history' => false])->save();
        $this->actingAs($me)->get('/')->assertDontSee('যেখানে থেমেছিলেন');
    }

    // ---- C2 / C3 ----

    public function test_a_piece_offers_text_size_and_the_night_page_and_a_quiet_rail_on_wide_screens(): void
    {
        config(['services.adsense.slots' => ['sidebar' => '9'], 'services.adsense.force' => true]);
        $post = $this->makePost(['slug' => 'raat', 'title' => 'রাতের গল্প']);
        $html = $this->get('/raat/')->assertOk()->getContent();

        $this->assertStringContainsString('data-reading', $html);
        foreach (['0', '1', '2'] as $v) {
            $this->assertStringContainsString('data-read-size-set="'.$v.'"', $html);
        }
        $this->assertSame(2, substr_count($html, 'data-read-night-set="toggle"'), 'Beside share, and in the rail');
        $this->assertMatchesRegularExpression('/data-read-size-set="0" aria-pressed="true"/', $html);
        $this->assertStringContainsString('aria-controls="read-panel"', $html);
        $this->assertStringContainsString('লেখার মাপ', $html);
        $this->assertStringContainsString('রাতের পাতা', $html);
        // night is offered to a dark device, never switched on by itself: the offer is hidden until app.js shows it
        $this->assertMatchesRegularExpression('/<p data-night-offer hidden/', $html);
        $this->assertStringNotContainsString('data-read-night=""', $html);
        // the rail: left margin, from 1280px, hidden on phones; the ad keeps the right margin
        $rail = substr($html, strpos($html, '<aside class="absolute right-full'), strpos($html, '</aside>', strpos($html, 'data-read-rail')) - strpos($html, '<aside class="absolute right-full'));
        $this->assertStringContainsString('right-full', $rail);
        $this->assertStringContainsString('hidden', $rail);
        $this->assertStringContainsString('min-[1280px]:block', $rail);
        $this->assertStringContainsString('data-progress-ring', $rail);
        $this->assertStringContainsString('data-share=', $rail);
        $this->assertStringContainsString('পরে পড়ব', $rail);
        $this->assertStringContainsString('absolute left-full', $html, 'The side ad is where it was (D111/D167)');

        // signed in: the rail's পরে পড়ব is the same save as under the piece
        $me = $this->reader();
        DB::table('saved_posts')->insert(['user_id' => $me->id, 'post_id' => $post->id, 'created_at' => now()]);
        $html = $this->actingAs($me)->get('/raat/')->getContent();
        $this->assertSame(2, substr_count($html, 'action="'.route('save', $post).'" data-toggle="save"'));
        $this->assertSame(2, substr_count($html, '✓ তালিকায় আছে</button>'));
    }

    public function test_the_night_page_stays_inside_the_logo_palette(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $night = substr($css, strpos($css, '/* C2: the night page'));
        $night = substr($night, 0, strpos($night, '/* C3'));
        preg_match_all('/#[0-9A-Fa-f]{6}\b/', $night, $m);
        $palette = ['#2DAAE2', '#7CCBEE', '#1A86C2', '#12304A', '#CB1B32', '#B0162B', '#7A1022', '#F08D90', '#EFC08D', '#FBEBD8', '#E39A6A', '#13212C', '#FFFBF4', '#4D5A63', '#E8DCCB', '#DDF1FA'];
        $this->assertNotEmpty($m[0]);
        foreach ($m[0] as $hex) {
            $this->assertContains(strtoupper($hex), $palette, $hex.' is not a logo colour');
        }
        $this->assertStringContainsString('background-color: var(--color-navy)', $night);
        $this->assertStringContainsString('color: var(--color-paper)', $night);
        $this->assertStringContainsString('color: var(--color-peach)', $night);
        // the choice is read before paint, wrapped so a blocked storage never stops the page
        $layout = file_get_contents(resource_path('views/components/layouts/app.blade.php'));
        $this->assertMatchesRegularExpression('/try\{[^}]*hp\.size[^}]*hp\.night/', $layout);
    }

    // ---- A5 ----

    public function test_the_end_of_a_piece_has_one_way_to_follow_and_the_email_is_folded_under_it(): void
    {
        $this->makePost(['slug' => 'shesh']);
        $html = $this->get('/shesh/')->assertOk()->getContent();
        $news = substr($html, strpos($html, 'id="news-h"'));
        $this->assertStringContainsString('হোয়াটসঅ্যাপে খবর নিন', $news);
        $this->assertMatchesRegularExpression('/<details class="group mt-2" data-mail-fold\s*>/', $news, 'E-mail is folded, not a second big block');
        $this->assertStringContainsString('ই-মেলে চান?', $news);
        $this->assertStringContainsString('id="newsletter-card"', $news);
        $this->assertStringNotContainsString('id="newsletter-footer"', $html, 'And no second sign-up in the footer');

        // a sign-up from the folded form opens it again, to show the answer
        $this->withSession(['newsletter_from' => 'card', 'newsletter' => 'দেখুন'])->get('/shesh/')->assertSeeInOrder(['data-mail-fold', 'open', '<summary'], false);

        // without a channel: the e-mail card, open
        config(['services.social.whatsapp' => null]);
        $html = $this->get('/shesh/')->getContent();
        $this->assertStringNotContainsString('data-mail-fold', $html);
        $this->assertStringContainsString('id="newsletter-card"', $html);
    }

    // ---- A7 ----

    public function test_cards_without_a_picture_use_the_title_card_not_a_lone_letter(): void
    {
        $writer = Author::create(['slug' => 'bina', 'display_name' => 'Bina', 'display_name_bn' => 'বীণা', 'published_count' => 2]);
        $post = $this->makePost(['slug' => 'ek', 'title' => 'একলা পথ'], $writer);
        $this->makePost(['slug' => 'dui', 'title' => 'দুপুরের রোদ', 'section_id' => $post->section_id, 'published_at' => now()->subDays(3)], $writer);
        $letter = 'bg-peach-light font-display text-3xl font-extrabold text-red" aria-hidden="true">';

        $author = $this->get('/author/bina/')->assertOk()->getContent();
        $this->assertStringNotContainsString($letter, $author);
        $this->assertStringContainsString('!aspect-square', $author, 'The small title card in the phone list');

        $piece = $this->get('/ek/')->assertOk()->getContent();
        $next = substr($piece, strpos($piece, 'id="next-h"'));
        $next = substr($next, 0, strpos($next, '</section>'));
        $this->assertStringContainsString('দুপুরের রোদ', $next);
        $this->assertStringContainsString('@container', $next);
        $this->assertStringNotContainsString('bg-peach-light font-display text-4xl', $next);
        $this->assertSame(1, substr_count($next, 'দুপুরের রোদ'), 'The title is on the card, not written twice');
    }

    // ---- C6 ----

    public function test_a_signed_in_reader_keeps_a_line_and_finds_it_in_the_library(): void
    {
        $post = $this->makePost(['slug' => 'nodi', 'title' => 'নদীর গান', 'body_html' => '<p>নদীর ধারে জোনাকি জ্বলে,<br>রাত নামে ধীরে।</p><p>অন্য কথা।</p>']);
        $me = $this->reader();

        // guests: the pill asks them to sign in; the route needs an account
        $this->get('/nodi/')->assertSee('সাইন ইন করে লাইন রাখুন');
        $this->post('/line/'.$post->id, ['text' => 'রাত নামে ধীরে।'])->assertRedirect();
        $this->assertSame(0, SavedLine::count());

        $this->actingAs($me)->get('/nodi/')->assertSee('data-keep-url="'.route('lines.store', $post).'"', false)->assertDontSee('সাইন ইন করে লাইন রাখুন');
        $this->actingAs($me)->postJson('/line/'.$post->id, ['text' => "নদীর ধারে <b>জোনাকি</b> জ্বলে,\n  রাত নামে ধীরে।"])->assertOk()->assertJson(['ok' => true]);
        $line = SavedLine::sole();
        $this->assertSame("নদীর ধারে জোনাকি জ্বলে,\nরাত নামে ধীরে।", $line->text, 'Plain text: no markup kept, line breaks kept');
        $this->assertSame([$me->id, $post->id], [$line->user_id, $line->post_id]);
        // the same line again is not kept twice
        $this->actingAs($me)->postJson('/line/'.$post->id, ['text' => "নদীর ধারে জোনাকি জ্বলে,\nরাত নামে ধীরে।"])->assertOk();
        $this->assertSame(1, SavedLine::count());

        $this->actingAs($me)->get('/account/reading/')->assertOk()->assertSee('রাখা লাইন')->assertSee('রাত নামে ধীরে।')->assertSee($post->url(), false)
            ->assertSee('action="'.route('lines.destroy', $line->id).'"', false);
    }

    public function test_only_words_from_a_live_piece_are_kept_and_only_by_their_owner_removed(): void
    {
        $post = $this->makePost(['slug' => 'nodi', 'body_html' => '<p>নদীর ধারে জোনাকি জ্বলে।</p>']);
        $me = $this->reader();
        $this->actingAs($me);

        $this->postJson('/line/'.$post->id, ['text' => 'আমার নিজের বানানো কথা'])->assertStatus(422);
        $this->postJson('/line/'.$post->id, ['text' => '<script>alert(1)</script>'])->assertStatus(422);
        $this->postJson('/line/'.$post->id, ['text' => str_repeat('জোনাকি ', 90)])->assertStatus(422);
        $this->postJson('/line/'.$post->id, ['text' => ''])->assertStatus(422);
        $draft = $this->makePost(['slug' => 'khosra', 'status' => 'pending', 'body_html' => '<p>নদীর ধারে জোনাকি জ্বলে।</p>']);
        $this->postJson('/line/'.$draft->id, ['text' => 'নদীর ধারে'])->assertNotFound();
        $kids = Section::firstOrCreate(['slug' => Section::KIDS], ['name_bn' => 'ছোটদের হাতপাখা', 'name_en' => 'Kids']);
        $child = $this->makePost(['slug' => 'chhoto', 'section_id' => $kids->id, 'body_html' => '<p>নদীর ধারে জোনাকি জ্বলে।</p>']);
        $this->postJson('/line/'.$child->id, ['text' => 'নদীর ধারে'])->assertNotFound();
        $this->assertSame(0, SavedLine::count());

        $this->postJson('/line/'.$post->id, ['text' => 'জোনাকি জ্বলে।'])->assertOk();
        $line = SavedLine::sole();
        $other = $this->reader();
        $this->actingAs($other)->post('/account/lines/'.$line->id.'/remove')->assertNotFound();
        $this->actingAs($other)->get('/account/reading/')->assertDontSee('জোনাকি জ্বলে।');
        $this->assertSame(1, SavedLine::count());
        $this->actingAs($me)->post('/account/lines/'.$line->id.'/remove')->assertRedirect();
        $this->assertSame(0, SavedLine::count());

        // a deleted account takes its lines with it
        SavedLine::create(['user_id' => $me->id, 'post_id' => $post->id, 'text' => 'জোনাকি']);
        $me->delete();
        $this->assertSame(0, SavedLine::count());
    }

    public function test_keeping_lines_is_rate_limited_and_capped(): void
    {
        $this->withMiddleware(\App\Http\Middleware\PerRouteThrottle::class);
        $post = $this->makePost(['slug' => 'nodi', 'body_html' => '<p>নদীর ধারে জোনাকি জ্বলে।</p>']);
        $me = $this->reader();
        $codes = collect(range(1, 21))->map(fn () => $this->actingAs($me)->postJson('/line/'.$post->id, ['text' => 'জোনাকি'])->status());
        $this->assertSame(429, $codes->last());

        $other = $this->reader();
        SavedLine::insert(collect(range(1, SavedLine::LIMIT))->map(fn ($i) => ['user_id' => $other->id, 'post_id' => $post->id, 'text' => 'নদী '.$i, 'created_at' => now()])->all());
        $this->actingAs($other)->postJson('/line/'.$post->id, ['text' => 'জোনাকি'])->assertStatus(422);
    }
}
