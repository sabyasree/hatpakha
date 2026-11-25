<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * D294 (owner, 2026-11-25: "make the calendar plan … do everything automatic. I don't want to spend time"): the days
 * readers search for Bengali writers and festivals, and the famous works students look up. App\Ai\CalendarDesk writes
 * one piece a few days before each day, and one "ফিরে পড়া" reading of a famous work a week, checks every fact on the
 * web and publishes it under "হাতপাখা ডেস্ক" by itself (Handover job "calendar"); anything it cannot verify waits.
 *
 * Dates are the widely documented birthdays (Gregorian). Days set by the Bengali calendar (২৫ বৈশাখ, ১১ জ্যৈষ্ঠ,
 * ৭ পৌষ) move by a day or so between years; the piece names the Bengali date and never claims a Gregorian one for it.
 */
final class LiteraryCalendar
{
    /** Days before the day that the piece goes out (searches start before the day). */
    public const LEAD_DAYS = 3;

    /** month-day => [key, section slug, what the piece is about (for the writer), short name] */
    public const DAYS = [
        '01-01' => ['jasimuddin', 'phire-pora', 'কবি জসীমউদ্‌দীনের জন্মদিন (১ জানুয়ারি ১৯০৩): "নকশী কাঁথার মাঠ" আর গ্রামবাংলার কবি', 'জসীমউদ্‌দীনের জন্মদিন'],
        '01-08' => ['ashapurna', 'phire-pora', 'আশাপূর্ণা দেবীর জন্মদিন (৮ জানুয়ারি ১৯০৯): "প্রথম প্রতিশ্রুতি" আর বাঙালি মেয়েদের অন্দরমহলের গল্প', 'আশাপূর্ণা দেবীর জন্মদিন'],
        '01-14' => ['mahasweta', 'phire-pora', 'মহাশ্বেতা দেবীর জন্মদিন (১৪ জানুয়ারি ১৯২৬): প্রান্তিক মানুষের কথাকার; তাঁর কাজের কথা, উদ্ধৃতি ছাড়া', 'মহাশ্বেতা দেবীর জন্মদিন'],
        '01-25' => ['madhusudan', 'phire-pora', 'মাইকেল মধুসূদন দত্তের জন্মদিন (২৫ জানুয়ারি ১৮২৪): অমিত্রাক্ষর ছন্দ, "মেঘনাদবধ কাব্য" আর "কপোতাক্ষ নদ"', 'মধুসূদনের জন্মদিন'],
        '02-01' => ['boimela', 'heritage', 'বইমেলার দিনগুলো: কলকাতা আর ঢাকার (অমর একুশে) বইমেলায় ঘোরা, নতুন বইয়ের গন্ধ, লিটল ম্যাগাজিনের টেবিল; কোনো তারিখ বা স্টল নম্বর নয়', 'বইমেলা'],
        '02-17' => ['jibanananda', 'phire-pora', 'জীবনানন্দ দাশের জন্মদিন (১৭ ফেব্রুয়ারি ১৮৯৯): "রূপসী বাংলা" আর "বনলতা সেন"-এর কবি', 'জীবনানন্দের জন্মদিন'],
        '02-21' => ['bhasha', 'heritage', '২১ ফেব্রুয়ারি, ভাষা দিবস ও আন্তর্জাতিক মাতৃভাষা দিবস: মাতৃভাষা বাংলার জন্য ১৯৫২-র আন্দোলন আর আজকের বাংলা ভাষা', 'ভাষা দিবস'],
        '02-26' => ['leela', 'phire-pora', 'লীলা মজুমদারের জন্মদিন (২৬ ফেব্রুয়ারি ১৯০৮): ছোটদের জন্য হাসি আর মায়ার লেখা, "পদিপিসির বর্মিবাক্স"', 'লীলা মজুমদারের জন্মদিন'],
        '04-14' => ['boishakh', 'heritage', 'পয়লা বৈশাখ, বাংলা নববর্ষ: হালখাতা, নতুন জামা, মঙ্গল শোভাযাত্রা; দুই বাংলার নতুন বছর', 'পয়লা বৈশাখ'],
        '05-02' => ['satyajit', 'phire-pora', 'সত্যজিৎ রায়ের জন্মদিন (২ মে ১৯২১): লেখক সত্যজিৎ, প্রফেসর শঙ্কু আর ফেলুদার স্রষ্টা; তাঁর লেখার কথা, উদ্ধৃতি ছাড়া', 'সত্যজিৎ রায়ের জন্মদিন'],
        '05-08' => ['rabindra', 'phire-pora', 'রবীন্দ্রনাথ ঠাকুরের জন্মদিন, ২৫ বৈশাখ (১৮৬১): বাঙালির ঘরে ঘরে রবীন্দ্রনাথ', 'রবীন্দ্র জয়ন্তী'],
        '05-25' => ['nazrul', 'phire-pora', 'কাজী নজরুল ইসলামের জন্মদিন, ১১ জ্যৈষ্ঠ (১৮৯৯): বিদ্রোহী কবি আর প্রেমের কবি; তাঁর কবিতার কথা, একটির বেশি লাইন উদ্ধৃতি নয়', 'নজরুল জয়ন্তী'],
        '06-26' => ['bankim', 'phire-pora', 'বঙ্কিমচন্দ্র চট্টোপাধ্যায়ের জন্মদিন (জুন ১৮৩৮): বাংলা উপন্যাসের শুরু, "দুর্গেশনন্দিনী" থেকে "আনন্দমঠ"', 'বঙ্কিমচন্দ্রের জন্মদিন'],
        '07-23' => ['tarashankar', 'phire-pora', 'তারাশঙ্কর বন্দ্যোপাধ্যায়ের জন্মদিন (২৩ জুলাই ১৮৯৮): রাঢ় বাংলার মাটি আর মানুষ; তাঁর লেখার কথা, উদ্ধৃতি ছাড়া', 'তারাশঙ্করের জন্মদিন'],
        '08-15' => ['sukanta', 'phire-pora', 'সুকান্ত ভট্টাচার্যের জন্মদিন (১৫ আগস্ট ১৯২৬): "ছাড়পত্র"-এর তরুণ কবি', 'সুকান্তের জন্মদিন'],
        '09-12' => ['bibhutibhushan', 'phire-pora', 'বিভূতিভূষণ বন্দ্যোপাধ্যায়ের জন্মদিন (১২ সেপ্টেম্বর ১৮৯৪): "পথের পাঁচালী", "আরণ্যক" আর প্রকৃতি', 'বিভূতিভূষণের জন্মদিন'],
        '09-15' => ['saratchandra', 'phire-pora', 'শরৎচন্দ্র চট্টোপাধ্যায়ের জন্মদিন (১৫ সেপ্টেম্বর ১৮৭৬): "মহেশ", "শ্রীকান্ত", "দেবদাস" আর সাধারণ মানুষের লেখক', 'শরৎচন্দ্রের জন্মদিন'],
        '09-26' => ['vidyasagar', 'phire-pora', 'ঈশ্বরচন্দ্র বিদ্যাসাগরের জন্মদিন (২৬ সেপ্টেম্বর ১৮২০): "বর্ণপরিচয়" আর বাংলা গদ্য', 'বিদ্যাসাগরের জন্মদিন'],
        '10-30' => ['sukumar', 'phire-pora', 'সুকুমার রায়ের জন্মদিন (৩০ অক্টোবর ১৮৮৭): "আবোল তাবোল", "হ-য-ব-র-ল" আর বাংলার ননসেন্স ছড়া', 'সুকুমার রায়ের জন্মদিন'],
        '11-30' => ['buddhadeva', 'phire-pora', 'বুদ্ধদেব বসুর জন্মদিন (৩০ নভেম্বর ১৯০৮): "কবিতা" পত্রিকা আর আধুনিক বাংলা কবিতা; তাঁর কাজের কথা, উদ্ধৃতি ছাড়া', 'বুদ্ধদেব বসুর জন্মদিন'],
        '12-09' => ['rokeya', 'phire-pora', 'বেগম রোকেয়ার জন্মদিন (৯ ডিসেম্বর ১৮৮০): "সুলতানার স্বপ্ন" আর মেয়েদের শিক্ষার লড়াই', 'বেগম রোকেয়ার জন্মদিন'],
        '12-23' => ['poushmela', 'heritage', 'শান্তিনিকেতনের পৌষমেলা (৭ পৌষ থেকে): বাউল গান, হাতের কাজ, শীতের মেলা', 'পৌষমেলা'],
    ];

