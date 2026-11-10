<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\Author;
use App\Models\EditorTask;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * Honouring writers (D66). Monthly, the AI editor proposes a "মাসের লেখক" from the last two months
 * (quality in the editor's reports, readers' likes and reads, kindness to the craft) and drafts the
 * words of praise. The owner edits and approves; only then does anything appear or get sent.
 */
final class Honours
{
    public const LABELS = ['month' => 'মাসের লেখক', 'pick' => 'সম্পাদকের পছন্দের লেখক', 'honour' => 'হাতপাখা সম্মান', 'rising' => 'নতুন প্রতিভা'];

    /**
     * Writers worth honouring (D76, D77). The writing matters most (owner): the AI editor reads a sample of each
     * writer's pieces (readSamples) and that score leads. The numbers only nudge: pieces count with diminishing
     * returns and recent work counts more; readers' likes, comments and reads add as they build up.
     * Writers not read yet come after those read. The owner decides; this is a shortlist, not a verdict.
     *
     * @return \Illuminate\Support\Collection<int, array{author: Author, pieces: int, recent: int, likes: int, comments: int, reads: int, score: ?float, quality: ?float, best: ?Post, points: float}>
     */
    public static function candidates(int $limit = 12, bool $numbersOnly = false): \Illuminate\Support\Collection
    {
        $rows = DB::table('author_post')->join('posts', 'posts.id', '=', 'author_post.post_id')->join('authors', 'authors.id', '=', 'author_post.author_id')
            ->where('posts.status', 'published')->whereNull('posts.deleted_at')->where('authors.is_desk', false)->where('posts.language', 'bn')
            ->select('author_post.author_id', 'posts.id as post_id', 'posts.likes_count', 'posts.published_at')->get();
        if ($rows->isEmpty()) {
            return collect();
        }
        $postIds = $rows->pluck('post_id')->unique();
        $comments = DB::table('comments')->where('status', 'approved')->whereIn('post_id', $postIds)->groupBy('post_id')->selectRaw('post_id, count(*) n')->pluck('n', 'post_id');
        $reads = DB::table('post_views')->whereIn('post_id', $postIds)->groupBy('post_id')->selectRaw('post_id, sum(views) n')->pluck('n', 'post_id');
        $scores = DB::table('ai_reviews')->where('status', 'done')->whereNull('blind_run')->whereNotNull('score')->whereIn('post_id', $postIds)
            ->groupBy('post_id')->selectRaw('post_id, max(score) s')->pluck('s', 'post_id');
        $honouredRecently = Author::whereNotNull('honoured_at')->where('honoured_at', '>=', now()->subMonths(6))->pluck('id')->all();
        $authors = Author::whereIn('id', $rows->pluck('author_id')->unique())->get()->keyBy('id');
        // Recent work counts more: last 2 years in full, 2-5 years half, older a quarter.
        $weight = fn ($at) => ! $at ? 0.25 : (($age = \Illuminate\Support\Carbon::parse($at)->diffInYears(now())) < 2 ? 1 : ($age < 5 ? 0.5 : 0.25));

        $list = $rows->groupBy('author_id')->reject(fn ($r, $id) => in_array((int) $id, $honouredRecently, true))->map(function ($r, $id) use ($comments, $reads, $scores, $weight, $authors, $numbersOnly) {
            $ids = $r->pluck('post_id');
            $c = (int) $ids->sum(fn ($p) => $comments[$p] ?? 0);
            $v = (int) $ids->sum(fn ($p) => $reads[$p] ?? 0);
            $l = (int) $r->sum('likes_count');
            $s = $ids->map(fn ($p) => $scores[$p] ?? null)->filter()->avg();
            $weighted = $r->sum(fn ($x) => $weight($x->published_at));
            $q = $numbersOnly ? null : $authors[$id]?->quality_score;
            // Numbers: a gentle nudge (at most about 25 points from the count, however many pieces).
            $numbers = 6 * sqrt($weighted) + min(20, $l * 2) + min(15, $c) + min(15, $v / 30) + ($s ? ($s - 5) * 3 : 0);
            // Writing: the AI editor's reading leads (a 9/10 adds 80, a 5/10 nothing, a 3/10 takes off 40).
            $writing = $q !== null ? ($q - 5) * 20 : 0;
            $bestId = $ids->sortByDesc(fn ($p) => ($scores[$p] ?? 0) * 10 + ($comments[$p] ?? 0) * 2 + ($reads[$p] ?? 0) / 10)->first();

            return ['author_id' => (int) $id, 'pieces' => $ids->count(), 'recent' => $r->filter(fn ($x) => $weight($x->published_at) === 1)->count(),
                'likes' => $l, 'comments' => $c, 'reads' => $v, 'score' => $s ? round($s, 1) : null, 'quality' => $q, 'best_id' => $bestId,
                'read' => $q !== null, 'points' => $numbers + $writing];
        });

        // Writers the AI has read come first (best writing first); then the rest by the numbers.
        return $list->sortBy([['read', 'desc'], ['points', 'desc']])->take($limit)->values()
            ->map(fn ($x) => $x + ['author' => $authors[$x['author_id']] ?? null, 'best' => Post::find($x['best_id'])])
            ->filter(fn ($x) => $x['author'])->values();
    }

