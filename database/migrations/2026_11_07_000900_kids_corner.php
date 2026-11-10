<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D101 (owner): ছোটদের হাতপাখা, a reading corner for children of Bengali families abroad.
 * The section, age bands and word meanings on pieces, an "AI voice" mark on readings, and the বর্ণমালা page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->string('age_band', 5)->nullable()->after('language');   // 3-5 | 6-8 | 9-12
            $t->json('glossary')->nullable()->after('age_band');        // [{word, bn, en}]
            $t->boolean('audio_ai')->default(false)->after('audio_status');
        });
        if (! DB::table('sections')->where('slug', 'chhotoder')->exists()) {
            DB::table('sections')->insert(['slug' => 'chhotoder', 'name_bn' => 'ছোটদের হাতপাখা', 'name_en' => "Children's Hatpakha", 'color' => '#2DAAE2',
                'format' => 'prose', 'sort' => 5, 'is_active' => true, 'nav_group' => 'kids', 'in_top_bar' => false,
                'about' => 'ছড়া, গল্প আর বর্ণমালা, শুনে শুনে পড়ার জন্য। বড়রা ছোটদের সঙ্গে বসে পড়ুন। (Bengali rhymes and stories with audio, for children learning Bengali, especially families living abroad.)',
                'created_at' => now(), 'updated_at' => now()]);
        }
        if (! DB::table('pages')->where('slug', 'bornomala')->exists()) {
            DB::table('pages')->insert(['slug' => 'bornomala', 'title' => 'বর্ণমালা', 'status' => 'published',
                'body_html' => '<p>প্রতিটি অক্ষর টিপে শুনুন, ছবির সঙ্গে শব্দটি বলুন। (Tap a letter to hear it.)</p>', 'created_at' => now(), 'updated_at' => now()]);
        }
        cache()->forget('sections.menu');
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['age_band', 'glossary', 'audio_ai']));
    }
};
