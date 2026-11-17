<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\Newsletter;
use App\Models\Post;
use App\Models\Serial;

/**
 * D225 (owner, 2026-11-17: "instead of a list, make some creative weekly newsletter"): the readers' letter is laid
 * out like a small magazine instead of a list of links. One piece leads, with its picture and one exact line from
 * the writing; each running serial appears once, with its newest episode; the other pieces follow as short rows;
 * then a reader's own words, today's riddle to try, and the week's invitations. Everything shown is taken from
 * the site: a quoted line that is not found word for word in the piece is dropped, and a reader's comment is used
 * only if they did not opt out (comments.feature_opt_out).
 */
final class LetterDesign
{
    public const MAX_PIECES = 7;

    /** One piece as the letter stores it. Title, writer, link and picture come from the piece, never from a person or the AI. */
    public static function item(Post $p, string $line = '', string $quote = ''): array
    {
        $p->loadMissing('authors', 'serial');
        $serial = $p->serial_id ? $p->serial : null;
        $image = $p->hasCardImage() ? $p->featured_image_path : ($serial?->cover_path ?: null);

        return [
            'id' => $p->id, 'title' => $p->title, 'author' => $p->authors->first()?->publicName() ?? '', 'url' => $p->url(),
            'line' => trim(strip_tags($line)), 'kind' => $serial ? 'serial' : 'piece',
            'image' => $image ? asset($image) : null,
            'serial' => $serial?->title, 'episode' => $serial ? $p->episodeLabel() : null,
            'quote' => $serial ? '' : self::quote($p, $quote),
        ];
    }

    /** A line is shown as "from the piece" only if it stands in the piece exactly (spaces and line breaks aside). */
    public static function quote(Post $p, string $quote): string
    {
        $flat = fn (string $t) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('~<br\s*/?>|</p>~i', ' ', $t)))));
        $q = trim($flat($quote), " \"'“”‘’");

        return mb_strlen($q) >= 12 && mb_strlen($q) <= 220 && str_contains($flat((string) $p->body_html), $q) ? $q : '';
    }

    /** The text the copywriter may quote from: the opening of a piece, lines kept apart. */
    public static function excerpt(Post $p, int $max = 1400): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('~<br\s*/?>|</p>~i', "\n", (string) $p->body_html)));

        return mb_substr(trim(preg_replace(['/[ \t]+/u', '/\n\s*\n+/u'], [' ', "\n"], $text)), 0, $max);
    }

    /**
     * How the pieces are laid out. Keys are the places in $n->items (the link numbers, D117).
     *
     * @return array{lead: ?int, serials: list<int>, more: list<int>}
     */
    public static function sections(Newsletter $n): array
    {
        $items = (array) $n->items;
        $designed = $n->source !== 'ai_writers' && collect($items)->contains(fn ($it) => ! empty($it['kind']));
        if (! $designed) {
            return ['lead' => null, 'serials' => [], 'more' => array_keys($items)];
        }
        $serials = array_keys(array_filter($items, fn ($it) => ($it['kind'] ?? '') === 'serial'));
        $pieces = array_values(array_diff(array_keys($items), $serials));
        $lead = $pieces[0] ?? null;
        if ($lead === null) {
            $lead = array_shift($serials);
        }

        return ['lead' => $lead, 'serials' => array_values($serials), 'more' => array_values(array_diff($pieces, [$lead]))];
    }

    /** A reader's own words about a piece, from the last weeks (opt-out respected). */
    public static function voice(): ?array
    {
        $c = Comment::with('post')->where('status', 'approved')->where('feature_opt_out', false)->where('created_at', '>=', now()->subDays(30))
            ->whereHas('post', fn ($q) => $q->where('status', 'published'))->latest()->limit(60)->get()
            ->first(fn ($c) => mb_strlen((string) $c->body) >= 40 && mb_strlen((string) $c->body) <= 260 && preg_match('/[\x{0985}-\x{09B9}]/u', (string) $c->body)
                && filled($c->author_name) && ! Comment::hasLink((string) $c->body));

        return $c ? ['kind' => 'voice', 'title' => 'পাঠকের কথা', 'line' => trim((string) $c->body),
            'author' => $c->author_name.' · “'.$c->post->title.'” পড়ে', 'url' => $c->post->url()] : null;
    }

    /** A detective case whose guessing is open (D213). */
    public static function guess(): ?array
    {
        $s = Serial::where('status', 'active')->where('writer', 'serial_detective')->get()->first(fn (Serial $s) => CaseGuesses::open($s));

        return $s ? ['kind' => 'guess', 'title' => 'আপনিও গোয়েন্দা: কে করেছে?', 'line' => '“'.$s->title.'”-এর শেষ পর্ব বেরোনোর আগে আপনার অনুমান পাঠান। শেষে জানা যাবে কতজন ঠিক ধরলেন।',
            'author' => 'অনুমান পাঠান', 'url' => $s->url()] : null;
    }
}
