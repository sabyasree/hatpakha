<?php

namespace App\Support;

/** Bengali formatting helpers. */
final class Bn
{
    public static function num(int|string $n): string
    {
        return strtr(is_int($n) ? number_format($n) : $n, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }

    private const MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    public static function date(?\DateTimeInterface $d): string
    {
        return $d ? self::num($d->format('j')).' '.self::MONTHS[(int) $d->format('n') - 1].' '.self::num($d->format('Y')) : '';
    }
}
