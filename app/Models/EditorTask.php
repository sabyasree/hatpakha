<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A job the AI editor prepared for the owner (D48). Open until the owner acts. */
class EditorTask extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'done_at' => 'datetime'];
    }

    public function subject(): MorphTo { return $this->morphTo(); }

    public static function open(string $type)
    {
        return static::where('type', $type)->where('status', 'open');
    }

    /** Only one open task of a type per subject/key. */
    public static function propose(string $type, string $title, array $payload = [], ?Model $subject = null): self
    {
        return static::firstOrCreate(
            ['type' => $type, 'status' => 'open', 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey()],
            ['title' => $title, 'payload' => $payload],
        );
    }

    public function close(string $status = 'done'): void
    {
        $this->forceFill(['status' => $status, 'done_by' => auth()->id(), 'done_at' => now()])->save();
        AuditLog::record("task.{$this->type}.{$status}", $this->subject, ['task' => $this->id]);
    }
}
