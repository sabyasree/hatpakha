<?php

/*
 * D317: বাংলা শিখি, a free starter for children and grown-ups who are learning to read Bengali (mostly families abroad).
 * Letters themselves are taught on /bornomala/ (D101); these lessons go on from there. Every example is an everyday word,
 * written to Bangla Academy spelling, with a plain Roman reading and the English meaning. Words that differ between the
 * two Bengals give both ("পশ্চিমবঙ্গে … / বাংলাদেশে …"). No sound yet (owner, 2026-11-27); a place for recordings later.
 *
 * Each lesson: [slug, Bengali title, English title, one-line intro, ...content]. Shapes are documented per lesson.
 */
return [
    'kar' => [
        'title' => 'কার: স্বরের চিহ্ন', 'seo_title' => 'বাংলা কার চিহ্ন ও বারোখড়ি: আ-কার থেকে ঔ-কার',
        'en' => 'Vowel signs (kar)',
        'intro' => 'ব্যঞ্জনবর্ণের সঙ্গে স্বর বসলে স্বরবর্ণটা পুরো লেখা হয় না, তার একটা ছোট চিহ্ন বসে। এই চিহ্নের নাম "কার"। অ-এর কোনো কার নেই: ক মানেই ক্ + অ, পড়া হয় "ko"। শব্দের শেষে এই অ প্রায়ই পড়া হয় না, যেমন কাক (kak)।',
        // [vowel, sign, name, roman name, where it sits (Bengali), example words [word, roman, english]]
        'rows' => [
            ['আ', 'া', 'আ-কার', 'a-kar', 'অক্ষরের ডানদিকে', [['মা', 'ma', 'mother'], ['বাবা', 'baba', 'father'], ['কাক', 'kak', 'crow']]],
            ['ই', 'ি', 'ই-কার', 'i-kar', 'অক্ষরের বাঁদিকে, লেখা হয় আগে কিন্তু পড়া হয় পরে', [['দিদি', 'didi', 'elder sister'], ['দিন', 'din', 'day'], ['কিল', 'kil', 'punch']]],
            ['ঈ', 'ী', 'ঈ-কার', 'ee-kar', 'অক্ষরের ডানদিকে', [['নদী', 'nodi', 'river'], ['দীপ', 'dip', 'lamp'], ['বীণা', 'bina', 'veena (a stringed instrument)']]],
            ['উ', 'ু', 'উ-কার', 'u-kar', 'অক্ষরের নিচে', [['ফুল', 'phul', 'flower'], ['কুকুর', 'kukur', 'dog'], ['পুতুল', 'putul', 'doll']]],
            ['ঊ', 'ূ', 'ঊ-কার', 'oo-kar', 'অক্ষরের নিচে', [['দূর', 'dur', 'far'], ['ভূত', 'bhut', 'ghost'], ['সূর্য', 'shurjo', 'sun']]],
            ['ঋ', 'ৃ', 'ঋ-কার', 'ri-kar', 'অক্ষরের নিচে', [['বৃষ্টি', 'brishti', 'rain'], ['কৃষক', 'krishok', 'farmer'], ['গৃহ', 'griho', 'home']]],
            ['এ', 'ে', 'এ-কার', 'e-kar', 'অক্ষরের বাঁদিকে', [['মেঘ', 'megh', 'cloud'], ['ছেলে', 'chhele', 'boy'], ['বেল', 'bel', 'wood apple']]],
            ['ঐ', 'ৈ', 'ঐ-কার', 'oi-kar', 'অক্ষরের বাঁদিকে', [['বৈশাখ', 'boishakh', 'Baishakh, the first Bengali month'], ['তৈরি', 'toiri', 'made; ready'], ['দৈত্য', 'doitto', 'giant']]],
            ['ও', 'ো', 'ও-কার', 'o-kar', 'দুই দিকে: বাঁদিকে এ-কার, ডানদিকে আ-কার', [['বোন', 'bon', 'sister'], ['খোকা', 'khoka', 'little boy'], ['ঘোড়া', 'ghora', 'horse']]],
            ['ঔ', 'ৌ', 'ঔ-কার', 'ou-kar', 'দুই দিকে: বাঁদিকে এ-কার, ডানদিকে একটা ছোট দাগ', [['নৌকা', 'nouka', 'boat'], ['মৌমাছি', 'moumachhi', 'honeybee'], ['পৌষ', 'poush', 'Poush, a winter month']]],
        ],
        // the consonants offered in the বারোখড়ি picker
        'barokhori' => ['ক', 'খ', 'গ', 'ঘ', 'চ', 'ছ', 'জ', 'ঝ', 'ট', 'ঠ', 'ড', 'ঢ', 'ত', 'থ', 'দ', 'ধ', 'ন', 'প', 'ফ', 'ব', 'ভ', 'ম', 'য', 'র', 'ল', 'শ', 'ষ', 'স', 'হ'],
        'notes' => [
            'র-এর সঙ্গে উ-কার আর ঊ-কার একটু অন্যরকম দেখায়: রু, রূ। হ-এর সঙ্গে উ-কার: হু।',
            'হ-এর সঙ্গে ঋ-কার: হৃ, যেমন হৃদয়।',
            'ই-কার আর ঈ-কার, উ-কার আর ঊ-কার উচ্চারণে এক; তফাত শুধু বানানে।',
        ],
    ],
    'phola' => [
        'title' => 'ফলা আর রেফ: চিহ্নের তালিকা', 'seo_title' => 'ফলা ও রেফ চিহ্নের তালিকা: ব-ফলা, য-ফলা, র-ফলা',
        'en' => 'Joined letter signs (phola and ref)',
        'intro' => 'কিছু ব্যঞ্জনবর্ণ অন্য অক্ষরের সঙ্গে জুড়লে ছোট চিহ্ন হয়ে যায়। নিচে-পাশে বসলে তার নাম "ফলা", মাথায় বসা র-এর নাম "রেফ"।',
        // [name, roman name, sign shown on ক, how it looks (Bengali), example words [word, roman, english]]
        'rows' => [
            ['য-ফলা', 'jo-phola', 'ক্য', 'অক্ষরের ডানদিকে একটা বাঁকা দাগ। আ-কারের সঙ্গে প্রায়ই "অ্যা" শোনায়। শব্দের মাঝে আগের অক্ষরটা দুবার পড়া হয়, যেমন সত্য (shotto)।', [['ব্যাঙ', 'byang', 'frog'], ['খ্যাতি', 'khyati', 'fame'], ['ব্যথা', 'byatha', 'pain']]],
            ['র-ফলা', 'ro-phola', 'ক্র', 'অক্ষরের নিচে একটা ছোট বাঁকা দাগ।', [['প্রথম', 'prothom', 'first'], ['গ্রাম', 'gram', 'village'], ['চক্র', 'chokro', 'wheel']]],
            ['ব-ফলা', 'bo-phola', 'ক্ব', 'অক্ষরের নিচে ছোট একটা ব। শব্দের মাঝে আগের অক্ষরটা দুবার পড়া হয়; শুরুতে প্রায় শোনা যায় না।', [['বিশ্ব', 'bishsho', 'world'], ['স্বপ্ন', 'shopno', 'dream'], ['দ্বার', 'dar', 'door, gate']]],
            ['ম-ফলা', 'mo-phola', 'ক্ম', 'অক্ষরের নিচে ছোট একটা ম। শব্দের মাঝে প্রায়ই আগের অক্ষরটা নাকের সুরে দুবার পড়া হয়; শুরুতে ম প্রায় শোনা যায় না, যেমন স্মৃতি (sriti)।', [['পদ্ম', 'poddo', 'lotus'], ['আত্মা', 'atta', 'soul'], ['স্মৃতি', 'sriti', 'memory']]],
            ['ল-ফলা', 'lo-phola', 'ক্ল', 'অক্ষরের নিচে ছোট একটা ল।', [['ক্লান্ত', 'klanto', 'tired'], ['বিপ্লব', 'biplob', 'revolution'], ['অম্ল', 'omlo', 'sour']]],
            ['রেফ', 'ref', 'র্ক', 'পরের অক্ষরের মাথায় একটা ছোট দাগ। আগে পড়া হয় র, তারপর অক্ষরটা।', [['কর্ম', 'kormo', 'work, deed'], ['বর্ষা', 'borsha', 'the rainy season'], ['সূর্য', 'shurjo', 'sun']]],
        ],
    ],
    'juktakkhor' => [
        'title' => 'যুক্তাক্ষরের তালিকা', 'seo_title' => 'যুক্তাক্ষর তালিকা: উদাহরণ ও উচ্চারণসহ',
        'en' => 'Conjunct letters (juktakkhor)',
        'intro' => 'দুটো বা তিনটে ব্যঞ্জনবর্ণ পাশাপাশি জুড়ে গেলে হয় যুক্তাক্ষর (বাংলাদেশে বেশি বলা হয় যুক্তবর্ণ)। কোনোটায় দুটো অক্ষরই চেনা যায়, কোনোটা দেখতে একেবারে নতুন। রোজকার লেখায় যেগুলো সবচেয়ে বেশি আসে, সেগুলো দিয়ে শুরু করা ভালো।',
        // [conjunct, made of, example [word, roman, english]]
        'rows' => [
            ['ক্ত', 'ক + ত', ['রক্ত', 'rokto', 'blood']], ['ক্ষ', 'ক + ষ', ['ক্ষমা', 'khoma', 'forgiveness']], ['ক্স', 'ক + স', ['বাক্স', 'bakso', 'box']],
            ['গ্ন', 'গ + ন', ['অগ্নি', 'ogni', 'fire']], ['ঙ্ক', 'ঙ + ক', ['অঙ্ক', 'ongko', 'arithmetic']], ['ঙ্গ', 'ঙ + গ', ['বঙ্গ', 'bongo', 'Bengal']],
            ['চ্চ', 'চ + চ', ['উচ্চ', 'uchcho', 'high']], ['চ্ছ', 'চ + ছ', ['ইচ্ছা', 'ichchha', 'wish']], ['জ্জ', 'জ + জ', ['লজ্জা', 'lojja', 'shame, shyness']],
            ['জ্ঞ', 'জ + ঞ', ['জ্ঞান', 'gyan', 'knowledge']], ['ঞ্চ', 'ঞ + চ', ['পঞ্চাশ', 'ponchash', 'fifty']], ['ঞ্জ', 'ঞ + জ', ['গঞ্জ', 'gonj', 'market town']],
            ['ট্ট', 'ট + ট', ['চট্টগ্রাম', 'chottogram', 'Chattogram (a city)']], ['ণ্ট', 'ণ + ট', ['ঘণ্টা', 'ghonta', 'bell; hour']], ['ণ্ড', 'ণ + ড', ['খণ্ড', 'khondo', 'piece, part']],
            ['ত্ত', 'ত + ত', ['উত্তর', 'uttor', 'answer; north']], ['ত্ম', 'ত + ম', ['মহাত্মা', 'mohatta', 'great soul, Mahatma']], ['দ্দ', 'দ + দ', ['উদ্দেশ্য', 'uddeshsho', 'purpose']],
            ['দ্ধ', 'দ + ধ', ['যুদ্ধ', 'juddho', 'war']], ['দ্ভ', 'দ + ভ', ['অদ্ভুত', 'odbhut', 'strange']], ['ন্ত', 'ন + ত', ['শান্ত', 'shanto', 'calm']],
            ['ন্দ', 'ন + দ', ['আনন্দ', 'anondo', 'joy']], ['ন্ধ', 'ন + ধ', ['বন্ধু', 'bondhu', 'friend']], ['ন্ন', 'ন + ন', ['অন্ন', 'onno', 'cooked rice, food']],
            ['প্ত', 'প + ত', ['সপ্তাহ', 'shoptaho', 'week']], ['ব্দ', 'ব + দ', ['শব্দ', 'shobdo', 'word; sound']], ['ম্প', 'ম + প', ['কম্পন', 'kompon', 'trembling']],
            ['ম্ব', 'ম + ব', ['লম্বা', 'lomba', 'long, tall']], ['ল্প', 'ল + প', ['গল্প', 'golpo', 'story']], ['ল্ল', 'ল + ল', ['উল্লাস', 'ullash', 'delight']],
            ['শ্চ', 'শ + চ', ['পশ্চিম', 'poshchim', 'west']], ['ষ্ট', 'ষ + ট', ['কষ্ট', 'koshto', 'hardship']], ['ষ্ঠ', 'ষ + ঠ', ['ষষ্ঠ', 'shoshtho', 'sixth']],
            ['ষ্ণ', 'ষ + ণ', ['কৃষ্ণ', 'krishno', 'Krishna; dark']], ['স্ত', 'স + ত', ['বাস্তব', 'bastob', 'real']], ['স্থ', 'স + থ', ['স্থান', 'sthan', 'place']],
            ['হ্ন', 'হ + ন', ['চিহ্ন', 'chinho', 'sign, mark']], ['হ্ম', 'হ + ম', ['ব্রহ্মপুত্র', 'brommhoputro', 'the Brahmaputra river']],
            ['ন্ত্র', 'ন + ত + র', ['মন্ত্র', 'montro', 'mantra, chant']], ['ক্ষ্ম', 'ক + ষ + ম', ['লক্ষ্মী', 'lokkhi', 'Lakshmi; a good, gentle child']],
        ],
        'notes' => [
            'ক্ষ দেখতে নতুন, কিন্তু তৈরি ক আর ষ দিয়ে। শব্দের শুরুতে পড়া হয় "খ"-এর মতো।',
            'জ্ঞ-ও তেমন: জ আর ঞ দিয়ে তৈরি, শব্দের শুরুতে পড়া হয় "গ্য"-এর মতো, যেমন জ্ঞান (gyan)।',
            'হ্ন আর হ্ম-তে আগে ন বা ম, পরে হ শোনা যায়, যেমন চিহ্ন (chinho), ব্রহ্মপুত্র (brommhoputro)।',
        ],
    ],
    'first-words' => [
        'title' => 'প্রথম শব্দ আর ছোট বাক্য',
        'en' => 'First words and sentences',
        'intro' => 'বাড়িতে রোজ যে কথাগুলো বলা হয়, সেগুলো দিয়েই পড়া শুরু হোক। দুই বাংলায় যেখানে শব্দ আলাদা, দুটোই দেওয়া আছে।',
        // groups: [heading, [[word, roman, english, note]]]
        'groups' => [
            ['পরিবার', [['মা', 'ma', 'mother', ''], ['বাবা', 'baba', 'father', ''], ['দাদা', 'dada', 'elder brother; in Bangladeshi Muslim families, father\'s father', 'বাংলাদেশে মুসলমান পরিবারে দাদা মানে বাবার বাবা; বড় ভাই সেখানে ভাইয়া'], ['দিদি', 'didi', 'elder sister', 'বাংলাদেশে আর অনেক মুসলমান পরিবারে আপা, আপু'],
                ['ঠাকুমা', 'thakuma', 'father\'s mother', 'বাংলাদেশে আর অনেক মুসলমান পরিবারে দাদি'], ['দিদিমা', 'didima', 'mother\'s mother', 'বাংলাদেশে আর অনেক মুসলমান পরিবারে নানি'], ['দাদু', 'dadu', 'grandfather (father\'s or mother\'s father)', 'বাংলাদেশে বাবার বাবা দাদা, মায়ের বাবা নানা'], ['মামা', 'mama', 'mother\'s brother', ''], ['কাকা', 'kaka', 'father\'s younger brother', 'বাংলাদেশে আর অনেক মুসলমান পরিবারে চাচা']]],
            ['রং', [['লাল', 'lal', 'red', ''], ['নীল', 'nil', 'blue', ''], ['সবুজ', 'sobuj', 'green', ''], ['হলুদ', 'holud', 'yellow', ''], ['সাদা', 'shada', 'white', ''], ['কালো', 'kalo', 'black', '']]],
            ['শরীর', [['চোখ', 'chokh', 'eye', ''], ['কান', 'kan', 'ear', ''], ['নাক', 'nak', 'nose', ''], ['মুখ', 'mukh', 'face; mouth', ''], ['হাত', 'hat', 'hand', ''], ['পা', 'pa', 'leg, foot', '']]],
            ['খাবার', [['ভাত', 'bhat', 'cooked rice', ''], ['ডাল', 'dal', 'lentils', ''], ['মাছ', 'machh', 'fish', ''], ['দুধ', 'dudh', 'milk', ''], ['জল', 'jol', 'water', 'বাংলাদেশে আর অনেক মুসলমান পরিবারে পানি'], ['রুটি', 'ruti', 'flatbread', '']]],
            ['সংখ্যা', [['এক ১', 'ek', 'one', ''], ['দুই ২', 'dui', 'two', ''], ['তিন ৩', 'tin', 'three', ''], ['চার ৪', 'char', 'four', ''], ['পাঁচ ৫', 'panch', 'five', ''],
                ['ছয় ৬', 'chhoy', 'six', ''], ['সাত ৭', 'shat', 'seven', ''], ['আট ৮', 'at', 'eight', ''], ['নয় ৯', 'noy', 'nine', ''], ['দশ ১০', 'dosh', 'ten', '']]],
            ['সপ্তাহের দিন', [['রবিবার', 'robibar', 'Sunday', ''], ['সোমবার', 'shombar', 'Monday', ''], ['মঙ্গলবার', 'mongolbar', 'Tuesday', ''], ['বুধবার', 'budhbar', 'Wednesday', ''],
                ['বৃহস্পতিবার', 'brihoshpotibar', 'Thursday', ''], ['শুক্রবার', 'shukrobar', 'Friday', ''], ['শনিবার', 'shonibar', 'Saturday', '']]],
        ],
        // [sentence, roman, english]
        'sentences' => [
            ['আমার নাম মিঠু।', 'amar nam mithu', 'My name is Mithu.'],
            ['তুমি কেমন আছ?', 'tumi kemon achho?', 'How are you?'],
            ['আমি ভালো আছি।', 'ami bhalo achhi', 'I am well.'],
            ['আমার খিদে পেয়েছে।', 'amar khide peyechhe', 'I am hungry.'],
            ['এটা কী?', 'eta ki?', 'What is this?'],
            ['আমি তোমাকে ভালোবাসি।', 'ami tomake bhalobashi', 'I love you.'],
            ['ধন্যবাদ।', 'dhonnobad', 'Thank you.'],
            ['আবার দেখা হবে।', 'abar dekha hobe', 'See you again.'],
        ],
    ],
];
