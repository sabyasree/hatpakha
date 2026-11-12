<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A ready-to-post marketing draft (D51). Plain text only; printed escaped. */
class SocialPost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['texts' => 'array', 'posted_at' => 'datetime', 'fb_scheduled_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function hasFor(Model $subject, string $kind): bool
    {
        return static::where('kind', $kind)->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())->exists();
    }
}
