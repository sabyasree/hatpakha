<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** D168: one piece given to one human reviewer, and what they said. The writer never sees it; the desk decides (D42). */
class PieceReview extends Model
{
    protected $guarded = ['id'];

    public const VERDICTS = [
        'good' => 'ভালো লেগেছে, প্রকাশ করা যায়',
        'changes' => 'কিছু বদলালে ভালো হবে',
        'not_yet' => 'এখনও তৈরি নয়',
    ];

    public const STATUSES = ['assigned' => 'পড়া বাকি', 'done' => 'মতামত দিয়েছেন', 'withdrawn' => 'ফিরিয়ে নেওয়া'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'opened_at' => 'datetime', 'submitted_at' => 'datetime', 'desk_read_at' => 'datetime', 'rating' => 'integer'];
    }

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }

    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }

    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }

    public function isOpen(): bool { return $this->status === 'assigned'; }

    /** Blind review (owner): the writer's name shows only once the piece is public anyway. */
    public function showsWriter(): bool
    {
        return (bool) $this->post?->isLive();
    }

    /** What the reviewer may see of the AI's report, after giving their own view: the reading, not the desk's tools. */
    public function aiForReviewer(): ?array
    {
        $r = $this->post?->latestAiReview;
        if (! $r || $r->status !== 'done' || ! is_array($r->report)) {
            return null;
        }
        $rep = $r->report;

        return array_filter([
            'score' => isset($rep['quality_score']) ? (int) $rep['quality_score'] : null,
            'decision' => $rep['decision'] ?? null,
            'summary' => $rep['summary'] ?? null,
            'strengths' => array_values(array_filter((array) ($rep['strengths'] ?? []), 'is_string')),
            'suggestions' => array_values(array_filter((array) ($rep['suggestions'] ?? []), 'is_string')),
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** Open assignments for a reviewer (also shown as a count on their account). */
    public static function openFor(User $user): int
    {
        return static::where('reviewer_id', $user->id)->where('status', 'assigned')->count();
    }
}
