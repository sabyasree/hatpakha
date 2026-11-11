<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A piece of work the owner gave the AI team by hand (D72). */
class TeamRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['redo' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'seen_at' => 'datetime', 'steps' => 'array'];
    }

    /**
     * Owner + UX review (2026-11-11): the steps of a job, recorded as they happen.
     * route: who does it (only for a request in the owner's words; otherwise "skipped"); work: the member at work.
     * Each: ['start' => ISO time, 'end' => ISO time|null, 'note' => text|null, 'skipped' => bool].
     */
    public function step(string $key, array $data): void
    {
        $steps = $this->steps ?? [];
        $steps[$key] = array_merge($steps[$key] ?? [], $data);
        $this->forceFill(['steps' => $steps])->save();
    }

    /** The phase to show: waiting | route | work | retry | ready | failed */
    public function phase(): string
    {
        return match (true) {
            $this->status === 'done' => 'ready',
            $this->status === 'failed' => 'failed',
            $this->status === 'queued' && $this->attempts > 0 => 'retry',
            $this->status === 'queued' => 'waiting',
            isset($this->steps['work']['start']) => 'work',
            default => 'route',
        };
    }

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}
