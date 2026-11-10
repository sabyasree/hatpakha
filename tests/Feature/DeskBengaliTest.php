<?php

namespace Tests\Feature;

use Tests\TestCase;

/** /desk is Bengali-only (D36): every Filament string must have a Bengali version. */
class DeskBengaliTest extends TestCase
{
    private const NAMESPACES = [
        'filament' => 'filament-panels', 'forms' => 'filament-forms', 'tables' => 'filament-tables',
        'schemas' => 'filament-schemas', 'support' => 'filament', 'notifications' => 'filament-notifications',
        'infolists' => 'filament-infolists', 'query-builder' => 'filament-query-builder',
        'widgets' => 'filament-widgets', 'actions' => 'filament-actions',
    ];

    /** Same in every language on purpose: text direction, a unit, and file names. */
    private const SAME = [
        'filament-panels::layout.direction', 'filament-forms::components.file_upload.editor.fields.rotation.unit',
        'filament-actions::export.file_name', 'filament-actions::import.example_csv.file_name', 'filament-actions::import.failure_csv.file_name',
    ];

    public function test_every_filament_string_has_bengali(): void
    {
        $missing = [];
        foreach (self::NAMESPACES as $pkg => $ns) {
            $en = base_path("vendor/filament/{$pkg}/resources/lang/en");
            if (! is_dir($en)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($en, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $rel = substr((string) $file, strlen($en) + 1);
                $key = substr($rel, 0, -4);
                foreach (array_keys(\Illuminate\Support\Arr::dot(require $file)) as $k) {
                    if (! in_array("{$ns}::{$key}.{$k}", self::SAME, true) && trans("{$ns}::{$key}.{$k}", [], 'bn') === trans("{$ns}::{$key}.{$k}", [], 'en')
                        && preg_match('/[a-z]{3}/i', (string) trans("{$ns}::{$key}.{$k}", [], 'en'))) {
                        $missing[] = "{$ns}::{$key}.{$k}";
                    }
                }
            }
        }
        $this->assertSame([], $missing, 'Add these to lang/vendor/<namespace>/bn/');
    }

    public function test_desk_pages_load_the_bengali_script(): void
    {
        $this->assertFileExists(public_path('js/desk-bn.js'));
        $this->get('/desk/login')->assertOk()->assertSee('js/desk-bn.js', false)->assertSee('lang="bn"', false);
    }
}
