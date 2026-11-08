<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Owner (2026-11-08): "generate 3 new posts for heritage and kitchen; ask the writer to write". Queues six drafts for
 * the copywriter (D95, written with the literary rules D106/D107). Each arrives as a draft under হাতপাখা ডেস্ক with
 * facts to check; nothing is published without the owner.
 */
return new class extends Migration
{
    private const TOPICS = [
        'heritage_piece' => [
            'নবান্ন: অঘ্রাণে নতুন ধান ঘরে ওঠার দিন, উঠোনে নতুন চালের প্রথম ভোগ (ভোরের উঠোন থেকে লেখো)',
            'জয়দেব-কেঁদুলির মেলা: পৌষ সংক্রান্তিতে অজয়ের ধারে বাউলের আখড়া (শীতের রাতে আগুনের পাশে গান থেকে লেখো)',
            'পটের গান: পটুয়ারা পট খুলে গান গেয়ে গল্প বলেন (একটি পট খোলার মুহূর্ত থেকে লেখো)',
        ],
        'kitchen_piece' => [
            'জয়নগরের মোয়া: কনকচূড় ধানের খই আর নলেন গুড়, শীতের কয়েক সপ্তাহের জিনিস (মোয়ার বাক্স খোলার মুহূর্ত থেকে লেখো)',
            'কড়াইশুঁটির কচুরি: শীতের সকালে শুঁটি ছাড়ানো থেকে গরম কচুরি (শুঁটি ছাড়ানোর দুপুর থেকে লেখো)',
            'বড়ি দেওয়া: শীতের রোদে ছাদে কলাইয়ের ডালের বড়ি শুকোনো (ছাদের রোদ আর কাপড়ে সারি সারি বড়ি থেকে লেখো)',
        ],
    ];

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        foreach (self::TOPICS as $job => $topics) {
            foreach ($topics as $topic) {
                $waiting = \App\Models\TeamRequest::where('agent', 'copywriter')->where('job', $job)->where('instruction', $topic)->exists();
                if (! $waiting) {
                    \App\Ai\TeamJobs::queue('copywriter', $job, $topic);
                    echo "  queued: {$job}: ".mb_substr($topic, 0, 40).PHP_EOL;
                }
            }
        }
    }

    public function down(): void {}
};
