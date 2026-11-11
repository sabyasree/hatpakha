<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\WebPush;
use Illuminate\Http\Request;

/** D127: the installable app (service worker) and turning phone notifications on or off. */
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

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_-]+={0,2}$/'],
            'keys.auth' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+={0,2}$/'],
        ]);
        if (! WebPush::allowedEndpoint($data['endpoint']) || strlen(WebPush::b64d(rtrim($data['keys']['p256dh'], '='))) !== 65
            || strlen(WebPush::b64d(rtrim($data['keys']['auth'], '='))) !== 16) {
            return response()->json(['ok' => false], 422);
        }
        $ua = (string) $request->userAgent();
        $device = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iPhone/iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac',
            default => null,
        };

        // A browser belongs to whoever turned it on last (one person per browser).
        PushSubscription::updateOrCreate(['endpoint_hash' => PushSubscription::hashOf($data['endpoint'])], [
            'user_id' => $request->user()->id, 'endpoint' => $data['endpoint'], 'p256dh' => rtrim($data['keys']['p256dh'], '='),
            'auth' => rtrim($data['keys']['auth'], '='), 'device' => $device, 'failures' => 0,
        ]);
        if (PushSubscription::where('user_id', $request->user()->id)->count() > 10) {   // old phones drop off
            PushSubscription::where('user_id', $request->user()->id)->oldest('updated_at')->first()?->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $endpoint = (string) $request->input('endpoint');
        PushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', PushSubscription::hashOf($endpoint))->delete();

        return response()->json(['ok' => true]);
    }

    /** "Send me a test": only to the person's own browsers. */
    public function test(Request $request)
    {
        $n = WebPush::toUser($request->user(), ['title' => 'হাতপাখা', 'body' => 'ফোনে খবর চালু হয়েছে। নতুন লেখা এলে এভাবেই জানাব।', 'url' => url('/').'/', 'tag' => 'test']);

        return response()->json(['ok' => $n > 0, 'sent' => $n]);
    }
}
