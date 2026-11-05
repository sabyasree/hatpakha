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
}
