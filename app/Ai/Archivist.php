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

    /** January: the yearly anthology list (D10) from last year's pieces: AI scores + what readers read. */
    public static function anthology(?int $year = null): ?Collection
    {
        $year ??= now('Asia/Kolkata')->year - 1;
        if (Collection::where('kind', 'anthology')->where('title', 'like', '%'.\App\Support\Bn::year($year).'%')->exists()) {
            return null;
        }
        $editorial = \App\Models\Section::where('slug', 'editorial')->value('id');
        $posts = Post::published()->with('authors', 'section', 'latestAiReview')->whereYear('published_at', $year)
            ->when($editorial, fn ($q) => $q->where(fn ($w) => $w->whereNull('section_id')->orWhere('section_id', '!=', $editorial)))->get();
        if ($posts->count() < 10) {
            return null;
        }
        $views = DB::table('post_views')->whereIn('post_id', $posts->pluck('id'))->groupBy('post_id')->selectRaw('post_id, SUM(views) v')->pluck('v', 'post_id');
        $list = $posts->map(fn (Post $p) => 'id '.$p->id.' | '.$p->section?->name_bn.' | '.$p->title.' | '.($p->authors->first()?->publicName() ?? '')
            .' | AI score '.($p->latestAiReview?->score ?? '?').' | reads '.($views[$p->id] ?? 0))->implode("\n");
        [$d] = Claude::json('archive',
            'Choose 20-30 pieces for the yearly anthology (বার্ষিক সংকলন) of a Bengali literary magazine: the best writing of the year, a fair mix of sections and writers '
            .'(at most 2 per writer). Quality first, reader interest second. Also a 3-4 sentence Bengali intro (no invented facts). Use only ids given.',
            "<pieces year=\"{$year}\">\n".e($list)."\n</pieces>",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['intro', 'ids'], 'properties' => ['intro' => ['type' => 'string'], 'ids' => ['type' => 'array', 'items' => ['type' => 'integer']]]],
            null, 3000);
        $ids = array_values(array_unique(array_intersect(array_map('intval', (array) ($d['ids'] ?? [])), $posts->pluck('id')->all())));

        return $d && count($ids) >= 10 ? self::propose('anthology', 'বার্ষিক সংকলন '.\App\Support\Bn::year($year), $d['intro'], array_slice($ids, 0, 30)) : null;
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
