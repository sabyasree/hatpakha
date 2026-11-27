<?php

namespace App\Support;

/**
 * D302 (owner, 2026-11-26: "instead of only US/UK… whole global, so anyone can change the time zone and see the
 * dates"): the festivals whose tithi times are shown in any time zone on /janun/puja/pujor-somoy/ and on each puja page.
 * Times follow the বিশুদ্ধ সিদ্ধান্ত পঞ্জিকা (Kolkata), as most published calendars do; the page says so.
 */
final class FestivalPlan
{
    /** key => [name, the janun page key it belongs to (or null), events to find (label => what it is, in English for the search)] */
    public const FESTIVALS = [
        'saraswati' => ['সরস্বতী পুজো', 'saraswati-mantra', ['পঞ্চমী তিথি' => 'Magha Shukla Panchami tithi (Saraswati Puja / Vasant Panchami)']],
        'shiboratri' => ['শিবরাত্রি', 'shiboratri', ['চতুর্দশী তিথি' => 'Phalguna Krishna Chaturdashi tithi (Maha Shivaratri)']],
        'dol' => ['দোলযাত্রা', 'dol', ['পূর্ণিমা তিথি' => 'Phalguna Purnima tithi (Dol Jatra / Dol Purnima)']],
        'boishakh' => ['পয়লা বৈশাখ', 'boishakh', ['পয়লা বৈশাখ (পশ্চিমবঙ্গ)' => 'the day of Poila Baishakh (Bengali New Year) in West Bengal, day only']],
        'jamai' => ['জামাইষষ্ঠী', 'jamai', ['ষষ্ঠী তিথি' => 'Jyeshtha Shukla Shashthi tithi (Jamai Shashthi)']],
        'bishwokarma' => ['বিশ্বকর্মা পুজো', 'bishwokarma', ['বিশ্বকর্মা পুজোর দিন' => 'the day of Vishwakarma Puja in West Bengal (Bhadra Sankranti), day only']],
        'durga' => ['দুর্গাপুজো', 'durga-anjali', [
            'মহালয়া (অমাবস্যা)' => 'Mahalaya Amavasya tithi',
            'ষষ্ঠী' => 'Durga Puja Maha Shashthi tithi',
            'মহাসপ্তমী' => 'Durga Puja Maha Saptami tithi',
            'মহাষ্টমী' => 'Durga Puja Maha Ashtami tithi',
            'সন্ধিপুজো' => 'Sandhi Puja (the 48 minutes joining Ashtami and Navami): its start and end',
            'মহানবমী' => 'Durga Puja Maha Navami tithi',
            'বিজয়া দশমী' => 'Vijaya Dashami tithi',
        ]],
        'kojagori' => ['কোজাগরী লক্ষ্মী পুজো', 'kojagori', ['পূর্ণিমা তিথি' => 'Ashwin Purnima tithi (Kojagori Lakshmi Puja)']],
        'kali' => ['কালীপুজো', 'kali-pranam', ['অমাবস্যা তিথি' => 'Kartik Amavasya tithi (Kali Puja / Shyama Puja)']],
        'bhaiphonta' => ['ভাইফোঁটা', 'bhaiphonta', ['দ্বিতীয়া তিথি' => 'Kartik Shukla Dwitiya tithi (Bhai Phonta)']],
    ];

    /**
     * D308 (owner, 2026-11-27: "build the festival pages… it should create every year automatically"): each festival's
     * yearly page /janun/puja/{slug}-{year}/ and its English name for titles. A page exists once at least half of that
     * year's events are checked (D302/D304); a year's pages stay once shown, so no address Google knows disappears.
     */
    public const PAGES = [
        'saraswati' => ['saraswati-puja', 'Saraswati Puja'], 'shiboratri' => ['shivaratri', 'Shivaratri'], 'dol' => ['dol-jatra', 'Dol Jatra'],
        'boishakh' => ['poila-boishakh', 'Poila Boishakh'], 'jamai' => ['jamai-shashthi', 'Jamai Shashthi'], 'bishwokarma' => ['vishwakarma-puja', 'Vishwakarma Puja'],
        'durga' => ['durga-puja', 'Durga Puja'], 'kojagori' => ['lakshmi-puja', 'Kojagori Lakshmi Puja'], 'kali' => ['kali-puja', 'Kali Puja'], 'bhaiphonta' => ['bhai-phonta', 'Bhai Phonta'],
    ];

    /** The cities every festival page shows, in this order (the diaspora research, D302). */
    public const CITIES = [
        'Asia/Kolkata' => 'কলকাতা', 'Asia/Dhaka' => 'ঢাকা', 'Europe/London' => 'লন্ডন', 'America/New_York' => 'নিউ ইয়র্ক',
        'America/Toronto' => 'টরন্টো', 'America/Los_Angeles' => 'ক্যালিফোর্নিয়া', 'Australia/Sydney' => 'সিডনি', 'Asia/Dubai' => 'দুবাই',
    ];

