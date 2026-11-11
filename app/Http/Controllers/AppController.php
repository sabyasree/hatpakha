<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\PushSubscription;
use App\Models\Serial;
use App\Seo\Seo;
use App\Support\Url;
use App\Support\WebPush;
use Illuminate\Http\Request;

/**
 * D127/D128: Hatpakha as an app. The service worker, the /app/ page, and phone notifications per browser:
 * with or without an account. A browser's topics: "account" (the follows of the signed-in reader),
 * "daily" (once a day, the day's new pieces), "serial:{id}" and "author:{id}".
 * Knowing a browser's push address is what proves it is yours (it is long and secret to that browser).
 */
class AppController extends Controller
{
    /** The service worker, with this release's version on top so browsers pick up a new one after each deploy. Never cached. */
    public function serviceWorker()
    {
        $manifest = public_path('build/manifest.json');
        $version = substr(hash('sha256', (is_file($manifest) ? (string) filemtime($manifest) : '0').filemtime(resource_path('js/sw.js'))), 0, 12);
        $js = "const VERSION = '{$version}';\n".file_get_contents(resource_path('js/sw.js'));

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** /app/: how to keep Hatpakha on a phone or computer, and the daily notification. */
    public function page()
    {
        $seo = new Seo(
            title: 'হাতপাখা ফোনে রাখুন — অ্যাপের মতো, বিনামূল্যে',
            description: 'হাতপাখা ফোনের হোম স্ক্রিনে রাখুন: এক ছোঁয়ায় খুলবে, আগে পড়া লেখা ইন্টারনেট ছাড়াও পড়া যাবে, নতুন লেখার খবর ফোনে। Android, iPhone আর কম্পিউটারে।',
            canonical: Url::to('/app'),
        );

        return view('app', ['seo' => $seo]);
    }

    /** Turn on topics for this browser (creating its subscription if needed). */
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_-]+={0,2}$/'],
            'keys.auth' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+={0,2}$/'],
            'add' => ['array', 'max:5'],
            'add.*' => ['string', 'max:30'],
        ]);
        if (! WebPush::allowedEndpoint($data['endpoint']) || strlen(WebPush::b64d(rtrim($data['keys']['p256dh'], '='))) !== 65
            || strlen(WebPush::b64d(rtrim($data['keys']['auth'], '='))) !== 16) {
            return response()->json(['ok' => false], 422);
        }
        $add = $this->validTopics($data['add'] ?? [], $request);
        if ($add === null) {
            return response()->json(['ok' => false], 422);
        }

        $sub = PushSubscription::firstOrNew(['endpoint_hash' => PushSubscription::hashOf($data['endpoint'])]);
        $topics = array_values(array_unique(array_merge($sub->topics ?? [], $add)));
        if (count($topics) > 60) {
            return response()->json(['ok' => false, 'error' => 'too_many'], 422);
        }
        $ua = (string) $request->userAgent();
        $sub->fill([
            'endpoint' => $data['endpoint'], 'p256dh' => rtrim($data['keys']['p256dh'], '='), 'auth' => rtrim($data['keys']['auth'], '='),
            'topics' => $topics, 'failures' => 0,
            'device' => match (true) {
                str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iPhone/iPad',
                str_contains($ua, 'Android') => 'Android',
                str_contains($ua, 'Windows') => 'Windows',
                str_contains($ua, 'Mac OS') => 'Mac',
                default => $sub->device,
            },
        ]);
        if (in_array('account', $add, true)) {
            $sub->user_id = $request->user()->id;   // a browser belongs to whoever linked it last
        }
        $sub->save();
        if ($sub->user_id && PushSubscription::where('user_id', $sub->user_id)->count() > 10) {   // old phones drop off
            PushSubscription::where('user_id', $sub->user_id)->whereKeyNot($sub->id)->oldest('updated_at')->first()?->delete();
        }

        return response()->json(['ok' => true, 'topics' => $sub->topics]);
    }

    /** Turn topics off; with no topics given (or none left), forget the browser. */
    public function unsubscribe(Request $request)
    {
        $sub = $this->find($request);
        if (! $sub) {
            return response()->json(['ok' => true, 'topics' => []]);
        }
        $remove = array_filter((array) $request->input('topics', []), 'is_string');
        $left = $remove ? array_values(array_diff($sub->topics ?? [], $remove)) : [];
        if (! $left) {
            $sub->delete();

            return response()->json(['ok' => true, 'topics' => []]);
        }
        $sub->forceFill(['topics' => $left, 'user_id' => in_array('account', $left, true) ? $sub->user_id : null])->save();

        return response()->json(['ok' => true, 'topics' => $left]);
    }

    /** What this browser has on (the buttons on a page ask once). */
    public function state(Request $request)
    {
        $sub = $this->find($request);

        return response()->json(['topics' => $sub?->topics ?? []]);
    }

    /** "Send me a test": only to this one browser. */
    public function test(Request $request)
    {
        $sub = $this->find($request);
        $ok = $sub && WebPush::send($sub, ['title' => 'হাতপাখা', 'body' => 'ফোনে খবর চালু আছে। নতুন লেখা এলে এভাবেই জানাব।', 'url' => url('/').'/', 'tag' => 'test', 'kind' => 'test']);

        return response()->json(['ok' => $ok]);
    }

    /** D132: a notification was tapped (from the service worker; only its kind is counted, nothing about the person). */
    public function opened(Request $request)
    {
        \App\Support\WebPush::count((string) $request->json('kind', 'other'), 'opened');

        return response()->noContent();
    }

    private function find(Request $request): ?PushSubscription
    {
        $endpoint = (string) $request->input('endpoint');

        return $endpoint === '' ? null : PushSubscription::where('endpoint_hash', PushSubscription::hashOf($endpoint))->first();
    }

    /** Only topics that exist: the reader's own account, the daily note, a live serial, a writer with published pieces. */
    private function validTopics(array $topics, Request $request): ?array
    {
        $out = [];
        foreach ($topics as $t) {
            $ok = match (true) {
                $t === 'account' => $request->user() !== null,
                $t === 'daily' => true,
                (bool) preg_match('/^serial:(\d+)$/', $t, $m) => Serial::live()->whereKey((int) $m[1])->exists(),
                (bool) preg_match('/^author:(\d+)$/', $t, $m) => Author::whereKey((int) $m[1])->where('is_desk', false)->exists(),
                (bool) preg_match('/^section:(\d+)$/', $t, $m) => \App\Models\Section::whereKey((int) $m[1])->where('is_active', true)->exists(),   // D132
                default => false,
            };
            if (! $ok) {
                return null;
            }
            $out[] = $t;
        }

        return $out;
    }
}
