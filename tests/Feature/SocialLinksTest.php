<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D116: the Facebook page is linked from the site (footer, Organization sameAs); Meta's domain check tag on request. */
class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_links_the_facebook_page_in_the_footer_and_structured_data(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="https://www.facebook.com/Hatpakhaonline"', $html);
        $this->assertStringContainsString('"sameAs":["https://www.facebook.com/Hatpakhaonline","https://www.instagram.com/hatpakha/"]', $html);
        $this->assertStringContainsString('href="https://www.instagram.com/hatpakha/"', $html);
        $this->assertStringNotContainsString('facebook-domain-verification', $html);
    }

    public function test_domain_verification_tag_only_for_a_valid_code(): void
    {
        config(['services.social.facebook_domain_verification' => 'abc123def456ghi789']);
        $this->get('/')->assertSee('<meta name="facebook-domain-verification" content="abc123def456ghi789">', false);

        config(['services.social.facebook_domain_verification' => '"><script>x</script>']);
        $this->get('/')->assertDontSee('facebook-domain-verification', false);
    }
}
