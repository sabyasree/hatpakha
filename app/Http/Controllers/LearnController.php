<?php

namespace App\Http\Controllers;

use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\JanunPlan;
use App\Support\Url;

/** D317: বাংলা শিখি, a free starter after the বর্ণমালা: vowel signs, joined signs, conjuncts, first words; worksheets to print. */
class LearnController extends Controller
{
    public const LESSONS = ['kar', 'phola', 'juktakkhor', 'first-words'];

    public static function lessons(): array
    {
        return require database_path('content/learn-bengali.php');
    }

    public function index()
    {
        $url = Url::to('/janun/learn-bengali');
        $seo = new Seo(image: asset('images/og/card-janun.jpg'), title: 'Learn Bengali for Kids, Free · বাংলা শিখি: কার, ফলা, যুক্তাক্ষর, প্রথম শব্দ',
            description: JanunPlan::clip('বাংলা পড়তে শেখার বিনা পয়সার পাঠ: বর্ণমালার পর কার, ফলা, যুক্তাক্ষর আর প্রথম শব্দ, ইংরেজি মানে ও উচ্চারণসহ, ছাপার ওয়ার্কশিট। Learn to read Bengali, free.', 158),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['বাংলা শিখি', $url]])]);
        request()->attributes->set('hp.no_ads', true);   // D101: pages for children carry no ads and no analytics

        return view('janun.learn', ['lessons' => self::lessons(), 'seo' => $seo]);
    }

    public function show(string $lesson)
    {
        $all = self::lessons();
        abort_unless(isset($all[$lesson]), 404);
        $l = $all[$lesson];
        $url = Url::to('/janun/learn-bengali/'.$lesson);
        $seo = new Seo(image: asset('images/og/card-janun.jpg'), title: $l['title'].' · '.$l['en'].' · বাংলা শিখি',
            description: JanunPlan::clip($l['title'].': '.$l['intro'].' '.$l['en'].', with Roman readings and English meanings.', 158),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['বাংলা শিখি', Url::to('/janun/learn-bengali')], [$l['title'], $url]]),
                JsonLd::learningResource($l['title'], $url, $l['en'])]);
        request()->attributes->set('hp.no_ads', true);   // D101: pages for children carry no ads and no analytics
        $i = array_search($lesson, self::LESSONS, true);

        return view('janun.learn-lesson', ['key' => $lesson, 'l' => $l, 'seo' => $seo,
            'prev' => $i > 0 ? self::LESSONS[$i - 1] : null, 'next' => self::LESSONS[$i + 1] ?? null, 'all' => $all]);
    }

    /** A worksheet: grey letters to write over, empty boxes to copy. Not for search (the lesson page is). */
    public function print(string $lesson)
    {
        $all = self::lessons();
        abort_unless(isset($all[$lesson]), 404);

        return view('janun.learn-print', ['key' => $lesson, 'l' => $all[$lesson], 'seo' => new Seo(title: $all[$lesson]['title'].': ওয়ার্কশিট', noindex: true,
            canonical: Url::to('/janun/learn-bengali/'.$lesson))]);
    }
}
