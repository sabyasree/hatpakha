<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\BnSearch;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A2 (research 2026-11, owner approved): /lekhok/, every writer with a published piece, অ to হ (then names written in
 * English letters), with a letter bar, the number of pieces, the photo when set (D261) and a search box. One grouped
 * query builds the list; only scalars are cached (D18); a page loads just its 60 writers.
 */
class WritersController extends Controller
{
    public const PER_PAGE = 60;

    public function __invoke(Request $request)
    {
        $all = self::index();
        $q = trim(preg_replace('/\s+/u', ' ', mb_substr(strip_tags((string) $request->query('q', '')), 0, 60)));
        $words = array_values(array_filter(explode(' ', BnSearch::key($q))));
        $list = $words ? array_values(array_filter($all, fn ($w) => collect($words)->every(fn ($x) => str_contains($w[4], $x)))) : $all;

        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator(array_slice($list, ($page - 1) * self::PER_PAGE, self::PER_PAGE), count($list), self::PER_PAGE, $page);
        $paginator->withPath(Url::to('/lekhok'))->withQueryString();
        abort_if($page > 1 && $paginator->isEmpty(), 404);

        $rows = collect($paginator->items());
        $models = Author::whereKey($rows->pluck(0))->get(['id', 'slug', 'display_name', 'display_name_bn', 'avatar_path'])->keyBy('id');
        $writers = $rows->map(fn ($w) => ['author' => $models[$w[0]] ?? null, 'name' => $w[1], 'n' => $w[2], 'letter' => $w[3]])->filter(fn ($w) => $w['author'])->values();

        // The letter bar: each letter that starts a name, and the page it starts on (only for the whole list).
        $letters = [];
        if (! $words) {
            foreach ($all as $i => $w) {
                $letters[$w[3]] ??= intdiv($i, self::PER_PAGE) + 1;
            }
        }

        $count = count($all);
        $seo = new Seo(
            title: 'লেখকেরা'.($page > 1 ? ' — পাতা '.\App\Support\Bn::num((string) $page) : ''),
            description: 'হাতপাখায় ২০১২ থেকে যাঁরা লিখেছেন, '.\App\Support\Bn::num((string) $count).' জন লেখক: নাম ধরে অ থেকে হ, প্রত্যেকের সব লেখা এক জায়গায়।',
            canonical: $page > 1 || $q !== '' ? $request->fullUrl() : Url::to('/lekhok'),
            noindex: $page > 1 || $q !== '' || $count === 0,
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', url('/').'/'], ['আর্কাইভ', Url::to('/archive')], ['লেখকেরা', Url::to('/lekhok')]])],
        );

        return view('writers', ['writers' => $writers, 'paginator' => $paginator, 'letters' => $letters, 'q' => $q, 'count' => $count, 'seo' => $seo]);
    }

    /**
     * Every writer with a published piece, sorted, as scalars: [id, shown name, pieces, letter, folded name].
     * The desk byline is not a writer.
     *
     * @return list<array{0: int, 1: string, 2: int, 3: string, 4: string}>
     */
    public static function index(): array
    {
        return Cache::remember('writers:index:v1', 3600, function () {
            $rows = DB::table('author_post')
                ->join('posts', 'posts.id', '=', 'author_post.post_id')
                ->join('authors', 'authors.id', '=', 'author_post.author_id')
                ->whereIn('posts.status', ['published', 'scheduled'])->where('posts.published_at', '<=', now())
                ->where('authors.is_desk', false)
                ->groupBy('authors.id', 'authors.display_name', 'authors.display_name_bn')
                ->selectRaw('authors.id, authors.display_name, authors.display_name_bn, count(distinct posts.id) as n')
                ->get();
            $list = $rows->map(function ($r) {
                $name = (new Author(['display_name' => $r->display_name, 'display_name_bn' => $r->display_name_bn]))->publicName();

                return [(int) $r->id, $name, (int) $r->n, self::letter($name), BnSearch::key($name.' '.$r->display_name)];
            })->all();
            usort($list, fn ($a, $b) => [self::script($a[1]), mb_strtolower($a[1])] <=> [self::script($b[1]), mb_strtolower($b[1])]);

            return $list;
        });
    }

    /** Bengali names first (অ … হ, in the order of the alphabet), then English letters, then anything else. */
    private static function script(string $name): int
    {
        return preg_match('/^\p{Bengali}/u', $name) ? 0 : (preg_match('/^[A-Za-z]/', $name) ? 1 : 2);
    }

    /** The letter a name is filed under: its first Bengali letter, a capital A–Z, or "#". */
    public static function letter(string $name): string
    {
        $name = ltrim($name);
        if (preg_match('/^[\x{0985}-\x{09B9}\x{09DC}-\x{09DF}]/u', $name, $m)) {
            return $m[0];
        }

        return preg_match('/^[A-Za-z]/', $name) ? strtoupper($name[0]) : '#';
    }
}
