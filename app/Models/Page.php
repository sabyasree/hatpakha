<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use App\Support\Slug;
use App\Support\Url;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A static page at /{slug}/ (old WordPress pages keep their URLs). Body sanitised on save. */
class Page extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['noindex' => 'boolean', 'show_in_footer' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $page) {
            if ($page->isDirty('body_html')) {
                $page->body_html = HtmlSanitizer::clean($page->body_html);
            }
            if ($page->isDirty('slug')) {
                $page->slug = Slug::normalize($page->slug);
            }
        });
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('pages.footer'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('pages.footer'));
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function url(): string
    {
        return Url::to('/'.Slug::encode($this->slug));
    }

    public function metaTitle(): string
    {
        return $this->seo_title ?: $this->title;
    }

    public function metaDescription(): string
    {
        return $this->seo_description
            ?: (\Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $this->body_html)))), 155) ?: $this->title);
    }

    /**
     * Footer links as [title, url] pairs. Cached as scalars only (Laravel 13 will not unserialize
     * models from the cache, D18); cleared whenever a page is saved.
     *
     * @return array<int,array{0:string,1:string}>
     */
    public static function footerLinks(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('pages.footer', 600, fn () => self::published()
            ->where('show_in_footer', true)->orderBy('sort')->get()
            ->map(fn (self $p) => [$p->title, $p->url()])->all());
    }
}