    /**
     * The famous works students and readers look up ("… কবিতার বিষয়বস্তু", "… গল্পের মূলভাব"). Only writers whose
     * work is out of copyright, so a few lines may be quoted; one a week, in this order, then round again.
     * [key, work, writer]
     */
    public const WORKS = [
        ['postmaster', 'পোস্টমাস্টার (গল্প)', 'রবীন্দ্রনাথ ঠাকুর'],
        ['banalata', 'বনলতা সেন (কবিতা)', 'জীবনানন্দ দাশ'],
        ['kabuliwala', 'কাবুলিওয়ালা (গল্প)', 'রবীন্দ্রনাথ ঠাকুর'],
        ['abol-tabol', 'আবোল তাবোল (ছড়ার বই)', 'সুকুমার রায়'],
        ['mahesh', 'মহেশ (গল্প)', 'শরৎচন্দ্র চট্টোপাধ্যায়'],
        ['kopotakkho', 'কপোতাক্ষ নদ (সনেট)', 'মাইকেল মধুসূদন দত্ত'],
        ['sonar-tori', 'সোনার তরী (কবিতা)', 'রবীন্দ্রনাথ ঠাকুর'],
        ['pather-panchali', 'পথের পাঁচালী (উপন্যাস)', 'বিভূতিভূষণ বন্দ্যোপাধ্যায়'],
        ['chharpatra', 'ছাড়পত্র (কবিতা)', 'সুকান্ত ভট্টাচার্য'],
        ['rupasi-bangla', 'রূপসী বাংলা (কবিতার বই)', 'জীবনানন্দ দাশ'],
        ['chhuti', 'ছুটি (গল্প)', 'রবীন্দ্রনাথ ঠাকুর'],
        ['moder-gorob', 'মোদের গরব, মোদের আশা (গান)', 'অতুলপ্রসাদ সেন'],
    ];

    /** The calendar days in [from, to] as [date, key, section, topic, name], oldest first. */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        for ($y = $from->year; $y <= $to->year; $y++) {
            foreach (self::DAYS as $md => [$key, $section, $topic, $name]) {
                $day = CarbonImmutable::parse("{$y}-{$md}", 'Asia/Kolkata')->startOfDay();
                if ($day->betweenIncluded($from->startOfDay(), $to->endOfDay())) {
                    $out[] = ['date' => $day, 'key' => $key.'-'.$y, 'section' => $section, 'topic' => $topic, 'name' => $name];
                }
            }
        }
        usort($out, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $out;
    }

    /** When a day's piece goes out: LEAD_DAYS before, 9am India, never in the past. */
    public static function publishAt(CarbonImmutable $day, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now('Asia/Kolkata');
        $at = $day->subDays(self::LEAD_DAYS)->setTime(9, 0);

        return $at->lessThan($now) ? $now->addDay()->setTime(9, 0) : $at;
    }
}
