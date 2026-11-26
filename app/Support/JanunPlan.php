<?php

namespace App\Support;

/**
 * D301 (owner, 2026-11-26: "go with janun… mantras fully automatic… build everything, group it and make publish
 * scheduled"): the "জেনে রাখুন" section. The SEO and UX reviews of 2026-11-25 chose three parts and these first pages;
 * App\Ai\JanunDesk writes, checks and schedules them (three a week), nothing goes out all at once.
 */
final class JanunPlan
{
    /** slug => [name, kicker, introduction] */
    public const SECTIONS = [
        'bhasha' => ['ভাষার ভাণ্ডার', 'প্রবাদ, বাগধারা, শব্দ ও বানান', 'প্রবাদ, বাগধারা, এক কথায় প্রকাশ, সমার্থক-বিপরীত শব্দ আর শুদ্ধ বানান: অর্থ ও উদাহরণসহ, বাংলা একাডেমির প্রমিত বানানে।'],
        'puja' => ['পুজো-পার্বণ', 'নিয়ম, উপকরণ ও মন্ত্র', 'বাঙালির পুজো-পার্বণ: কবে, কীভাবে, কী লাগে, আর মন্ত্রের মানে সহজ বাংলায়। প্রতিটি মন্ত্র দুটি নির্ভরযোগ্য সূত্রে মিলিয়ে দেওয়া।'],
        'pathshala' => ['পাঠশালা', 'ভাবসম্প্রসারণ, রচনা ও ব্যাকরণ', 'মাধ্যমিক, উচ্চমাধ্যমিক, এসএসসি ও এইচএসসির জন্য: ভাবসম্প্রসারণ, রচনা, পত্র লিখন আর ব্যাকরণ, হাতপাখার নিজের লেখা।'],
    ];