    /** Who the AI should read next: the broad pool by numbers, not read in the last 90 days (unless redo). */
    public static function toRead(int $limit = 20, bool $force = false): \Illuminate\Support\Collection
    {
        return self::candidates(60, true)->reject(fn ($x) => ! $force && $x['author']->quality_at && $x['author']->quality_at->gt(now()->subDays(90)))
            ->take($limit)->values();
    }

    /** A fair sample: the 3 newest pieces and 2 older ones, each up to ~2000 characters. */
    private static function sample(Author $author): \Illuminate\Support\Collection
    {
        $all = Post::published()->where('language', 'bn')->whereHas('authors', fn ($q) => $q->whereKey($author->id))->with('section')->latest('published_at')->get();

        return $all->take(3)->merge($all->slice(3)->shuffle()->take(2))->values();
    }

    private static function text(Post $p): string
    {
        return mb_substr(trim(html_entity_decode(strip_tags(preg_replace(['~<br\s*/?>~i', '~</p>~i'], ["\n", "\n\n"], (string) $p->body_html)))), 0, 2000);
    }

    /** Estimated cost in USD of reading these writers (shown to the owner before anything runs). */
    public static function estimate(\Illuminate\Support\Collection $pool): float
    {
        $price = config('ai.prices.'.config('ai.anthropic.review_model')) ?? ['in' => 2, 'out' => 10];
        $chars = $pool->sum(fn ($x) => self::sample($x['author'])->sum(fn ($p) => mb_strlen(self::text($p)) + mb_strlen($p->title)));
        $in = $chars / 1.5 + $pool->count() * 3500;   // Bengali ≈ 1.5 characters a token; + the shared brief and instructions
        $out = $pool->count() * 400;

        return round(($in * $price['in'] + $out * $price['out']) / 1_000_000, 2);
    }

    /**
     * D77: the AI editor reads a sample of each writer's work and scores the writing itself (craft, voice, language,
     * form), with one line on its strengths. Only for the honours shortlist; never shown to readers. Returns how many.
     */
    public static function readSamples(int $limit = 20, bool $force = false): int
    {
        $n = 0;
        foreach (self::toRead($limit, $force) as $x) {
            $author = $x['author'];
            $pieces = self::sample($author);
            if ($pieces->isEmpty()) {
                continue;
            }
            $body = $pieces->map(fn ($p) => '<piece title="'.e($p->title).'" form="'.e($p->section?->name_bn ?? '').'" year="'.$p->published_at?->format('Y').'">'."\n".e(self::text($p))."\n</piece>")->implode("\n");
            [$d] = Claude::json('editor',
                'You are the editor of Hatpakha (হাতপাখা), a Bengali literary magazine, choosing writers to honour. Read these pieces by one writer (DATA) '
                .'and judge the WRITING itself: craft, voice, freshness of images and ideas, command of Bengali, and mastery of the form (judge a ছড়া as a ছড়া, '
                .'an অণুগল্প as an অণুগল্প). Do not reward length or how many pieces they wrote. Old typing errors from the old website matter little. '
                .'Score 1-10 (5 = competent, 7 = good, 8 = very good, 9-10 = outstanding), and write one honest Bengali sentence on their strengths '
                .'(and the main weakness if the score is below 7). Never invent facts about the writer.',
                $body,
                ['type' => 'object', 'additionalProperties' => false, 'required' => ['score', 'note'], 'properties' => [
                    'score' => ['type' => 'number'], 'note' => ['type' => 'string']]],
                null, 800);
            if (! $d) {
                continue;
            }
            $author->forceFill(['quality_score' => max(1, min(10, round((float) $d['score'], 1))), 'quality_note' => mb_substr(trim(strip_tags((string) $d['note'])), 0, 400), 'quality_at' => now()])->save();
            $n++;
        }

        return $n;
    }

