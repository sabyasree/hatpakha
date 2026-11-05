<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array { return ['meta' => 'array']; }

    public function subject(): MorphTo { return $this->morphTo(); }

    public static function record(string $action, ?Model $subject = null, array $meta = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip' => request()?->ip(),
            'meta' => $meta ?: null,
        ]);
    }
}
