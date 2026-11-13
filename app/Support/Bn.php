<?php

namespace App\Support;

/** Bengali formatting helpers. */
final class Bn
{
    /** Year as digits without a thousands separator (২০১৬, not ২,০১৬). */
    public static function year(int $y): string
    {
        return self::num((string) $y);
    }

    /** /desk in English (D45): digits stay 0-9. The public site never sets this. */
    public static bool $latin = false;

    public static function num(int|string $n): string
    {
        if (self::$latin) {
            return is_int($n) ? number_format($n) : (string) $n;
        }

        return strtr(is_int($n) ? number_format($n) : $n, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }

    public const MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    public static function date(?\DateTimeInterface $d): string
    {
        if (! $d) {
            return '';
        }

        return self::$latin   // D169: English pages say "12 November 2026"
            ? $d->format('j F Y')
            : self::num($d->format('j')).' '.self::MONTHS[(int) $d->format('n') - 1].' '.self::num($d->format('Y'));
    }
}
