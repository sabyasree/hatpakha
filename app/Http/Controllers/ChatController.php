<?php

namespace App\Http\Controllers;

use App\Ai\ChatBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** The submission-page helper (D52). Rate-limited; answers are plain text (escaped by the page). */
class ChatController extends Controller
{
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:'.ChatBot::MAX_CHARS],
            'history' => ['nullable', 'array', 'max:6'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.text' => ['required', 'string', 'max:1200'],
        ]);
        $user = $request->user();
        $key = 'chat:'.($user ? 'u'.$user->id : 'ip'.$request->ip());
        if (! RateLimiter::attempt($key, $user ? 40 : 15, fn () => true, 3600)) {
            return response()->json(['answer' => 'অনেক প্রশ্ন হয়ে গেছে, এক ঘণ্টা পরে আবার জিজ্ঞাসা করুন।', 'escalated' => false], 429);
        }

        return response()->json(ChatBot::answer($user, $data['question'], $data['history'] ?? []));
    }
}
