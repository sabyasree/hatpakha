<?php

namespace Tests\Unit;

use App\Support\BodyFormatter as F;
use PHPUnit\Framework\TestCase;

/** Structure-only tidying: words must never change (owner instruction, D23). */
class BodyFormatterTest extends TestCase
{
    public function test_empty_paragraphs_nbsp_runs_and_edge_breaks_are_removed(): void
    {
        $out = F::format("<p>&nbsp;</p><p><br>  প্রথম&nbsp;&nbsp;&nbsp;লাইন<br></p><p> <br> </p>");
        $this->assertSame('<p>প্রথম লাইন</p>', $out);
    }

    public function test_loose_text_is_wrapped_and_blank_lines_split_paragraphs(): void
    {
        $out = F::format('<h3>শিরোনাম অংশ</h3>প্রথম অনুচ্ছেদ<br><br>দ্বিতীয় অনুচ্ছেদ');
        $this->assertSame("<h3>শিরোনাম অংশ</h3>\n<p>প্রথম অনুচ্ছেদ</p>\n<p>দ্বিতীয় অনুচ্ছেদ</p>", $out);
    }

    public function test_prose_long_lines_become_paragraphs_but_poem_lines_stay(): void
    {
        $long = str_repeat('অনেক লম্বা একটি বাক্য, ', 6);
        $html = "<p>{$long}<br>{$long}</p>";
        $this->assertSame(2, substr_count(F::format($html, 'prose'), '<p>'));
        $this->assertSame(1, substr_count(F::format($html, 'poem'), '<p>'));
        $this->assertSame("<p>এক লাইন<br>দুই লাইন</p>", F::format('<p>এক লাইন<br>দুই লাইন</p>', 'poem'));
    }

    public function test_poem_saved_one_line_per_paragraph_becomes_stanzas(): void
    {
        $html = '<p>এক</p><p>দুই</p><p>তিন</p><p>&nbsp;</p><p>চার</p><p>পাঁচ</p>';
        $this->assertSame("<p>এক<br>দুই<br>তিন</p>\n<p>চার<br>পাঁচ</p>", F::format($html, 'poem'));
        // Prose is never joined.
        $this->assertSame(5, substr_count(F::format($html, 'prose'), '<p>'));
    }

    public function test_repeated_title_is_dropped_and_all_bold_unwrapped(): void
    {
        $body = '<p><strong>বৃষ্টির দিন</strong></p><p><strong>'.str_repeat('সারাদিন বৃষ্টি পড়ছে। ', 15).'</strong></p>';
        $out = F::format($body, 'prose', 'বৃষ্টির দিন');
        $this->assertStringNotContainsString('বৃষ্টির দিন', $out);
        $this->assertStringNotContainsString('<strong>', $out);
        $this->assertStringContainsString('সারাদিন বৃষ্টি পড়ছে।', $out);
    }

    public function test_image_inside_a_sentence_moves_to_its_own_figure(): void
    {
        $out = F::format('<p>গল্পের শুরু <img src="/a.jpg" alt="ছবি"> তারপর</p>');
        $this->assertSame("<figure><img src=\"/a.jpg\" alt=\"ছবি\"></figure>\n<p>গল্পের শুরু তারপর</p>", $out);
    }

    public function test_words_never_change_and_formatting_is_stable(): void
    {
        $html = "ছড়া<br><br>&nbsp;&nbsp;দুই<p><b>এক</b> <i>দুই</i></p>পর<p>একটি</p>";
        $once = F::format($html, 'prose', 'ছড়া');
        // Only the repeated title line was removed; every other character survives in order.
        $this->assertSame(F::letters($html), 'ছড়া'.F::letters($once));
        $this->assertSame($once, F::format($once, 'prose', 'ছড়া'));
        // In a poem the first line stays, even when it equals the title.
        $this->assertStringContainsString('ছড়া', F::format($html, 'poem', 'ছড়া'));
    }
}