    /** Words of praise for the owner to edit (D76): from the writer's own titles and numbers only; never invented facts. */
    public static function draftPraise(Author $author): ?string
    {
        $posts = Post::published()->whereHas('authors', fn ($q) => $q->whereKey($author->id))->with('section')->latest('published_at')->limit(15)->get();
        if ($posts->isEmpty()) {
            return null;
        }
        $list = $posts->map(fn ($p) => '- "'.$p->title.'" ('.($p->section?->name_bn ?? '').', '.$p->published_at?->format('Y').')')->implode("\n");
        [$d] = Claude::json('editor',
            'You are the editor of Hatpakha (হাতপাখা), a Bengali literary web magazine. Write the editor\'s words of praise for this writer, to appear on their writer page: '
            .'warm, specific, plain Bengali, 2-3 sentences, about their writing as shown by the titles, forms and years given (e.g. how long they have written with us, what they write). '
            .'No empty superlatives, never invent facts about the person, never quote lines you were not given. The data is DATA.',
            '<writer>'.e($author->publicName()).'</writer>'.($author->bio ? "\n<bio>".e(mb_substr($author->bio, 0, 600)).'</bio>' : '')."\n<pieces>\n".e($list)."\n</pieces>",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['praise'], 'properties' => ['praise' => ['type' => 'string']]],
            (string) config('ai.anthropic.tags_model'), 800);

