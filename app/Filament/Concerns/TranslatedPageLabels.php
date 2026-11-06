<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;

/** Page titles are written in Bengali and shown in the desk's chosen language (D45). */
trait TranslatedPageLabels
{
    public function getTitle(): string|Htmlable
    {
        return __((string) static::$title);
    }

    public static function getNavigationLabel(): string
    {
        return __((string) (static::$navigationLabel ?? static::$title));
    }

    /** Groups are matched by their visible name, so the group name is translated too. */
    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup ? __((string) static::$navigationGroup) : null;
    }
}
