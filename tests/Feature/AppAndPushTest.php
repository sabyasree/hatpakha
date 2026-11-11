<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\FollowNotifier;
use App\Support\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D127: installable site (manifest, service worker, offline page) and phone notifications without an extra package. */
class AppAndPushTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

    protected function tearDown(): void
    {
        WebPush::$fixed = null;
        parent::tearDown();
    }

    private function keys(): void
    {
        [$pub, $priv] = WebPush::newKeys();
        config(['services.webpush.public_key' => $pub, 'services.webpush.private_key' => $priv, 'services.webpush.subject' => 'https://hatpakha.com/contact-us/']);
    }

    /** A browser's own key pair, as a phone would make it. */
    private function browser(): array
    {
        [$pub, $priv] = WebPush::newKeys();

        return ['p256dh' => $pub, 'auth' => WebPush::b64e(random_bytes(16)), 'private' => $priv];
    }

    public function test_pages_are_installable_and_the_worker_is_never_cached(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="manifest" href="/manifest.webmanifest">', $html);
        $this->assertStringContainsString('apple-touch-icon', $html);
        $this->assertStringContainsString('data-install hidden', $html);

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('হাতপাখা', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            [$w] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame((int) explode('x', $icon['sizes'])[0], $w);
        }
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        $this->assertFileExists(public_path('offline.html'));

        $sw = $this->get('/sw.js')->assertOk();
        $this->assertStringStartsWith('application/javascript', $sw->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $sw->headers->get('Cache-Control'));
        $this->assertSame([], $sw->headers->getCookies(), 'No session for the worker');
        $js = $sw->getContent();
        $this->assertMatchesRegularExpression("/^const VERSION = '[0-9a-f]{12}';/", $js);
        foreach (['desk', 'account', 'login', 'submit', 'livewire'] as $private) {
            $this->assertStringContainsString($private.'|', $js, $private.' is never touched by the worker');
        }
        $this->assertStringContainsString('\\bpublic\\b', $js, 'Only shared (guest) pages are kept offline, never personal ones');
        $this->assertStringContainsString('setAppBadge', $js, 'The app icon shows how many notifications wait');
    }

    public function test_encryption_matches_rfc_8291(): void
    {
        WebPush::$fixed = ['private' => WebPush::b64d('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'),
            'public' => WebPush::b64d('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8'),
            'salt' => WebPush::b64d('DGv6ra1nlYgDCS1FRnbzlw')];
        $body = WebPush::encrypt('When I grow up, I want to be a watermelon',
            WebPush::b64d('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'), WebPush::b64d('BTBZMqHH6r4Tts7J_aSIgg'));
        $this->assertSame('DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN', WebPush::b64e($body));
    }

    public function test_vapid_token_is_a_valid_es256_signature(): void
    {
        $this->keys();
        [$h, $c, $sig] = explode('.', WebPush::vapidToken('https://fcm.googleapis.com'));
        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(WebPush::b64d($h), true));
        $claims = json_decode(WebPush::b64d($c), true);
        $this->assertSame('https://fcm.googleapis.com', $claims['aud']);
        $this->assertLessThanOrEqual(time() + 24 * 3600, $claims['exp']);
        $raw = WebPush::b64d($sig);
        $this->assertSame(64, strlen($raw));
        // back to DER and check with the public key
        $int = fn ($b) => ($b = ltrim($b, "\0")) !== '' && ord($b[0]) > 0x7f ? "\0".$b : ($b === '' ? "\0" : $b);
        $r = $int(substr($raw, 0, 32));
        $s = $int(substr($raw, 32));
        $der = "\x30".chr(4 + strlen($r) + strlen($s))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').WebPush::b64d(config('services.webpush.public_key'))), 64, "\n")."-----END PUBLIC KEY-----\n";
        $this->assertSame(1, openssl_verify($h.'.'.$c, $der, $pem, OPENSSL_ALGO_SHA256));
    }

    public function test_readers_turn_it_on_and_off_only_for_real_push_services(): void
    {
        $this->keys();
        $user = User::factory()->create(['role' => Role::Reader, 'email_verified_at' => now()]);
        $b = $this->browser();
        $keys = ['p256dh' => $b['p256dh'], 'auth' => $b['auth']];
        $this->actingAs($user)->get('/account/settings/')->assertOk()->assertSee('<meta name="hp-push-key" content="'.config('services.webpush.public_key').'">', false)
            ->assertSee('data-push-topic="account"', false)->assertSee('ফোনে খবর');

        $this->postJson('/push/', ['endpoint' => 'https://evil.example/hook', 'keys' => $keys, 'add' => ['account']])->assertStatus(422);
        $this->postJson('/push/', ['endpoint' => 'https://169.254.169.254/x', 'keys' => $keys, 'add' => ['account']])->assertStatus(422);
        $this->postJson('/push/', ['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => 'short', 'auth' => $b['auth']], 'add' => ['account']])->assertStatus(422);
        $this->postJson('/push/', ['endpoint' => self::ENDPOINT, 'keys' => $keys, 'add' => ['anything']])->assertStatus(422);
        $this->assertSame(0, PushSubscription::count());

        $this->postJson('/push/', ['endpoint' => self::ENDPOINT, 'keys' => $keys, 'add' => ['account']], ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14)'])->assertOk()->assertJson(['topics' => ['account']]);
        $this->postJson('/push/', ['endpoint' => self::ENDPOINT, 'keys' => $keys, 'add' => ['daily']])->assertOk()->assertJson(['topics' => ['account', 'daily']]);
        $this->assertSame(1, PushSubscription::count(), 'The same browser is stored once');
        $this->assertSame($user->id, PushSubscription::first()->user_id);
        $this->assertSame('Android', PushSubscription::first()->device);
        $this->postJson('/push/state/', ['endpoint' => self::ENDPOINT])->assertJson(['topics' => ['account', 'daily']]);

        $this->postJson('/push/off/', ['endpoint' => self::ENDPOINT, 'topics' => ['account']])->assertJson(['topics' => ['daily']]);
        $this->assertNull(PushSubscription::first()->user_id, 'Turning off the follows unlinks the account');
        $this->postJson('/push/off/', ['endpoint' => self::ENDPOINT, 'topics' => ['daily']])->assertJson(['topics' => []]);
        $this->assertSame(0, PushSubscription::count(), 'Nothing left: the browser is forgotten');
        $this->postJson('/push/state/', ['endpoint' => self::ENDPOINT])->assertJson(['topics' => []]);
    }

    public function test_readers_without_an_account_follow_a_writer_a_serial_or_the_daily_note(): void
    {
        $this->keys();
        $writer = Author::create(['slug' => 'mou', 'display_name' => 'মৌ']);
        $desk = Author::create(['slug' => 'desk', 'display_name' => 'ডেস্ক', 'is_desk' => true]);
        $this->makePost(['status' => 'published', 'published_at' => now()->subDay()], $writer);
        $b = $this->browser();
        $keys = ['p256dh' => $b['p256dh'], 'auth' => $b['auth']];

        $this->get('/author/mou/')->assertOk()->assertSee('data-push-topic="author:'.$writer->id.'"', false);
        $this->get('/app/')->assertOk()->assertSee('data-push-topic="daily"', false)->assertSee('id="iphone"', false)->assertSee('Add to Home Screen');

        $this->postJson('/push/', ['endpoint' => self::ENDPOINT, 'keys' => $keys, 'add' => ['account']])->assertStatus(422);   // no account to link
        $this->postJson('/push/', ['endpoint' => self::ENDPOINT, 'keys' => $keys, 'add' => ['author:'.$desk->id]])->assertStatus(422);
        $this->postJson('/push/', ['endpoint' => self::ENDPOINT, 'keys' => $keys, 'add' => ['author:999999']])->assertStatus(422);
        $this->postJson('/push/', ['endpoint' => self::ENDPOINT, 'keys' => $keys, 'add' => ['author:'.$writer->id]])->assertOk();
        $this->postJson('/push/', ['endpoint' => self::ENDPOINT, 'keys' => $keys, 'add' => ['daily']])->assertOk()->assertJson(['topics' => ['author:'.$writer->id, 'daily']]);
        $this->assertNull(PushSubscription::first()->user_id);
    }

    public function test_the_app_page_and_the_footer_buttons(): void
    {
        $this->get('/app')->assertRedirect(url('/app').'/');
        $this->get('/app/')->assertOk()->assertSee('<link rel="canonical" href="'.url('/app').'/">', false)->assertSee('id="android"', false)->assertSee('id="computer"', false);
        $home = $this->get('/')->getContent();
        $this->assertStringContainsString('data-app-badges', $home);
        $this->assertStringContainsString(url('/app').'/#iphone', $home);
        $this->assertStringContainsString('data-install-now', $home);
        $this->assertStringContainsString(url('/app').'/', $this->get('/sitemap-pages.xml')->getContent());
    }

    public function test_followers_get_a_push_and_dead_subscriptions_are_removed(): void
    {
        $this->keys();
        $this->asLiveSite();
        config(['mail.default' => 'array']);
        $reader = User::factory()->create(['role' => Role::Reader, 'email_verified_at' => now()]);
        $b = $this->browser();
        PushSubscription::create(['user_id' => $reader->id, 'endpoint' => self::ENDPOINT, 'endpoint_hash' => PushSubscription::hashOf(self::ENDPOINT), 'p256dh' => $b['p256dh'], 'auth' => $b['auth'], 'topics' => ['account']]);
        $gone = 'https://updates.push.services.mozilla.com/wpush/v2/gone';
        PushSubscription::create(['user_id' => $reader->id, 'endpoint' => $gone, 'endpoint_hash' => PushSubscription::hashOf($gone), 'p256dh' => $b['p256dh'], 'auth' => $b['auth'], 'topics' => ['account']]);
        // A guest phone that follows the same writer by topic, and one that follows someone else.
        $other = 'https://web.push.apple.com/QOther';
        PushSubscription::create(['endpoint' => $other, 'endpoint_hash' => PushSubscription::hashOf($other), 'p256dh' => $b['p256dh'], 'auth' => $b['auth'], 'topics' => ['author:999999']]);

        $writer = Author::create(['slug' => 'mou', 'display_name' => 'মৌ']);
        $guest = 'https://web.push.apple.com/QGuest';
        PushSubscription::create(['endpoint' => $guest, 'endpoint_hash' => PushSubscription::hashOf($guest), 'p256dh' => $b['p256dh'], 'auth' => $b['auth'], 'topics' => ['author:'.$writer->id]]);
        DB::table('follows')->insert(['user_id' => $reader->id, 'author_id' => $writer->id, 'created_at' => now()]);
        $post = $this->makePost(['status' => 'published', 'slug' => 'megh', 'title' => 'মেঘ', 'published_at' => now()->subHour()], $writer);

        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201), 'web.push.apple.com/*' => Http::response('', 201), 'updates.push.services.mozilla.com/*' => Http::response('', 410)]);
        FollowNotifier::run();
        Http::assertSent(fn (Request $r) => $r->url() === $guest);
        Http::assertNotSent(fn (Request $r) => $r->url() === $other);

        Http::assertSent(function (Request $r) {
            return $r->url() === self::ENDPOINT && $r->hasHeader('Content-Encoding', 'aes128gcm') && str_starts_with($r->header('Authorization')[0], 'vapid t=')
                && $r->hasHeader('TTL') && strlen($r->body()) > 86;
        });
        $this->assertSame(3, PushSubscription::count(), 'A subscription the browser dropped (410) is removed');
        $this->assertNotNull(PushSubscription::where('endpoint_hash', PushSubscription::hashOf(self::ENDPOINT))->first()->last_sent_at);
        $this->assertNotNull($post->fresh()->followers_notified_at);
    }

    public function test_the_message_decrypts_on_the_phone(): void
    {
        $b = $this->browser();
        $body = WebPush::encrypt('{"title":"মেঘ"}', WebPush::b64d($b['p256dh']), WebPush::b64d($b['auth']));
        // What the browser does (RFC 8291 §3.4), with its own private key.
        $salt = substr($body, 0, 16);
        $asPublic = substr($body, 21, 65);
        $priv = openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode(hex2bin('30770201010420').WebPush::b64d($b['private']).hex2bin('a00a06082a8648ce3d030107a144034200').WebPush::b64d($b['p256dh'])), 64, "\n")."-----END EC PRIVATE KEY-----\n");
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$asPublic), 64, "\n")."-----END PUBLIC KEY-----\n";
        $ikm = hash_hkdf('sha256', openssl_pkey_derive($pem, $priv, 32), 32, "WebPush: info\0".WebPush::b64d($b['p256dh']).$asPublic, WebPush::b64d($b['auth']));
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $data = substr($body, 86);
        $plain = openssl_decrypt(substr($data, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($data, -16));
        $this->assertSame("{\"title\":\"মেঘ\"}\x02", $plain);
    }

    public function test_the_daily_note_lists_the_days_pieces_once(): void
    {
        $this->keys();
        $this->asLiveSite();
        $b = $this->browser();
        PushSubscription::create(['endpoint' => self::ENDPOINT, 'endpoint_hash' => PushSubscription::hashOf(self::ENDPOINT), 'p256dh' => $b['p256dh'], 'auth' => $b['auth'], 'topics' => ['daily']]);
        $no = 'https://web.push.apple.com/QNo';
        PushSubscription::create(['endpoint' => $no, 'endpoint_hash' => PushSubscription::hashOf($no), 'p256dh' => $b['p256dh'], 'auth' => $b['auth'], 'topics' => ['author:1']]);
        Http::fake(['*' => Http::response('', 201)]);

        $this->makePost(['status' => 'published', 'title' => 'মেঘ', 'published_at' => now()->subHours(3)]);
        $this->makePost(['status' => 'published', 'title' => 'নদী', 'published_at' => now()->subHours(2)]);
        $this->makePost(['status' => 'draft', 'title' => 'খসড়া']);
        $this->assertSame(1, \App\Support\PushNotifier::daily());
        Http::assertSentCount(1);
        $this->assertSame(0, \App\Support\PushNotifier::daily(), 'Nothing new since the last note: nothing sent');

        config(['services.webpush.daily' => false]);
        $this->makePost(['status' => 'published', 'title' => 'আলো', 'published_at' => now()]);
        $this->travel(1)->minutes();
        $this->assertSame(0, \App\Support\PushNotifier::daily(), 'PUSH_DAILY=false stops it');
    }

    public function test_staging_only_pushes_to_desk_staff(): void
    {
        $this->keys();
        $reader = User::factory()->create(['role' => Role::Reader]);
        $editor = User::factory()->create(['role' => Role::Editor]);
        foreach ([$reader, $editor] as $i => $u) {
            $b = $this->browser();
            $e = self::ENDPOINT.$i;
            PushSubscription::create(['user_id' => $u->id, 'endpoint' => $e, 'endpoint_hash' => PushSubscription::hashOf($e), 'p256dh' => $b['p256dh'], 'auth' => $b['auth'], 'topics' => ['account', 'daily']]);
        }
        $g = self::ENDPOINT.'guest';
        PushSubscription::create(['endpoint' => $g, 'endpoint_hash' => PushSubscription::hashOf($g), 'p256dh' => $b['p256dh'], 'auth' => $b['auth'], 'topics' => ['daily']]);
        Http::fake(['*' => Http::response('', 201)]);
        config(['mail.off_live' => null]);
        $this->assertSame(0, WebPush::toUser($reader, ['title' => 'x']));
        $this->assertSame(1, WebPush::toUser($editor, ['title' => 'x']));
        $this->makePost(['status' => 'published', 'published_at' => now()->subHour()]);
        $this->assertSame(1, \App\Support\PushNotifier::daily(), 'Staging: only the editor\'s phone, never guests or readers');
    }
}
