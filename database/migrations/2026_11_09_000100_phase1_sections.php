<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner (2026-11-09): Phase 1 of the new sections (docs/STRATEGY.md §3c), drafted by the AI desk until writers come,
 * and three first drafts for each (drafts only; the owner edits and publishes).
 */
return new class extends Migration
{
    private const SECTIONS = [
        ['phire-pora', 'ফিরে পড়া, ফিরে দেখা', 'literature', 22, 'বাংলা সাহিত্য আর সিনেমার চিরকালের কাজগুলোয় আবার ফিরে যাওয়া: একটি দৃশ্য, একটি লাইন, যা আজও মনে থেকে যায়।'],
        ['jonopod', 'জনপদের গল্প', 'life', 47, 'বাংলার একটি শহর, গ্রাম, পাড়া বা নদী: তার আলো, শব্দ, মানুষ আর পুরনো দিনের কথা।'],
        ['rohosyomoy', 'রহস্যময় বাংলা', 'literature', 23, 'বাংলার পুকুরঘাট, বটগাছ আর পোড়ো বাড়ির কিংবদন্তি আর লোককথা, গল্পের মতো করে বলা।'],
    ];

    private const TOPICS = [
        'classics_piece' => ['পথের পাঁচালী: অপু-দুর্গার রেলগাড়ি দেখার দৌড়', 'মেঘে ঢাকা তারা: নীতার শেষ চিৎকার', 'রবীন্দ্রনাথের "ছুটি": ফটিকের ফিরে যাওয়া'],
        'place_piece' => ['চন্দননগর: গঙ্গার ধারে স্ট্র্যান্ড আর জগদ্ধাত্রীর আলো', 'শান্তিনিকেতন: খোয়াইয়ের লাল মাটি আর পৌষমেলার সকাল', 'কুমোরটুলি: পুজোর আগে মাটির প্রতিমার গলি'],
        'legend_piece' => ['মেছো ভূত আর রাতের পুকুরঘাট', 'নিশির ডাক: রাতে নাম ধরে ডাকা', 'আলেয়া: জলাভূমির আলো যে পথ ভোলায়'],
    ];

    public function up(): void
    {
        foreach (self::SECTIONS as [$slug, $name, $group, $sort, $about]) {
            if (! DB::table('sections')->where('slug', $slug)->exists()) {
                DB::table('sections')->insert(['slug' => $slug, 'name_bn' => $name, 'format' => 'prose', 'sort' => $sort, 'nav_group' => $group,
                    'in_top_bar' => false, 'is_active' => true, 'about' => $about, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        cache()->forget('sections.menu');
        if (app()->runningUnitTests()) {
            return;
        }
        foreach (self::TOPICS as $job => $topics) {
            foreach ($topics as $topic) {
                if (! \App\Models\TeamRequest::where('agent', 'copywriter')->where('job', $job)->where('instruction', $topic)->exists()) {
                    \App\Ai\TeamJobs::queue('copywriter', $job, $topic);
                    echo '  queued: '.$job.': '.mb_substr($topic, 0, 30).PHP_EOL;
                }
            }
        }
    }

    public function down(): void {}
};
