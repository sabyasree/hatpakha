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
        'bhasha' => ['ভাষার ভাণ্ডার', 'প্রবাদ, বাগধারা, শব্দ ও বানান', 'প্রবাদ-প্রবচন, বাগধারা, এক কথায় প্রকাশ, সমার্থক ও বিপরীত শব্দ আর শুদ্ধ বানান: অর্থ আর উদাহরণসহ, বাংলা একাডেমির প্রমিত বানানে। পরীক্ষার আগে, লেখার সময়, বা শুধু ভালোবেসে।'],
        'puja' => ['পুজো-পার্বণ', 'নিয়ম, উপকরণ ও মন্ত্র', 'বাঙালির পুজো আর পার্বণ: কবে, কীভাবে, কী কী লাগে, আর মন্ত্রগুলোর মানে সহজ বাংলায়। প্রতিটি মন্ত্র অন্তত দুটি নির্ভরযোগ্য সূত্রের সঙ্গে মিলিয়ে দেওয়া।'],
        'pathshala' => ['পাঠশালা', 'ভাবসম্প্রসারণ ও রচনা', 'মাধ্যমিক, উচ্চমাধ্যমিক, এসএসসি আর এইচএসসির ছাত্রছাত্রীদের জন্য: ভাবসম্প্রসারণ আর রচনা, হাতপাখার নিজের লেখা, আর পরীক্ষায় কীভাবে লিখবে তার সহজ নিয়ম।'],
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
        'lokkhi-mantra' => ['puja', 'guide', 'lokkhi-puja-mantra', 'লক্ষ্মী পুজোর মন্ত্র, প্রণাম ও পুষ্পাঞ্জলি (অর্থসহ)', 'লক্ষ্মী পুজোর প্রণাম মন্ত্র ও পুষ্পাঞ্জলি মন্ত্র: অর্থসহ', 'The Lakshmi Puja mantras (pranam, pushpanjali, dhyan if widely documented) with word meanings, and a short note on the weekly Thursday puja and the Kojagori puja.'],
        'ek-kothay' => ['bhasha', 'list', 'ek-kothay-prokash', 'এক কথায় প্রকাশ', 'এক কথায় প্রকাশ: ১০০টি উদাহরণ, অর্থসহ', '100 common এক কথায় প্রকাশ (one-word substitutions) used in exams: the phrase and the one word, with a short example sentence of our own.'],
        'porishrom' => ['pathshala', 'study', 'porishrom-soubhagyer-prosuti', 'পরিশ্রম সৌভাগ্যের প্রসূতি: ভাবসম্প্রসারণ', 'পরিশ্রম সৌভাগ্যের প্রসূতি: ভাবসম্প্রসারণ (মূলভাব সহ)', 'The ভাবসম্প্রসারণ of "পরিশ্রম সৌভাগ্যের প্রসূতি", our own writing.'],
        'somarthok' => ['bhasha', 'list', 'somarthok-shobdo', 'সমার্থক শব্দ', 'সমার্থক শব্দ: ১০০টি শব্দ ও তার সমার্থক শব্দ', '100 common Bengali words each with 3-5 synonyms (সমার্থক শব্দ) and one example sentence of our own.'],
        'durga-anjali' => ['puja', 'guide', 'durga-puja-anjali-mantra', 'দুর্গাপুজোর অঞ্জলি মন্ত্র (অর্থসহ)', 'দুর্গাপুজোর অঞ্জলি মন্ত্র ও প্রণাম মন্ত্র: অর্থসহ', 'Durga Puja pushpanjali (অঞ্জলি) mantras and pranam mantra with word meanings, how anjali is given, and when (by the Bengali calendar; no Gregorian dates).'],
        'biporit' => ['bhasha', 'list', 'biporit-shobdo', 'বিপরীত শব্দ', 'বিপরীত শব্দ: ১০০টি শব্দ ও তার বিপরীত শব্দ', '100 common Bengali words with their opposites (বিপরীত শব্দ), one example sentence of our own each.'],
        'kirtiman' => ['pathshala', 'study', 'kirtimaner-mrittu-nei', 'কীর্তিমানের মৃত্যু নেই: ভাবসম্প্রসারণ', 'কীর্তিমানের মৃত্যু নেই: ভাবসম্প্রসারণ (মূলভাব সহ)', 'The ভাবসম্প্রসারণ of "কীর্তিমানের মৃত্যু নেই", our own writing.'],
        'banan-bhul' => ['bhasha', 'list', 'banan-bhul', 'সাধারণ বানান ভুল ও শুদ্ধ বানান', 'সাধারণ বাংলা বানান ভুল ও শুদ্ধ বানান: ১০০টি', '100 Bengali words commonly misspelt: the wrong form, the correct form (Bangla Academy প্রমিত বানান) and a one-line reason.'],
        'kali-pranam' => ['puja', 'guide', 'kali-puja-pranam-mantra', 'কালীপুজোর প্রণাম মন্ত্র ও নিয়ম', 'কালীপুজোর প্রণাম মন্ত্র: অর্থসহ, পুজোর নিয়ম', 'Kali Puja: when (by the Bengali calendar), the pranam mantra with meaning, what is needed and the main steps of a home puja.'],
        'rochona-noboborsho' => ['pathshala', 'study', 'rochona-bangla-noboborsho', 'রচনা: বাংলা নববর্ষ', 'রচনা: বাংলা নববর্ষ (পয়লা বৈশাখ) — ছাত্রছাত্রীদের জন্য', 'A school essay (রচনা) on বাংলা নববর্ষ, with headings (ভূমিকা … উপসংহার), our own writing, for both Bengals.'],
        'i-kar' => ['bhasha', 'guide', 'i-kar-ii-kar', 'ই-কার না ঈ-কার: বানানের নিয়ম', 'ই-কার না ঈ-কার: বাংলা বানানের সহজ নিয়ম', 'Bangla Academy rules for ি and ী in Bengali spelling (tatsama and non-tatsama words), with many examples.'],
        'bhaiphonta' => ['puja', 'guide', 'bhaiphonta-mantra-niyom', 'ভাইফোঁটার মন্ত্র ও নিয়ম', 'ভাইফোঁটার মন্ত্র ও নিয়ম: কীভাবে ফোঁটা দেবেন', 'Bhai Phonta: when (by the Bengali calendar), what is needed, how the phonta is given, the well-known phonta rhyme/mantra with meaning.'],
        'rochona-borsha' => ['pathshala', 'study', 'rochona-borshakal', 'রচনা: বর্ষাকাল', 'রচনা: বর্ষাকাল — ছাত্রছাত্রীদের জন্য', 'A school essay (রচনা) on বর্ষাকাল with headings, our own writing.'],
        'notto' => ['bhasha', 'guide', 'notto-sotto-bidhan', 'ণত্ব ও ষত্ব বিধান', 'ণত্ব বিধান ও ষত্ব বিধান: নিয়ম ও উদাহরণ', 'The ণত্ব and ষত্ব rules of Bengali spelling, with examples and exceptions.'],
        'jamai' => ['puja', 'guide', 'jamai-shoshthi', 'জামাইষষ্ঠী: নিয়ম ও তাৎপর্য', 'জামাইষষ্ঠী: কবে, কেন, কীভাবে পালন করা হয়', 'Jamai Shashthi: when (by the Bengali calendar), its meaning, the customs, food; no mantra unless widely documented.'],
        'ki-kii' => ['bhasha', 'guide', 'ki-na-kii', '"কি" না "কী": কখন কোনটি', '"কি" না "কী": কখন কোনটি লিখবেন, উদাহরণসহ', 'When to write কি and when কী (Bangla Academy rule), with many examples.'],
        'nobanno' => ['puja', 'guide', 'nobanno', 'নবান্ন উৎসব: কী, কবে, কীভাবে', 'নবান্ন উৎসব: কী, কবে, কীভাবে পালন করা হয়', 'Nabanna, the harvest festival of Bengal: when (অগ্রহায়ণ), the customs, food, its place in Bengali literature (no long quotes).'],
        'probad-english' => ['bhasha', 'list', 'probad-english', 'বাংলা প্রবাদের ইংরেজি প্রতিরূপ', 'বাংলা প্রবাদের ইংরেজি প্রতিরূপ: ৬০টি প্রবাদ', '60 Bengali proverbs with a close English proverb equivalent and the meaning in Bengali.'],
        'kojagori' => ['puja', 'guide', 'kojagori-lokkhi-puja-niyom', 'কোজাগরী লক্ষ্মী পুজোর নিয়ম ও উপকরণ', 'কোজাগরী লক্ষ্মী পুজোর নিয়ম, উপকরণ ও সময়', 'Kojagori Lakshmi Puja: when (আশ্বিন পূর্ণিমা), the list of things needed, the steps at home, the vrat katha in brief, mantras only if checked.'],
        // D301, added after the US/UK research of 2026-11-26 (families abroad, children who read Bengali slowly):
        'boishakh' => ['puja', 'guide', 'poila-boishakh', 'পয়লা বৈশাখ: বাংলা নববর্ষ', 'পয়লা বৈশাখ: বাংলা নববর্ষ কী, কবে, কীভাবে পালন করা হয়', 'Pohela/Poila Boishakh, the Bengali New Year, for families at home and abroad: why the day can differ (Bangladesh keeps a fixed day by its revised calendar; West Bengal follows the panjika), হালখাতা, মঙ্গল শোভাযাত্রা, food, songs. Inclusive of Bengalis of every faith. No mantra.'],
        'ekushey' => ['pathshala', 'study', 'ekushe-february', 'একুশে ফেব্রুয়ারি: ভাষা আন্দোলনের কথা', 'একুশে ফেব্রুয়ারি ও ভাষা আন্দোলন: ছোটদের জন্য সহজ কথায়', 'The Language Movement and 21 February (শহিদ দিবস, আন্তর্জাতিক মাতৃভাষা দিবস) told simply for school children, in Bangladesh, India and abroad: only well-documented facts and names; headings ভূমিকা … উপসংহার.'],
        'mas-ritu' => ['bhasha', 'guide', 'bangla-mas-o-ritu', 'বাংলা বারো মাস ও ছয় ঋতু', 'বাংলা বারো মাসের নাম ও ছয় ঋতু: সহজ তালিকা', 'The twelve Bengali months in order (বৈশাখ … চৈত্র), the six seasons and which months belong to each, the seven days of the week in Bengali, with the rough English months beside them (say that the exact start differs between Bangladesh and West Bengal).'],
        'somboddhon' => ['bhasha', 'list', 'attiyo-sombodhon', 'আত্মীয়দের বাংলা সম্বোধন', 'আত্মীয়দের বাংলা নাম: দাদু, ঠাকুমা, নানা, দাদি… কে কোন সম্পর্ক', 'Family relation words in Bengali (বাবার দিক, মায়ের দিক, শ্বশুরবাড়ি), each with who exactly it means (in Bengali, e.g. "মায়ের বাবা"); give both West Bengal and Bangladesh usage where they differ (দাদু/নানা, ঠাকুমা/দাদি, পিসি/ফুপু, মাসি/খালা, জল/পানি is not a relation: leave it out). At least 50 entries; example sentence of our own.'],
        'bornomala' => ['pathshala', 'guide', 'bangla-bornomala', 'বাংলা বর্ণমালা: স্বরবর্ণ ও ব্যঞ্জনবর্ণ', 'বাংলা বর্ণমালা: স্বরবর্ণ, ব্যঞ্জনবর্ণ, কার-চিহ্ন ও ফলা', 'The Bengali alphabet for children and parents (also families abroad): all স্বরবর্ণ and ব্যঞ্জনবর্ণ in order, the কার signs with examples (ক কা কি কী…), ফলা, and tips for parents teaching a child to read at home; one easy word for each letter.'],
        'juktakkhor' => ['bhasha', 'list', 'juktakkhor', 'বাংলা যুক্তাক্ষর: কোন অক্ষরে কী মিলে', 'বাংলা যুক্তাক্ষর তালিকা: কোন বর্ণ মিলে, উদাহরণসহ', 'Common Bengali conjunct letters (যুক্তবর্ণ): term = the conjunct (e.g. ক্ষ), meaning = which letters join (ক + ষ) and how it sounds, example = two common words using it. At least 60, most common first.'],
    ];

    /** Published three a week: Monday, Wednesday and Friday at 10am (India). */
    public const DAYS = [1, 3, 5];
}
