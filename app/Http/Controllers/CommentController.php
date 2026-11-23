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
        abort_unless($post->isLive() && ! $post->isKids(), 404);   // D101: no comments on children's pieces
        $user = $request->user();
        $back = $post->url().'#reactions';   // D241: the form and its answer are in the card under the piece
        // D253: a reply goes back to its own thread, on the piece or in the account, also when something is wrong
        // (UX review: a failed reply from the account had landed on the piece with its words in the wrong box)
        $replyTo = (int) $request->input('parent_id');
        if ($replyTo > 0) {
            $back = ($request->input('from') === 'account' && $user ? \App\Support\Url::to('/account/comments') : $post->url()).'#comment-'.$replyTo;
        }
        if ($user && AccountController::verificationRequired() && ! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['body' => 'মন্তব্য করার আগে ই-মেল যাচাই করুন (আপনার ইনবক্সে লিংক আছে)।'])->redirectTo($back);   // D59
        }

        // A signed-in reader has proved an address: only a guest is timed (a quick "ধন্যবাদ!" reply is not a robot).
        if (filled($request->input('website')) || (! $user && ! self::humanSpeed((string) $request->input('started')))) {
            throw ValidationException::withMessages(['body' => $replyTo ? 'উত্তর যায়নি। একটু পরে আবার “উত্তর পাঠান” টিপুন।' : 'কিছু একটা ঠিক হয়নি। পাতাটি আবার খুলে চেষ্টা করুন।'])->redirectTo($back);
        }
        $data = validator($request->all(), [
            'name' => [$user ? 'nullable' : 'required', 'string', 'min:2', 'max:80'],
            'body' => ['required', 'string', 'min:2', 'max:3000'],
            'not_featured' => ['nullable', 'boolean'],
            'parent_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'in:account'],
        ], [
            'name.required' => 'আপনার নাম লিখুন।',
            'body.required' => 'মন্তব্য লিখুন।',
            'body.min' => 'আর একটু লিখুন।',
            'body.max' => 'মন্তব্য একটু ছোট করুন (৩০০০ অক্ষরের মধ্যে)।',
        ]);
        if ($data->fails()) {
            throw (new ValidationException($data))->redirectTo($back);   // a failed reply goes back to its own box (UX review)
        }
        $data = $data->validated();
        if (Comment::hasLink($data['body']) || Comment::hasLink((string) ($data['name'] ?? ''))) {
            throw ValidationException::withMessages(['body' => 'মন্তব্যে লিংক, ওয়েবসাইট বা ই-মেলের ঠিকানা দেওয়া যায় না। ঠিকানাটি বাদ দিয়ে আবার পাঠান।'])->redirectTo($back);
        }
        if (! $user && ! Turnstile::passes($request->input('cf-turnstile-response'), $request->ip(), 'comment')) {
            throw ValidationException::withMessages(['body' => 'আপনি মানুষ কি না যাচাই হয়নি। আবার চেষ্টা করুন।'])->redirectTo($back);
        }

        // D252: a reply. It joins the thread of the comment it answers (one level), and only a published one on this piece.
        $parent = null;
        if ($data['parent_id'] ?? null) {
            $parent = Comment::where('post_id', $post->id)->where('status', 'approved')->find($data['parent_id']);
            if (! $parent) {
                throw ValidationException::withMessages(['body' => 'যে মন্তব্যের উত্তর দিচ্ছিলেন, সেটি আর নেই।'])->redirectTo($back);
            }
            $parent = $parent->parent_id ? ($parent->parent ?? $parent) : $parent;
            $back = (($data['from'] ?? null) === 'account' && $user ? \App\Support\Url::to('/account/comments') : $post->url()).'#comment-'.$parent->id;
        }
        $status = Comment::statusFor($user);
        $comment = Comment::create([
            'post_id' => $post->id,
            'parent_id' => $parent?->id,
            'user_id' => $user?->id,
            'author_name' => $user ? $user->name : trim($data['name']),
            'body' => trim(str_replace("\r\n", "\n", $data['body'])),
            'status' => $status,
            'feature_opt_out' => (bool) ($data['not_featured'] ?? false),
        ]);
        if ($status === 'pending') {
            // The AI editor pre-screens it after the reader's page has been sent (advice only).
            dispatch(function () use ($comment) {
                \App\Ai\CommentScreen::run($comment);
                \App\Support\Handover::comment($comment->fresh());   // D208: a clearly fine comment appears at once
            })->afterResponse();
        }

        $what = $parent ? 'উত্তর' : 'মন্তব্য';
        $message = $status === 'approved' ? "ধন্যবাদ! আপনার {$what} প্রকাশিত হয়েছে।" : "ধন্যবাদ! আপনার {$what} পৌঁছেছে। সম্পাদক দেখে নিলেই এখানে দেখা যাবে।";

        return redirect($back)->with($parent ? 'reply_status' : 'comment_status', $parent ? ['to' => $parent->id, 'text' => $message] : $message);
    }

    /** Encrypted timestamp in the form: posted back in under 3 seconds = a bot. */
    public static function startedToken(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    public static function humanSpeedPublic(string $started): bool
    {
        return self::humanSpeed($started);
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
