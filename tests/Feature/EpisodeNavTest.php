<?php

namespace Tests\Feature;

use App\Models\Serial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D281 (owner: "on the 2nd or any episode there is no way to go to the previous one or start from the 1st"). */
class EpisodeNavTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function serial(): array
    {
        Carbon::setTestNow('2026-12-20 10:00:00');
        $s = Serial::create(['title' => 'কুয়াশার শহর', 'slug' => 'kuasha', 'status' => 'active', 'day' => 6]);
        $eps = [];
        foreach ([1 => 'কুয়াশা নামে', 2 => 'চিঠি', 4 => 'রাতের ট্রেন'] as $n => $t) {   // number 3 is missing: no button may hide
            $eps[$n] = $this->makePost(['slug' => 'kuasha-'.$n, 'title' => 'কুয়াশার শহর (পর্ব '.$n.'): '.$t, 'serial_id' => $s->id, 'episode_no' => $n,
                'published_at' => '2026-12-0'.$n.' 03:30:00', 'format' => 'prose', 'body_html' => '<p>পর্ব '.$n.' এর লেখা</p>']);
        }

        return [$s, $eps];
    }

    public function test_a_middle_episode_shows_where_you_are_and_the_way_back(): void
    {
        [$s, $eps] = $this->serial();
        $page = $this->get($eps[2]->url())->assertOk();
        $page->assertSee('এটি পর্ব ২ · মোট ৩টি পর্ব বেরিয়েছে')->assertSee('← প্রথম পর্ব থেকে পড়ুন')->assertSee('← আগের পর্ব')
            ->assertSee('পরের পর্ব →')->assertSee('aria-current="page"', false)->assertSee('সব পর্ব (৩)')->assertDontSee('✓ পড়েছেন');
        $this->assertStringContainsString('href="'.$eps[4]->url().'"', $page->getContent(), 'A missing number does not hide the next one');
        $this->get($eps[4]->url())->assertSee('শুরু থেকে পড়ুন (পর্ব ১)')->assertSee('পরের পর্ব আসবে');
        $this->get($eps[1]->url())->assertSee('পর্ব ১ (শুরু)')->assertDontSee('← প্রথম পর্ব থেকে পড়ুন')->assertDontSee('← আগের পর্ব');
    }

    public function test_a_signed_in_reader_sees_what_they_read_and_where_they_stopped(): void
    {
        [$s, $eps] = $this->serial();
        $u = User::factory()->create();
        DB::table('reading_history')->insert([
            ['user_id' => $u->id, 'post_id' => $eps[1]->id, 'progress' => 100, 'read_at' => now()],
            ['user_id' => $u->id, 'post_id' => $eps[2]->id, 'progress' => 40, 'read_at' => now()],
        ]);
        $this->actingAs($u)->get($eps[4]->url())->assertSee('✓ পড়েছেন')->assertSee('অর্ধেক পড়া');
        $this->actingAs($u)->get($s->url())->assertSee('পর্ব ১ থেকে পড়া শুরু করুন')->assertSee('যেখানে থেমেছিলেন: পর্ব ২')->assertSee('শুরু এখান থেকে');
    }

    public function test_the_last_episode_of_a_finished_serial(): void
    {
        [$s, $eps] = $this->serial();
        $s->forceFill(['status' => 'finished'])->save();
        $this->get($eps[4]->url())->assertSee('গল্প শেষ')->assertSee('সব ধারাবাহিক দেখুন')->assertSee('সমাপ্ত');
    }
}
