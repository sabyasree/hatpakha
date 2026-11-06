<?php

namespace App\Jobs;

use App\Ai\ReviewService;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Writes an AI report for a new submission. Dispatched after the response, so writers never wait. */
class ReviewSubmission implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $postId) {}

    public function handle(ReviewService $service): void
    {
        $post = Post::find($this->postId);
        if ($post && $post->status === 'pending') {
            $service->run($post);
        }
    }
}
