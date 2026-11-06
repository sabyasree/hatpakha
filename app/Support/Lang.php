<?php

namespace App\Support;

/** /desk speaks Bengali or English (D45). Bengali strings are the keys; English lives in lang/en.json. */
final class Lang
{
    /** Translate every value of a label list (constants can't call __() themselves). */
    public static function t(array $labels): array
    {
        return array_map(fn ($v) => is_string($v) ? __($v) : $v, $labels);
    }
}
