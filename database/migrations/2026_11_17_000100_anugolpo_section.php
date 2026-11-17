<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D223 (owner, 2026-11-17, after a writer asked whether অণুগল্প may be sent: "add a separate section for anugolpo. And
 * add to the menus"): a section of its own for very short stories, beside গল্প in the সাহিত্য group of the menus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('sections')->where('slug', 'anugolpo')->exists()) {
            DB::table('sections')->insert(['slug' => 'anugolpo', 'name_bn' => 'অণুগল্প', 'name_en' => 'Micro fiction', 'format' => 'prose', 'sort' => 21, 'nav_group' => 'literature',
                'is_active' => true, 'about' => 'খুব ছোট গল্প: একটা মুহূর্ত, একটা মোচড়, আর যা বলা হল না তার রেশ। এক মিনিটে পড়া যায়, অনেকক্ষণ মনে থাকে।',
                'created_at' => now(), 'updated_at' => now()]);
        }
        cache()->deleteMultiple(['sections.nav', 'sections.all', 'sections.menu']);
    }

    public function down(): void
    {
        DB::table('sections')->where('slug', 'anugolpo')->whereNotExists(fn ($q) => $q->from('posts')->whereColumn('posts.section_id', 'sections.id'))->delete();
        cache()->deleteMultiple(['sections.nav', 'sections.all', 'sections.menu']);
    }
};