    /** The checked times of one festival's year, or null when fewer than half its events are checked (no page then). */
    public static function yearRows(string $key, int $year): ?\Illuminate\Support\Collection
    {
        if (! isset(self::FESTIVALS[$key])) {
            return null;
        }
        $rows = \App\Models\FestivalTime::where(['festival' => $key, 'cycle' => $year, 'status' => 'verified'])->orderBy('position')->get();

        return $rows->count() * 2 >= count(self::FESTIVALS[$key][2]) && $rows->isNotEmpty() ? $rows : null;
    }

    /** Every festival page that exists: list of [key, year], soonest first within a year. */
    public static function livePages(): \Illuminate\Support\Collection
    {
        return \App\Models\FestivalTime::where('status', 'verified')->get(['festival', 'cycle', 'day'])->groupBy(fn ($t) => $t->festival.'|'.$t->cycle)
            ->filter(fn ($rows, $k) => isset(self::PAGES[explode('|', $k)[0]]) && $rows->count() * 2 >= count(self::FESTIVALS[explode('|', $k)[0]][2]))
            ->map(fn ($rows, $k) => [explode('|', $k)[0], (int) explode('|', $k)[1], $rows->pluck('day')->filter()->min()])
            ->sortBy(fn ($f) => $f[1].'-'.($f[2]?->format('m-d') ?? '99'))->values()->map(fn ($f) => [$f[0], $f[1]]);
    }

    public static function pageUrl(string $key, int $year): string
    {
        return Url::to('/janun/puja/'.self::PAGES[$key][0].'-'.$year);
    }

    /** Common time zones first in the picker (the browser offers every other one). */
    public const ZONES = [
        'Asia/Kolkata' => 'ভারত', 'Asia/Dhaka' => 'বাংলাদেশ', 'Europe/London' => 'ব্রিটেন', 'America/New_York' => 'আমেরিকা (নিউ ইয়র্ক)',
        'America/Chicago' => 'আমেরিকা (শিকাগো)', 'America/Los_Angeles' => 'আমেরিকা (ক্যালিফোর্নিয়া)', 'America/Toronto' => 'কানাডা (টরন্টো)',
        'Australia/Sydney' => 'অস্ট্রেলিয়া (সিডনি)', 'Asia/Tokyo' => 'জাপান', 'Asia/Singapore' => 'সিঙ্গাপুর', 'Asia/Dubai' => 'দুবাই',
        'Europe/Berlin' => 'জার্মানি', 'Pacific/Auckland' => 'নিউজিল্যান্ড',
    ];

    /** The times page exists once two festivals have ever had checked times (it then stays, with a "coming soon" note between years). */
    public static function pageLive(): bool
    {
        return \App\Models\FestivalTime::where('status', 'verified')->distinct()->count('festival') >= 2;
    }

    /**
     * The coming festivals with checked times, soonest first: list of [key, name, cycle, rows].
     *
     * @return \Illuminate\Support\Collection<int, array{0: string, 1: string, 2: int, 3: \Illuminate\Support\Collection}>
     */
    public static function upcoming(?string $only = null): \Illuminate\Support\Collection
    {
        return \App\Models\FestivalTime::upcoming()->when($only, fn ($q) => $q->where('festival', $only))
            ->whereIn('festival', array_keys(self::FESTIVALS))->orderBy('position')->get()
            ->groupBy(fn ($t) => $t->festival.'|'.$t->cycle)
            ->map(fn ($rows, $k) => [explode('|', $k)[0], self::FESTIVALS[explode('|', $k)[0]][0], (int) explode('|', $k)[1], $rows->values()])
            ->sortBy(fn ($f) => $f[3]->map(fn ($t) => $t->starts_at ?? $t->day)->filter()->min())->values();
    }

    /** "১৩ ফেব্রুয়ারি ২০২৭, শনিবার, ৫:১২" in India time: what readers without JavaScript see. */
    public static function show(\Carbon\CarbonInterface $t, bool $withTime = true, string $zone = 'Asia/Kolkata'): string
    {
        $t = \Carbon\CarbonImmutable::instance($t)->setTimezone($zone);
        $days = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];

        // the date in the chosen zone itself (Bn::date always shows India's day; a city table needs New York's)
        $date = Bn::num($t->format('j')).' '.Bn::MONTHS[(int) $t->format('n') - 1].' '.Bn::num($t->format('Y'));

        return $date.', '.$days[$t->dayOfWeek].($withTime ? ', '.self::part($t->hour).' '.Bn::num($t->format('g:i')) : '');
    }

    /** সকাল, দুপুর, বিকেল, সন্ধ্যা or রাত (the same rule as public/js/hp-tz.js). */
    public static function part(int $hour): string
    {
        return match (true) {
            $hour < 4 => 'মধ্যরাতের পর', $hour < 6 => 'ভোর', $hour < 12 => 'সকাল', $hour >= 12 && $hour < 16 => 'দুপুর', $hour >= 16 && $hour < 18 => 'বিকেল', $hour >= 18 && $hour < 20 => 'সন্ধ্যা', default => 'রাত',
        };
    }
}
