<?php

namespace App\Support;

/**
 * D315 (owner, 2026-11-27: "build … festival greetings with share pictures", automatic once checked, English words in
 * the address): /janun/greetings/{slug}/. Each occasion: Bengali name, English name for the title, the festival page it
 * belongs to (FestivalPlan key or null), the month it usually falls in (only to decide which set is written first;
 * 0 = any time of the year) and what the writer should keep in mind.
 */
final class GreetingPlan
{
    public const OCCASIONS = [
        'birthday' => ['জন্মদিনের শুভেচ্ছা', 'Birthday Wishes in Bengali', null, 0, 'birthday wishes for family, friends, children, colleagues'],
        'christmas' => ['বড়দিনের শুভেচ্ছা', 'Christmas Wishes in Bengali', null, 12, 'Christmas (বড়দিন), the birth of Jesus (Bangla Academy spelling যিশু, যিশুখ্রিস্ট; not যীশু), church, cake, carols, as celebrated in Bengal; warm and respectful'],
        'happy-new-year' => ['ইংরেজি নববর্ষের শুভেচ্ছা', 'Happy New Year Wishes in Bengali', null, 1, 'the 1 January new year'],
        'saraswati-puja' => ['সরস্বতী পুজোর শুভেচ্ছা', 'Saraswati Puja Wishes in Bengali', 'saraswati', 1, 'Saraswati Puja / Basanta Panchami: learning, students, spring, yellow'],
        'shivaratri' => ['শিবরাত্রির শুভেচ্ছা', 'Shivaratri Wishes in Bengali', 'shiboratri', 2, 'Maha Shivaratri: fasting, the night prayer to Shiva'],
        'dol-holi' => ['দোলযাত্রার শুভেচ্ছা', 'Dol Jatra and Holi Wishes in Bengali', 'dol', 3, 'Dol Jatra / Basanta Utsav: colours (আবির), spring, Radha-Krishna'],
        'eid-ul-fitr' => ['ঈদুল ফিতরের শুভেচ্ছা', 'Eid ul-Fitr Wishes in Bengali (Eid Mubarak)', null, 3, 'Eid ul-Fitr after the month of Ramadan: ঈদ মোবারক, prayer, joy, ফিতরা and the poor remembered, visiting family; Bangladeshi and Indian Bengali readers; when the Prophet is named write (সা.)'],
        'poila-baishakh' => ['পয়লা বৈশাখের শুভেচ্ছা', 'Poila Baishakh (Bengali New Year) Wishes', 'boishakh', 4, 'শুভ নববর্ষ, the Bengali new year, for both Bengals: হালখাতা, নববর্ষের শোভাযাত্রা, new clothes; do not name any procession'],
        'eid-ul-adha' => ['ঈদুল আজহার শুভেচ্ছা', 'Eid ul-Adha Wishes in Bengali (Eid Mubarak)', null, 5, 'Eid ul-Adha (কোরবানির ঈদ): the sacrifice in remembrance of Ibrahim (আ.)\'s devotion, the Hajj, sharing with the poor and neighbours; respectful; no detail of slaughter, no jokes about the animal'],
        'jamai-shashthi' => ['জামাইষষ্ঠীর শুভেচ্ছা', 'Jamai Shashthi Wishes in Bengali', 'jamai', 6, 'Jamai Shashthi: the mother-in-law and the son-in-law, food, family, light humour allowed'],
        'rakhi' => ['রাখিবন্ধনের শুভেচ্ছা', 'Raksha Bandhan (Rakhi) Wishes in Bengali', null, 8, 'Raksha Bandhan (রাখিবন্ধন, রাখিপূর্ণিমা): brothers and sisters; Rabindranath\'s রাখিবন্ধন of 16 October 1905 against the Partition of Bengal (not on Rakhi Purnima) may be mentioned as a separate memory'],
        'durga-puja' => ['শারদীয়ার শুভেচ্ছা', 'Durga Puja Wishes in Bengali (Sharodiya)', 'durga', 9, 'শারদীয়া / Durga Puja: autumn, কাশফুল, ঢাক, homecoming, pandals'],
        'bijoya' => ['বিজয়ার শুভেচ্ছা', 'Bijoya Dashami Wishes in Bengali', 'durga', 10, 'Bijoya Dashami: প্রণাম to elders, কোলাকুলি, sweets, আসছে বছর আবার হবে'],
        'lakshmi-puja' => ['লক্ষ্মীপুজোর শুভেচ্ছা', 'Lakshmi Puja Wishes in Bengali', 'kojagori', 10, 'Kojagori Lakshmi Puja: the full moon, আলপনা, প্রসাদ, home and plenty'],
        'kali-puja-diwali' => ['কালীপুজো ও দীপাবলির শুভেচ্ছা', 'Kali Puja and Diwali Wishes in Bengali', 'kali', 10, 'Kali Puja and Dipaboli: lamps (প্রদীপ), light over darkness; safe, quiet, no firecrackers'],
        'bhai-phonta' => ['ভাইফোঁটার শুভেচ্ছা', 'Bhai Phonta Wishes in Bengali', 'bhaiphonta', 11, 'Bhai Phonta: sisters\' blessing for brothers, the ফোঁটা, sweets'],
    ];

