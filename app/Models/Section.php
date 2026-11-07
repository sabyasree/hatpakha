<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['is_active' => 'boolean', 'in_top_bar' => 'boolean']; }

    /** Menu groups (D95), in this order. */
    public const GROUPS = ['literature' => 'সাহিত্য', 'life' => 'জীবন ও সংস্কৃতি', 'english' => 'English', 'other' => 'আরও'];

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
     * The site menu (D95, UX review): the top bar's chosen sections, every section grouped by kind for the
     * "সব বিভাগ" panel and the phone menu, and the English section on its own. Only sections with at least one
     * published piece are shown, so a reader never lands on an empty page. Plain arrays, cached 10 minutes.
     *
     * @return array{top: list<array{0:string,1:string}>, groups: list<array{label:string, links: list<array{0:string,1:string}>}>, english: ?string}
     */
    public static function menu(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('sections.menu', 600, function () {
            $live = self::where('is_active', true)->whereHas('posts', fn ($q) => $q->published())->orderBy('sort')->get();
            $groups = [];
            foreach (self::GROUPS as $key => $label) {
                if ($key === 'english') {
                    continue;
                }
                $links = $live->where('nav_group', $key)->map(fn (self $s) => [$s->name_bn, $s->url()])->values()->all();
                if ($links) {
                    $groups[] = ['label' => $label, 'links' => $links];
                }
            }
            $english = $live->firstWhere('nav_group', 'english');

            return [
                'top' => $live->where('in_top_bar', true)->where('nav_group', '!=', 'english')->take(5)->map(fn (self $s) => [$s->name_bn, $s->url()])->values()->all(),
                'groups' => $groups,
                'english' => $english?->url(),
            ];
        });
    }
}
