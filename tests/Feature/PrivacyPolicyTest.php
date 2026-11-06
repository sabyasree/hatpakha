<?php

namespace Tests\Feature;

use App\Console\Commands\ImportWordPress;
use Tests\TestCase;

/** The privacy copy must describe what the site really does (D52, D54). */
class PrivacyPolicyTest extends TestCase
{
    public function test_privacy_copy_covers_counter_ai_and_chat(): void
    {
        $body = ImportWordPress::revisedPage('privacy-policy')['body'];
        foreach (['কোন লেখা কত পড়া হচ্ছে', 'কোনও কুকি লাগে না', 'Anthropic', 'OpenAI', 'সহায়ক চ্যাটে', 'শংসাপত্র', 'নিশ্চিত করার লিংক', 'লেখকদের জন্য চিঠি'] as $must) {
            $this->assertStringContainsString($must, $body);
        }
    }
}
