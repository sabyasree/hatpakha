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
        'christmas' => ['বড়দিনের শুভেচ্ছা', 'Christmas Wishes in Bengali', null, 12, 'Christmas (বড়দিন), as celebrated in Bengal, warm and respectful'],
        'happy-new-year' => ['ইংরেজি নববর্ষের শুভেচ্ছা', 'Happy New Year Wishes in Bengali', null, 1, 'the 1 January new year'],
        'saraswati-puja' => ['সরস্বতী পুজোর শুভেচ্ছা', 'Saraswati Puja Wishes in Bengali', 'saraswati', 1, 'Saraswati Puja / Basanta Panchami: learning, students, spring, yellow'],
        'shivaratri' => ['শিবরাত্রির শুভেচ্ছা', 'Shivaratri Wishes in Bengali', 'shiboratri', 2, 'Maha Shivaratri: fasting, the night prayer to Shiva'],
        'dol-holi' => ['দোলযাত্রার শুভেচ্ছা', 'Dol Jatra and Holi Wishes in Bengali', 'dol', 3, 'Dol Jatra / Basanta Utsav: colours (আবির), spring, Radha-Krishna'],
        'eid-ul-fitr' => ['ঈদুল ফিতরের শুভেচ্ছা', 'Eid ul-Fitr Wishes in Bengali (Eid Mubarak)', null, 3, 'Eid ul-Fitr after Ramadan: ঈদ মোবারক, joy, sharing, the poor remembered; Bangladeshi and Indian Bengali readers'],
        'poila-baishakh' => ['পয়লা বৈশাখের শুভেচ্ছা', 'Poila Baishakh (Bengali New Year) Wishes', 'boishakh', 4, 'শুভ নববর্ষ, the Bengali new year, for both Bengals: হালখাতা, মঙ্গল শোভাযাত্রা, new clothes'],
        'eid-ul-adha' => ['ঈদুল আজহার শুভেচ্ছা', 'Eid ul-Adha Wishes in Bengali', null, 5, 'Eid ul-Adha (কোরবানির ঈদ): sacrifice, sharing; respectful, no detail of slaughter'],
        'jamai-shashthi' => ['জামাইষষ্ঠীর শুভেচ্ছা', 'Jamai Shashthi Wishes in Bengali', 'jamai', 6, 'Jamai Shashthi: the mother-in-law and the son-in-law, food, family, light humour allowed'],
        'rakhi' => ['রাখিবন্ধনের শুভেচ্ছা', 'Raksha Bandhan (Rakhi) Wishes in Bengali', null, 8, 'Rakhi Bandhan: brothers and sisters; Rabindranath\'s Rakhi of 1905 as unity of people may be mentioned'],
        'durga-puja' => ['শারদীয়ার শুভেচ্ছা', 'Durga Puja Wishes in Bengali (Sharodiya)', 'durga', 9, 'শারদীয়া / Durga Puja: autumn, কাশফুল, ঢাক, homecoming, pandals'],
        'bijoya' => ['শুভ বিজয়ার শুভেচ্ছা', 'Bijoya Dashami Wishes in Bengali', 'durga', 10, 'Bijoya Dashami: প্রণাম to elders, কোলাকুলি, sweets, আসছে বছর আবার হবে'],
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

    /** Which sets are written first: the occasions coming soonest (any-time ones first of all). */
    public static function order(int $month): array
    {
        return collect(self::OCCASIONS)->sortBy(fn ($o) => $o[3] === 0 ? -1 : ($o[3] - $month + 12) % 12)->keys()->all();
    }
}
