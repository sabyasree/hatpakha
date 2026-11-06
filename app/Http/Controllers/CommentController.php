<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Support\Turnstile;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Reader comments (D39): guests and signed-in readers. Plain text only, escaped on output.
 * Guests: always checked by an editor. Signed-in: the first comments are checked; after
 * Comment::TRUST_AFTER approved ones they appear at once (editors can still remove them).
 */
class CommentController extends Controller
{
    public function store(Request $request, Post $post)
    {
        abort_unless($post->isLive(), 404);
        $user = $request->user();
        $back = $post->url().'#comments';
        if ($user && AccountController::verificationRequired() && ! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['body' => 'মন্তব্য করার আগে ই-মেল যাচাই করুন (আপনার ইনবক্সে লিংক আছে)।'])->redirectTo($back);   // D59
        }

        if (filled($request->input('website')) || ! self::humanSpeed((string) $request->input('started'))) {
            throw ValidationException::withMessages(['body' => 'কিছু একটা ঠিক হয়নি। পাতাটি আবার খুলে চেষ্টা করুন।'])->redirectTo($back);
        }
        $data = $request->validate([
            'name' => [$user ? 'nullable' : 'required', 'string', 'min:2', 'max:80'],
            'body' => ['required', 'string', 'min:2', 'max:3000'],
            'not_featured' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'আপনার নাম লিখুন।',
            'body.required' => 'মন্তব্য লিখুন।',
            'body.max' => 'মন্তব্য একটু ছোট করুন (৩০০০ অক্ষরের মধ্যে)।',
        ]);
        if (Comment::hasLink($data['body']) || Comment::hasLink((string) ($data['name'] ?? ''))) {
            throw ValidationException::withMessages(['body' => 'মন্তব্যে লিংক, ওয়েবসাইট বা ই-মেলের ঠিকানা দেওয়া যায় না। ঠিকানাটি বাদ দিয়ে আবার পাঠান।'])->redirectTo($back);
        }
        if (! $user && ! Turnstile::passes($request->input('cf-turnstile-response'), $request->ip(), 'comment')) {
            throw ValidationException::withMessages(['body' => 'আপনি মানুষ কি না যাচাই হয়নি। আবার চেষ্টা করুন।'])->redirectTo($back);
        }

        $status = Comment::statusFor($user);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user?->id,
            'author_name' => $user ? $user->name : trim($data['name']),
            'body' => trim(str_replace("\r\n", "\n", $data['body'])),
            'status' => $status,
            'feature_opt_out' => (bool) ($data['not_featured'] ?? false),
        ]);
        if ($status === 'pending') {
            // The AI editor pre-screens it after the reader's page has been sent (advice only).
            dispatch(fn () => \App\Ai\CommentScreen::run($comment))->afterResponse();
        }

        return redirect($back)->with('comment_status', $status === 'approved'
            ? 'ধন্যবাদ! আপনার মন্তব্য প্রকাশিত হয়েছে।'
            : 'ধন্যবাদ! সম্পাদক একবার দেখে নিলেই আপনার মন্তব্য এখানে দেখা যাবে।');
    }

    /** Encrypted timestamp in the form: posted back in under 3 seconds = a bot. */
    public static function startedToken(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    private static function humanSpeed(string $started): bool
    {
        try {
            $t = (int) Crypt::decryptString($started);
        } catch (\Throwable) {
            return false;
        }

        return now()->timestamp - $t >= 3 && now()->timestamp - $t < 86400 * 2;
    }
}
