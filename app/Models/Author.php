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
        return ['links' => 'array', 'is_guest' => 'boolean', 'is_desk' => 'boolean', 'claimed_at' => 'datetime', 'honoured_at' => 'datetime', 'quality_at' => 'datetime', 'quality_score' => 'float', 'relaunch_letter_at' => 'datetime', 'pinned_post_ids' => 'array'];
    }

    protected static function booted(): void
    {
        // A4: the folded name the search matches in the database.
        static $hasColumn = false;   // older migrations save writers before the column exists
        static::saving(function (self $a) use (&$hasColumn) {
            $hasColumn = $hasColumn || \Illuminate\Support\Facades\Schema::hasColumn('authors', 'search_name');
            if ($hasColumn && ($a->isDirty(['display_name', 'display_name_bn', 'slug']) || $a->search_name === null)) {
                $a->search_name = self::searchKey($a->display_name, $a->display_name_bn, $a->slug);
            }
        });
    }

    public static function searchKey(?string $name, ?string $nameBn, ?string $slug): string
    {
        return mb_substr(\App\Support\BnSearch::key(trim($name.' '.$nameBn.' '.str_replace('-', ' ', (string) $slug))), 0, 500);
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
        // Never show an e-mail address as a name (privacy): "bijoy_bose_82@yahoo.com" → "Bijoy Bose".
        if (str_contains($n, '@')) {
            $n = trim(preg_replace('/[0-9]+$/', '', preg_replace('/[_.\-]+/', ' ', strstr($n, '@', true)))) ?: 'হাতপাখার লেখক';

            return ucwords($n);
        }

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

    /** Up to this many pieces a writer may pin on their page ("নির্বাচিত লেখা", 2026-11-24). */
    public const PINS = 3;

    /**
     * The writer's pinned pieces, in the order they chose, that are still theirs and live for readers.
     *
     * @return \Illuminate\Support\Collection<int, Post>
     */
    public function pinnedPosts(): \Illuminate\Support\Collection
    {
        $ids = array_slice(array_values(array_unique(array_map('intval', (array) $this->pinned_post_ids))), 0, self::PINS);
        if (! $ids) {
            return collect();
        }
        $posts = Post::published()->whereIn('id', $ids)->whereHas('authors', fn ($q) => $q->whereKey($this->id))->with(['authors', 'section'])->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $posts->get($id))->filter()->values();
    }

    /** Keeps published_count right (it decides full vs compact profile and indexing). */
    public function refreshCount(): void
    {
        $this->forceFill(['published_count' => $this->posts()->published()->count()])->saveQuietly();
    }
}
