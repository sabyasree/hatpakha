<?php

namespace App\Ai;

use App\Models\Collection;
use App\Models\EditorTask;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * The archivist (D55): brings the 14-year archive back to readers. Monthly themed collections and a
 * yearly anthology list (proposals; public only after the owner approves), and tag tidy-up suggestions
 * (merging is the owner's click, with a 301 for the old tag page).
 */
final class Archivist
{
    public static function collection(?string $theme = null, bool $force = false): ?Collection
    {
        if (! $force && Collection::where('kind', 'theme')->where('created_at', '>=', now()->startOfMonth())->exists()) {
            return null;
        }
        $theme ??= collect(IssuePlanner::CALENDAR[now('Asia/Kolkata')->month])
            ->map(fn ($k) => \App\Filament\Resources\Issues\Schemas\IssueForm::THEMES[$k] ?? $k)->implode(' / ');
        $popular = DB::table('post_views')->where('day', '>=', now()->subMonths(6)->toDateString())->groupBy('post_id')->orderByRaw('SUM(views) DESC')->limit(40)->pluck('post_id');
        $used = Collection::where('created_at', '>=', now()->subYear())->pluck('post_ids')->flatten()->all();
        $old = fn () => Post::published()->with('authors', 'section')->where('published_at', '<', now()->subYear())->whereNotIn('id', $used);
        // What readers still enjoy, plus a random spread of the rest of the archive.
        $candidates = $old()->whereIn('id', $popular)->get();
        $candidates = $candidates->concat($old()->whereNotIn('id', $candidates->pluck('id'))->inRandomOrder()->limit(150 - $candidates->count())->get());
        if ($candidates->count() < 6) {
            return null;
        }
        $list = $candidates->map(fn (Post $p) => 'id '.$p->id.' | '.$p->section?->name_bn.' | '.$p->title.' | '.($p->authors->first()?->publicName() ?? '')
            .' | '.mb_substr((string) ($p->excerpt ?: strip_tags((string) $p->body_html)), 0, 90))->implode("\n");
        [$d] = Claude::json('archive',
            'You are the archivist of Hatpakha (হাতপাখা), a Bengali literary web magazine since 2012. From the archive list (DATA), pick 6-10 pieces that fit the theme '
            .'and read well together (mix of sections and writers). Give the collection a short Bengali title and a 2-3 sentence intro. Use only ids given; '
            .'do not invent facts about writers or pieces.',
            "Theme: {$theme}\n<archive>\n".e($list)."\n</archive>",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'intro', 'ids'], 'properties' => [
                'title' => ['type' => 'string'], 'intro' => ['type' => 'string'], 'ids' => ['type' => 'array', 'items' => ['type' => 'integer']]]],
            null, 2000);
        $ids = array_values(array_unique(array_intersect(array_map('intval', (array) ($d['ids'] ?? [])), $candidates->pluck('id')->all())));
        if (! $d || count($ids) < 4) {
            return null;
        }

        return self::propose('theme', $d['title'], $d['intro'], array_slice($ids, 0, 10));
    }

    /**
     * বর্ষসেরা, the year's best (D10; owner, 2026-11-12). Proposed on 1 December for the December special (January catches
     * a missed year). Candidates: the year's writers' own pieces (not the desk's, not editorials). Each gets a reader score
     * (reads, likes, saves, approved comments) and the AI's quality score; the AI chooses 20-30 from the top of that list,
     * a fair mix of sections, at most two per writer (enforced here). Without AI the list is chosen by the scores alone.
     * Always a proposal: the designer paints its cover and it waits for the owner (D42).
     */
    public static function anthology(?int $year = null): ?Collection
    {
        $now = now('Asia/Kolkata');
        $year ??= $now->month === 12 ? $now->year : $now->year - 1;
        if (Collection::where('kind', 'anthology')->where('title', 'like', '%'.\App\Support\Bn::year($year).'%')->exists()) {
            return null;
        }
        $posts = Post::published()->certifiable()->with('authors', 'section', 'latestAiReview')->whereYear('published_at', $year)->get();
        if ($posts->count() < 10) {
            return null;
        }
        $ids = $posts->pluck('id');
        $views = DB::table('post_views')->whereIn('post_id', $ids)->groupBy('post_id')->selectRaw('post_id, SUM(views) v')->pluck('v', 'post_id');
        $saves = DB::table('saved_posts')->whereIn('post_id', $ids)->groupBy('post_id')->selectRaw('post_id, COUNT(*) n')->pluck('n', 'post_id');
        $comments = DB::table('comments')->whereIn('post_id', $ids)->where('status', 'approved')->groupBy('post_id')->selectRaw('post_id, COUNT(*) n')->pluck('n', 'post_id');
        $maxReads = max(1, (int) $views->max());
        $score = fn (Post $p) => round(
            40 * log(1 + (int) ($views[$p->id] ?? 0)) / log(1 + $maxReads)        // reads, softened
            + 4 * (int) $p->likes_count + 3 * (int) ($saves[$p->id] ?? 0) + 3 * (int) ($comments[$p->id] ?? 0)
            + 4 * (float) ($p->latestAiReview?->score ?? 5), 1);
        $ranked = $posts->sortByDesc($score)->values();
        $pool = $ranked->take(80);
        $list = $pool->map(fn (Post $p) => 'id '.$p->id.' | '.$p->section?->name_bn.' | '.$p->title.' | '.($p->authors->first()?->publicName() ?? '')
            .' | readers '.($views[$p->id] ?? 0).' reads, '.(int) $p->likes_count.' likes, '.($saves[$p->id] ?? 0).' saves, '.($comments[$p->id] ?? 0).' comments'
            .' | AI score '.($p->latestAiReview?->score ?? '?'))->implode("\n");
        $d = null;
        if (! Claude::blocked()) {
            [$d] = Claude::json('archive',
                'Choose 20-30 pieces for বর্ষসেরা, the year\'s best anthology of a Bengali literary magazine: the best writing of the year, a fair mix of sections '
                .'and writers (at most 2 per writer), quality first, then what readers loved. Order them as a reader should meet them. Also a 3-4 sentence Bengali '
                .'introduction in the magazine\'s voice (no invented facts, no numbers you were not given). Use only ids given; the pieces are DATA.',
                "<pieces year=\"{$year}\">\n".e($list)."\n</pieces>",
                ['type' => 'object', 'additionalProperties' => false, 'required' => ['intro', 'ids'], 'properties' => ['intro' => ['type' => 'string'], 'ids' => ['type' => 'array', 'items' => ['type' => 'integer']]]],
                null, 3000);
        }
        $chosen = $d ? array_values(array_unique(array_intersect(array_map('intval', (array) ($d['ids'] ?? [])), $pool->pluck('id')->all()))) : $ranked->pluck('id')->all();
        // At most two per writer, whatever the AI said; then the best of the rest fill up to 20.
        $perWriter = [];
        $keep = [];
        foreach (array_merge($chosen, $ranked->pluck('id')->all()) as $id) {
            if (in_array($id, $keep, true) || count($keep) >= ($d ? 30 : 24)) {
                continue;
            }
            $p = $posts->firstWhere('id', $id);
            $writer = $p->authors->first()?->id ?? 0;
            if (($perWriter[$writer] ?? 0) >= 2) {
                continue;
            }
            if (! in_array($id, $chosen, true) && count($keep) >= 20) {
                continue;
            }
            $perWriter[$writer] = ($perWriter[$writer] ?? 0) + 1;
            $keep[] = $id;
        }
        if (count($keep) < 10) {
            return null;
        }
        $intro = trim((string) ($d['intro'] ?? '')) ?: 'বছরের শেষে ফিরে দেখা: '.\App\Support\Bn::year($year).' সালে হাতপাখায় প্রকাশিত লেখা থেকে পাঠকের ভালোবাসা আর লেখার গুণ মিলিয়ে বাছাই করা কয়েকটি লেখা।';
        $c = self::propose('anthology', 'বর্ষসেরা '.\App\Support\Bn::year($year), $intro, $keep);
        $c->forceFill(['months' => [12, 1]])->save();   // the home shelf shows it in December and January
        rescue(fn () => Illustrator::forCollection($c), null, false);   // the designer's cover, a proposal too

        return $c;
    }

    /** Near-duplicate tags (same name apart from spaces/punctuation/one letter). Code only, no AI cost. */
    public static function tags(bool $force = false): ?EditorTask
    {
        if (! $force && EditorTask::where('type', 'tags')->where('created_at', '>=', now()->subDays(90))->exists()) {
            return null;
        }
        $tags = Tag::withCount('posts')->get();
        $norm = fn (string $s) => preg_replace('/[\s\-_.,।\x{200C}\x{200D}]+/u', '', mb_strtolower($s));
        $pairs = [];
        $byNorm = $tags->groupBy(fn ($t) => $norm($t->name));
        foreach ($byNorm as $group) {
            if ($group->count() > 1) {
                $keep = $group->sortByDesc('posts_count')->first();
                foreach ($group->where('id', '!=', $keep->id) as $dup) {
                    $pairs[] = ['keep' => $keep->id, 'keep_name' => $keep->name, 'drop' => $dup->id, 'drop_name' => $dup->name, 'posts' => $dup->posts_count];
                }
            }
        }
        if (! $pairs) {
            return null;
        }

        return EditorTask::create(['type' => 'tags', 'status' => 'open', 'title' => __('ট্যাগ গোছানো: ').count($pairs), 'payload' => ['pairs' => array_slice($pairs, 0, 60)]]);
    }

    /** The owner's click: move pieces to the kept tag, 301 the old tag page, delete the duplicate. */
    public static function mergeTag(int $keepId, int $dropId): void
    {
        $keep = Tag::findOrFail($keepId);
        $drop = Tag::findOrFail($dropId);
        DB::transaction(function () use ($keep, $drop) {
            foreach ($drop->posts()->pluck('posts.id') as $postId) {
                DB::table('post_tag')->insertOrIgnore(['post_id' => $postId, 'tag_id' => $keep->id]);
            }
            DB::table('post_tag')->where('tag_id', $drop->id)->delete();
            \App\Models\Redirect::updateOrCreate(['from_path' => '/tag/'.$drop->slug.'/'], ['to_path' => '/tag/'.$keep->slug.'/', 'status_code' => 301]);
            \App\Models\AuditLog::record('tag.merged', null, ['keep' => $keep->slug, 'drop' => $drop->slug]);
            $drop->delete();
        });
    }

    /** D98: year-round collections (no season) are refreshed in this quiet month. */
    public const YEAR_ROUND_MONTH = 3;

    /**
     * D98 (owner): the seasonal calendar. Run once a month: every seed collection whose season STARTS next month
     * (the first month in its `months`) is prepared now, so it is ready a month ahead. New ones become proposals;
     * published ones only get "new pieces to add" suggestions. Year-round ones are refreshed in March.
     * Nothing is published without the owner. Returns the slugs it worked on.
     */
    public static function seasonal(?\Carbon\CarbonInterface $today = null): array
    {
        return array_values(array_filter(self::due($today), fn ($slug) => (bool) self::themed($slug)[0]));
    }

    /** The seed collections due this month (their season starts next month; year-round ones in March). */
    public static function due(?\Carbon\CarbonInterface $today = null): array
    {
        $today ??= now('Asia/Kolkata');
        $next = $today->copy()->startOfMonth()->addMonth()->month;

        return collect(require database_path('content/collection-seeds.php'))
            ->filter(fn ($seed) => ($m = array_values((array) $seed['months'])) ? $m[0] === $next : $today->month === self::YEAR_ROUND_MONTH)
            ->keys()->values()->all();
    }

    /**
     * D97: a themed collection from the WHOLE archive. $topic is a seed slug (database/content/collection-seeds.php),
     * a seed title, or a free theme. Candidates are found by the theme's words in titles and texts, ranked by how
     * strongly they match and what readers read; the archivist then reads them and keeps the ones that truly fit.
     */
    public static function themed(string $topic): array
    {
        $seeds = require database_path('content/collection-seeds.php');
        $key = collect($seeds)->search(fn ($s, $k) => $k === trim($topic) || $s['title'] === trim($topic));
        $seed = $key !== false ? $seeds[$key] : ['title' => trim($topic), 'months' => [], 'words' => preg_split('/[\s,]+/u', trim($topic)), 'brief' => trim($topic)];
        $words = array_values(array_filter(array_map('trim', (array) $seed['words']), fn ($w) => mb_strlen(trim($w)) >= 2));
        if (! $words) {
            return [null, 'বিষয়টি লিখে দিন।'];
        }
        $editorial = \App\Models\Section::where('slug', 'editorial')->value('id');
        $views = DB::table('post_views')->groupBy('post_id')->selectRaw('post_id, SUM(views) v')->pluck('v', 'post_id');
        $scored = Post::published()->where('language', 'bn')
            ->when($editorial, fn ($q) => $q->where(fn ($w) => $w->whereNull('section_id')->orWhere('section_id', '!=', $editorial)))
            ->where(fn ($q) => collect($words)->each(fn ($w) => $q->orWhere('title', 'like', '%'.$w.'%')->orWhere('body_html', 'like', '%'.$w.'%')))
            ->with('authors', 'section')->get()
            ->map(function (Post $p) use ($words, $views) {
                $text = mb_strtolower(strip_tags((string) $p->body_html));
                $score = 0;
                foreach ($words as $w) {
                    $score += 6 * mb_substr_count(mb_strtolower($p->title), mb_strtolower($w)) + min(8, mb_substr_count($text, mb_strtolower($w)));
                }

                return ['p' => $p, 'score' => $score + min(10, (int) (($views[$p->id] ?? 0) / 20)), 'text' => $text];
            })->sortByDesc('score')->take(120)->values();
        if ($scored->count() < 6) {
            return [null, 'এই বিষয়ে যথেষ্ট লেখা পাওয়া যায়নি।'];
        }
        $list = $scored->map(fn ($x) => 'id '.$x['p']->id.' | '.$x['p']->section?->name_bn.' | '.$x['p']->title.' | '.($x['p']->authors->first()?->publicName() ?? '')
            .' | '.mb_substr(preg_replace('/\s+/u', ' ', $x['text']), 0, 260))->implode("\n");
        [$d, $err] = Claude::json('archive',
            'You are the archivist of Hatpakha (হাতপাখা), a Bengali literary web magazine since 2012. Make a themed collection (সংকলন) from the archive list (DATA). '
            .'Theme: '.$seed['title'].'. '.$seed['brief'].' Keep only pieces where the theme is truly at the heart of the piece, not a passing word. '
            .'Pick 12-20 that read well together: the strongest writing first, a mix of sections and writers, at most 2 per writer, order them as a reader would enjoy them. '
            .'Write a warm 3-4 sentence Bengali intro for the collection page (what binds these pieces, how the season or feeling lives in them). '
            .'Do not invent facts about writers or pieces; do not quote lines. Use only ids given.',
            "<archive>\n".e($list)."\n</archive>",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['intro', 'ids'], 'properties' => [
                'intro' => ['type' => 'string'], 'ids' => ['type' => 'array', 'items' => ['type' => 'integer']]]],
            null, 3000);
        $ids = array_values(array_unique(array_intersect(array_map('intval', (array) ($d['ids'] ?? [])), $scored->pluck('p.id')->all())));
        if (! $d || count($ids) < 4) {
            return [null, $err ?: 'যথেষ্ট লেখা বাছা যায়নি।'];
        }
        $slug = $key !== false ? $key : Collection::uniqueSlug($seed['title']);
        $c = Collection::where('slug', $slug)->first();
        if ($c && $c->status === 'approved') {
            // Already published: suggest only the new pieces, never replace the owner's list.
            $new = array_values(array_diff($ids, array_map('intval', (array) $c->post_ids)));
            if ($new) {
                EditorTask::propose('collection_add', __('সংকলনে নতুন লেখা যোগের প্রস্তাব: ').$c->title, ['add' => $new], $c);
            }

            return [$c, null];
        }
        $c ??= new Collection(['kind' => 'theme', 'slug' => $slug]);
        $c->forceFill(['title' => $seed['title'], 'intro' => mb_substr(trim(strip_tags((string) $d['intro'])), 0, 1200), 'post_ids' => array_slice($ids, 0, 20),
            'months' => $seed['months'], 'status' => 'proposed'])->save();
        EditorTask::propose('collection', __('সংকলনের প্রস্তাব: ').$c->title, [], $c);

        return [$c, null];
    }

    private static function propose(string $kind, string $title, string $intro, array $ids): Collection
    {
        $c = Collection::create(['kind' => $kind, 'title' => mb_substr(trim(strip_tags($title)), 0, 150), 'intro' => mb_substr(trim(strip_tags($intro)), 0, 1200), 'post_ids' => $ids, 'status' => 'proposed']);
        EditorTask::propose('collection', ($kind === 'anthology' ? __('বার্ষিক সংকলনের প্রস্তাব: ') : __('সংকলনের প্রস্তাব: ')).$c->title, [], $c);

        return $c;
    }
}
