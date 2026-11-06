<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Enums\Role;
use App\Filament\Pages\Honours;
use App\Models\Author;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D76: one place to honour writers; the honour shows on the site at once. */
class HonoursPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_shortlist_honour_with_drafted_praise_and_it_shows_on_the_site(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $a = Author::create(['display_name' => 'শ্রাবণী দে', 'slug' => 'srabani']);
        foreach (range(1, 3) as $i) {
            $this->makePost(['status' => 'published', 'slug' => 'p'.$i, 'title' => 'কবিতা '.$i, 'published_at' => now()->subYears($i)], $a);
        }
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Claude::$fake = fn () => ['praise' => 'বহু বছর ধরে হাতপাখায় লিখছেন।'];

        Livewire::test(Honours::class)->assertSee('শ্রাবণী দে')->assertSee('এখনও কাউকে সম্মান দেওয়া হয়নি')
            ->callAction('honour', ['label' => 'pick', 'praise' => 'বহু বছর ধরে হাতপাখায় লিখছেন।', 'tell' => false], ['author' => $a->id])->assertHasNoActionErrors();

        $a->refresh();
        $this->assertSame('সম্পাদকের পছন্দের লেখক', $a->honour_label);
        $this->assertSame('বহু বছর ধরে হাতপাখায় লিখছেন।', $a->honour_note);
        $this->assertTrue(\App\Models\Certificate::where('author_id', $a->id)->where('kind', 'honour')->exists());
        $this->get('/')->assertSee('হাতপাখা সম্মান জানায়')->assertSee('শ্রাবণী দে');
        $this->get($a->url())->assertSee('সম্পাদকের পছন্দের লেখক')->assertSee('বহু বছর ধরে হাতপাখায় লিখছেন।');
        $this->assertSame('বহু বছর ধরে হাতপাখায় লিখছেন।', \App\Ai\Honours::draftPraise($a));

        Livewire::test(Honours::class)->call('unhonour', $a->id);
        $this->assertNull($a->fresh()->honour_label);
    }

    /** D77: the writing leads; a prolific writer does not outrank a better one just by count. */
    public function test_writing_quality_outranks_number_of_pieces(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $many = Author::create(['display_name' => 'অনেক লেখা', 'slug' => 'many']);
        $good = Author::create(['display_name' => 'ভালো লেখা', 'slug' => 'good']);
        foreach (range(1, 40) as $i) {
            $this->makePost(['status' => 'published', 'slug' => 'm'.$i, 'published_at' => now()->subMonths($i), 'body_html' => '<p>সাধারণ লেখা '.$i.'</p>'], $many);
        }
        foreach (range(1, 4) as $i) {
            $this->makePost(['status' => 'published', 'slug' => 'g'.$i, 'published_at' => now()->subMonths($i), 'body_html' => '<p>চমৎকার লেখা '.$i.'</p>'], $good);
        }
        $this->assertSame('অনেক লেখা', \App\Ai\Honours::candidates()->first()['author']->display_name, 'Before reading: numbers only');

        $this->assertGreaterThan(0, \App\Ai\Honours::estimate(\App\Ai\Honours::toRead()));
        Claude::$fake = fn ($purpose, $user) => str_contains($user, 'চমৎকার') ? ['score' => 9, 'note' => 'ছবির মতো স্পষ্ট ভাষা।'] : ['score' => 5, 'note' => 'চলনসই।'];
        $this->assertSame(2, \App\Ai\Honours::readSamples());
        $this->assertSame(0, \App\Ai\Honours::readSamples(), 'Not read again within 90 days');

        $top = \App\Ai\Honours::candidates()->first();
        $this->assertSame('ভালো লেখা', $top['author']->display_name, 'The better writing leads, whatever the count');
        $this->assertSame(9.0, $top['quality']);

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(Honours::class)->assertSee('লেখার মান')->assertSee('ছবির মতো স্পষ্ট ভাষা।')
            ->callAction('read', ['redo' => true])->assertHasNoActionErrors();
        $this->assertSame(1, \App\Models\TeamRequest::where('job', 'read_writers')->count());
    }

    /** D78: spotlight with an exact line, the editor's reason on the pick, milestones after approval. */
    public function test_spotlight_pick_reason_and_milestones(): void
    {
        $a = Author::create(['display_name' => 'মৌমিতা পাল', 'slug' => 'moumita']);
        $p = $this->makePost(['status' => 'published', 'slug' => 'brishti', 'title' => 'বৃষ্টির দিন', 'published_at' => now()->subDays(5),
            'body_html' => '<p>জানলার কাচে বৃষ্টি আঁকে নদীর মানচিত্র।</p><p>দ্বিতীয় লাইন।</p>'], $a);
        \App\Ai\Honours::give($a, 'month', 'ছোট ছবিতে বড় কথা বলেন।', false, $p->id, 'জানলার কাচে বৃষ্টি আঁকে নদীর মানচিত্র।');
        $this->get('/')->assertSee('হাতপাখা সম্মান জানায়')->assertSee('মৌমিতা পাল')->assertSee('জানলার কাচে বৃষ্টি আঁকে নদীর মানচিত্র।')->assertSee('বৃষ্টির দিন');

        \App\Ai\Honours::give($a->fresh(), 'month', 'x', false, $p->id, 'এই লাইন লেখায় নেই');
        $this->assertNull($a->fresh()->honour_quote, 'An invented line never reaches the home page');

        \App\Models\Setting::put('home.pick', json_encode(['post_id' => $p->id, 'note' => 'বর্ষার এমন ছবি অনেকদিন পড়িনি।'], JSON_UNESCAPED_UNICODE));
        $this->get('/')->assertSee('সম্পাদকের পছন্দ')->assertSee('কেন পছন্দ')->assertSee('বর্ষার এমন ছবি অনেকদিন পড়িনি।');

        // Milestones: a 10th piece reached recently is proposed; it shows only after approval.
        $b = Author::create(['display_name' => 'দশের লেখক', 'slug' => 'dosh']);
        foreach (range(1, 10) as $i) {
            $this->makePost(['status' => 'published', 'slug' => 'd'.$i, 'published_at' => now()->subDays(100 - $i * 9)], $b);
        }
        $this->assertGreaterThan(0, \App\Support\Milestones::detect(true));
        $m = \App\Models\Milestone::where('author_id', $b->id)->where('kind', 'pieces_10')->sole();
        $this->assertSame('১০তম লেখা', $m->label);
        $this->assertSame(0, \App\Support\Milestones::detect(true), 'Never twice');
        $this->get('/')->assertDontSee('১০তম লেখা');

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(Honours::class)->assertSee('মাইলফলক')->assertSee('১০তম লেখা')->call('milestone', $m->id, true);
        auth()->logout();
        $this->get('/')->assertSee('অভিনন্দন')->assertSee('দশের লেখক')->assertSee('১০তম লেখা');
    }
}
