<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SavedLine;
use App\Support\Url;
use Illuminate\Http\Request;

/**
 * C6 (research 2026-11): a signed-in reader keeps a line they chose in a piece ("রাখুন") and finds it in their library.
 * Only live pieces (not children's: no line picking there), plain text only, words that are really in the piece,
 * a limit per reader; a reader can see and remove only their own lines (docs/SECURITY.md §3, §4).
 */
class SavedLineController extends Controller
{
    public function store(Request $request, Post $post)
    {
        abort_unless($post->isLive() && ! $post->isKids(), 404);
        $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $text = SavedLine::clean((string) $request->input('text'));
        $user = $request->user();

        $fail = match (true) {
            mb_strlen($text) < 2 => 'একটু বড় অংশ বেছে নিন।',
            mb_strlen($text) > SavedLine::MAX => 'একটু ছোট অংশ বেছে নিন (২-৪ লাইন)।',
            ! SavedLine::isInPiece($text, $post) => 'লেখার ভেতরের লাইন বেছে নিন।',
            SavedLine::where('user_id', $user->id)->count() >= SavedLine::LIMIT => 'আর লাইন রাখা যাচ্ছে না। লাইব্রেরি থেকে পুরনো কিছু সরিয়ে দিন।',
            default => null,
        };
        if ($fail) {
            return $request->expectsJson() ? response()->json(['message' => $fail], 422) : redirect($post->url())->with('status', $fail);
        }

        SavedLine::firstOrCreate(['user_id' => $user->id, 'post_id' => $post->id, 'text' => $text]);
        $msg = 'লাইনটি রাখা হলো। লাইব্রেরিতে পাবেন।';

        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $msg]) : redirect($post->url())->with('status', $msg);
    }

    public function destroy(Request $request, int $line)
    {
        // Someone else's line answers exactly like a missing one
        SavedLine::where('user_id', $request->user()->id)->whereKey($line)->firstOrFail()->delete();

        return redirect(Url::to('/account/reading').'#lines')->with('status', __('লাইনটি সরানো হল।'));
    }
}
