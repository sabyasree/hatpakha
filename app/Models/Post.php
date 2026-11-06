<?php

namespace App\Models;

use App\Support\BodyFormatter;
use App\Support\HtmlSanitizer;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'pending', 'scheduled', 'published', 'rejected', 'archived'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'submitted_at' => 'datetime',
            'noindex' => 'boolean',
            'audit_reasons' => 'array',
            'audit_decided_at' => 'datetime',
            'edited_in_desk_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $post) {
            // Security: body is sanitised on every save, whatever the source (docs/SECURITY.md §4).
            if ($post->isDirty('body_html')) {
                // Then tidy structure only (never words): docs/DECISIONS.md D23.
                $post->body_html = BodyFormatter::format(HtmlSanitizer::clean($post->body_html), $post->format ?: 'prose', $post->title);
                $post->reading_minutes = self::estimateMinutes($post->body_html);
            }
            if ($post->isDirty('slug')) {
                $post->slug = Slug::normalize($post->slug);
            }
        });
    }

    public static function estimateMinutes(string $html): int
    {
        $words = count(preg_split('/\s+/u', trim(strip_tags($html)), -1, PREG_SPLIT_NO_EMPTY));

        return max(1, (int) round($words / 180));
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)->withPivot('position')->orderByPivot('position');
    }

    public function section(): BelongsTo { return $this->belongsTo(Section::class); }

    public function issue(): BelongsTo { return $this->belongsTo(Issue::class); }

    public function tags(): BelongsToMany { return $this->belongsToMany(Tag::class); }

    public function moods(): BelongsToMany { return $this->belongsToMany(Mood::class); }

    public function comments(): HasMany { return $this->hasMany(Comment::class); }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->where('published_at', '<=', now());
    }

    public function isPoem(): bool { return $this->format === 'poem'; }

    public function url(): string { return \App\Support\Url::to('/'.Slug::encode($this->slug)); }

    /** Cards show the photo only for large images; otherwise the title itself is the card (title-card). */
    public function hasCardImage(): bool
    {
        return $this->featured_image_path && ($this->featured_image_width ?? 0) >= 1000;
    }

    public function metaTitle(): string { return $this->seo_title ?: $this->title; }

    public function metaDescription(): string
    {
        return $this->seo_description
            ?: $this->excerpt
            ?: mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($this->body_html))), 0, 155);
    }

    /** Share/og image rule (docs/SEO.md §2): photos under 1200px are never the og:image. */
    public function hasLargeImage(): bool
    {
        return $this->featured_image_path && ($this->featured_image_width ?? 0) >= 1200;
    }
}
