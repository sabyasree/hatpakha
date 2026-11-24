<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sign-up, two-step sign-in, settings and the footer link to these pages by address (QA review, 2026-11-24). Their
 * texts ship with the code (database/content/pages) and are loaded by `hatpakha:import-wp --pages`; a missing one
 * would be a link to nothing on a fresh copy.
 */
class LegalPagesTest extends TestCase
{
    public function test_the_linked_pages_ship_with_the_code(): void
    {
        foreach (['privacy-policy', 'terms-conditions', 'contact-us', 'about-hatpakha'] as $slug) {
            $file = base_path('database/content/pages/'.$slug.'.html');
            $this->assertFileExists($file, $slug);
            $this->assertGreaterThan(200, strlen((string) file_get_contents($file)), $slug.' has real text');
        }
    }
}
