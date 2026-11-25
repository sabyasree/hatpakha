<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D292 (owner approved the drafts, 2026-11-25: "ok, put them on the site"): introductions for the sections that had
 * none. Shown at the top of the section page and used as its Google description. A section whose introduction the desk
 * has written since is left alone.
 */
return new class extends Migration
{
    public const ABOUT = [
        'poetry' => 'এক দশকের বেশি সময় ধরে হাতপাখায় ছাপা বাংলা কবিতা: নতুন কবির প্রথম কবিতা থেকে চেনা কবির নতুন লেখা। প্রেম, প্রকৃতি, শহর, স্মৃতি: যা কিছু মন ছুঁয়ে যায়। নিজের কবিতা পাঠান বিনামূল্যে; প্রতিটি লেখার উত্তর দেওয়া হয় ২–৩ সপ্তাহে।',
        'short-stories' => 'বাংলা ছোটগল্প: পাড়ার গল্প, প্রেমের গল্প, ভূতের আর রহস্যের গল্প, ছোটবেলার স্মৃতি। নতুন লেখকদের গল্প এখানে চেনা লেখকদের পাশে ছাপা হয়। আপনার গল্পও পাঠান, বিনামূল্যে।',
        'literature' => 'সাহিত্য, গান, ইতিহাস আর উৎসব নিয়ে বাংলা প্রবন্ধ: রবীন্দ্রনাথ ও শিলং থেকে মহালয়ার ভোর, "কবিতা কী" থেকে কলকাতার জন্মকথা। যে লেখা পড়ে ভাবতে ইচ্ছে করে, তেমন লেখা পাঠান।',
        'travel' => 'লেখকদের নিজের চোখে দেখা পথ: সিকিম, কাশ্মীর আর গুজরাট থেকে আলাস্কা, গ্র্যান্ড ক্যানিয়ন আর অস্ট্রেলিয়া। কোথায় গেলেন, কী দেখলেন, কী মনে রয়ে গেল: বাংলায় ভ্রমণকাহিনি। আপনার ভ্রমণের গল্প পাঠান।',
        'social' => 'সমাজ আর সময় নিয়ে লেখা: উৎসবের শহর থেকে চা বাগান, রাস্তার আলো থেকে ঘরের ভেতরের কথা। গল্প, শ্রুতিনাটক আর ছোট ছবির ভাবনাও এখানে।',
        'inspiration' => 'মন ভালো রাখার, এগিয়ে চলার লেখা: দুঃখের পরে সুখ, প্রবাসের দিন, প্রিয় মানুষের কথা। যে লেখা কাউকে একটু সাহস দিতে পারে, তেমন লেখা পাঠান।',
        'translated' => 'অন্য ভাষার কবিতা, গল্প আর প্রবন্ধ, বাংলায়: যে লেখা পড়ে মনে হয়েছে বাঙালি পাঠকেরও পড়া দরকার। অনুবাদ পাঠানোর সময় মূল লেখক আর মূল লেখার নাম দিন। মূল লেখাটি স্বত্বমুক্ত হতে হবে, নয়তো অনুবাদের অনুমতি থাকতে হবে।',
        'english' => 'Poems, stories and essays in English from Hatpakha\'s writers: on love, rain, memory, faith and everyday life. Send yours; publishing is always free.',
    ];

    public function up(): void
    {
        foreach (self::ABOUT as $slug => $text) {
            DB::table('sections')->where('slug', $slug)->where(fn ($q) => $q->whereNull('about')->orWhere('about', ''))->update(['about' => $text]);
        }
        cache()->forget('sections.menu');
    }

    public function down(): void
    {
        // Text only; edit or clear it in the desk (বিভাগ → পরিচিতি).
    }
};
