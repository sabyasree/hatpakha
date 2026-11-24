<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * C6: a line a signed-in reader kept from a piece ("রাখুন"). Plain text only, at most 500 characters, and only words
 * that are really in the piece (App\Http\Controllers\SavedLineController). Seen only by that reader.
 */
class SavedLine extends Model
{
    public const UPDATED_AT = null;

    public const MAX = 500;

    /** How many lines one reader may keep. */
    public const LIMIT = 300;

    protected $fillable = ['user_id', 'post_id', 'text'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Spaces made plain, line breaks kept (a poem's lines), no markup at all. */
    public static function clean(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $text) ?? '';
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', str_replace("\r", '', $text)) ?? '';
        $text = preg_replace('/ *\n[\s]*/u', "\n", $text) ?? '';

        return trim($text);
    }

    /** The line, with every space removed, appears in the piece's own text (so only the writer's words are kept). */
    public static function isInPiece(string $text, Post $post): bool
    {
        $squash = fn (string $s) => preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $s) ?? '';
        $body = $squash(html_entity_decode(strip_tags((string) $post->body_html), ENT_QUOTES | ENT_HTML5));
        $line = $squash($text);

        return $line !== '' && str_contains($body, $line);
    }
}
