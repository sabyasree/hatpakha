<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\InstagramPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Instagram (owner, 2026-11-12): the browser's card (or the piece's own picture) with the Instagram caption. */
class InstagramPageTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.facebook_page.id' => '307497312677802', 'services.facebook_page.token' => 'page-token', 'services.facebook_page.app_secret' => 'secret',
            'services.facebook_page.instagram_id' => '17841400000000000', 'mail.off_live' => 'allow']);
        SocialPost::query()->update(['status' => 'dismissed']);   // the seeded relaunch post stays out
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function social(?string $picture = null): SocialPost
    {
        $path = null;
        if ($picture) {
            @mkdir(public_path('wp-content/uploads/test-ig'), 0775, true);
            $img = imagecreatetruecolor(...explode('x', $picture));
            $path = 'wp-content/uploads/test-ig/'.uniqid().'.jpg';
            imagejpeg($img, public_path($path));
            $this->files[] = public_path($path);
        }
        $post = $this->makePost(['slug' => 'ig-'.uniqid(), 'title' => 'নদীর গান', 'featured_image_path' => $path]);

        return SocialPost::create(['kind' => 'piece', 'subject_type' => $post->getMorphClass(), 'subject_id' => $post->id, 'title' => 'নদীর গান',
            'texts' => ['facebook' => 'f '.$post->url(), 'whatsapp' => 'w', 'instagram' => "নদীর গান\n#হাতপাখা"]]);
    }

    private function fakeInstagram(): void
    {
        Http::fake([
            '*/17841400000000000/media' => Http::response(['id' => 'container-1']),
            '*/17841400000000000/media_publish' => Http::response(['id' => 'ig-media-1']),
            '*/ig-media-1*' => Http::response(['permalink' => 'https://www.instagram.com/p/abc/']),
            '*/307497312677802/feed' => Http::response(['id' => 'fb-1']),
        ]);
    }

    public function test_the_pieces_own_picture_goes_up_with_the_caption_and_only_once(): void
    {
        $this->fakeInstagram();
        $s = $this->social('1200x800');
        [$ok, $where] = InstagramPage::publish($s);
        $this->assertTrue($ok);
        $this->assertSame('https://www.instagram.com/p/abc/', $where);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/media') && str_contains($r['image_url'], '/wp-content/uploads/test-ig/') && $r['caption'] === "নদীর গান\n#হাতপাখা"
            && $r['appsecret_proof'] === hash_hmac('sha256', 'page-token', 'secret'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/media_publish') && $r['creation_id'] === 'container-1');
        $this->assertSame('ig-media-1', $s->fresh()->ig_media_id);
        $this->assertFalse(InstagramPage::publish($s->fresh())[0], 'Once only');
    }

    public function test_no_picture_instagram_accepts_means_no_post(): void
    {
        $this->fakeInstagram();
        $this->assertSame('ইনস্টাগ্রামের জন্য ছবি নেই', InstagramPage::postable($this->social()));
        $this->assertSame('ইনস্টাগ্রামের জন্য ছবি নেই', InstagramPage::postable($this->social('600x1400')), 'Too tall for Instagram');
        Http::assertNothingSent();
    }

    public function test_the_card_from_the_desk_goes_up_and_automatic_mode_posts_to_both(): void
    {
        $this->fakeInstagram();
        $s = $this->social();   // no picture of its own: the card is the picture
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\Marketing::class)
            ->assertSee('ইনস্টাগ্রামে দিন')
            ->set('cardJpeg', UploadedFile::fake()->image('card.jpg', 1080, 1080))
            ->call('instagramNow', $s->id, true);
        $s->refresh();
        $this->assertStringStartsWith('media/cards/', $s->texts['card_image']);
        $this->files[] = public_path($s->texts['card_image']);
        $this->assertSame('ig-media-1', $s->ig_media_id);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/media') && str_contains($r['image_url'], '/media/cards/'));

        // Automatic mode (owner's switch): Facebook, then Instagram when there is a picture.
        $s->forceFill(['status' => 'posted'])->save();   // the oldest one is done, so the new one is next
        $auto = $this->social('1080x1080');
        \App\Models\Setting::put(\App\Support\FacebookPage::AUTO, '1');
        $this->assertSame($auto->id, \App\Support\FacebookPage::autoPost()?->id);
        $this->assertSame('fb-1', $auto->fresh()->fb_post_id);
        $this->assertSame('ig-media-1', $auto->fresh()->ig_media_id);
    }
}
