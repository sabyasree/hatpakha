<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;

/**
 * D293 (owner: "yes" to adding the Bengali names): Search Console shows ~1,500 searches a quarter for "durga 108 names
 * in bengali" reaching the desk's English-only list. A numbered Bengali list goes above the English one, which stays as
 * it is; the numbering follows the English list (it splits 44/45 and joins 61, and its 69–72 are four names here).
 * Saved through the model, so it is sanitised like every body. Added once.
 */
return new class extends Migration
{
    public const NAMES = ['সতী', 'সাধ্বী', 'ভবপ্রীতা', 'ভবানী', 'ভবমোচনী', 'আর্যা', 'দুর্গা', 'জয়া', 'আদ্যা', 'ত্রিনেত্রা',
        'শূলধারিণী', 'পিনাকধারিণী', 'চিত্রা', 'চণ্ডঘণ্টা', 'মহাতপা', 'মনঃ', 'বুদ্ধি', 'অহংকারা', 'চিত্তরূপা', 'চিতা',
        'চিতি', 'সর্বমন্ত্রময়ী', 'সত্তা', 'সত্যানন্দস্বরূপিণী', 'অনন্তা', 'ভাবিনী', 'ভাব্যা', 'ভব্যা', 'অভব্যা', 'সদাগতি',
        'শাম্ভবী', 'দেবমাতা', 'চিন্তা', 'রত্নপ্রিয়া', 'সর্ববিদ্যা', 'দক্ষকন্যা', 'দক্ষযজ্ঞবিনাশিনী', 'অপর্ণা', 'অনেকবর্ণা', 'পাটলা',
        'পাটলাবতী', 'পট্টাম্বরপরিধানা', 'কলমঞ্জীররঞ্জিনী', 'অমেয়া', 'বিক্রমা', 'ক্রূরা', 'সুন্দরী', 'সুরসুন্দরী', 'বনদুর্গা', 'মাতঙ্গী',
        'মতঙ্গমুনিপূজিতা', 'ব্রাহ্মী', 'মাহেশ্বরী', 'ঐন্দ্রী', 'কৌমারী', 'বৈষ্ণবী', 'চামুণ্ডা', 'বারাহী', 'লক্ষ্মী', 'পুরুষাকৃতি',
        'বিমলোৎকর্ষিণী', 'জ্ঞানা', 'ক্রিয়া', 'নিত্যা', 'বুদ্ধিদা', 'বহুলা', 'বহুলপ্রেমা', 'সর্ববাহনবাহনা', 'নিশুম্ভশুম্ভহননী', 'মহিষাসুরমর্দিনী',
        'মধুকৈটভহন্ত্রী', 'চণ্ডমুণ্ডবিনাশিনী', 'সর্বাসুরবিনাশা', 'সর্বদানবঘাতিনী', 'সর্বশাস্ত্রময়ী', 'সত্যা', 'সর্বাস্ত্রধারিণী', 'অনেকশস্ত্রহস্তা', 'অনেকাস্ত্রধারিণী', 'কুমারী',
        'একাকন্যা', 'কৈশোরী', 'যুবতী', 'যতি', 'অপ্রৌঢ়া', 'প্রৌঢ়া', 'বৃদ্ধমাতা', 'বলপ্রদা', 'মহোদরী', 'মুক্তকেশী',
        'ঘোররূপা', 'মহাবলা', 'অগ্নিজ্বালা', 'রৌদ্রমুখী', 'কালরাত্রি', 'তপস্বিনী', 'নারায়ণী', 'ভদ্রকালী', 'বিষ্ণুমায়া', 'জলোদরী',
        'শিবদূতী', 'করালী', 'অনন্তা', 'পরমেশ্বরী', 'কাত্যায়নী', 'সাবিত্রী', 'প্রত্যক্ষা', 'ব্রহ্মবাদিনী'];

    public const HEADING = 'বাংলায় মা দুর্গার ১০৮ নাম';

    public function up(): void
    {
        $post = Post::where('slug', 'maa-durga-108-names')->first();
        if (! $post || str_contains((string) $post->body_html, self::HEADING) || count(self::NAMES) !== 108) {
            return;
        }
        $items = collect(self::NAMES)->map(fn ($n, $i) => '<li>'.\App\Support\Bn::num((string) ($i + 1)).') '.$n.'</li>')->implode('');
        $post->body_html = '<h3>'.self::HEADING.'</h3><ul>'.$items.'</ul><p>নিচে একই ১০৮টি নাম ইংরেজিতে, প্রতিটির অর্থ সহ।</p>'.$post->body_html;
        $post->save();
    }

    public function down(): void {}
};
