<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Support\BnSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner (2026-11-11): search that forgives Bengali spelling, finds writers by name and a poem by one of its lines. */
class BnSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_spellings_fold_together(): void
    {
        foreach ([['রবীন্দ্রনাথ', 'রবিন্দ্রনাথ'], ['বিষণ্ণ', 'বিসন্ন'], ['পড়া', 'পরা'], ['জীবনানন্দ', 'জিবনানন্দ'], ['শ্রাবণ', 'স্রাবন'], ['চাঁদ', 'চাদ'], ['১২৩', '123']] as [$a, $b]) {
            $this->assertSame(BnSearch::key($a), BnSearch::key($b), "$a = $b");
        }
        $this->assertNotSame(BnSearch::key('মেঘ'), BnSearch::key('মন'));
    }

    public function test_search_forgives_spelling_finds_writers_lines_and_suggests(): void
    {
        $tagore = Author::create(['slug' => 'tagore', 'display_name' => 'Rabindranath Tagore', 'display_name_bn' => 'রবীন্দ্রনাথ ঠাকুর', 'published_count' => 1]);
        $poem = $this->makePost(['status' => 'published', 'slug' => 'srabon', 'title' => 'শ্রাবণের মেঘ', 'published_at' => now(), 'format' => 'poem',
            'body_html' => '<p>আকাশ জুড়ে মেঘ<br>নদীর ধারে বিষণ্ণ বিকেল নামে<br>ঘরে ফেরে পাখি</p>'], $tagore);

        $this->get('/search/?q=রবিন্দ্রনাথ')->assertOk()->assertSee('রবীন্দ্রনাথ ঠাকুর')->assertSee('শ্রাবণের মেঘ');
        $this->get('/search/?q=স্রাবনের')->assertSee('শ্রাবণের মেঘ');
        $this->get('/search/?q=বিসন্ন বিকেল')->assertSee('শ্রাবণের মেঘ')->assertSee('নদীর ধারে বিষণ্ণ বিকেল নামে', false);
        $this->get('/search/?q=শ্রাবনের মেগ')->assertSee('শ্রাবণের মেঘ');   // ঘ/গ
        $this->get('/search/?q=শ্রাবণের মেঝ')->assertSee('কিছু পাওয়া গেল না')->assertSee('আপনি কি খুঁজছিলেন');
        $this->get('/search/?q=%25%25')->assertOk();
    }

    public function test_misspelled_writer_is_found_even_with_a_stale_count_and_snippet_is_one_sentence(): void
    {
        $mou = Author::create(['slug' => 'mou', 'display_name' => 'মৌ চক্রবর্তী', 'published_count' => 0]);   // count not yet refreshed
        $this->makePost(['slug' => 'nodi', 'title' => 'নদীর ধারে', 'format' => 'prose',
            'body_html' => '<p>সকালে বৃষ্টি হয়েছিল অনেকক্ষণ ধরে, মাঠ ভিজে গেছে পুরো। দুপুরে কুয়াশা নামে মাঠের ওপর আস্তে আস্তে। সন্ধ্যায় সব পরিষ্কার হয়ে আসে আবার, আকাশে তারা ফোটে।</p>'], $mou);
        $this->get('/search/?q=মৌ চক্রবর্তি')->assertSee('নদীর ধারে');
        $this->get('/search/?q=কুয়াসা')->assertSee('দুপুরে কুয়াশা নামে মাঠের ওপর আস্তে আস্তে।', false)->assertSee('কাছাকাছি বানানের')->assertDontSee('সন্ধ্যায় সব পরিষ্কার', false);

        $post = $this->makePost(['slug' => 'later', 'status' => 'pending'], $mou);
        $post->update(['status' => 'published', 'published_at' => now()]);
        $this->assertSame(2, $mou->fresh()->published_count, 'Publishing from anywhere refreshes the count');
    }
}