    /**
     * key => [section, kind, slug, title (H1), Google title (≤ 60), what to write]. The order is the publishing order:
     * Saraswati Puja goes early, two months before its late-January searches; exam pages before the exam season.
     */
    public const PAGES = [
        'probad' => ['bhasha', 'list', 'probad', 'বাংলা প্রবাদ-প্রবচন ও তার অর্থ', 'বাংলা প্রবাদ-প্রবচন ও অর্থ: উদাহরণসহ ১০০টি প্রবাদ', '100 well-known Bengali proverbs (প্রবাদ-প্রবচন): each with its meaning in simple Bengali and one example sentence of our own.'],
        'saraswati-mantra' => ['puja', 'guide', 'saraswati-puja-mantra', 'সরস্বতী পুজোর মন্ত্র, পুষ্পাঞ্জলি ও নিয়ম', 'সরস্বতী পুজোর পুষ্পাঞ্জলি মন্ত্র ও প্রণাম মন্ত্র: অর্থসহ', 'Saraswati Puja (বসন্ত পঞ্চমী): when it falls (by the Bengali calendar; no Gregorian date claimed), what is needed, the steps at home and in schools, the pushpanjali and pranam mantras with word meanings.'],
        'bhabsomprosaron-niyom' => ['pathshala', 'study', 'bhabsomprosaron-lekhar-niyom', 'ভাবসম্প্রসারণ লেখার নিয়ম (নমুনাসহ)', 'ভাবসম্প্রসারণ লেখার নিয়ম: কীভাবে লিখবে, নমুনাসহ', 'How to write a ভাবসম্প্রসারণ for school exams in both Bengals: structure (মূলভাব, সম্প্রসারিত ভাব, মন্তব্য), length, common mistakes, with one short sample of our own.'],
        'bagdhara' => ['bhasha', 'list', 'bagdhara', 'বাগধারা ও তার অর্থ, বাক্যে প্রয়োগসহ', 'বাগধারা ও তার অর্থ: বাক্যে প্রয়োগসহ ১০০টি বাগধারা', '100 well-known Bengali বাগধারা (idioms): meaning and one example sentence of our own each.'],
        'lokkhi-mantra' => ['puja', 'guide', 'lokkhi-puja-mantra', 'লক্ষ্মী পুজোর মন্ত্র, প্রণাম ও পুষ্পাঞ্জলি (অর্থসহ)', 'লক্ষ্মী পুজোর প্রণাম মন্ত্র ও পুষ্পাঞ্জলি মন্ত্র: অর্থসহ', 'The Lakshmi Puja mantras (pranam, pushpanjali, dhyan if widely documented) with word meanings, and a short note on the weekly Thursday (বৃহস্পতিবার) puja. Do not cover the Kojagori puja here: it has its own page.'],
        'ek-kothay' => ['bhasha', 'list', 'ek-kothay-prokash', 'এক কথায় প্রকাশ', 'এক কথায় প্রকাশ: ১০০টি উদাহরণ, অর্থসহ', '100 common এক কথায় প্রকাশ (one-word substitutions) used in exams: the phrase and the one word, with a short example sentence of our own.'],
        'porishrom' => ['pathshala', 'study', 'porishrom-soubhagyer-prosuti', 'পরিশ্রম সৌভাগ্যের প্রসূতি: ভাবসম্প্রসারণ', 'পরিশ্রম সৌভাগ্যের প্রসূতি: ভাবসম্প্রসারণ (মূলভাব সহ)', 'The ভাবসম্প্রসারণ of "পরিশ্রম সৌভাগ্যের প্রসূতি", our own writing.'],
        'somarthok' => ['bhasha', 'list', 'somarthok-shobdo', 'সমার্থক শব্দ', 'সমার্থক শব্দ: ১০০টি শব্দ ও তার সমার্থক শব্দ', '100 common Bengali words each with 3-5 synonyms (সমার্থক শব্দ) and one example sentence of our own.'],
        'ekushey' => ['pathshala', 'study', 'ekushe-february', 'একুশে ফেব্রুয়ারি: ভাষা আন্দোলনের কথা', 'একুশে ফেব্রুয়ারি ও ভাষা আন্দোলন: ছোটদের জন্য সহজ কথায়', 'The Language Movement and 21 February (শহিদ দিবস, আন্তর্জাতিক মাতৃভাষা দিবস) told simply for school children, in Bangladesh, India and abroad: only well-documented facts and names; headings ভূমিকা … উপসংহার.'],
        'durga-anjali' => ['puja', 'guide', 'durga-puja-anjali-mantra', 'দুর্গাপুজোর অঞ্জলি মন্ত্র (অর্থসহ)', 'দুর্গাপুজোর অঞ্জলি মন্ত্র ও প্রণাম মন্ত্র: অর্থসহ', 'Durga Puja pushpanjali (অঞ্জলি) mantras and pranam mantra with word meanings, how anjali is given, and when (by the Bengali calendar; no Gregorian dates).'],
        'biporit' => ['bhasha', 'list', 'biporit-shobdo', 'বিপরীত শব্দ', 'বিপরীত শব্দ: ১০০টি শব্দ ও তার বিপরীত শব্দ', '100 common Bengali words with their opposites (বিপরীত শব্দ), one example sentence of our own each.'],
        'kirtiman' => ['pathshala', 'study', 'kirtimaner-mrittu-nei', 'কীর্তিমানের মৃত্যু নেই: ভাবসম্প্রসারণ', 'কীর্তিমানের মৃত্যু নেই: ভাবসম্প্রসারণ (মূলভাব সহ)', 'The ভাবসম্প্রসারণ of "কীর্তিমানের মৃত্যু নেই", our own writing.'],
        'banan-bhul' => ['bhasha', 'list', 'banan-bhul', 'সাধারণ বানান ভুল ও শুদ্ধ বানান', 'সাধারণ বাংলা বানান ভুল ও শুদ্ধ বানান: ১০০টি', '100 Bengali words commonly misspelt: the wrong form, the correct form (Bangla Academy প্রমিত বানান) and a one-line reason.'],
        'kali-pranam' => ['puja', 'guide', 'kali-puja-pranam-mantra', 'কালীপুজোর প্রণাম মন্ত্র ও নিয়ম', 'কালীপুজোর প্রণাম মন্ত্র: অর্থসহ, পুজোর নিয়ম', 'Kali Puja: when (by the Bengali calendar), the pranam mantra with meaning, what is needed and the main steps of a home puja.'],
        'rochona-noboborsho' => ['pathshala', 'study', 'rochona-bangla-noboborsho', 'রচনা: বাংলা নববর্ষ', 'রচনা: বাংলা নববর্ষ — ছাত্রছাত্রীদের জন্য', 'A school essay (রচনা) on বাংলা নববর্ষ, with headings (ভূমিকা … উপসংহার), our own writing, for both Bengals.'],
        'i-kar' => ['bhasha', 'guide', 'i-kar-ii-kar', 'ই-কার না ঈ-কার: বানানের নিয়ম', 'ই-কার না ঈ-কার: বাংলা বানানের সহজ নিয়ম', 'Bangla Academy rules for ি and ী in Bengali spelling (tatsama and non-tatsama words), with many examples.'],
        'bhaiphonta' => ['puja', 'guide', 'bhaiphonta-mantra-niyom', 'ভাইফোঁটার মন্ত্র ও নিয়ম', 'ভাইফোঁটার মন্ত্র ও নিয়ম: কীভাবে ফোঁটা দেবেন', 'Bhai Phonta: when (by the Bengali calendar), what is needed, how the phonta is given, the well-known phonta rhyme/mantra with meaning.'],
        'rochona-borsha' => ['pathshala', 'study', 'rochona-borshakal', 'রচনা: বর্ষাকাল', 'রচনা: বর্ষাকাল — ছাত্রছাত্রীদের জন্য', 'A school essay (রচনা) on বর্ষাকাল with headings, our own writing.'],
        'notto' => ['bhasha', 'guide', 'notto-sotto-bidhan', 'ণত্ব ও ষত্ব বিধান', 'ণত্ব বিধান ও ষত্ব বিধান: নিয়ম ও উদাহরণ', 'The ণত্ব and ষত্ব rules of Bengali spelling, with examples and exceptions.'],
        'jamai' => ['puja', 'guide', 'jamai-shoshthi', 'জামাইষষ্ঠী: নিয়ম ও তাৎপর্য', 'জামাইষষ্ঠী: কবে, কেন, কীভাবে পালন করা হয়', 'Jamai Shashthi: when (by the Bengali calendar), its meaning, the customs, food; no mantra unless widely documented.'],
        'ki-kii' => ['bhasha', 'guide', 'ki-na-kii', '"কি" না "কী": কখন কোনটি', '"কি" না "কী": কখন কোনটি লিখবেন, উদাহরণসহ', 'When to write কি and when কী (Bangla Academy rule), with many examples.'],
        'nobanno' => ['puja', 'guide', 'nobanno', 'নবান্ন উৎসব: কী, কবে, কীভাবে', 'নবান্ন উৎসব: কী, কবে, কীভাবে পালন করা হয়', 'Nabanna, the harvest festival of Bengal: when (অগ্রহায়ণ), the customs, food, its place in Bengali literature (no long quotes).'],
        'probad-english' => ['bhasha', 'list', 'probad-english', 'বাংলা প্রবাদের ইংরেজি প্রতিরূপ', 'বাংলা প্রবাদের ইংরেজি প্রতিরূপ: ৬০টি প্রবাদ', '60 Bengali proverbs with a close English proverb equivalent and the meaning in Bengali.'],
        'kojagori' => ['puja', 'guide', 'kojagori-lokkhi-puja-niyom', 'কোজাগরী লক্ষ্মী পুজোর নিয়ম ও উপকরণ', 'কোজাগরী লক্ষ্মী পুজোর নিয়ম, উপকরণ ও ব্রতকথা', 'Kojagori Lakshmi Puja: when (আশ্বিন পূর্ণিমা), the list of things needed, the steps at home, the vrat katha in brief, mantras only if checked.'],
        // D301, added after the US/UK research of 2026-11-26 (families abroad, children who read Bengali slowly):
        'boishakh' => ['puja', 'guide', 'poila-boishakh', 'পয়লা বৈশাখ: বাংলা নববর্ষ', 'পয়লা বৈশাখ: বাংলা নববর্ষ কী, কবে, কীভাবে পালন করা হয়', 'Pohela/Poila Boishakh, the Bengali New Year, for families at home and abroad: why the day can differ (Bangladesh keeps a fixed day by its revised calendar; West Bengal follows the panjika), হালখাতা, মঙ্গল শোভাযাত্রা, food, songs. Inclusive of Bengalis of every faith. No mantra.'],
        'mas-ritu' => ['bhasha', 'guide', 'bangla-mas-o-ritu', 'বাংলা বারো মাস ও ছয় ঋতু', 'বাংলা বারো মাসের নাম ও ছয় ঋতু: সহজ তালিকা', 'The twelve Bengali months in order (বৈশাখ … চৈত্র), the six seasons and which months belong to each, the seven days of the week in Bengali, with the rough English months beside them (say that the exact start differs between Bangladesh and West Bengal).'],
        'somboddhon' => ['bhasha', 'list', 'attiyo-sombodhon', 'আত্মীয়দের বাংলা সম্বোধন', 'আত্মীয়দের বাংলা নাম: দাদু, ঠাকুমা, নানা, দাদি… কে কোন সম্পর্ক', 'Family relation words in Bengali (বাবার দিক, মায়ের দিক, শ্বশুরবাড়ি), each with who exactly it means (in Bengali, e.g. "মায়ের বাবা"); give both West Bengal and Bangladesh usage where they differ (দাদু/নানা, ঠাকুমা/দাদি, পিসি/ফুপু, মাসি/খালা, জল/পানি is not a relation: leave it out). At least 50 entries; example sentence of our own.'],
        'bornomala' => ['pathshala', 'guide', 'bangla-bornomala', 'বাংলা বর্ণমালা: স্বরবর্ণ ও ব্যঞ্জনবর্ণ', 'বাংলা বর্ণমালা: স্বরবর্ণ, ব্যঞ্জনবর্ণ, কার-চিহ্ন ও ফলা', 'The Bengali alphabet for children and parents (also families abroad): all স্বরবর্ণ and ব্যঞ্জনবর্ণ in order, the কার signs with examples (ক কা কি কী…), ফলা, and tips for parents teaching a child to read at home; one easy word for each letter.'],
        'juktakkhor' => ['bhasha', 'list', 'juktakkhor', 'বাংলা যুক্তাক্ষর: কোন অক্ষরে কী মিলে', 'বাংলা যুক্তাক্ষর তালিকা: কোন বর্ণ মিলে, উদাহরণসহ', 'Common Bengali conjunct letters (যুক্তবর্ণ): term = the conjunct (e.g. ক্ষ), meaning = which letters join (ক + ষ) and how it sounds, example = two common words using it. At least 60, most common first.'],
        // D301, added after the SEO review of 2026-11-26 (large exam and festival searches still missing):
        'sondhi' => ['pathshala', 'guide', 'sondhi-bicched', 'সন্ধি ও সন্ধি বিচ্ছেদ', 'সন্ধি বিচ্ছেদ: স্বরসন্ধি, ব্যঞ্জনসন্ধি, বিসর্গসন্ধি, উদাহরণসহ', 'Bengali grammar: সন্ধি (স্বরসন্ধি, ব্যঞ্জনসন্ধি, বিসর্গসন্ধি, নিপাতনে সিদ্ধ), each rule with many common exam examples (word = parts); as in standard school grammar books of both Bengals.'],
        'shiboratri' => ['puja', 'guide', 'shibratri-brot-niyom', 'শিবরাত্রি: ব্রতের নিয়ম ও মন্ত্র', 'শিবরাত্রি ব্রতের নিয়ম, চার প্রহরের পুজো ও প্রণাম মন্ত্র', 'Shivaratri: when (ফাল্গুন কৃষ্ণা চতুর্দশী), the fast, the four prahar puja at home, what is needed, the pranam mantra with meaning (only if checked).'],
        'somas' => ['pathshala', 'guide', 'somas', 'সমাস: প্রকারভেদ ও ব্যাসবাক্য', 'সমাস কাকে বলে, কত প্রকার: ব্যাসবাক্যসহ উদাহরণ', 'Bengali grammar: সমাস, its kinds (দ্বন্দ্ব, কর্মধারয়, তৎপুরুষ, বহুব্রীহি, দ্বিগু, অব্যয়ীভাব, others as taught), each with ব্যাসবাক্য examples used in exams.'],
        'potro' => ['pathshala', 'study', 'potro-likhon', 'পত্র লিখন: চিঠি ও আবেদনপত্র লেখার নিয়ম', 'পত্র লিখন: ব্যক্তিগত চিঠি ও আবেদনপত্র লেখার নিয়ম, নমুনাসহ', 'How to write letters for school exams: the parts of a personal letter and of a formal application (আবেদনপত্র: প্রধান শিক্ষকের কাছে ছুটির আবেদন), with one sample of each of our own writing; mention that addresses and dates follow the student\'s own.'],
        'dol' => ['puja', 'guide', 'dol-jatra', 'দোলযাত্রা: কবে, কেন, কীভাবে', 'দোলযাত্রা ও দোল পূর্ণিমা: তাৎপর্য, নিয়ম ও শান্তিনিকেতনের বসন্তোৎসব', 'Dol Jatra (ফাল্গুনী পূর্ণিমা): its meaning, ন্যাড়াপোড়া, the customs at home, Sri Chaitanya\'s birth on this day, Santiniketan\'s বসন্তোৎসব; inclusive, festive. Mantra only if checked.'],
        'karok' => ['pathshala', 'guide', 'karok-bibhokti', 'কারক ও বিভক্তি', 'কারক ও বিভক্তি: ছয় প্রকার কারক, উদাহরণসহ', 'Bengali grammar: কারক (কর্তা, কর্ম, করণ, নিমিত্ত/সম্প্রদান, অপাদান, অধিকরণ) and বিভক্তি, how to find each, with exam examples; mention where Bangladesh and West Bengal textbooks name them differently.'],
        'bhab-songroho' => ['pathshala', 'study', 'bhabsomprosaron-songroho', 'ভাবসম্প্রসারণ সংগ্রহ: পরীক্ষার জনপ্রিয় লাইন', 'ভাবসম্প্রসারণ সংগ্রহ: পরীক্ষায় বারবার আসা ১২টি লাইন', 'Twelve lines often set for ভাবসম্প্রসারণ in school exams (not the two that have their own pages: পরিশ্রম সৌভাগ্যের প্রসূতি, কীর্তিমানের মৃত্যু নেই). Each line is one section: heading = the line (and its poet only if certain), paragraphs = মূলভাব then সম্প্রসারিত ভাব, our own writing.'],
        'lingo' => ['pathshala', 'guide', 'lingo-poriborton', 'লিঙ্গ পরিবর্তন ও পদ পরিবর্তন', 'লিঙ্গ পরিবর্তন ও পদ পরিবর্তন: নিয়ম ও তালিকা', 'Bengali grammar: লিঙ্গ (kinds and the rules of changing gender, with a long list of pairs) and পদ পরিবর্তন (বিশেষ্য ↔ বিশেষণ, with a long list), as taught in schools.'],
        'rochona-shit' => ['pathshala', 'study', 'rochona-shitkal', 'রচনা: শীতকাল', 'রচনা: শীতকাল — ছাত্রছাত্রীদের জন্য', 'A school essay (রচনা) on শীতকাল with headings (ভূমিকা … উপসংহার), our own writing.'],
        'bishwokarma' => ['puja', 'guide', 'bishwokarma-puja', 'বিশ্বকর্মা পুজো: নিয়ম ও মন্ত্র', 'বিশ্বকর্মা পুজোর নিয়ম, ঘুড়ি ওড়ানো ও প্রণাম মন্ত্র', 'Vishwakarma Puja (ভাদ্র সংক্রান্তি): its meaning, the puja in workshops and homes, kite flying, the pranam mantra with meaning (only if checked).'],
    ];

