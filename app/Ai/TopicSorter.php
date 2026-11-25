<?php

namespace App\Ai;

use App\Models\Post;
use App\Models\Setting;
use App\Support\Topics;

/**
 * D293: the archivist reads published pieces, twenty at a time, and puts each on up to three topic pages
 * (App\Support\Topics). It never changes a piece; the desk can move any piece by hand, and then the archivist leaves it
 * alone. Nothing runs until the owner starts it from the AI team page (archivist → "বিষয়ের পাতায় সাজান"); after that
 * the hourly shift carries on until the archive is done, and sorts each new piece once it is published.
 */
final class TopicSorter
{
    public const BATCH = 20;

    public const SETTING = 'topics.sort';

    public static function started(): bool
    {
        return Setting::get(self::SETTING) === 'on';
    }

    public static function start(): void
    {
        Setting::put(self::SETTING, 'on');
    }

    /** Published pieces nobody has sorted yet (newest first). */
    public static function waiting()
    {
        return Post::published()->whereNull('topics_by');
    }

    /** Sort up to $max waiting pieces. @return int pieces sorted */
    public static function backlog(int $max = 100): int
    {
        $done = 0;
        foreach (self::waiting()->with('section')->orderByDesc('published_at')->limit($max)->get()->chunk(self::BATCH) as $batch) {
            $err = self::sort($batch->values()->all());
            if ($err !== null) {
                break;   // paused, no key or no answer: try again next hour
            }
            $done += $batch->count();
        }
        if ($done) {
            Topics::forget();
        }

        return $done;
    }

    /** @param  Post[]  $posts  @return ?string error */
    public static function sort(array $posts): ?string
    {
        $slugs = array_keys(Topics::LIST);
        $list = collect(Topics::LIST)->map(fn ($t, $s) => "- {$s}: {$t[0]} ({$t[2]})")->implode("\n");
        $pieces = collect($posts)->map(function (Post $p) {
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br />', '</p>'], "\n", (string) $p->body_html)))));

            return '<piece id="'.$p->id.'" form="'.e((string) $p->format).'" section="'.e((string) $p->section?->name_bn).'"><title>'.e($p->title).'</title><text>'
                .e(mb_substr($text, 0, 900)).'</text></piece>';
        })->implode("\n");

        [$d, $err] = Claude::json('topics',
            'You sort the pieces of হাতপাখা, a Bengali literary web magazine, onto its topic pages, so that readers searching '
            .'for, say, "ভূতের গল্প" find them. The topic pages (slug: name (page title)):'."\n".$list."\n\n"
            .'For each piece in DATA give 0 to 3 topic slugs. Choose a topic only when it is what the piece is mainly about '
            .'(a ghost story is a ghost story; a poem that only mentions rain once is not a rain poem). '
            .'Topics ending in "-golpo" are for prose stories only, topics ending in "-kobita" for poems only; '
            .'"brishtir-kobita", "maa-ke-niye", "bondhutto", "pujor-lekha" and "kolkata" may be any form; "paharer-bhromon" is for travel writing. '
            .'Lists, how-to pieces, advertisements and pieces that are not literature get no topic. Most pieces fit 0 or 1 topic. '
            .'Never rewrite or judge the piece. Answer for every id given.',
            '<pieces>'."\n".$pieces."\n".'</pieces>',
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['pieces'], 'properties' => [
                'pieces' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'topics'], 'properties' => [
                    'id' => ['type' => 'integer'],
                    'topics' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $slugs]],
                ]]],
            ]], (string) config('ai.anthropic.tags_model'), 4000);
        if (! is_array($d)) {
            return $err ?: 'no answer';
        }

        $answers = collect($d['pieces'] ?? [])->keyBy(fn ($a) => (int) ($a['id'] ?? 0));
        foreach ($posts as $p) {
            if (! $answers->has($p->id)) {
                continue;   // not answered: stays waiting for the next run
            }
            $topics = array_values(array_slice(array_unique(array_intersect((array) $answers[$p->id]['topics'], $slugs)), 0, 3));
            // Only if the desk has not placed it meanwhile.
            Post::whereKey($p->id)->whereNull('topics_by')->update(['topics' => json_encode($topics), 'topics_by' => 'ai']);
        }

        return null;
    }

    /** What sorting the whole archive should cost (USD), from its length; shown before the owner starts it. */
    public static function estimate(): array
    {
        $n = self::waiting()->count();
        $tokensIn = $n * 1100 + (int) ceil($n / self::BATCH) * 900;   // ~900 Bengali characters and the title a piece; the instructions a batch
        $tokensOut = $n * 25 + (int) ceil($n / self::BATCH) * 800;
        $price = (array) config('ai.prices.'.config('ai.anthropic.tags_model'), ['in' => 0.10, 'out' => 0.50]);

        return ['pieces' => $n, 'usd' => round($tokensIn / 1e6 * ($price['in'] ?? 0) + $tokensOut / 1e6 * ($price['out'] ?? 0), 2)];
    }
}
