<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** D189: a reader's comment under one of our social posts, and our answer (App\Support\CommentReplies). */
class SocialComment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['commented_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    public function post(): BelongsTo { return $this->belongsTo(SocialPost::class, 'social_post_id'); }
}
