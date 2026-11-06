<?php

namespace Tests\Feature;

use Tests\TestCase;

/** docs/DESIGN.md: no colours outside the logo palette in the UI (CLAUDE.md §2). */
class DesignPaletteTest extends TestCase
{
    private const PALETTE = ['2DAAE2', '7CCBEE', '1A86C2', '12304A', 'CB1B32', 'B0162B', '7A1022', 'F08D90', 'EFC08D',
        'FBEBD8', 'E39A6A', '13212C', 'FFFBF4', '4D5A63', 'E8DCCB', 'DDF1FA', 'FFFFFF', 'FFF', '000000'];

    public function test_templates_css_and_seeded_sections_use_only_palette_colours(): void
    {
        $files = array_merge(
            glob(resource_path('views/{,*/,*/*/}*.blade.php'), GLOB_BRACE),
            [resource_path('css/app.css'), database_path('seeders/DatabaseSeeder.php'), app_path('Providers/Filament/AdminPanelProvider.php')]
        );
        $bad = [];
        foreach ($files as $f) {
            preg_match_all('/#([0-9a-f]{6}|[0-9a-f]{3})\b/i', file_get_contents($f), $m);
            foreach ($m[1] as $hex) {
                if (! in_array(strtoupper($hex), self::PALETTE, true)) {
                    $bad[] = basename($f).": #{$hex}";
                }
            }
        }
        $this->assertSame([], $bad, 'Off-palette colours found');
    }
}
