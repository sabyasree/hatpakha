<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(fn () => cache()->forget('issue.latest'));
    }

    protected function casts(): array { return ['published_at' => 'datetime', 'ai_plan' => 'array', 'ebook_built_at' => 'datetime', 'ebook_approved_at' => 'datetime']; }

    public function editorial(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Post::class, 'editorial_post_id'); }

    public function posts(): HasMany { return $this->hasMany(Post::class); }

    /**
     * The issue's editorial (owner, 2026-11-12: "the editorial section is missing"): the one linked to the issue, or else
     * a piece of this issue in the সম্পাদকীয় section (issues made by hand or imported from WordPress have no link).
     */
    public function editorialPost(): ?Post
    {
        if ($this->editorial_post_id && ($linked = Post::find($this->editorial_post_id))) {
            return $linked;
        }

        return Post::where('issue_id', $this->id)->whereHas('section', fn ($q) => $q->where('slug', 'editorial'))
            ->orderByRaw("case when status in ('published', 'scheduled') then 0 else 1 end")->oldest('published_at')->first();
    }

    /** The newest published issue with pieces (home page and menu: the way into the month's magazine). Only the id is cached (D18). */
    /** D272 (audit): readers see an issue only from its day; approved for a later date, it waits (desk preview stays). */
    public function isLive(): bool
    {
        return $this->status === 'published' && (! $this->published_at || $this->published_at->lte(now()));
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'published')->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /** "<date>, সকাল ৯:০০" in India time, for an approved issue still waiting for its day. */
    public function goesOutText(): string
    {
        $at = $this->published_at?->copy()->setTimezone('Asia/Kolkata');

        return $at ? 'প্রকাশ হবে '.\App\Support\Bn::date($at).', '.($at->hour < 12 ? 'সকাল' : ($at->hour < 17 ? 'দুপুর' : 'সন্ধ্যা')).' '.\App\Support\Bn::num($at->format('g:i')) : '';
    }

    /** Monthly issues have a year-month address; a special issue (ফিরে আসা) has a word: both are fine. */
    public const SLUG = '\d{4}-\d{2}|[a-z][a-z0-9-]{2,40}';

    public static function latestLive(): ?self
    {
        $id = cache()->remember('issue.latest', 600, fn () => static::where('status', 'published')->where('published_at', '<=', now())
            ->whereHas('posts', fn ($q) => $q->published())->latest('published_at')->value('id'));

        return $id ? static::find($id) : null;
    }

    /** The editorial readers can see, or null. */
    public function liveEditorial(): ?Post
    {
        $post = $this->editorialPost();

        return $post?->isLive() ? $post->loadMissing('authors', 'section') : null;
    }

    public function url(): string { return \App\Support\Url::to('/sonkha/'.$this->slug); }
}
