<?php

namespace App\Support;

/**
 * D316 (owner, 2026-11-27: "build … 5", baby names; three letter pages a week; automatic once checked; English words in
 * the address): /janun/names/{boy|girl}-names-{letter}/. A name is printed only when two dictionaries give the meaning
 * we print (NameDesk::DICTIONARIES); a page needs MIN_NAMES such names or it waits.
 */
final class NamePlan
{
    /** Roman letter for the address => the Bengali letter. Letters that begin enough names, in the order they are written. */
    public const LETTERS = [
        'a' => 'আ', 'o' => 'অ', 's' => 'স', 'r' => 'র', 'sh' => 'শ', 'p' => 'প', 'd' => 'দ', 'm' => 'ম', 'b' => 'ব', 'n' => 'ন',
        'i' => 'ই', 'k' => 'ক', 't' => 'ত', 'j' => 'জ', 'h' => 'হ', 'u' => 'উ', 'bh' => 'ভ', 'ch' => 'চ', 'g' => 'গ', 'l' => 'ল',
        'e' => 'এ', 'dh' => 'ধ', 'ri' => 'ঋ', 'ee' => 'ঈ', 'oi' => 'ঐ', 'ow' => 'ও', 'y' => 'য', 'th' => 'থ', 'kh' => 'খ', 'ph' => 'ফ', 'gh' => 'ঘ', 'chh' => 'ছ', 'jh' => 'ঝ',
    ];

    /** How English-speaking parents spell names that begin with each letter, for titles (অ names are written with A: Arun, Ananta). */
    public const EN_LETTER = ['a' => 'A', 'o' => 'A', 's' => 'S', 'r' => 'R', 'sh' => 'Sh', 'p' => 'P', 'd' => 'D', 'm' => 'M', 'b' => 'B', 'n' => 'N',
        'i' => 'I', 'k' => 'K', 't' => 'T', 'j' => 'J', 'h' => 'H', 'u' => 'U', 'bh' => 'Bh', 'ch' => 'Ch', 'g' => 'G', 'l' => 'L',
        'e' => 'E', 'dh' => 'Dh', 'ri' => 'Ri', 'ee' => 'I', 'oi' => 'Oi', 'ow' => 'O', 'y' => 'J', 'th' => 'Th', 'kh' => 'Kh', 'ph' => 'F', 'gh' => 'Gh', 'chh' => 'Chh', 'jh' => 'Jh'];

    public const GENDERS = ['boy' => ['ছেলেদের', 'Boy'], 'girl' => ['মেয়েদের', 'Girl']];

    public const MIN_NAMES = 25;

    /** Name pages go out on Tuesday, Thursday and Saturday at 10am India (the other জেনে রাখুন pages: Mon, Wed, Fri). */
    public const DAYS = [2, 4, 6];

    /** @return array<int, array{0: string, 1: string}> [gender, letter] in the order they are written */
    public static function order(): array
    {
        $out = [];
        foreach (array_keys(self::LETTERS) as $l) {
            $out[] = ['boy', $l];
            $out[] = ['girl', $l];
        }

        return $out;
    }

    public static function slug(string $gender, string $letter): string
    {
        return $gender.'-names-'.$letter;
    }

    public static function title(string $gender, string $letter): string
    {
        return self::LETTERS[$letter].' দিয়ে '.self::GENDERS[$gender][0].' নাম';
    }
}
