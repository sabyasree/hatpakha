<?php

namespace Tests\Feature;

use App\Ai\ReviewSchema;
use App\Enums\Role;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\AiReview;
use App\Models\Author;
use App\Models\Post;
use App\Models\SiteError;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D191 (owner, 2026-11-14: "how to make sure this kind of thing will not happen"): tidy examples hide errors. These
 * are deliberately awkward pieces, the kinds real writers send, and every desk screen and dialog for a piece is opened
 * against each of them. A new desk dialog or a new kind of awkward piece is added here.
 */
class MessyPiecesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: array, 1: list<array>, 2: bool}> name => [post fields, AI edits, has a writer account] */
    private function pieces(): array
    {
        $long = str_repeat('অনেক লম্বা একটা শিরোনাম যা থামতেই চায় না ', 8);

        return [
            'a poem whose fix spans two lines' => [['title' => 'বৃষ্টি', 'body_html' => "<p>আমি করিনা<br>ভয় ,মেঘলা আকাশ।</p>"], [['original' => "করিনা\nভয় ,", 'replacement' => "করি না\nভয়,", 'kind' => 'spacing']], true],
            'a fix with an arrow and quotes in it' => [['title' => 'তীর → চিহ্ন', 'body_html' => '<p>সে বলল "যাও → ওদিকে" আর \'থামো\'।</p>'], [['original' => '"যাও → ওদিকে"', 'replacement' => '“যাও → ওদিকে”', 'kind' => 'punctuation']], true],
            'a very long title, no writer account' => [['title' => mb_substr($long, 0, 380), 'body_html' => '<p>ছোট লেখা।</p>'], [], false],
            'English and Bengali mixed, with markup-like text' => [['title' => 'A day in কলকাতা <b>bold</b> & more', 'body_html' => '<p>Mixed text &lt;script&gt;alert(1)&lt;/script&gt; আর বাংলা। 5 < 6 &amp; 7 > 3.</p>'], [['original' => 'আর বাংলা।', 'replacement' => 'আর বাংলা ।', 'kind' => 'spacing']], true],
            'an empty body' => [['title' => 'ফাঁকা', 'body_html' => ''], [], true],
            'one enormous paragraph with emoji' => [['title' => 'লম্বা 😀', 'body_html' => '<p>'.str_repeat('একটানা লেখা 😀 থামা নেই ', 900).'</p>'], [['original' => 'একটানা লেখা', 'replacement' => 'এক টানা লেখা', 'kind' => 'spelling']], true],
            'no AI report at all' => [['title' => 'রিপোর্ট নেই', 'body_html' => '<p>এআই এখনও পড়েনি।</p>'], null, true],
            'an AI report with almost nothing in it' => [['title' => 'আধখানা রিপোর্ট', 'body_html' => '<p>কিছু কথা।</p>'], 'bare', true],
        ];
    }

    private function make(string $name, array $fields, $edits, bool $account, string $status): Post
    {
        static $n = 0;
        $n++;
        $writer = $account ? User::factory()->create(['role' => Role::Writer]) : null;
        $author = Author::create(['slug' => 'lekhok-'.$n, 'display_name' => 'lekhok_'.$n.'@example.com', 'user_id' => $writer?->id]);
        $post = $this->makePost(['slug' => 'messy-'.$n, 'status' => $status, 'published_at' => $status === 'published' ? now()->subDay() : null] + $fields, $author);
        $post->forceFill(['submitted_by' => $writer?->id, 'submitted_at' => now()->subDay()])->saveQuietly();
        if ($edits === 'bare') {
            AiReview::create(['post_id' => $post->id, 'provider' => 'anthropic', 'model' => 'm', 'status' => 'done', 'score' => null, 'report' => ['decision' => 'changes']]);
        } elseif (is_array($edits)) {
            AiReview::create(['post_id' => $post->id, 'provider' => 'anthropic', 'model' => 'm', 'status' => 'done', 'score' => 7,
                'report' => ReviewSchema::normalise(['quality_score' => 7, 'summary' => 'ঠিক আছে', 'genre' => 'কবিতা', 'strengths' => [], 'suggestions' => ['আর একবার দেখুন'], 'edits' => $edits,
                    'decision' => 'changes', 'note_to_writer' => "প্রথম লাইন\n\nদ্বিতীয় অনুচ্ছেদ", 'excerpt' => '', 'suggested_section' => 'poetry', 'tags' => [], 'seo_title' => '', 'seo_description' => '',
                    'policy_flags' => [], 'fits_magazine' => true, 'fit_reason' => ''])]);
        }

        return $post->fresh();
    }

    public function test_every_desk_dialog_for_a_piece_opens_for_every_awkward_piece(): void
    {
        $owner = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \App\Filament\Support\Safe::$failed = [];
        $names = array_merge(array_map(fn ($a) => $a->getName(), PostsTable::reviewActions()), ['history']);
        $this->assertContains('requestChanges', $names);

        foreach ($this->pieces() as $label => [$fields, $edits, $account]) {
            foreach (['pending', 'changes', 'published'] as $status) {
                $post = $this->make($label, $fields, $edits, $account, $status);
                $page = \Livewire\Livewire::test(EditPost::class, ['record' => $post->id])->assertOk();
                foreach ($names as $action) {
                    try {
                        // Opening is what broke; a dialog not offered for this piece simply does not open.
                        \Livewire\Livewire::test(EditPost::class, ['record' => $post->id])->mountAction($action)->assertOk();
                    } catch (\Throwable $e) {
                        $this->fail("“{$action}” on “{$label}” ({$status}): ".$e::class.': '.mb_substr($e->getMessage(), 0, 300));
                    }
                }
            }
        }
        \Livewire\Livewire::test(ListPosts::class)->assertOk()->set('activeTab', 'all')->assertOk()->set('activeTab', 'changes')->assertOk();
        $this->assertSame([], \App\Filament\Support\Safe::$failed, 'No part of a dialog was replaced by the "could not be shown" line');
        $this->assertSame(0, SiteError::count(), 'Nothing was swallowed on the way: '.SiteError::pluck('message')->implode(' | '));
    }

    public function test_awkward_pieces_show_on_the_site_and_in_the_writers_own_pages(): void
    {
        foreach ($this->pieces() as $label => [$fields, $edits, $account]) {
            $live = $this->make($label, $fields, $edits, $account, 'published');
            $this->get($live->url())->assertOk();
            $back = $this->make($label, $fields, $edits, $account, 'changes');
            $back->forceFill(['review_note' => "একটু দেখুন\n\n— সম্পাদক"])->saveQuietly();
            if ($back->submitted_by) {
                $this->actingAs(User::find($back->submitted_by));
                $this->get('/account/')->assertOk();
                $this->get('/submit/'.$back->id.'/')->assertOk();
                auth()->logout();
            }
        }
        $this->get('/')->assertOk();
        $this->get('/archive/')->assertOk();
        $this->get('/feed/')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
        $this->assertSame(0, SiteError::count(), SiteError::pluck('message')->implode(' | '));
    }

    public function test_errors_are_kept_once_per_kind_without_personal_details_and_reach_the_owner(): void
    {
        $boom = fn () => report(new \RuntimeException('Mail to reader@example.com failed (Connection: mysql, SQL: select secret)'));
        $boom();
        $boom();
        $e = SiteError::firstOrFail();
        $this->assertSame(1, SiteError::count(), 'The same error from the same line is one row');
        $this->assertSame(2, $e->count);
        $this->assertStringNotContainsString('reader@example.com', $e->message);
        $this->assertStringNotContainsString('select secret', $e->message);
        $this->assertStringStartsWith('tests/Feature/MessyPiecesTest.php:', $e->place);
        $this->assertSame(1, \App\Support\SiteErrors::lastDay()['new']);
        $this->assertSame(1, \App\Ai\Brief::today()['errors_new']);

        $owner = User::factory()->create(['role' => Role::Admin]);
        $this->assertStringContainsString('নতুন রকমের ত্রুটি', implode(' ', array_map('strval', (new \App\Notifications\EditorBrief(\App\Ai\Brief::today()))->toMail($owner)->introLines)));
        $this->actingAs($owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $this->assertSame('1', \App\Filament\Resources\SiteErrors\SiteErrorResource::getNavigationBadge());
        \Livewire\Livewire::test(\App\Filament\Resources\SiteErrors\Pages\ListSiteErrors::class)->assertSee('Mail to …@… failed')->callTableAction('seen', $e);
        $this->assertNull(\App\Filament\Resources\SiteErrors\SiteErrorResource::getNavigationBadge());

        // A part of a dialog that cannot be drawn says so and lets the rest work.
        $html = (string) \App\Filament\Support\Safe::view('filament.no-such-view');
        \App\Filament\Support\Safe::$failed = [];
        $this->assertStringContainsString('এই অংশটি এখন দেখানো গেল না', $html);
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(\App\Filament\Resources\SiteErrors\SiteErrorResource::canAccess(), 'Admins only');
    }

    /** D197 (owner: "no option to see the likes or visits for the pieces as an admin"). */
    public function test_the_desk_shows_what_readers_did_with_each_piece(): void
    {
        $busy = $this->makePost(['slug' => 'busy', 'title' => 'অনেকে পড়েছেন']);
        $quiet = $this->makePost(['slug' => 'quiet', 'title' => 'কম পড়া']);
        $this->makePost(['slug' => 'draft-x', 'title' => 'খসড়া', 'status' => 'draft', 'published_at' => null]);
        $busy->forceFill(['likes_count' => 7])->saveQuietly();
        \Illuminate\Support\Facades\DB::table('post_views')->insert([
            ['post_id' => $busy->id, 'day' => now()->toDateString(), 'views' => 40, 'search' => 10, 'social' => 5, 'internal' => 0],
            ['post_id' => $busy->id, 'day' => now()->subDays(90)->toDateString(), 'views' => 100, 'search' => 0, 'social' => 0, 'internal' => 0],
            ['post_id' => $quiet->id, 'day' => now()->toDateString(), 'views' => 3, 'search' => 0, 'social' => 0, 'internal' => 0],
        ]);
        $reader = User::factory()->create();
        \Illuminate\Support\Facades\DB::table('saved_posts')->insert(['user_id' => $reader->id, 'post_id' => $busy->id, 'created_at' => now()]);
        \Illuminate\Support\Facades\DB::table('reading_history')->insert(['user_id' => $reader->id, 'post_id' => $busy->id, 'progress' => 100, 'read_at' => now()]);

        $row = \App\Filament\Resources\PieceStats\PieceStatResource::getEloquentQuery()->find($busy->id);
        $this->assertSame([40, 140, 7, 1, 1, 10, 5], [(int) $row->views_30, (int) $row->views_all, (int) $row->likes_count, (int) $row->saves_n, (int) $row->finished_n, (int) $row->from_search, (int) $row->from_social]);

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\PieceStats\Pages\ListPieceStats::class)
            ->assertSee('৪৩ বার খোলা হয়েছে')->assertCanSeeTableRecords([$busy, $quiet], inOrder: true)->assertDontSee('খসড়া')
            ->sortTable('views_30', 'asc')->assertCanSeeTableRecords([$quiet, $busy], inOrder: true)
            ->sortTable('likes_count', 'desc')->assertCanSeeTableRecords([$busy, $quiet], inOrder: true);
    }
}
