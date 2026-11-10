<?php

namespace App\Support;

/**
 * D119: whether this page carries the Google Analytics tag. Only on the live site (hatpakha.com), only with a
 * valid measurement id, never on children's pages (D101: children's pages carry no ads or tracking).
 */
final class Analytics
{
    /** EU/EEA, UK and Switzerland: analytics waits for the visitor's consent there. */
    public const CONSENT_REGIONS = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'GB', 'CH'];

    public static function id(): ?string
    {
        $id = strtoupper(trim((string) config('services.google.measurement_id')));
        if (! preg_match('/^G-[A-Z0-9]{4,14}$/', $id) || ! RelaunchLetters::onLiveSite() || request()->attributes->get('hp.no_ads')) {
            return null;
        }

        return $id;
    }
}
