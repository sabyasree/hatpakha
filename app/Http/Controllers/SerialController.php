<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Serial;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** D108: ধারাবাহিক. The list of serials, each serial's page with all its episodes, and "follow" for new episodes. */
class SerialController extends Controller
{
    public function index()
    {
        $serials = Serial::live()->get();
        $seo = new Seo(title: 'ধারাবাহিক', description: 'হাতপাখার ধারাবাহিক গল্প: রহস্য, অলৌকিক আর সম্পর্কের গল্প, সপ্তাহে একটি করে নতুন পর্ব।',
            canonical: Url::to('/dharabahik'), noindex: $serials->isEmpty(),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['ধারাবাহিক', Url::to('/dharabahik')]])]);

        return view('serials', compact('serials', 'seo'));
    }

    public function show(Request $request, string $slug)
    {
        $serial = Serial::where('slug', $slug)->firstOrFail();
        $editor = $request->user()?->hasRole(Role::Editor) ?? false;
        abort_unless($serial->isPublic() || $editor, 404);
        $episodes = $serial->liveEpisodes()->get();
        $seo = new Seo(
            title: $serial->title.' — ধারাবাহিক',
            description: $serial->logline ?: "হাতপাখার ধারাবাহিক: {$serial->title}।",
            canonical: $serial->url(),
            noindex: $episodes->isEmpty(),
            image: $serial->cover_path ? asset($serial->cover_path) : null,
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['ধারাবাহিক', Url::to('/dharabahik')], [$serial->title, $serial->url()]])],
            shareTitle: $serial->title.' | হাতপাখার ধারাবাহিক',
        );
        $following = $serial->isFollowedBy($request->user());
        if ($serial->isPublic() && $episodes->isNotEmpty()) {
            \App\Support\Ads::allow();   // D111
        }

        return view('serial', compact('serial', 'episodes', 'seo', 'following'));
    }

    /** Signed-in readers follow a serial: one e-mail when a new episode comes out (they asked). Toggle. */
    public function follow(Request $request, Serial $serial)
    {
        abort_unless($serial->isPublic(), 404);
        $key = ['serial_id' => $serial->id, 'user_id' => $request->user()->id];
        if (DB::table('serial_follows')->where($key)->exists()) {
            DB::table('serial_follows')->where($key)->delete();
            $msg = 'নতুন পর্বের খবর আর পাঠানো হবে না।';
        } else {
            DB::table('serial_follows')->insert($key + ['created_at' => now()]);
            $msg = 'নতুন পর্ব এলে ই-মেলে জানাব।';
        }

        return back()->with('serial_status', $msg);
    }
}
