<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D95 (owner): two new sections (ঐতিহ্য ও উৎসব, রান্নাঘরের গল্প), every English piece in the English section,
 * and menu groups so the top bar and the phone menu can show sections by kind. Also a desk-only note on posts
 * for AI drafts ("check these facts before publishing").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $t) {
            $t->string('nav_group', 20)->default('literature')->after('sort');   // literature | life | english | other
            $t->boolean('in_top_bar')->default(false)->after('nav_group');
        });
        Schema::table('posts', function (Blueprint $t) {
            $t->text('ai_note')->nullable()->after('ai_drafted_at');   // desk only, never shown to readers
        });

        $groups = ['poetry' => 'literature', 'short-stories' => 'literature', 'literature' => 'literature', 'translated' => 'literature',
            'sher-o-shayari' => 'literature', 'travel' => 'life', 'social' => 'life', 'inspiration' => 'life', 'editorial' => 'other', 'english' => 'english'];
        foreach ($groups as $slug => $g) {
            DB::table('sections')->where('slug', $slug)->update(['nav_group' => $g]);
        }
        DB::table('sections')->whereIn('slug', ['poetry', 'short-stories', 'literature', 'travel'])->update(['in_top_bar' => true]);

        foreach ([
            ['heritage', 'ঐতিহ্য ও উৎসব', 'Heritage & Festivals', '#E39A6A', 45],
            ['kitchen', 'রান্নাঘরের গল্প', 'Kitchen Stories', '#B0162B', 46],
        ] as [$slug, $bn, $en, $colour, $sort]) {
            if (! DB::table('sections')->where('slug', $slug)->exists()) {
                DB::table('sections')->insert(['slug' => $slug, 'name_bn' => $bn, 'name_en' => $en, 'color' => $colour, 'format' => 'prose',
                    'sort' => $sort, 'is_active' => true, 'nav_group' => 'life', 'in_top_bar' => false, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // Every English piece lives in the English section (URLs don't change: pieces are /{slug}/).
        $english = DB::table('sections')->where('slug', 'english')->value('id');
        if ($english) {
            $n = DB::table('posts')->where('language', 'en')->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', '!=', $english))
                ->update(['section_id' => $english, 'updated_at' => now()]);
            if ($n && ! app()->runningUnitTests()) {
                echo "  English pieces moved to the English section: {$n}".PHP_EOL;
            }
        }
        foreach (['sections.all', 'sections.nav', 'sections.menu'] as $k) {
            cache()->forget($k);
        }
    }

    public function down(): void
    {
        Schema::table('sections', fn (Blueprint $t) => $t->dropColumn(['nav_group', 'in_top_bar']));
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn('ai_note'));
    }
};
