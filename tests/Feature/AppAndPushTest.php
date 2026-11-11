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
        $this->actingAs($user)->get('/account/settings/')->assertOk()->assertSee('data-push-key="'.config('services.webpush.public_key').'"', false)->assertSee('ফোনে খবর');

        $this->postJson('/account/push/', ['endpoint' => 'https://evil.example/hook', 'keys' => ['p256dh' => $b['p256dh'], 'auth' => $b['auth']]])->assertStatus(422);
        $this->postJson('/account/push/', ['endpoint' => 'https://169.254.169.254/x', 'keys' => ['p256dh' => $b['p256dh'], 'auth' => $b['auth']]])->assertStatus(422);
        $this->postJson('/account/push/', ['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => 'short', 'auth' => $b['auth']]])->assertStatus(422);
        $this->assertSame(0, PushSubscription::count());

        $this->postJson('/account/push/', ['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => $b['p256dh'], 'auth' => $b['auth']]], ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14)'])->assertOk();
        $this->postJson('/account/push/', ['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => $b['p256dh'], 'auth' => $b['auth']]])->assertOk();
        $this->assertSame(1, PushSubscription::count(), 'The same browser is stored once');

        // Another person can't remove someone else's browser by knowing the address.
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->postJson('/account/push/off/', ['endpoint' => self::ENDPOINT])->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->actingAs($user)->postJson('/account/push/off/', ['endpoint' => self::ENDPOINT])->assertOk();
        $this->assertSame(0, PushSubscription::count());

        auth()->logout();
        $this->postJson('/account/push/', ['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => $b['p256dh'], 'auth' => $b['auth']]])->assertUnauthorized();
    }

    public function test_followers_get_a_push_and_dead_subscriptions_are_removed(): void
    {
        $this->keys();
        $this->asLiveSite();
        config(['mail.default' => 'array']);
        $reader = User::factory()->create(['role' => Role::Reader, 'email_verified_at' => now()]);
        $b = $this->browser();
        PushSubscription::create(['user_id' => $reader->id, 'endpoint' => self::ENDPOINT, 'endpoint_hash' => PushSubscription::hashOf(self::ENDPOINT), 'p256dh' => $b['p256dh'], 'auth' => $b['auth']]);
        $gone = 'https://updates.push.services.mozilla.com/wpush/v2/gone';
        PushSubscription::create(['user_id' => $reader->id, 'endpoint' => $gone, 'endpoint_hash' => PushSubscription::hashOf($gone), 'p256dh' => $b['p256dh'], 'auth' => $b['auth']]);

        $writer = Author::create(['slug' => 'mou', 'display_name' => 'মৌ']);
        DB::table('follows')->insert(['user_id' => $reader->id, 'author_id' => $writer->id, 'created_at' => now()]);
        $post = $this->makePost(['status' => 'published', 'slug' => 'megh', 'title' => 'মেঘ', 'published_at' => now()->subHour()], $writer);

        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201), 'updates.push.services.mozilla.com/*' => Http::response('', 410)]);
        FollowNotifier::run();

        Http::assertSent(function (Request $r) {
            return $r->url() === self::ENDPOINT && $r->hasHeader('Content-Encoding', 'aes128gcm') && str_starts_with($r->header('Authorization')[0], 'vapid t=')
                && $r->hasHeader('TTL') && strlen($r->body()) > 86;
        });
        $this->assertSame(1, PushSubscription::count(), 'A subscription the browser dropped (410) is removed');
        $this->assertNotNull(PushSubscription::first()->last_sent_at);
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

    public function test_staging_only_pushes_to_desk_staff(): void
    {
        $this->keys();
        $reader = User::factory()->create(['role' => Role::Reader]);
        $editor = User::factory()->create(['role' => Role::Editor]);
        foreach ([$reader, $editor] as $i => $u) {
            $b = $this->browser();
            $e = self::ENDPOINT.$i;
            PushSubscription::create(['user_id' => $u->id, 'endpoint' => $e, 'endpoint_hash' => PushSubscription::hashOf($e), 'p256dh' => $b['p256dh'], 'auth' => $b['auth']]);
        }
        Http::fake(['*' => Http::response('', 201)]);
        config(['mail.off_live' => null]);
        $this->assertSame(0, WebPush::toUser($reader, ['title' => 'x']));
        $this->assertSame(1, WebPush::toUser($editor, ['title' => 'x']));
    }
}
