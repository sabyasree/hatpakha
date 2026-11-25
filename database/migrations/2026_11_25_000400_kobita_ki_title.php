<?php

use App\Support\Slug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** D293: Search Console shows "কবিতা কাকে বলে" (777 a quarter) more than "কবিতা কী"; the title now says both. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('posts')->where('slug', Slug::normalize('কবিতা-কী'))->where('seo_title', 'কবিতা কী: কবিতার সংজ্ঞা ও বৈশিষ্ট্য')
            ->update(['seo_title' => 'কবিতা কী, কবিতা কাকে বলে: সংজ্ঞা ও বৈশিষ্ট্য']);
    }

    public function down(): void {}
};
