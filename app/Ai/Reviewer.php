<?php

namespace App\Ai;

use App\Models\Post;

/** One editorial review by one provider. Implementations must never publish or change the post. */
interface Reviewer
{
    public function provider(): string;

    public function model(): string;

    /** @param array<int,string> $sections allowed section slugs */
    public function review(Post $post, array $sections): ReviewOutcome;
}
