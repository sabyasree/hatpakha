<?php

namespace App\Ai;

final class ReviewOutcome
{
    /** @param array<string,int> $usage in, out, cache_read, cache_write */
    public function __construct(
        public readonly ?array $report,
        public readonly array $usage = ['in' => 0, 'out' => 0, 'cache_read' => 0, 'cache_write' => 0],
        public readonly ?string $error = null,
    ) {}
}
