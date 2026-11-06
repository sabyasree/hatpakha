<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A public byline: a registered writer, a guest author (no account) or the "হাতপাখা ডেস্ক". */
class Author extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['links' => 'array', 'is_guest' => 'boolean', 'is_desk' => 'boolean', 'claimed_at' => 'datetime', 'honoured_at' => 'datetime', 'quality_at' => 'datetime', 'quality_score' => 'float'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withPivot('position');
    }

    public function certificates(): HasMany { return $this->hasMany(Certificate::class); }

    public function publicName(): string
    {
        if ($this->display_name_bn) {
            return $this->display_name_bn;
        }
        // Old WordPress logins ("sambuddha_chatterjee") read as names ("Sambuddha Chatterjee") until the writer sets one (D59).
        $n = (string) $this->display_name;

        return preg_match('/^[a-z0-9]+([_.\-][a-z0-9]+)+$/', $n) ? ucwords(preg_replace('/[_.\-]+/', ' ', $n)) : $n;
    }

    /** First letter for the generated initials avatar. */
    public function initial(): string
    {
        return mb_substr($this->publicName(), 0, 1);
    }

    /** SEO rule: author pages with fewer than 3 published pieces are noindex (docs/SEO.md §4). */
    public function isIndexable(): bool
    {
        return $this->published_count >= 3;
    }

    public function url(): string
    {
        return \App\Support\Url::to('/author/'.rawurlencode($this->slug));
    }

    /** Keeps published_count right (it decides full vs compact profile and indexing). */
    public function refreshCount(): void
    {
        $this->forceFill(['published_count' => $this->posts()->published()->count()])->saveQuietly();
    }
}
