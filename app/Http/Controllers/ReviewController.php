<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PieceReview;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * D168: a reviewer (রিভিউয়ার) reads the pieces the admin gave them and says what they think, in their own account.
 * Their own view first; the AI's report opens only after they send it (owner). Writers' names stay hidden until a
 * piece is public. Feedback reaches only the desk, never the writer (D42).
 */
class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $reviews = PieceReview::with('post.section')->where('reviewer_id', $user->id)->where('status', '!=', 'withdrawn')
            ->orderByRaw("case status when 'assigned' then 0 else 1 end")->orderByRaw('due_on is null')->orderBy('due_on')
            ->latest('updated_at')->get();
        abort_unless($user->is_reviewer || $reviews->isNotEmpty(), 404);

        return view('account.reviews', AccountController::shell($request, 'review', 'রিভিউ করার লেখা') + [
            'open' => $reviews->where('status', 'assigned')->values(),
            'done' => $reviews->where('status', 'done')->values(),
        ]);
    }

    public function show(Request $request, PieceReview $review)
    {
        $review = $this->mine($request, $review);
        if (! $review->opened_at) {
            $review->forceFill(['opened_at' => now()])->save();
        }

        return view('account.review', AccountController::shell($request, 'review', __('রিভিউ: :title', ['title' => $review->post->title])) + [
            'review' => $review,
            'ai' => $review->status === 'done' ? $review->aiForReviewer() : null,
            'body' => \App\Support\Images::renderBody(\App\Support\Embeds::render((string) $review->post->body_html)),
        ]);
    }

    public function submit(Request $request, PieceReview $review)
    {
        $review = $this->mine($request, $review);
        if (! $review->isOpen()) {
            return redirect(Url::to('/account/review/'.$review->id).'#ai')->with('status', __('আপনার মতামত আগেই পৌঁছেছে।'));
        }
        $data = $request->validate([
            'verdict' => ['required', Rule::in(array_keys(PieceReview::VERDICTS))],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'liked' => ['required', 'string', 'min:10', 'max:4000'],
            'improve' => ['nullable', 'string', 'max:4000'],
        ], [
            'verdict.required' => __('সব মিলিয়ে আপনার মতামত কী, একটি বাছুন।'),
            'liked.required' => __('“কেমন লাগল” ঘরে দু-এক লাইন লিখুন।'),
            'liked.min' => __('“কেমন লাগল” ঘরে আর একটু লিখুন (অন্তত দু-এক লাইন)।'),
        ]);
        $review->update([
            'verdict' => $data['verdict'], 'rating' => $data['rating'] ?? null,
            'liked' => trim(strip_tags($data['liked'])), 'improve' => filled($data['improve'] ?? null) ? trim(strip_tags($data['improve'])) : null,
            'status' => 'done', 'submitted_at' => now(), 'desk_read_at' => null,
        ]);
        AuditLog::record('review.submitted', $review->post, ['reviewer' => $review->reviewer_id]);

        return redirect(Url::to('/account/review/'.$review->id).'#ai')
            ->with('status', __('ধন্যবাদ! আপনার মতামত সম্পাদকদের কাছে পৌঁছেছে। এবার চাইলে নিচে এআই কী বলেছে দেখে নিন।'));
    }

    /** After reading the AI's report, one more note (optional; can be changed later). */
    public function afterAi(Request $request, PieceReview $review)
    {
        $review = $this->mine($request, $review);
        abort_unless($review->status === 'done', 403);
        $data = $request->validate(['after_ai' => ['nullable', 'string', 'max:2000']]);
        $review->update(['after_ai' => filled($data['after_ai'] ?? null) ? trim(strip_tags($data['after_ai'])) : null, 'desk_read_at' => null]);

        return redirect(Url::to('/account/review/'.$review->id).'#ai')->with('status', __('সেভ হয়েছে। ধন্যবাদ!'));
    }

    /** Only the reviewer it was given to, and never once the desk took it back. */
    private function mine(Request $request, PieceReview $review): PieceReview
    {
        abort_unless($review->reviewer_id === $request->user()->id && $review->status !== 'withdrawn' && $review->post, 404);

        return $review->load('post.section', 'post.authors');
    }
}
