<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['is_active' => 'boolean', 'in_top_bar' => 'boolean']; }

    /** Menu groups (D95), in this order. */
    public const GROUPS = ['literature' => 'সাহিত্য', 'life' => 'জীবন ও সংস্কৃতি', 'kids' => 'ছোটদের', 'other' => 'আরও'];

    /** D101: the children's section. */
    public const KIDS = 'chhotoder';

    /**
     * What people type into Google for a section (2026-11-25: "কবিতা | হাতপাখা" matched nobody's search):
     * slug => [page title, description used when the section has no introduction of its own].
     */
    public const SEARCH = [
        'poetry' => ['বাংলা কবিতা (Bangla Kobita): নতুন ও চেনা কবিদের কবিতা', 'হাতপাখার বাংলা কবিতা: ২০১২ থেকে নতুন ও চেনা কবিদের কবিতা, এক জায়গায়। পড়ুন, আর নিজের কবিতা পাঠান বিনামূল্যে।'],
        'short-stories' => ['বাংলা ছোটগল্প (Bangla Golpo): নতুন লেখকদের গল্প', 'হাতপাখার বাংলা ছোটগল্প: ২০১২ থেকে নতুন ও চেনা লেখকদের গল্প, এক জায়গায়। পড়ুন, আর নিজের গল্প পাঠান বিনামূল্যে।'],
        'literature' => ['বাংলা প্রবন্ধ ও নিবন্ধ', 'হাতপাখার বাংলা প্রবন্ধ: সাহিত্য, সমাজ আর মানুষ নিয়ে লেখা, ২০১২ থেকে। পড়ুন, আর নিজের প্রবন্ধ পাঠান বিনামূল্যে।'],
        'travel' => ['বাংলা ভ্রমণকাহিনি ও ভ্রমণকথা (Bhromon Kahini)', 'হাতপাখার বাংলা ভ্রমণকাহিনি: লেখকদের নিজের চোখে দেখা পাহাড়, সমুদ্র, শহর আর গ্রাম। পড়ুন, আর নিজের ভ্রমণের গল্প পাঠান।'],
        'translated' => ['অনুবাদ সাহিত্য: বাংলায় অনুবাদ', 'হাতপাখার অনুবাদ: নানা ভাষার কবিতা, গল্প আর প্রবন্ধ, বাংলায়। পড়ুন, আর নিজের অনুবাদ পাঠান বিনামূল্যে।'],
        'anugolpo' => ['বাংলা অণুগল্প', null],
        'english' => ['English poems, stories and essays', 'Poems, stories and essays in English from Hatpakha (হাতপাখা), an online Bengali literary magazine. ISSN 2321-3035.'],
    ];

    /** The page title for Google (without " | হাতপাখা"). */
    public function searchTitle(): string
    {
        return self::SEARCH[$this->slug][0] ?? $this->name_bn;
    }

    public function searchDescription(): string
    {
        return $this->about ?: (self::SEARCH[$this->slug][1] ?? "হাতপাখার {$this->name_bn} — সব লেখা।");
    }

    public function posts(): HasMany { return $this->hasMany(Post::class); }

    public function url(): string { return \App\Support\Url::to('/category/'.rawurlencode($this->slug)); }

    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::deleteMultiple(['sections.nav', 'sections.all', 'sections.menu', 'sections.choices']));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::deleteMultiple(['sections.nav', 'sections.all', 'sections.menu', 'sections.choices']));
    }

    /** Header menu (D65): cached 10 minutes as plain arrays (models are never cached, D18). */
    /** Every active section, for the "বিভাগ" menu on phones (mobile UX review). */
    public static function allLinks(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('sections.all', 600, fn () => self::where('is_active', true)->whereHas('posts', fn ($q) => $q->published())
            ->orderBy('sort')->get()->map(fn (self $s) => [$s->name_bn, $s->url()])->all());
    }

    /** C5: the sections a search can be narrowed to, slug => name (scalars only, D18). */
    public static function searchChoices(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('sections.choices', 600, fn () => self::where('is_active', true)->where('slug', '!=', 'dharabahik')
            ->whereHas('posts', fn ($q) => $q->published())->orderBy('sort')->pluck('name_bn', 'slug')->all());
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
            $link = fn (self $s) => [$s->name_bn, $s->slug === 'dharabahik' ? \App\Support\Url::to('/dharabahik') : $s->url(), $s->slug === 'english' ? 'en' : 'bn'];   // D108
            $groups = [];
            foreach (self::GROUPS as $key => $label) {
                $links = $live->where('nav_group', $key)->map($link)->values()->all();
                if ($key === 'kids' && $links) {
                    $links[] = ['বর্ণমালা', \App\Support\Url::to('/bornomala'), 'bn'];   // D101
                }
                if ($links) {
                    $groups[] = ['label' => $label, 'links' => $links];
                }
            }

            return ['top' => $live->where('in_top_bar', true)->take(5)->map($link)->values()->all(), 'groups' => $groups];
        });
    }
}
