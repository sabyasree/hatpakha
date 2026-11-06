<?php

namespace Tests\Feature;

use Tests\TestCase;

/** During a deploy visitors see a Bengali "back soon" page (503), never a 500. */
class MaintenancePageTest extends TestCase
{
    public function test_maintenance_page_is_bengali_self_contained_and_503(): void
    {
        $html = view('errors.503')->render();
        $this->assertStringContainsString('একটু পরেই ফিরছি', $html);
        $this->assertStringContainsString('noindex', $html);
        $this->assertStringNotContainsString('build/', $html, 'Must not depend on Vite assets');

        $this->artisan('down', ['--render' => 'errors::503', '--retry' => 30])->assertExitCode(0);
        try {
            $this->get('/')->assertStatus(503)->assertSee('একটু পরেই ফিরছি')->assertHeader('Retry-After', '30');
        } finally {
            $this->artisan('up');
        }
    }
}
