<?php

namespace App\Support;

/**
 * Writers shown by an old WordPress login ("kolomsainak007", "8257929077") get the name they wrote in their own profile
 * (owner, 2026-11-12: "check if the user added a name to their profile, or else keep as is"). Nothing is invented: a
 * writer who never filled in a name keeps the login.
 */
final class WriterNames
{
    /** A login, not a name: one word of Latin letters/digits/._@-, with a digit, a separator or all lower case. */
    public static function loginLike(?string $n): bool
    {
        $n = trim((string) $n);

        return $n !== '' && (bool) preg_match('/^[A-Za-z0-9._@\-]+$/', $n)
            && (bool) preg_match('/[0-9._@\-]|^[a-z]+$/', $n);
    }

    /** "BHASKAR DAS" / "chirantan dasgupta" → "Bhaskar Das" / "Chirantan Dasgupta"; mixed case and Bengali are left alone. */
    public static function tidy(string $n): string
    {
        $n = trim(preg_replace('/\s+/u', ' ', html_entity_decode($n)));

        return implode(' ', array_map(
            fn ($w) => preg_match('/^[A-Za-z]+$/', $w) && ($w === strtolower($w) || $w === strtoupper($w)) ? ucfirst(strtolower($w)) : $w,
            explode(' ', $n),
        ));
    }

    /** The name from a profile (first + last), or null when it is empty, a login itself, or the same as the login. */
    public static function fromProfile(?string $first, ?string $last, string $login): ?string
    {
        $name = self::tidy(trim(strip_tags((string) $first).' '.strip_tags((string) $last)));
        if ($name === '' || mb_strlen($name) > 80 || self::loginLike($name) || str_contains($name, '@') || preg_match('~https?://|www\.~i', $name)
            || mb_strtolower($name) === mb_strtolower(trim($login))) {
            return null;
        }

        return $name;
    }
}
