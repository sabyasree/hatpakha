<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** D325: how one letter, sign or joined letter is written, stroke by stroke. Children see only approved ones. */
class LearnStroke extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['strokes' => 'array'];
    }

    /** @return array<string, array> glyph => strokes, approved only */
    public static function approved(array $glyphs): array
    {
        $glyphs = array_values(array_unique(array_map([\App\Support\LearnCourse::class, 'nfc'], array_filter($glyphs))));

        return $glyphs === [] ? [] : self::where('status', 'approved')->whereIn('glyph', $glyphs)->get()->mapWithKeys(fn ($s) => [$s->glyph => $s->strokes])->all();
    }

    /** Strokes sent from the desk: at most 12 strokes of 300 points, numbers kept inside the 0–100 box. */
    public static function clean(mixed $strokes): ?array
    {
        if (! is_array($strokes) || $strokes === [] || count($strokes) > 12) {
            return null;
        }
        $out = [];
        foreach ($strokes as $st) {
            if (! is_array($st) || count($st) < 2 || count($st) > 300) {
                return null;
            }
            $out[] = array_map(fn ($p) => [round(max(0, min(100, (float) ($p[0] ?? 0))), 1), round(max(0, min(100, (float) ($p[1] ?? 0))), 1)], array_values($st));
        }

        return $out;
    }
}
