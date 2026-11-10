<?php

namespace App\Filament\Concerns;

/** Resource names are written in Bengali and shown in the desk's chosen language (D45). */
trait TranslatedResourceLabels
{
    public static function getModelLabel(): string
    {
        return __((string) static::$modelLabel);
    }

    public static function getPluralModelLabel(): string
    {
        return __((string) static::$pluralModelLabel);
    }

    public static function getNavigationLabel(): string
    {
        return __((string) (static::$navigationLabel ?? static::$pluralModelLabel));
    }

    /** Groups are matched by their visible name, so the group name is translated too. */
    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup ? __((string) static::$navigationGroup) : null;
    }
}
