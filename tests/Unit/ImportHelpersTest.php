<?php

namespace Tests\Unit;

use App\Console\Commands\ImportWordPress;
use App\Support\Slug;
use PHPUnit\Framework\TestCase;

class ImportHelpersTest extends TestCase
{
    public function test_autop_turns_blank_lines_into_paragraphs_and_newlines_into_breaks(): void
    {
        $this->assertSame("<p>এক<br>\nদুই</p>\n<p>তিন</p>", ImportWordPress::autop("এক\nদুই\n\nতিন"));
        $this->assertSame('<p>already</p>', ImportWordPress::autop('<p>already</p>'));
    }

    public function test_slug_normalisation_decodes_and_nfc_normalises(): void
    {
        $encoded = '%e0%a6%95%e0%a7%8b%e0%a6%a8%e0%a6%9c%e0%a6%a8-%e0%a6%a4%e0%a7%81%e0%a6%ae%e0%a6%bf';
        $this->assertSame('কোনজন-তুমি', Slug::normalize($encoded));
        $this->assertSame('a-b', Slug::normalize('/A-B/'));
    }
}