    /** Pages on the same subject link to each other first (SEO review): any two keys in one group. */
    public const RELATED = [
        ['i-kar', 'ki-kii', 'notto', 'banan-bhul', 'juktakkhor'],
        ['probad', 'bagdhara', 'probad-english'],
        ['somarthok', 'biporit', 'ek-kothay'],
        ['lokkhi-mantra', 'kojagori'],
        ['saraswati-mantra', 'durga-anjali', 'kali-pranam', 'bhaiphonta'],
        ['boishakh', 'rochona-noboborsho', 'mas-ritu'],
        ['bhabsomprosaron-niyom', 'porishrom', 'kirtiman', 'bhab-songroho'],
        ['rochona-borsha', 'rochona-shit', 'rochona-noboborsho', 'potro'],
        ['sondhi', 'somas', 'karok', 'lingo'],
        ['bornomala', 'juktakkhor', 'somboddhon'],
        ['nobanno', 'jamai', 'dol', 'bishwokarma', 'shiboratri'],
    ];

    /** @return list<string> the keys related to this one */
    public static function related(string $key): array
    {
        return collect(self::RELATED)->filter(fn ($g) => in_array($key, $g, true))->flatten()->reject(fn ($k) => $k === $key)->unique()->values()->all();
    }

    /** A Google description of at most $max characters, cut between words (SEO review). */
    public static function clip(string $text, int $max = 155): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $sp = mb_strrpos($cut, ' ');

        return rtrim($sp > $max / 2 ? mb_substr($cut, 0, $sp) : $cut, ' ,;:—-').'…';
    }

    /** Published three a week: Monday, Wednesday and Friday at 10am (India). */
    public const DAYS = [1, 3, 5];
}