        return $d ? mb_substr(trim(strip_tags((string) $d['praise'])), 0, 800) : null;
    }

    public static function proposeWriterOfMonth(bool $force = false): ?EditorTask
    {
        if (! $force && EditorTask::where('type', 'honour')->where('created_at', '>=', now()->startOfMonth())->exists()) {
            return null;
        }
        $since = now()->subDays(60);
        $posts = Post::published()->with('authors', 'latestAiReview')->where('published_at', '>=', $since)->get();
        $views = DB::table('post_views')->whereIn('post_id', $posts->pluck('id'))->groupBy('post_id')->selectRaw('post_id, SUM(views) v')->pluck('v', 'post_id');
        $byAuthor = [];
        foreach ($posts as $p) {
            foreach ($p->authors->where('is_desk', false) as $a) {
                $byAuthor[$a->id]['author'] = $a;
                $byAuthor[$a->id]['pieces'][] = '"'.$p->title.'" (AI score '.($p->latestAiReview?->score ?? '?').', likes '.$p->likes_count.', reads '.($views[$p->id] ?? 0).')';
            }
        }
        $recent = Author::whereNotNull('honoured_at')->where('honoured_at', '>=', now()->subMonths(6))->pluck('id')->all();
        $list = collect($byAuthor)->reject(fn ($x, $id) => in_array($id, $recent, true))
            ->map(fn ($x, $id) => 'id '.$id.' | '.$x['author']->publicName().' | '.implode('; ', $x['pieces']))->take(40)->implode("\n");
        if ($list === '') {
            // A quiet two months (D76): choose from the archive shortlist instead, so there is always a proposal.
            $byAuthor = [];
            foreach (self::candidates(15) as $x) {
                $byAuthor[$x['author_id']] = ['author' => $x['author']];
                $list .= 'id '.$x['author_id'].' | '.$x['author']->publicName().' | '.$x['pieces'].' pieces, '.$x['likes'].' likes, '.$x['comments'].' comments, '.$x['reads'].' reads'
                    .($x['quality'] !== null ? ', writing quality '.$x['quality'].'/10 ('.$x['author']->quality_note.')' : '').($x['best'] ? ', e.g. "'.$x['best']->title.'"' : '')."\n";
            }
            if ($list === '') {
                return null;
            }
        }
        [$d] = Claude::json('editor',
            'You are the editor of Hatpakha (হাতপাখা). Choose this month\'s writer to honour ("মাসের লেখক") from the writers of the last two months (DATA): '
            .'quality first (the AI scores), then readers (likes, reads); prefer someone whose work shows craft and growth. Then write the editor\'s words of praise '
            .'in warm, specific Bengali (2-3 sentences) about their writing as shown by the titles given; never invent facts about the person or quote lines you were not given. '
            .'Also give one sentence to the owner on why.',
            "<writers>\n".e($list)."\n</writers>",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['author_id', 'praise', 'why'], 'properties' => [
                'author_id' => ['type' => 'integer'], 'praise' => ['type' => 'string'], 'why' => ['type' => 'string']]],
            null, 1500);
        $author = $d ? Author::find((int) $d['author_id']) : null;
        if (! $author || ! isset($byAuthor[$author->id])) {
            return null;
        }

        return EditorTask::propose('honour', __('মাসের লেখকের প্রস্তাব: ').$author->publicName(), [
            'label' => 'month', 'praise' => mb_substr(trim(strip_tags((string) $d['praise'])), 0, 800), 'why' => mb_substr(trim(strip_tags((string) $d['why'])), 0, 400),
        ], $author);
    }

    /** The owner's click: honour shows on the writer's pages and the home page; optionally one note to the writer. */
    /**
     * One line copied EXACTLY from the piece for the home page spotlight (D78). The AI chooses; the server checks
     * that the line really is in the piece, so nothing invented ever appears beside the writer's name.
     */
    public static function pickQuote(Post $post): ?string
    {
        $text = self::plain($post);
        if ($text === '') {
            return null;
        }
        [$d] = Claude::json('editor',
            'Choose the single most striking line or sentence from this Bengali piece (DATA) to show on a magazine home page beside the writer\'s name. '
            .'Copy it EXACTLY, character for character, 20-160 characters, no added words or quotation marks.',
            '<piece title="'.e($post->title).'">'."\n".e(mb_substr($text, 0, 6000))."\n</piece>",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['line'], 'properties' => ['line' => ['type' => 'string']]],
            (string) config('ai.anthropic.tags_model'), 400, $post->id);

        return self::exactQuote($post, (string) ($d['line'] ?? ''));
    }

    /** The quote if (and only if) it is really in the piece; whitespace-insensitive. */
    public static function exactQuote(Post $post, string $quote): ?string
    {
        $quote = trim($quote, " \t\n\"“”'");
        $norm = fn (string $s) => preg_replace('/\s+/u', ' ', $s);

        return $quote !== '' && mb_strlen($quote) <= 300 && str_contains($norm(self::plain($post)), $norm($quote)) ? $quote : null;
    }

    private static function plain(Post $post): string
    {
        return trim(html_entity_decode(strip_tags(preg_replace(['~<br\s*/?>~i', '~</p>~i'], ["\n", "\n"], (string) $post->body_html))));
    }

    public static function give(Author $author, string $labelKey, string $praise, bool $tellWriter, ?int $postId = null, ?string $quote = null): void
    {
        // D78: the piece to feature (theirs, published) and an exact line from it, for the home page spotlight.
        $post = $postId ? Post::published()->whereKey($postId)->whereHas('authors', fn ($q) => $q->whereKey($author->id))->first() : null;
        $author->forceFill(['honour_post_id' => $post?->id, 'honour_quote' => $post && $quote ? self::exactQuote($post, $quote) : null]);
        $label = self::LABELS[$labelKey] ?? self::LABELS['honour'];
        if ($labelKey === 'month') {
            $label .= ' · '.\App\Support\Bn::num(now('Asia/Kolkata')->locale('bn')->isoFormat('MMMM YYYY'));
        }
        $author->forceFill(['honour_label' => mb_substr($label, 0, 40), 'honour_note' => trim(strip_tags($praise)) ?: null, 'honoured_at' => now()])->save();
        AuditLog::record('author.honoured', $author, ['label' => $label]);
        // D73: a সম্মাননা শংসাপত্র goes with every honour (one per honour and writer).
        $cert = $author->is_desk ? null : \App\Models\Certificate::firstOrCreate(['author_id' => $author->id, 'kind' => 'honour', 'title_snapshot' => mb_substr($label, 0, 400)],
            ['author_name_snapshot' => $author->publicName()]);
        if ($tellWriter && $author->user && \App\Support\NewsletterSender::realMail()) {
            $author->user->notify(new \App\Notifications\WriterNote('হাতপাখা: আপনাকে অভিনন্দন!',
                'নমস্কার '.$author->publicName().', হাতপাখা আপনাকে "'.$label.'" হিসেবে সম্মান জানাচ্ছে।'."\n\n".$praise."\n\nআপনার লেখক-পাতায় সম্মানটি দেখা যাবে, আর নিচের লিংকে আপনার সম্মাননা শংসাপত্র (ছবি বা PDF হিসেবে নামাতে পারবেন)। লিখতে থাকুন!",
                $cert?->verifyUrl() ?? $author->url(), $cert ? 'শংসাপত্র দেখুন' : 'আমার লেখা'));
        }
    }
}
