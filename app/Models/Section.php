<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function posts(): HasMany { return $this->hasMany(Post::class); }

    public function url(): string { return \App\Support\Url::to('/category/'.rawurlencode($this->slug)); }

    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::deleteMultiple(['sections.nav', 'sections.all']));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::deleteMultiple(['sections.nav', 'sections.all']));
    }

    /** Header menu (D65): cached 10 minutes as plain arrays (models are never cached, D18). */
    /** Every active section, for the "বিভাগ" menu on phones (mobile UX review). */
    public static function allLinks(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('sections.all', 600, fn () => self::where('is_active', true)->whereHas('posts', fn ($q) => $q->published())
            ->orderBy('sort')->get()->map(fn (self $s) => [$s->name_bn, $s->url()])->all());
    }

    public static function navLinks(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('sections.nav', 600, fn () => self::where('is_active', true)->orderBy('sort')->limit(6)->get()
            ->map(fn (self $s) => [$s->name_bn, $s->url()])->all());
    }
}
