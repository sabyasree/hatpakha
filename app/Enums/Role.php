<?php

namespace App\Enums;

/** User roles, lowest to highest. See docs/SECURITY.md §3. */
enum Role: string
{
    case Reader = 'reader';
    case Writer = 'writer';
    case TrustedWriter = 'trusted_writer';
    case Editor = 'editor';
    case ChiefEditor = 'chief_editor';
    case Admin = 'admin';

    public function rank(): int
    {
        return match ($this) {
            self::Reader => 0,
            self::Writer => 1,
            self::TrustedWriter => 2,
            self::Editor => 3,
            self::ChiefEditor => 4,
            self::Admin => 5,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public function label(): string
    {
        return match ($this) {
            self::Reader => 'পাঠক',
            self::Writer => 'লেখক',
            self::TrustedWriter => 'বিশ্বস্ত লেখক',
            self::Editor => 'সম্পাদক',
            self::ChiefEditor => 'প্রধান সম্পাদক',
            self::Admin => 'অ্যাডমিন',
        };
    }
}
