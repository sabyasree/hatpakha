<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Mood;
use App\Models\Section;
use Illuminate\Database\Seeder;

/** Base reference data. Safe to run repeatedly (updateOrCreate). */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sections keep the old WordPress category slugs so /category/{slug}/ URLs survive.
        $sections = [
            ['poetry', 'কবিতা', 'Poetry', 'poem', '#CB1B32', 10],
            ['short-stories', 'গল্প', 'Stories', 'prose', '#13212C', 20],
            ['literature', 'প্রবন্ধ', 'Essays', 'prose', '#1A86C2', 30],
            ['travel', 'ভ্রমণ', 'Travel', 'prose', '#2DAAE2', 40],
            ['editorial', 'সম্পাদকীয়', 'Editorial', 'prose', '#7A1022', 50],
            ['social', 'সমাজ', 'Social', 'prose', '#12304A', 60],
            ['inspiration', 'অনুপ্রেরণা', 'Inspiration', 'prose', '#E39A6A', 70],
            ['sher-o-shayari', 'শের-ও-শায়েরি', 'Sher-O-Shayari', 'poem', '#B0162B', 80],
            ['translated', 'অনুবাদ', 'Translated', 'prose', '#1B3A52', 90],
            ['english', 'English', 'English', 'prose', '#12304A', 100],
        ];
        foreach ($sections as [$slug, $bn, $en, $format, $color, $sort]) {
            Section::updateOrCreate(['slug' => $slug], ['name_bn' => $bn, 'name_en' => $en, 'format' => $format, 'color' => $color, 'sort' => $sort]);
        }

        $moods = [['prem', 'প্রেম'], ['hasi', 'হাসি'], ['bhoy', 'গা ছমছমে'], ['smriti', 'স্মৃতি'], ['bhraman', 'বেড়াতে যাই'], ['sahas', 'সাহস']];
        foreach ($moods as $i => [$slug, $name]) {
            Mood::updateOrCreate(['slug' => $slug], ['name_bn' => $name, 'sort' => ($i + 1) * 10]);
        }

        // Collective byline for archive pieces whose writer is unknown (PLAN.md §5, DB-AUDIT.md).
        Author::updateOrCreate(['slug' => 'hatpakha-desk'], ['display_name' => 'Hatpakha Desk', 'display_name_bn' => 'হাতপাখা ডেস্ক', 'is_desk' => true, 'is_guest' => true]);
    }
}
