<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** D188: one personal invitation to one writer (App\Support\WriterInvites). */
class WriterInvite extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['send_after' => 'datetime', 'sent_at' => 'datetime', 'reminded_at' => 'datetime', 'answered_at' => 'datetime'];
    }

    public function author(): BelongsTo { return $this->belongsTo(Author::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }

    public function piece(): BelongsTo { return $this->belongsTo(Post::class, 'piece_id'); }
}
