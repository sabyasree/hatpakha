<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Enums\Role;
use App\Models\Setting;
use App\Models\SocialComment;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\CommentReplies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D189: readers' comments under our Facebook and Instagram posts are answered by the team itself, inside tight rules. */
class CommentRepliesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'services.facebook_page.id' => '111', 'services.facebook_page.token' => 'tok', 'services.facebook_page.app_secret' => null,
            'services.facebook_page.instagram_id' => '222', 'mail.off_live' => 'allow']);
        Carbon::setTestNow(Carbon::parse('2026-11-19 12:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function social(array $a = []): SocialPost
    {
        return tap((new SocialPost)->forceFill($a + ['kind' => 'piece', 'format' => 'card', 'title' => 'মায়ের চিঠি', 'texts' => ['facebook' => 'আপনার মায়ের কোন কথাটা মনে পড়ে?', 'instagram' => 'ক'], 'fb_post_id' => '111_9', 'status' => 'posted']))->save();
    }

    public function test_comments_are_read_once_without_names_and_riddle_posts_are_left_alone(): void
    {
        Http::fake([
            'graph.facebook.com/*/111_9/comments*' => Http::response(['data' => [
                ['id' => 'c1', 'message' => 'মা বলতেন, ভাত ফেলতে নেই।', 'from' => ['id' => '900', 'name' => 'Rina Basu'], 'created_time' => '2026-11-19T05:00:00+0000'],
                ['id' => 'c2', 'message' => 'আমাদের নিজের কমেন্ট', 'from' => ['id' => '111', 'name' => 'Hatpakha']],
                ['id' => 'c3', 'message' => '❤️', 'from' => ['id' => '901']],
            ]]),
            'graph.facebook.com/*/111_7/comments*' => Http::response(['data' => [['id' => 'c4', 'message' => 'চোখ', 'from' => ['id' => '902']]]]),
            'graph.facebook.com/*/ig9/comments*' => Http::response(['data' => [['id' => 'i1', 'text' => 'খুব ভালো লাগল', 'timestamp' => '2026-11-19T05:10:00+0000']]]),
        ]);
        $this->social(['ig_media_id' => 'ig9']);
        $this->social(['kind' => 'riddle', 'fb_post_id' => '111_7', 'title' => 'আজকের ধাঁধা']);
        $this->assertSame(4, CommentReplies::fetch());
        $this->assertSame(0, CommentReplies::fetch(), 'Each comment once');
        $this->assertSame(['c1' => 'new', 'c3' => 'skipped', 'c4' => 'skipped', 'i1' => 'new'], SocialComment::orderBy('remote_id')->pluck('status', 'remote_id')->all());
        $this->assertFalse(SocialComment::where('remote_id', 'c2')->exists(), 'Our own comment is not a reader\'s');
        $this->assertStringNotContainsString('Rina', json_encode(SocialComment::all()->toArray()), 'No names are kept');
    }

    public function test_safe_comments_are_answered_touchy_ones_wait_for_the_owner_and_a_bad_reply_is_stopped(): void
    {
        Http::fake(['graph.facebook.com/*/c1/comments' => Http::response(['id' => 'r1']), 'graph.facebook.com/*/i1/replies' => Http::response(['id' => 'r2']), 'graph.facebook.com/*' => Http::response([])]);
        $s = $this->social(['ig_media_id' => 'ig9']);
        $mk = fn (string $id, string $net, string $text) => SocialComment::create(['social_post_id' => $s->id, 'network' => $net, 'remote_id' => $id, 'text' => $text, 'status' => 'new']);
        [$kind, $ig, $angry, $promise, $spam] = [$mk('c1', 'facebook', 'মা বলতেন, ভাত ফেলতে নেই।'), $mk('i1', 'instagram', 'খুব ভালো লাগল'), $mk('c5', 'facebook', 'আমার লেখা ছাপেননি কেন?'), $mk('c6', 'facebook', 'আমার কবিতা নেবেন?'), $mk('c7', 'facebook', 'Earn money fast')];
        Claude::$fake = fn () => ['answers' => [
            ['id' => $kind->id, 'action' => 'reply', 'reply' => 'ভাত ফেলতে নেই, কথাটা আমাদের অনেকের বাড়িতেই ছিল। মনে করিয়ে দিলেন।', 'reason' => ''],
            ['id' => $ig->id, 'action' => 'reply', 'reply' => 'ভালো লাগল জেনে আমাদেরও ভালো লাগল।', 'reason' => ''],
            ['id' => $angry->id, 'action' => 'flag', 'reply' => '', 'reason' => 'নিজের লেখা নিয়ে অভিযোগ'],
            ['id' => $promise->id, 'action' => 'reply', 'reply' => 'অবশ্যই পাঠান, আপনার কবিতা ছাপা হবে।', 'reason' => ''],
            ['id' => $spam->id, 'action' => 'skip', 'reply' => '', 'reason' => 'বিজ্ঞাপন'],
        ]];
        $this->assertSame(0, CommentReplies::answer(), 'Not within ten minutes of the comment');
        Carbon::setTestNow(now()->addMinutes(15));
        $this->assertSame(2, CommentReplies::answer());
        $this->assertSame(['replied', 'r1'], [$kind->fresh()->status, $kind->fresh()->reply_id]);
        $this->assertSame('replied', $ig->fresh()->status);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/i1/replies') && str_contains($r['message'], 'ভালো লাগল'));
        $this->assertSame('flagged', $angry->fresh()->status);
        $this->assertSame('flagged', $promise->fresh()->status, 'A reply that promises publication never goes out');
        $this->assertSame('skipped', $spam->fresh()->status);
        $this->assertSame(0, CommentReplies::answer(), 'Never twice');

        $this->assertTrue(CommentReplies::safe('লেখা পাঠানোর পাতা: hatpakha.com/submit'));
        $this->assertFalse(CommentReplies::safe('দেখুন https://example.com'));
        $this->assertFalse(CommentReplies::safe(str_repeat('ক', 400)));
    }

    public function test_quiet_at_night_and_the_owner_can_switch_it_off_or_delete_a_reply(): void
    {
        Http::fake(['graph.facebook.com/*/r1' => Http::response(['success' => true]), 'graph.facebook.com/*' => Http::response(['id' => 'r9'])]);
        $s = $this->social();
        $c = SocialComment::create(['social_post_id' => $s->id, 'network' => 'facebook', 'remote_id' => 'c1', 'text' => 'ভালো লাগল', 'status' => 'new']);
        Claude::$fake = fn () => ['answers' => [['id' => $c->id, 'action' => 'reply', 'reply' => 'ধন্যবাদ, ভালো লাগল জেনে।', 'reason' => '']]];
        Carbon::setTestNow(Carbon::parse('2026-11-19 23:30', 'Asia/Kolkata'));
        $this->assertSame(0, CommentReplies::answer(), 'Not at night in Bengal');
        Carbon::setTestNow(Carbon::parse('2026-11-20 09:00', 'Asia/Kolkata'));
        Setting::put(CommentReplies::AUTO, '0');
        $this->assertSame(0, CommentReplies::answer(), 'Switched off');
        Setting::put(CommentReplies::AUTO, '1');
        $this->assertSame(1, CommentReplies::answer());

        $c->forceFill(['reply_id' => 'r1'])->save();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\Marketing::class)->assertSee('পাঠকের কমেন্ট ও আমাদের উত্তর')->assertSee('ধন্যবাদ, ভালো লাগল জেনে।')
            ->call('deleteReply', $c->id);
        $this->assertSame('deleted', $c->fresh()->status);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with(strtok($r->url(), '?'), '/r1'));
    }
}
