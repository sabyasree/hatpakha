<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\FacebookPage;
use App\Support\InstagramPage;
use App\Support\MarketingPlan;
use App\Support\MarketingStats;
use App\Support\Studio;
use App\Support\WordGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D182: the automatic marketing plan (docs/MARKETING.md): planned posts, drawn media, formats, caps, answers, numbers. */
class MarketingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.facebook_page.id' => '111', 'services.facebook_page.token' => 'tok', 'services.facebook_page.app_secret' => null,
            'services.facebook_page.instagram_id' => '222', 'mail.off_live' => 'allow']);
        Carbon::setTestNow(Carbon::parse('2026-11-19 18:00', 'Asia/Kolkata'));   // a Thursday, after the games began
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        array_map(fn ($d) => array_map('unlink', glob($d.'/*')) && rmdir($d), glob(storage_path(Studio::INBOX).'/*', GLOB_ONLYDIR) ?: []);
        parent::tearDown();
    }

    private static function social(array $a): SocialPost
    {
        return tap((new SocialPost)->forceFill($a))->save();
    }

    public function test_a_day_is_planned_once_with_real_riddles_and_no_spoiler(): void
    {
        $friday = Carbon::parse('2026-11-20', 'Asia/Kolkata');
        $this->assertSame(2, MarketingPlan::plan($friday));
        $this->assertSame(0, MarketingPlan::plan($friday), 'Planning the same day again makes nothing new');

        $today = WordGame::riddle($friday)['words'][0];
        $before = WordGame::riddle($friday->copy()->subDay())['words'][0];
        $card = SocialPost::where('plan_key', '2026-11-20:riddle')->firstOrFail();
        $this->assertSame('card', $card->format);
        $this->assertStringContainsString($today['clue'], $card->texts['facebook']);
        $this->assertStringNotContainsString($today['answer'], str_replace($today['clue'], '', $card->texts['facebook']), 'Today\'s answer is not in the post');
        $this->assertStringContainsString('গতকালের উত্তর: '.$before['answer'], $card->texts['facebook']);
        $this->assertStringContainsString($today['answer'], $card->comment);
        $this->assertSame('2026-11-21 08:30', $card->comment_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'));
        $this->assertSame('09:05', $card->slot_at->timezone('Asia/Kolkata')->format('H:i'));
        $this->assertSame('', $card->texts['instagram']);

        $reel = SocialPost::where('plan_key', '2026-11-20:reel')->firstOrFail();
        $this->assertSame('reel', $reel->format);
        $this->assertSame($before['clue'], $reel->design['reel']['clue'], 'The reel that shows an answer uses yesterday\'s riddle');
        $this->assertSame(WordGame::letters($before['answer']), $reel->design['reel']['letters']);

        // Wednesday: a word from the word game. The first day of the games: nothing from "yesterday".
        MarketingPlan::plan(Carbon::parse('2026-11-25', 'Asia/Kolkata'));
        $this->assertSame('word', SocialPost::where('plan_key', '2026-11-25:word')->firstOrFail()->kind);
        MarketingPlan::plan(Carbon::parse(WordGame::START, 'Asia/Kolkata'));
        $first = SocialPost::where('plan_key', WordGame::START.':riddle')->firstOrFail();
        $this->assertStringNotContainsString('গতকালের উত্তর', $first->texts['facebook']);
        $this->assertFalse(SocialPost::where('plan_key', WordGame::START.':reel')->exists());
    }

    public function test_the_studio_lists_work_and_takes_in_only_real_pictures_and_videos(): void
    {
        MarketingPlan::plan(Carbon::parse('2026-11-20', 'Asia/Kolkata'));
        $jobs = Studio::pending();
        $this->assertCount(2, $jobs);
        $this->artisan('hatpakha:studio', ['what' => 'list'])->assertSuccessful();
        $card = SocialPost::where('plan_key', '2026-11-20:riddle')->firstOrFail();
        $reel = SocialPost::where('plan_key', '2026-11-20:reel')->firstOrFail();

        $in = storage_path(Studio::INBOX);
        @mkdir($in.'/'.$card->id, 0775, true);
        @mkdir($in.'/'.$reel->id, 0775, true);
        @mkdir($in.'/999999', 0775, true);
        $img = imagecreatetruecolor(1080, 1350);
        imagejpeg($img, $in.'/'.$card->id.'/1.jpg');
        file_put_contents($in.'/'.$reel->id.'/reel.mp4', '<?php echo "not a video";');   // a wrong file is refused
        file_put_contents($in.'/999999/1.jpg', 'x');

        $this->assertSame(1, Studio::take());
        $card->refresh();
        $this->assertCount(1, $card->media['images']);
        $this->assertFileExists(public_path($card->media['images'][0]));
        $this->assertStringStartsWith('media/cards/', $card->media['images'][0]);
        $this->assertNull($reel->fresh()->media);
        $this->assertSame([], glob($in.'/*'), 'The inbox is emptied');
        @unlink(public_path($card->media['images'][0]));
    }

    public function test_due_posts_go_out_in_their_format_wait_for_their_picture_and_keep_to_the_cap(): void
    {
        Http::fake([
            'graph.facebook.com/*/111/photos' => Http::response(['id' => 'ph1', 'post_id' => '111_9']),
            'graph.facebook.com/*/111/feed' => Http::response(['id' => '111_8']),
            'graph.facebook.com/*/111_9/comments' => Http::response(['id' => 'c1']),
            'graph.facebook.com/*/111/video_reels' => Http::sequence()->push(['video_id' => 'v1', 'upload_url' => 'x'])->push(['success' => true]),
            'rupload.facebook.com/*' => Http::response(['success' => true]),
            'graph.facebook.com/*/222/media' => Http::response(['id' => 'cont1']),
            'graph.facebook.com/*/cont1*' => Http::response(['status_code' => 'FINISHED']),
            'graph.facebook.com/*/222/media_publish' => Http::response(['id' => 'ig1']),
            'graph.facebook.com/*' => Http::response(['permalink' => 'https://www.instagram.com/reel/x/']),
        ]);
        MarketingPlan::plan(Carbon::parse('2026-11-20', 'Asia/Kolkata'));
        $card = SocialPost::where('plan_key', '2026-11-20:riddle')->firstOrFail();
        $reel = SocialPost::where('plan_key', '2026-11-20:reel')->firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-11-20 09:10', 'Asia/Kolkata'));
        $this->assertSame(0, MarketingPlan::run(), 'Its picture is not drawn yet: it waits');
        $card->forceFill(['media' => ['images' => ['media/cards/x.jpg']]])->save();
        $this->assertSame(1, MarketingPlan::run());
        $this->assertSame('111_9', $card->fresh()->fb_post_id);
        $this->assertSame('posted', $card->fresh()->status);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/111/photos') && str_contains($r['url'], 'media/cards/x.jpg') && str_contains($r['caption'], 'আজকের ধাঁধা'));
        $this->assertSame(0, MarketingPlan::run(), 'Never twice');

        // The reel: Facebook at once, Instagram after it has processed the video.
        Carbon::setTestNow(Carbon::parse('2026-11-20 13:35', 'Asia/Kolkata'));
        $reel->forceFill(['media' => ['video' => 'media/cards/r.mp4', 'cover' => 'media/cards/c.jpg']])->save();
        $this->assertSame(1, MarketingPlan::run());
        $this->assertSame('v1', $reel->fresh()->fb_post_id);
        $this->assertSame('cont1', $reel->fresh()->ig_container);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'rupload.facebook.com') && str_contains($r->header('file_url')[0], 'media/cards/r.mp4'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/222/media') && $r['media_type'] === 'REELS');
        MarketingPlan::run();
        $this->assertSame('ig1', $reel->fresh()->ig_media_id);

        // Next morning: the promised answer, as our comment.
        Carbon::setTestNow(Carbon::parse('2026-11-21 08:35', 'Asia/Kolkata'));
        MarketingPlan::run();
        $this->assertSame('c1', $card->fresh()->fb_comment_id);

        // The cap, and the owner's switch.
        Carbon::setTestNow(Carbon::parse('2026-11-22 12:00', 'Asia/Kolkata'));
        foreach (range(1, 6) as $i) {
            self::social(['kind' => 'custom', 'title' => 'p'.$i, 'texts' => ['facebook' => 'লেখা '.$i, 'instagram' => ''], 'slot_at' => now()->subMinutes(30 - $i)]);
        }
        MarketingPlan::run();
        MarketingPlan::run();
        MarketingPlan::run();
        $this->assertSame(MarketingPlan::FB_CAP, SocialPost::where('title', 'like', 'p%')->whereNotNull('fb_post_id')->count());
        Setting::put(FacebookPage::AUTO, '0');
        SocialPost::where('title', 'like', 'p%')->update(['fb_post_id' => null, 'status' => 'ready']);
        $this->assertSame(0, MarketingPlan::run(), 'Switched off: nothing goes by itself');
    }

    public function test_a_card_never_drawn_goes_as_text_and_an_old_post_is_put_away(): void
    {
        Http::fake(['graph.facebook.com/*/111/feed' => Http::response(['id' => '111_8']), 'graph.facebook.com/*' => Http::response([])]);
        MarketingPlan::plan(Carbon::parse('2026-11-20', 'Asia/Kolkata'));
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:10', 'Asia/Kolkata'));   // three hours after the morning card's time
        MarketingPlan::run();
        $card = SocialPost::where('plan_key', '2026-11-20:riddle')->firstOrFail();
        $this->assertSame('111_8', $card->fb_post_id);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/111/feed') && str_contains($r['link'], '/khela/dhadha/?utm_source=facebook'));

        Carbon::setTestNow(Carbon::parse('2026-11-20 17:00', 'Asia/Kolkata'));   // the reel was never drawn: dropped, not posted as text
        MarketingPlan::run();
        $reel = SocialPost::where('plan_key', '2026-11-20:reel')->firstOrFail();
        $this->assertSame('dismissed', $reel->status);
        $this->assertNull($reel->fb_post_id);
    }

    public function test_piece_posts_get_a_card_or_slides_and_an_evening_time(): void
    {
        $post = $this->makePost(['slug' => 'kobita', 'title' => 'শীতের সকাল', 'body_html' => '<p>কুয়াশার চাদর গায়ে মাঠ ঘুমিয়ে আছে।</p>']);
        $s = MarketingPlan::dress(self::social(['kind' => 'piece', 'subject_type' => $post->getMorphClass(), 'subject_id' => $post->id, 'title' => $post->title,
            'texts' => ['facebook' => 'ক', 'instagram' => 'খ', 'whatsapp' => 'গ', 'quote' => 'কুয়াশার চাদর গায়ে মাঠ ঘুমিয়ে আছে।']]));
        $this->assertSame('card', $s->format);
        $this->assertSame('line', $s->design['slides'][0]['t']);
        $this->assertSame('19:35', $s->slot_at->timezone('Asia/Kolkata')->format('H:i'));

        $poem = MarketingPlan::dress(self::social(['kind' => 'piece', 'subject_type' => $post->getMorphClass(), 'subject_id' => $post->id, 'title' => $post->title,
            'texts' => ['facebook' => 'ক', 'instagram' => 'খ', 'whatsapp' => 'গ', 'quote' => '', 'slides' => ["এক\nদুই", "তিন\nচার"]]]));
        $this->assertSame('carousel', $poem->format);
        $this->assertSame(['title', 'stanza', 'stanza', 'end'], array_column($poem->design['slides'], 't'));
        $this->assertSame('21:15', $poem->slot_at->timezone('Asia/Kolkata')->format('H:i'), 'The second evening post takes the later time');

        // A reel from the piece's line, on a Tuesday, Thursday or Saturday.
        MarketingPlan::plan(Carbon::parse('2026-11-21', 'Asia/Kolkata'));
        $reel = SocialPost::where('plan_key', '2026-11-21:reel')->firstOrFail();
        $this->assertSame('line', $reel->design['reel']['t']);
        $this->assertSame('কুয়াশার চাদর গায়ে মাঠ ঘুমিয়ে আছে।', $reel->design['reel']['quote']);
        $this->assertStringContainsString($post->url(), $reel->texts['facebook']);
    }

    public function test_numbers_are_read_back_and_shown(): void
    {
        Http::fake([
            'graph.facebook.com/*/111_9/insights*' => Http::response(['data' => [['values' => [['value' => 900]]]]]),
            'graph.facebook.com/*/111_9*' => Http::response(['shares' => ['count' => 4], 'comments' => ['summary' => ['total_count' => 12]], 'reactions' => ['summary' => ['total_count' => 30]]]),
        ]);
        $s = self::social(['kind' => 'riddle', 'format' => 'card', 'title' => 'ধাঁধা', 'texts' => ['facebook' => 'ক'], 'fb_post_id' => '111_9', 'status' => 'posted', 'slot_at' => now()->subHours(3)]);
        $this->assertSame(1, MarketingStats::collect());
        $this->assertSame(['reach' => 900, 'talk' => 16], MarketingStats::sum($s->fresh()));
        $this->assertSame('riddle / card', MarketingStats::table()[0]['what']);

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\Marketing::class)->assertSee('আজ ও কালের পোস্ট')->assertSee('আজকের হোয়াটসঅ্যাপ চ্যানেলের বার্তা')->assertSee('৯০০')->assertSee('নিজে থেকে পোস্ট: চালু');
    }

    public function test_streak_reminder_goes_once_only_to_those_who_asked_and_have_not_played(): void
    {
        config(['services.webpush.public' => 'x', 'services.webpush.private' => 'y']);
        $keeps = User::factory()->create();
        $played = User::factory()->create();
        foreach ([$keeps, $played] as $u) {
            foreach ([2, 1] as $ago) {
                DB::table('game_scores')->insert(['user_id' => $u->id, 'game' => 'word', 'day' => now('Asia/Kolkata')->subDays($ago)->toDateString(), 'points' => 50, 'seconds' => 60, 'created_at' => now()]);
            }
        }
        DB::table('game_scores')->insert(['user_id' => $played->id, 'game' => 'word', 'day' => now('Asia/Kolkata')->toDateString(), 'points' => 50, 'seconds' => 60, 'created_at' => now()]);
        $this->actingAs($keeps)->postJson('/push/', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => rtrim(strtr(base64_encode(str_repeat('a', 65)), '+/', '-_'), '='), 'auth' => rtrim(strtr(base64_encode(str_repeat('b', 16)), '+/', '-_'), '=')], 'add' => ['games']])->assertOk();
        $this->assertSame($keeps->id, PushSubscription::firstOrFail()->user_id);
        $this->assertSame(2, \App\Support\GameScores::streak($keeps)['now']);
        if (\App\Support\WebPush::configured()) {
            Http::fake(['*' => Http::response('', 201)]);
            $this->assertLessThanOrEqual(1, \App\Support\PushNotifier::streakSaver());
        }
        auth()->logout();
        $this->postJson('/push/', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/def', 'keys' => ['p256dh' => rtrim(strtr(base64_encode(str_repeat('a', 65)), '+/', '-_'), '='), 'auth' => rtrim(strtr(base64_encode(str_repeat('b', 16)), '+/', '-_'), '=')], 'add' => ['games']])->assertStatus(422);
    }

    /** D183: links carry a tag for Google Analytics, and the weekly review changes the plan by fixed rules. */
    public function test_links_are_tagged_and_the_weekly_review_tunes_the_plan_from_the_numbers(): void
    {
        $s = self::social(['kind' => 'riddle', 'format' => 'card', 'title' => 'ধাঁধা', 'texts' => ['facebook' => "ধাঁধা\n\n".url('/khela/dhadha').'/']]);
        $tagged = \App\Support\MarketingAnalyst::tag($s->texts['facebook'], $s, 'facebook');
        $this->assertStringEndsWith('/khela/dhadha/?utm_source=facebook&utm_medium=social&utm_campaign=riddle&utm_content=sp'.$s->id, $tagged);
        $this->assertSame('hatpakha.com/khela', \App\Support\MarketingAnalyst::tag('hatpakha.com/khela', $s, 'facebook'), 'Only real links are touched');

        $this->assertSame(\App\Support\MarketingAnalyst::DEFAULTS, \App\Support\MarketingAnalyst::tuning());
        $hours = [];
        foreach (range(0, 23) as $h) {
            $hours[] = ['hour' => (string) $h, 'sessions' => $h === 8 ? 300 : ($h === 21 ? 400 : 20)];   // readers come at 8 and at 21
        }
        $card = ['ga' => ['hours' => $hours], 'social' => ['by_kind' => [
            ['what' => 'riddle / reel', 'posts' => 4, 'reach' => 2000, 'talk' => 40.0], ['what' => 'piece / reel', 'posts' => 3, 'reach' => 500, 'talk' => 5.0]]]];
        [$new, $why] = \App\Support\MarketingAnalyst::tune($card);
        $this->assertSame(['morning' => '08:05', 'evening' => '20:35', 'midday' => 'riddle'], $new);
        $this->assertCount(3, $why);
        $this->assertSame($new, \App\Support\MarketingAnalyst::tuning());
        [, $again] = \App\Support\MarketingAnalyst::tune($card);
        $this->assertSame([], $again, 'Nothing changes twice');
        [$few] = \App\Support\MarketingAnalyst::tune(['ga' => ['hours' => [['hour' => '3', 'sessions' => 5]]], 'social' => ['by_kind' => []]]);
        $this->assertSame($new, $few, 'Too few numbers change nothing');

        // The plan follows: morning card at 08:05, a line reel only on Tuesday and Saturday.
        MarketingPlan::plan(Carbon::parse('2026-11-26', 'Asia/Kolkata'));   // a Thursday
        $this->assertSame('08:05', SocialPost::where('plan_key', '2026-11-26:riddle')->firstOrFail()->slot_at->timezone('Asia/Kolkata')->format('H:i'));
        $this->assertSame('riddle', SocialPost::where('plan_key', '2026-11-26:reel')->firstOrFail()->design['reel']['t']);
        $this->assertSame('20:35', MarketingPlan::evening()->timezone('Asia/Kolkata')->format('H:i'));

        // The review runs without Google or AI set up, keeps the site's own counts, and shows on the page.
        User::factory()->count(2)->create();
        $data = \App\Support\MarketingAnalyst::review();
        $this->assertGreaterThanOrEqual(2, $data['card']['site']['signups']['now']);
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\Marketing::class)->assertSee('সপ্তাহের হিসাব')->assertSee('নতুন সদস্য')->assertSee('০৮:০৫');
    }

    /** D184: invite links and the বন্ধু medal, the Friday leaderboard post, and the weekly letter's come-back blocks. */
    public function test_invites_count_only_friends_who_read_or_play_and_earn_the_friend_medal(): void
    {
        $me = User::factory()->create();
        $code = \App\Support\Invites::code($me);
        $this->assertSame($code, \App\Support\Invites::code($me->fresh()), 'One link for good');
        $this->get('/bondhu/'.$code.'/')->assertRedirect()->assertCookie(\App\Support\Invites::COOKIE, $code);
        $this->get('/bondhu/'.str_repeat('Z', 8).'/')->assertRedirect()->assertCookie(\App\Support\Invites::COOKIE, '');

        $friend = User::factory()->create();
        $request = \Illuminate\Http\Request::create('/register', 'POST');
        $request->cookies->set(\App\Support\Invites::COOKIE, $code);
        \App\Support\Invites::claim($friend, $request);
        \App\Support\Invites::claim($me, $request);
        $this->assertSame($me->id, $friend->fresh()->invited_by);
        $this->assertNull($me->fresh()->invited_by, 'Nobody invites themselves');
        $this->assertSame(0, \App\Support\Invites::count($me), 'Signing up alone is not enough');
        DB::table('game_scores')->insert(['user_id' => $friend->id, 'game' => 'word', 'day' => now('Asia/Kolkata')->toDateString(), 'points' => 50, 'seconds' => 60, 'created_at' => now()]);
        $this->assertSame(1, \App\Support\Invites::count($me));
        $badge = collect(\App\Support\Badges::for($me))->firstWhere('key', 'friend');
        $this->assertSame(1, $badge['tier']);
        $this->assertStringContainsString('<svg', \App\Support\Badges::medalSvg('friend', 1, 200));

        $this->actingAs($me)->get('/account/profile/')->assertOk()->assertSee('বন্ধুকে হাতপাখায় আনুন')->assertSee('/bondhu/'.$code.'/')->assertSee('https://wa.me/?text=', false);
    }

    public function test_friday_names_the_top_three_only_when_three_show_their_names(): void
    {
        $friday = Carbon::parse('2026-11-20', 'Asia/Kolkata');
        $users = User::factory()->count(3)->create();
        foreach ($users as $i => $u) {
            DB::table('game_scores')->insert(['user_id' => $u->id, 'game' => 'word', 'day' => now('Asia/Kolkata')->toDateString(), 'points' => 100 - $i * 10, 'seconds' => 60, 'created_at' => now()]);
        }
        $users[2]->forceFill(['board_hidden' => true])->save();
        MarketingPlan::plan($friday);
        $this->assertFalse(SocialPost::where('plan_key', '2026-11-20:board')->exists(), 'Two names are not a top three');
        $users[2]->forceFill(['board_hidden' => false])->save();
        MarketingPlan::plan($friday);
        $board = SocialPost::where('plan_key', '2026-11-20:board')->firstOrFail();
        $this->assertSame('17:00', $board->slot_at->timezone('Asia/Kolkata')->format('H:i'));
        $this->assertCount(3, $board->design['slides'][0]['rows']);
        $this->assertStringContainsString($users[0]->name, $board->texts['facebook']);
    }

    public function test_the_weekly_letter_carries_come_back_blocks_with_their_own_links(): void
    {
        $blocks = \App\Ai\EditorJobs::comeBack();
        $this->assertSame('আজকের ধাঁধা', $blocks[0]['title']);
        $n = \App\Models\Newsletter::create(['subject' => 'চিঠি', 'intro' => 'ক', 'closing' => 'খ', 'audience' => 'readers', 'source' => 'ai_weekly', 'status' => 'draft', 'items' => []]);
        $n->forceFill(['extras' => $blocks])->save();
        $this->assertStringContainsString('/khela/dhadha/?utm_source=hatpakha-chithi', \App\Support\NewsletterLinks::target($n->fresh(), 'x0'));
        $html = view('emails.newsletter', ['newsletter' => $n->fresh(), 'deliveryId' => null, 'unsubscribeUrl' => url('/')])->render();
        $this->assertStringContainsString('এ সপ্তাহে আরও', $html);
        $this->assertStringContainsString(e($blocks[0]['line']), $html);
    }

    /** D185: nothing from the children's section goes out by itself; an old story is shared every day. */
    public function test_kids_pieces_are_left_out_and_old_stories_are_shared(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '111_8'])]);
        $kids = \App\Models\Section::firstOrCreate(['slug' => \App\Models\Section::KIDS], ['name_bn' => 'ছোটদের হাতপাখা', 'name_en' => 'Kids', 'format' => 'prose']);
        $piece = $this->makePost(['slug' => 'chhoto', 'title' => 'টুনটুনির গল্প']);
        $piece->forceFill(['section_id' => $kids->id])->saveQuietly();
        $this->assertTrue(\App\Ai\Marketer::kids($piece->fresh()));
        $this->assertNull(\App\Ai\Marketer::forPiece($piece->fresh()), 'Not made by itself');

        $s = self::social(['kind' => 'piece', 'subject_type' => $piece->getMorphClass(), 'subject_id' => $piece->id, 'title' => $piece->title,
            'texts' => ['facebook' => 'ক', 'instagram' => '', 'quote' => 'খ'], 'slot_at' => now()->subMinutes(5)]);
        $this->assertSame(0, MarketingPlan::run());
        $this->assertNull($s->fresh()->fb_post_id);
        $this->assertNull($s->fresh()->slot_at, 'Taken out of the queue; still there for the owner to post by hand');
        $this->assertSame(0, MarketingPlan::run(), 'And not put back');
        MarketingPlan::plan(Carbon::parse('2026-11-21', 'Asia/Kolkata'));
        $this->assertNotSame('line', SocialPost::where('plan_key', '2026-11-21:reel')->first()?->design['reel']['t'] ?? null, 'No reel from a children\'s piece');
    }

    /** Owner, 2026-11-14: stop or delete a planned post; see where it went or why not; old posts never pile up on one evening. */
    public function test_planned_posts_can_be_stopped_and_show_what_happened_on_each_network(): void
    {
        Http::fake(['graph.facebook.com/*/v1*' => Http::response(['status' => ['video_status' => 'ready'], 'permalink_url' => '/reel/777/']), 'graph.facebook.com/*' => Http::response([])]);
        $wait = self::social(['kind' => 'riddle', 'format' => 'card', 'title' => 'ধাঁধা', 'texts' => ['facebook' => 'ক', 'instagram' => ''], 'slot_at' => now()->addHours(3), 'media' => ['images' => ['media/cards/x.jpg']]]);
        $bad = self::social(['kind' => 'piece', 'format' => 'reel', 'title' => 'রিল', 'texts' => ['facebook' => 'ক', 'instagram' => 'খ'], 'slot_at' => now()->addHours(4), 'media' => ['video' => 'media/cards/r.mp4'],
            'fb_post_id' => 'v1', 'ig_error' => 'Media upload has failed']);
        \App\Models\AuditLog::record('marketing.facebook', null, ['social_post' => $bad->id, 'fb' => 'v1']);

        $where = collect(\App\Filament\Pages\Marketing::where($bad))->keyBy('net');
        $this->assertStringContainsString('ভিডিও তৈরি করছে', $where['Facebook']['text'], 'Accepted is not yet on the Page');
        $this->assertStringContainsString('নিজে থেকে', $where['Facebook']['text']);
        $this->assertTrue($where['Instagram']['bad']);
        $this->assertStringContainsString('Media upload has failed', $where['Instagram']['text']);
        $this->assertSame(1, FacebookPage::checkReels());
        $where = collect(\App\Filament\Pages\Marketing::where($bad->fresh()))->keyBy('net');
        $this->assertSame('https://www.facebook.com/reel/777/', $where['Facebook']['link']);
        $this->assertSame('এখানে যাবে না', collect(\App\Filament\Pages\Marketing::where($wait))->keyBy('net')['Instagram']['text']);

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $page = \Livewire\Livewire::test(\App\Filament\Pages\Marketing::class)->assertSee('থামান')->assertSee('খুলে দেখুন')->assertSee('Media upload has failed');
        $page->call('dismiss', $wait->id)->assertSee('থামানো আছে')->assertSee('আবার চালু');
        Carbon::setTestNow(now()->addHours(4));
        $this->assertSame(0, MarketingPlan::run(), 'A stopped post does not go out');
        $page->call('deleteSocial', $wait->id);
        $this->assertNull(SocialPost::find($wait->id));
    }

    public function test_older_posts_wait_their_turn_one_an_evening(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([])]);
        foreach (range(1, 8) as $i) {
            tap(self::social(['kind' => 'custom', 'title' => 'old'.$i, 'texts' => ['facebook' => 'ক', 'instagram' => '']]))->forceFill(['created_at' => now()->subDays(5)])->save();
        }
        MarketingPlan::run();
        MarketingPlan::run();
        $slots = SocialPost::whereNull('plan_key')->whereNotNull('slot_at')   // the seeded announcement is an older post too
            ->pluck('slot_at')->map(fn ($t) => $t->timezone('Asia/Kolkata')->format('d H:i'))->sort()->values()->all();
        $this->assertSame(['19 21:15', '20 21:15', '21 21:15'], $slots, 'Three evenings ahead, one older post each; the rest wait');
        $fresh = MarketingPlan::dress(self::social(['kind' => 'custom', 'title' => 'new', 'texts' => ['facebook' => 'ক', 'instagram' => '']]));
        $this->assertSame('19 19:35', $fresh->slot_at->timezone('Asia/Kolkata')->format('d H:i'), 'Today\'s own post still gets the first evening time');
    }
}
