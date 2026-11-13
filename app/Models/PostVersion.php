<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** D171: the piece as it stood when it was sent in or sent back (body_html was sanitised when the post was saved). */
class PostVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public const KINDS = ['submitted' => 'লেখক পাঠালেন', 'sent_back' => 'সম্পাদক বদল চাইলেন', 'resubmitted' => 'লেখক আবার পাঠালেন'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function snap(Post $post, string $kind, ?string $note = null, ?int $userId = null): self
    {
        return static::create([
            'post_id' => $post->id, 'kind' => $kind, 'title' => (string) $post->title, 'body_html' => (string) $post->body_html,
            'note' => filled($note) ? mb_substr(trim(strip_tags($note)), 0, 2000) : null, 'user_id' => $userId,
        ]);
    }

    /** The version before this one. */
    public function previous(): ?self
    {
        return static::where('post_id', $this->post_id)->where('id', '<', $this->id)->latest('id')->first();
    }
}
