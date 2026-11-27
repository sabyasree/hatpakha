<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;

/**
 * D330 (games research, owner: "make it very user friendly… they should like to play"): after an answer, a line from a
 * হাতপাখা piece that uses the word, with a link to it. Real text only, never written for the game: the games lead into
 * the magazine (the longest-staying readers both play and read). Words of three letters or more; the word must stand
 * on its own in the line (a Bengali ending such as -এর, -টি, -গুলো is allowed after it). Children's pieces and the
 * desk's own pieces are left out. Cached a month per word, as plain values (D18).
 */
final class InMagazine
{
    private const ENDINGS = '(?:গুলো|গুলি|দের|েরা|ের|কে|টি|টা|রা|র|ে|য়|ও|ই)?';

    /** @return ?array{u: string, t: string, s: string} link, title, the line */
    public static function line(string $word): ?array
    {
        $word = \Normalizer::normalize(trim($word), \Normalizer::FORM_C) ?: '';
        if (mb_strlen($word) < 3 || preg_match('/[^\x{0980}-\x{09FF}]/u', $word)) {
            return null;
        }
        $hit = Cache::remember('hp.inmag.'.md5($word), 86400 * 30, function () use ($word) {
            $pattern = '/(?<![\x{0980}-\x{09FF}])'.preg_quote($word, '/').self::ENDINGS.'(?![\x{0980}-\x{09FF}])/u';
            $posts = Post::published()->where('body_html', 'like', '%'.$word.'%')
                ->whereDoesntHave('section', fn ($q) => $q->where('slug', \App\Models\Section::KIDS))
                ->whereDoesntHave('authors', fn ($q) => $q->where('is_desk', true))
                ->latest('published_at')->limit(12)->get(['id', 'slug', 'title', 'body_html', 'section_id']);
            foreach ($posts as $p) {
                $text = WeeklyQuiz::plain((string) $p->body_html);
                if (! preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                $at = mb_strlen(substr($text, 0, $m[0][1]));
                // the sentence round the word, at most about 110 letters
                $start = max(0, $at - 55);
                $from = mb_substr($text, $start, 130);
                if ($start > 0 && ($cut = mb_strpos($from, ' ')) !== false && $cut < 20) {
                    $from = mb_substr($from, $cut + 1);
                }
                foreach (['।', '?', '!'] as $stop) {
                    $pos = mb_strpos($from, $stop, max(0, mb_strpos($from, $word) ?: 0));
                    if ($pos !== false && $pos > 20) {
                        $from = mb_substr($from, 0, $pos + 1);
                        break;
                    }
                }
                $line = trim($from);
                if (mb_strlen($line) > 120) {
                    $line = rtrim(mb_substr($line, 0, 118)).'…';
                }

                return ['u' => $p->url(), 't' => (string) $p->title, 's' => ($start > 0 ? '…' : '').$line];
            }

            return false;
        });

        return $hit ?: null;
    }
}
