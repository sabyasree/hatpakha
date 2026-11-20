<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * D248 (owner: "the declined piece must be safe: it must never come out under someone else's name"; then "yes, build
 * it"). The web search (D50) cannot see a piece that was sent to Hatpakha and never published anywhere. So every
 * piece's text is fingerprinted (runs of five words, hashed, every other hash kept), and a new submission is compared
 * with every piece already here, published, waiting or declined. If a large part of it is in another writer's
 * piece, the editor is told before deciding. It only reports; the editor decides. Costs nothing: no outside service.
 */
final class TextPrints
{
    public const RUN = 5;

    /** A match is reported when this share of the new piece's runs is in one other writer's piece… */
    public const SHARE = 0.3;

    /** …and at least this many runs, so a common phrase or a famous line quoted is not a match. */
    public const MIN_RUNS = 6;

    /** @return list<int> the fingerprint of a text */
    public static function hashes(string $html): array
    {
        $text = html_entity_decode(strip_tags(preg_replace(['~<br\s*/?>~i', '~</p>~i'], [' ', ' '], $html)), ENT_QUOTES | ENT_HTML5);
        $text = mb_strtolower(preg_replace('/[\x{200C}\x{200D}]/u', '', $text));
        $words = preg_split('/[^\p{L}\p{M}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        for ($i = 0; $i + self::RUN <= count($words); $i++) {
            $h = crc32(implode(' ', array_slice($words, $i, self::RUN)));
            if ($h % 2 === 0) {   // the same half everywhere, so shares stay comparable and the table stays small
                $out[$h] = true;
            }
        }

        return array_keys($out);
    }

    /** Write (or rewrite) one piece's fingerprint. */
    public static function store(Post $post): int
    {
        $hashes = self::hashes((string) $post->body_html);
        DB::transaction(function () use ($post, $hashes) {
            DB::table('text_prints')->where('post_id', $post->id)->delete();
            foreach (array_chunk($hashes, 500) as $chunk) {
                DB::table('text_prints')->insertOrIgnore(array_map(fn ($h) => ['post_id' => $post->id, 'hash' => $h], $chunk));
            }
        });
        DB::table('posts')->where('id', $post->id)->update(['print_count' => count($hashes)]);

        return count($hashes);
    }

    /** The pieces already here that hold a large part of this one, by other writers. Writes them on the piece. */
    public static function check(Post $post): array
    {
        $mine = self::hashes((string) $post->body_html);
        if (count($mine) < self::MIN_RUNS) {
            $post->forceFill(['own_match' => []])->saveQuietly();

            return [];
        }
        $authors = $post->authors()->pluck('authors.id')->all();
        $users = $post->authors()->whereNotNull('user_id')->pluck('user_id')->push($post->submitted_by)->filter()->unique()->all();
        $hits = DB::table('text_prints')->whereIn('hash', $mine)->where('post_id', '!=', $post->id)
            ->groupBy('post_id')->selectRaw('post_id, count(*) as n')->havingRaw('count(*) >= ?', [self::MIN_RUNS])->orderByDesc('n')->limit(20)->pluck('n', 'post_id');
        $found = [];
        foreach (Post::withTrashed()->with('authors')->whereIn('id', $hits->keys())->get() as $other) {
            $share = $hits[$other->id] / count($mine);
            $same = $other->authors->pluck('id')->intersect($authors)->isNotEmpty()
                || $other->authors->pluck('user_id')->filter()->intersect($users)->isNotEmpty()
                || ($other->submitted_by && in_array($other->submitted_by, $users, true));
            if ($share < self::SHARE || $same) {
                continue;   // the same writer sending their own piece again is not copying
            }
            $found[] = ['post_id' => $other->id, 'title' => mb_substr((string) $other->title, 0, 160), 'writer' => (string) ($other->authors->first()?->publicName() ?? ''),
                'status' => $other->trashed() ? 'deleted' : (string) $other->status, 'share' => (int) round(100 * min(1, $share))];
        }
        usort($found, fn ($a, $b) => $b['share'] <=> $a['share']);
        $found = array_slice($found, 0, 5);
        $post->forceFill(['own_match' => $found])->saveQuietly();
        if ($found) {
            \App\Models\EditorTask::propose('plagiarism', __('আগে জমা পড়া অন্য লেখকের লেখার সঙ্গে মিল: ').$post->title, ['own' => $found], $post);
            \App\Models\AuditLog::record('post.own_match', $post, ['matches' => array_column($found, 'post_id')]);
        }

        return $found;
    }

    /** One line for the editor (approve dialog, piece page): empty when nothing matched. */
    public static function summary(Post $post): string
    {
        $m = (array) $post->own_match;
        if (! $m) {
            return '';
        }
        $status = ['published' => 'প্রকাশিত', 'rejected' => 'মনোনীত হয়নি', 'changes' => 'বদল চাওয়া', 'pending' => 'অপেক্ষায়', 'draft' => 'খসড়া', 'deleted' => 'মুছে ফেলা', 'scheduled' => 'প্রকাশের অপেক্ষায়', 'archived' => 'সরানো'];

        return __('আগে হাতপাখায় জমা পড়া অন্য লেখকের লেখার সঙ্গে মিল:').' '.implode(' · ', array_map(fn ($x) => '“'.$x['title'].'” — '.$x['writer'].' ('
            .($status[$x['status']] ?? $x['status']).', '.Bn::num((string) $x['share']).'% মিল)', $m));
    }

    /** The pieces not fingerprinted yet, a batch at a time (scheduler): the old ones fill in over a few minutes. */
    public static function backfill(int $batch = 300): int
    {
        $done = 0;
        foreach (Post::withTrashed()->whereNull('print_count')->orderBy('id')->limit($batch)->get(['id', 'body_html']) as $p) {
            self::store($p);
            $done++;
        }

        return $done;
    }
}
