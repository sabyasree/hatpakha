<?php

namespace App\Support;

use Normalizer;

/**
 * Slugs are stored DECODED and NFC-normalised (Bengali has look-alike byte sequences).
 * Old WordPress slugs are percent-encoded lowercase; we decode them on import.
 */
final class Slug
{
    public static function normalize(string $slug): string
    {
        $slug = rawurldecode(trim($slug, "/ \t\n\r"));
        $nfc = class_exists(Normalizer::class) ? Normalizer::normalize($slug, Normalizer::FORM_C) : $slug;

        return mb_strtolower($nfc === false ? $slug : $nfc);
    }

    /** Encode for use in a URL path segment (each segment separately). */
    public static function encode(string $slug): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $slug)));
    }
}