    /** The groups every set is written for: who the message is sent to. */
    public const GROUPS = ['chhoto' => 'ছোট শুভেচ্ছা', 'poribar' => 'পরিবারের জন্য', 'bondhu' => 'বন্ধুদের জন্য', 'office' => 'সহকর্মী ও অফিসের জন্য'];

    /** At least this many of our own lines left after the check, in at least three groups, or the set is held. */
    public const MIN_LINES = 16;

    /** A line that fits a share picture (Bengali letters counted with their signs). */
    public const CARD_MAX = 110;

    /** Occasions on a fixed Gregorian day (month-day). */
    public const FIXED = ['christmas' => '12-25', 'happy-new-year' => '01-01', 'poila-baishakh' => '04-14'];

    /** The Eids move about eleven days earlier each year: their usual month, by year (approximate, only for the order). */
    public const MOVING = ['eid-ul-fitr' => [2027 => 3, 2028 => 2, 2029 => 2, 2030 => 2], 'eid-ul-adha' => [2027 => 5, 2028 => 5, 2029 => 4, 2030 => 4]];

    /**
     * When the occasion next comes, as well as we know without asking anyone: a checked festival day (FestivalPlan) first,
     * then a fixed day, then the usual month (its 15th). Used only to decide what is written first and what the hub shows
     * first; never printed. Any-time occasions (birthday) are "today".
     */
    public static function next(string $slug, \Carbon\CarbonImmutable $now): \Carbon\CarbonImmutable
    {
        $today = $now->timezone('Asia/Kolkata')->startOfDay();
        [, , $festival, $month] = self::OCCASIONS[$slug];
        if ($month === 0) {
            return $today;
        }
        if ($festival && ($day = \App\Models\FestivalTime::where(['festival' => $festival, 'status' => 'verified'])->whereDate('day', '>=', $today->toDateString())->min('day'))) {
            return \Carbon\CarbonImmutable::parse($day, 'Asia/Kolkata')->startOfDay();
        }
        foreach ([$today->year, $today->year + 1] as $y) {
            $d = isset(self::FIXED[$slug]) ? \Carbon\CarbonImmutable::parse($y.'-'.self::FIXED[$slug], 'Asia/Kolkata')
                : \Carbon\CarbonImmutable::create($y, self::MOVING[$slug][$y] ?? $month, 15, 0, 0, 0, 'Asia/Kolkata');
            if ($d->gte($today)) {
                return $d;
            }
        }

        return $today->addYear();
    }

    /** Which sets are written first: the occasions coming soonest (any-time ones first of all). */
    public static function order(\Carbon\CarbonImmutable $now): array
    {
        return collect(array_keys(self::OCCASIONS))->sortBy(fn ($k) => self::next($k, $now)->timestamp)->values()->all();
    }
}
