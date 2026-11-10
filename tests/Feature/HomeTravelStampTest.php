<?php

namespace Tests\Feature;

use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** A travel piece's cover shows on its home-page stamp (owner found it missing). */
class HomeTravelStampTest extends TestCase
{
    use RefreshDatabase;

    public function test_travel_stamp_shows_the_cover(): void
    {
        $travel = Section::firstOrCreate(['slug' => 'travel'], ['name' => 'Travel', 'name_bn' => 'ভ্রমণ']);
        $this->makePost(['slug' => 'chhattisgarh', 'title' => 'ছোট্ট ছোট্ট পায়ে চত্তিশগড়', 'status' => 'published', 'published_at' => now()->subDay(),
            'section_id' => $travel->id, 'featured_image_path' => 'media/covers/2026/11/abcdefabcdefabcdefabcdef.jpg', 'featured_image_width' => 1536, 'featured_image_height' => 1024]);
        $this->makePost(['slug' => 'no-picture', 'title' => 'ছবি ছাড়া ভ্রমণ', 'status' => 'published', 'published_at' => now()->subDays(2), 'section_id' => $travel->id]);
        Cache::flush();

        $this->get('/')->assertOk()->assertSee('ভ্রমণের ডাকটিকিট')
            ->assertSee('media/covers/2026/11/abcdefabcdefabcdefabcdef.jpg', false)
            ->assertSee('ছোট্ট ছোট্ট পায়ে চত্তিশগড়')->assertSee('ছবি ছাড়া ভ্রমণ');
    }
}
