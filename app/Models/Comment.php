<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Plain-text reader comment. Always escaped on output. */
class Comment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['feature_opt_out' => 'boolean']; }

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Approved comments a signed-in reader needs before theirs appear without a check (D39). */
    public const TRUST_AFTER = 2;

    /** Guests: always checked. Signed-in: checked until trusted. Spam-flagged accounts: always checked. */
    public static function statusFor(?User $user): string
    {
        if (! $user || filled($user->spam_reason)) {
            return 'pending';
        }

        return static::where('user_id', $user->id)->where('status', 'approved')->count() >= self::TRUST_AFTER ? 'approved' : 'pending';
    }
}
