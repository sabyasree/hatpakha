<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** D96: a short introduction per section (shown on its page; invites writers while it is empty). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', fn (Blueprint $t) => $t->text('about')->nullable()->after('name_en'));
        $about = [
            'heritage' => 'বাংলার মন্দির, মেলা, পার্বণ, হাতের কাজ আর পুরনো শহরের গল্প। দুই বাংলার যে উৎসব বা ঐতিহ্য আপনি কাছ থেকে দেখেছেন, নিজের চোখে দেখা খুঁটিনাটি দিয়ে লিখুন।',
            'kitchen' => 'একটি রান্না, একটি উপকরণ বা রান্নাঘরের একটি রীতি, আর তার সঙ্গে জড়িয়ে থাকা মানুষ, ঋতু আর উৎসব। রেসিপির তালিকা নয়, রান্নাঘরের গল্প লিখুন।',
        ];
        foreach ($about as $slug => $text) {
            DB::table('sections')->where('slug', $slug)->whereNull('about')->update(['about' => $text]);
        }
        cache()->forget('sections.menu');
    }

    public function down(): void
    {
        Schema::table('sections', fn (Blueprint $t) => $t->dropColumn('about'));
    }
};
