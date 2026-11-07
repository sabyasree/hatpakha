<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['is_active' => 'boolean', 'in_top_bar' => 'boolean']; }

    /** Menu groups (D95), in this order. */
    public const GROUPS = ['literature' => 'সাহিত্য', 'life' => 'জীবন ও সংস্কৃতি', 'other' => 'আরও'];

    public function posts(): HasMany { return $this->hasMany(Post::class); }

    public function url(): string { return \App\Support\Url::to('/category/'.rawurlencode($this->slug)); }

    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::deleteMultiple(['sections.nav', 'sections.all', 'sections.menu']));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::deleteMultiple(['sections.nav', 'sections.all', 'sections.menu']));
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

    /**
     * The site menu (D95, UX review): the top bar's chosen sections, and every section grouped by kind for the
     * "সব বিভাগ" panel and the phone menu. English is an ordinary section in "আরও" (owner, 2026-11-07).
     * Every active section is shown, new empty ones too: their page invites writers (owner, D96). Plain arrays, cached 10 minutes.
     * Each link is [name, url, lang].
     *
     * @return array{top: list<array{0:string,1:string,2:string}>, groups: list<array{label:string, links: list<array{0:string,1:string,2:string}>}>}
     */
    public static function menu(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('sections.menu', 600, function () {
            $live = self::where('is_active', true)->orderBy('sort')->get();
            $link = fn (self $s) => [$s->name_bn, $s->url(), $s->slug === 'english' ? 'en' : 'bn'];
            $groups = [];
            foreach (self::GROUPS as $key => $label) {
                $links = $live->where('nav_group', $key)->map($link)->values()->all();
                if ($links) {
                    $groups[] = ['label' => $label, 'links' => $links];
                }
            }

            return ['top' => $live->where('in_top_bar', true)->take(5)->map($link)->values()->all(), 'groups' => $groups];
        });
    }
}
