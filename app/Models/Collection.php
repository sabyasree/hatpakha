<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A themed collection or yearly anthology list (D55). Plain text title/intro, escaped on output. */
class Collection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['post_ids' => 'array', 'months' => 'array', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // D97: an approved collection always has its own address.
        static::saving(function (self $c) {
            if (blank($c->slug) && $c->status === 'approved') {
                $c->slug = self::uniqueSlug((string) $c->title, $c->id);
            }
        });
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('collections.live'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('collections.live'));
    }

    public function url(): string
    {
        return \App\Support\Url::to('/sonkolon/'.$this->slug);
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = \App\Support\Transliterate::slug($title) ?: 'sonkolon';
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** Approved collections as plain arrays (id, slug, title, post ids, months), cached 10 minutes (D18). */
    public static function live(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('collections.live', 600, fn () => static::where('status', 'approved')->whereNotNull('slug')
            ->latest('approved_at')->get(['id', 'slug', 'title', 'post_ids', 'months'])
            ->map(fn (self $c) => ['id' => $c->id, 'slug' => $c->slug, 'title' => $c->title, 'ids' => array_map('intval', (array) $c->post_ids), 'months' => array_map('intval', (array) $c->months)])->all());
    }

    /** The collection for the home shelf: one in season (Kolkata month), else the latest approved. */
    public static function featured(): ?self
    {
        $live = collect(self::live());
        $pick = $live->first(fn ($c) => in_array(now('Asia/Kolkata')->month, $c['months'], true)) ?? $live->first();

        return $pick ? static::find($pick['id']) : null;
    }

    /** The next published piece after this one in a collection's order (null at the end). */
    public static function nextAfter(array $ids, int $postId): ?Post
    {
        $rest = array_slice($ids, (int) array_search($postId, $ids, true) + 1);
        $found = Post::published()->whereIn('id', $rest)->get()->keyBy('id');

        return collect($rest)->map(fn ($id) => $found[$id] ?? null)->filter()->first();
    }

    /** Approved collections that contain this piece. */
    public static function forPost(int $postId): array
    {
        return collect(self::live())->filter(fn ($c) => in_array($postId, $c['ids'], true))->values()->all();
    }

    /** Published pieces in the chosen order (unpublished ones silently drop out). */
    public function posts()
    {
        $ids = array_map('intval', (array) $this->post_ids);
        $posts = Post::published()->with('authors', 'section')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $posts[$id] ?? null)->filter()->values();
    }
}
