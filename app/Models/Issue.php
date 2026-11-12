<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    protected $guarded = ['id'];

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

    /** The editorial readers can see, or null. */
    public function liveEditorial(): ?Post
    {
        $post = $this->editorialPost();

        return $post?->isLive() ? $post->loadMissing('authors', 'section') : null;
    }

    public function url(): string { return \App\Support\Url::to('/sonkha/'.$this->slug); }
}
