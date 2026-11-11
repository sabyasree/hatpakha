<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** D127: one browser where a reader turned on notifications (keys are the browser's public ones; nothing secret of ours). */
class PushSubscription extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['endpoint', 'p256dh', 'auth'];

    protected function casts(): array
    {
        return ['last_sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashOf(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
