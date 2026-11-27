<?php

/*
 * D321: বাংলা শিখি, the course (owner, 2026-11-27: "plan approved … build phase 1 to 3"). docs/LEARN-BENGALI.md.
 *
 * Fixed content, reviewed in the pull request (never written by the AI job). Letters come a few at a time, the most
 * useful first, and every word of a lesson uses only what has been taught by then (LearnCourseTest checks every word).
 *
 * unit:   slug (English words in the address, D314) => title, en, intro (for parents), tip [bn, en], sticker [emoji, name], lessons
 * lesson: title, new (letters, signs or joined letters taught here), words [word, roman, english, picture],
 *         sentences [sentence, roman, english, picture] (optional), pairs (look-alikes, optional), story (unit 14)
 * Pictures are emoji (they draw on every phone without loading anything); '' when a word has no clear picture.
 * Words that differ between families or the two Bengals are left for the reference pages (/janun/learn-bengali/first-words/).
 */
return [
    // How each letter or sign is said, for parents who read little Bengali (plain Roman letters).
    'sounds' => [
        'অ' => 'o', 'আ' => 'a', 'ই' => 'i', 'ঈ' => 'i (long ই)', 'উ' => 'u', 'ঊ' => 'u (long উ)', 'ঋ' => 'ri', 'এ' => 'e', 'ঐ' => 'oi', 'ও' => 'o', 'ঔ' => 'ou',
        'ক' => 'ko', 'খ' => 'kho', 'গ' => 'go', 'ঘ' => 'gho', 'ঙ' => 'ng (umo)', 'চ' => 'cho', 'ছ' => 'chho', 'জ' => 'jo', 'ঝ' => 'jho', 'ঞ' => 'nyo (inside joined letters)',
        'ট' => 'to (hard t)', 'ঠ' => 'tho (hard th)', 'ড' => 'do (hard d)', 'ঢ' => 'dho (hard dh)', 'ণ' => 'no (murdhonyo n)',
        'ত' => 'to (soft t)', 'থ' => 'tho (soft th)', 'দ' => 'do (soft d)', 'ধ' => 'dho (soft dh)', 'ন' => 'no',
        'প' => 'po', 'ফ' => 'pho / fo', 'ব' => 'bo', 'ভ' => 'bho', 'ম' => 'mo', 'য' => 'jo (antostho jo)', 'র' => 'ro', 'ল' => 'lo',
        'শ' => 'sho', 'ষ' => 'sho (murdhonyo sh)', 'স' => 'sho / so', 'হ' => 'ho', 'ড়' => 'ro (flapped r)', 'ঢ়' => 'rho', 'য়' => 'yo (antostho yo)',
        'ং' => 'ng (onushshar)', 'ঃ' => 'h, doubles the next letter (bishorgo)', 'ঁ' => 'nasal sound (chondrobindu)', 'ৎ' => 't at the end (khondo to)',
        'া' => 'a (a-kar)', 'ি' => 'i (i-kar)', 'ী' => 'i (long i-kar)', 'ু' => 'u (u-kar)', 'ূ' => 'u (long u-kar)', 'ৃ' => 'ri (ri-kar)',
        'ে' => 'e (e-kar)', 'ৈ' => 'oi (oi-kar)', 'ো' => 'o (o-kar)', 'ৌ' => 'ou (ou-kar)',
        '্র' => 'ro-phola: r after the letter', '্য' => 'jo-phola: doubles the letter in the middle of a word, "æ" with a-kar', '্ব' => 'bo-phola: doubles the letter in the middle of a word',
        '্ম' => 'mo-phola: doubles the letter, often silent at the start', '্ল' => 'lo-phola: l after the letter', 'র্' => 'ref: r before the letter',
    ],

    // D322: for learners who do not read Bengali (parents, foreigners): each sound compared with English. Approximate by nature;
    // shown on /janun/learn-bengali/english/ and in the grown-ups' sheet of every lesson. [roman, how it sounds]
    'sounds_en' => [
        'vowels' => [
            'অ' => ['o', 'between "o" in "hot" (British) and "aw" in "saw", short. Every consonant carries this sound unless a sign says otherwise.'],
            'আ' => ['a', '"a" in "father"'],
            'ই' => ['i', '"ee" in "see", but short'],
            'ঈ' => ['i', 'the same as ই today; only the spelling differs'],
            'উ' => ['u', '"oo" in "food", but short'],
            'ঊ' => ['u', 'the same as উ today; only the spelling differs'],
            'ঋ' => ['ri', '"ri" in "river"'],
            'এ' => ['e', '"e" in "bed"; in some words "a" in "cat"'],
            'ঐ' => ['oi', '"oi" in "coin"'],
            'ও' => ['o', '"o" in "go", but pure, without sliding into "w"'],
            'ঔ' => ['ou', '"o" then "u" run together, like "ou" in "soul"'],
        ],
        'consonants' => [
            'ক' => ['k', '"k" in "skin" (no puff of air)'], 'খ' => ['kh', '"k" with a strong puff of air'], 'গ' => ['g', '"g" in "go"'], 'ঘ' => ['gh', '"g" with a breath after it, as in "dog-house" said quickly'], 'ঙ' => ['ng', '"ng" in "sing"'],
            'চ' => ['ch', '"ch" in "church", without much breath'], 'ছ' => ['chh', '"ch" with a strong puff of air'], 'জ' => ['j', '"j" in "jam"'], 'ঝ' => ['jh', '"j" with a breath after it, as in "hedge-hog" said quickly'], 'ঞ' => ['n', '"n" in "pinch"; only inside joined letters'],
            'ট' => ['t', 'a hard "t": the tip of the tongue curled back against the roof of the mouth'], 'ঠ' => ['th', 'the same hard "t" with a puff of air (never the "th" of "thin")'], 'ড' => ['d', 'a hard "d", tongue curled back'], 'ঢ' => ['dh', 'the same hard "d" with a breath'], 'ণ' => ['n', '"n"; today said like ন'],
            'ত' => ['t', 'a soft "t": the tongue touches the back of the top teeth, as in Spanish or French'], 'থ' => ['th', 'the same soft "t" with a puff of air (never the "th" of "the")'], 'দ' => ['d', 'a soft "d", tongue on the teeth; close to "th" in "this"'], 'ধ' => ['dh', 'the same soft "d" with a breath'], 'ন' => ['n', '"n" in "net"'],
            'প' => ['p', '"p" in "spin" (no puff of air)'], 'ফ' => ['ph / f', '"p" with a puff of air; many people say "f"'], 'ব' => ['b', '"b" in "bat"'], 'ভ' => ['bh', '"b" with a breath, as in "club-house" said quickly; often close to "v"'], 'ম' => ['m', '"m" in "mat"'],
            'য' => ['j', '"j" in "jam" (the same sound as জ)'], 'র' => ['r', 'a light tapped "r", as in Spanish "pero"'], 'ল' => ['l', '"l" in "leaf"'],
            'শ' => ['sh', '"sh" in "shop"'], 'ষ' => ['sh', '"sh" in "shop" (the same sound as শ)'], 'স' => ['sh / s', 'usually "sh"; "s" in some joined letters (স্ত, স্ক)'], 'হ' => ['h', '"h" in "hat"'],
            'ড়' => ['r', 'a flapped "r" with the tongue curled back; note the dot under it'], 'ঢ়' => ['rh', 'the same flapped "r" with a breath'], 'য়' => ['y', '"y" in "yes"; between vowels often hardly heard'],
        ],
        'signs' => [
            'ং' => ['ng', '"ng" in "sing": রং = rong'], 'ঃ' => ['h', 'a short breath; inside a word it doubles the next letter: দুঃখ = dukkho'], 'ঁ' => ['~', 'makes the vowel nasal, as in French "bon": চাঁদ = chand'], 'ৎ' => ['t', 'a "t" with no vowel after it, at the end of a word: হঠাৎ = hothat'],
        ],
    ],

    'units' => [
        'first-letters' => [
            'title' => 'প্রথম অক্ষর', 'en' => 'First letters',
            'intro' => 'সাতটা চেনা অক্ষর আর আ-কার। প্রথম পাঠ থেকেই পড়া যায়: মা, বাবা, কাকা।',
            'tip' => ['bn' => 'অক্ষরটা দেখিয়ে জোরে বলুন, তারপর শিশুকে বলতে দিন। একদিনে একটা পাঠই যথেষ্ট।', 'en' => 'Point at the letter and say it aloud, then let your child say it. One lesson a day is plenty.'],
            'sticker' => ['🐟', 'ইলিশ'],
            'lessons' => [
                ['title' => 'ম আর আ-কার', 'new' => ['ম', 'া'], 'words' => [['মা', 'ma', 'mother', '👩'], ['মামা', 'mama', 'mother\'s brother', '👨']]],
                ['title' => 'ব', 'new' => ['ব'], 'words' => [['বাবা', 'baba', 'father', '👨'], ['মা', 'ma', 'mother', '👩'], ['মামা', 'mama', 'mother\'s brother', '']]],
                ['title' => 'ক', 'new' => ['ক'], 'words' => [['কাক', 'kak', 'crow', '🐦'], ['কাকা', 'kaka', 'father\'s younger brother', '']]],
                ['title' => 'ল', 'new' => ['ল'], 'words' => [['লাল', 'lal', 'red', '🔴'], ['মালা', 'mala', 'garland', '📿'], ['কাল', 'kal', 'tomorrow; yesterday', ''], ['বালা', 'bala', 'bangle', '']]],
                ['title' => 'ন', 'new' => ['ন'], 'words' => [['নাক', 'nak', 'nose', '👃'], ['কান', 'kan', 'ear', '👂'], ['নাম', 'nam', 'name', '']]],
                ['title' => 'প আর ত', 'new' => ['প', 'ত'], 'words' => [['পাতা', 'pata', 'leaf', '🍃'], ['তাল', 'tal', 'palm fruit', '🌴'], ['তাপ', 'tap', 'heat', '🔥'], ['পাকা', 'paka', 'ripe', '']]],
            ],
        ],
        'letters-2' => [
            'title' => 'আরও অক্ষর ১', 'en' => 'More letters 1',
            'intro' => 'র, স, দ, জ, হ, চ, গ, ট। প্রতিটির সঙ্গে আ-কার দিয়ে নতুন শব্দ।',
            'tip' => ['bn' => 'শব্দটা পড়ার আগে আঙুল দিয়ে প্রতিটি অক্ষর ছুঁয়ে বলুক, তারপর একসঙ্গে।', 'en' => 'Before reading a word, let your child touch each letter and say it, then say the whole word.'],
            'sticker' => ['🐦', 'দোয়েল'],
            'lessons' => [
                ['title' => 'র', 'new' => ['র'], 'words' => [['রাত', 'rat', 'night', '🌙'], ['তারা', 'tara', 'star', '⭐'], ['বার', 'bar', 'day of the week; time', '']], 'pairs' => [['ব', 'র']]],
                ['title' => 'স', 'new' => ['স'], 'words' => [['সাপ', 'shap', 'snake', '🐍'], ['সাত', 'shat', 'seven', '7️⃣'], ['মাস', 'mash', 'month', '📅']]],
                ['title' => 'দ', 'new' => ['দ'], 'words' => [['দাদা', 'dada', 'elder brother', '👦'], ['বাদাম', 'badam', 'peanut', '🥜'], ['দাম', 'dam', 'price', '🏷️']]],
                ['title' => 'জ', 'new' => ['জ'], 'words' => [['রাজা', 'raja', 'king', '🤴'], ['জাল', 'jal', 'net', '🥅'], ['কাজ', 'kaj', 'work', '']]],
                ['title' => 'হ', 'new' => ['হ'], 'words' => [['হাত', 'hat', 'hand', '✋'], ['হাল', 'hal', 'plough; rudder', ''], ['হার', 'har', 'necklace', '']]],
                ['title' => 'চ আর গ', 'new' => ['চ', 'গ'], 'words' => [['চা', 'cha', 'tea', '☕'], ['চাকা', 'chaka', 'wheel', '🛞'], ['গান', 'gan', 'song', '🎵'], ['বাগান', 'bagan', 'garden', '🌳']]],
                ['title' => 'ট', 'new' => ['ট'], 'words' => [['টাকা', 'taka', 'money; taka', '💵'], ['টান', 'tan', 'pull', ''], ['টাটকা', 'tatka', 'fresh', '']]],
            ],
        ],
        'letters-3' => [
            'title' => 'আরও অক্ষর ২', 'en' => 'More letters 2',
            'intro' => 'ছ, খ, ঘ, ড, ভ, শ, থ, ধ। মাছ, বাঘ, ভাত: রোজকার শব্দ।',
            'tip' => ['bn' => 'রান্নাঘরে বা বাজারে চেনা জিনিস দেখিয়ে জিজ্ঞেস করুন: এটা কোন অক্ষর দিয়ে শুরু?', 'en' => 'In the kitchen or at the shop, point at things and ask: which letter does this start with?'],
            'sticker' => ['🪔', 'প্রদীপ'],
            'lessons' => [
                ['title' => 'ছ', 'new' => ['ছ'], 'words' => [['মাছ', 'machh', 'fish', '🐟'], ['ছাতা', 'chhata', 'umbrella', '☂️'], ['ছাদ', 'chhad', 'roof', '']]],
                ['title' => 'খ', 'new' => ['খ'], 'words' => [['খাতা', 'khata', 'exercise book', '📒'], ['খাবার', 'khabar', 'food', '🍲'], ['খাল', 'khal', 'canal', '']]],
                ['title' => 'ঘ', 'new' => ['ঘ'], 'words' => [['বাঘ', 'bagh', 'tiger', '🐯'], ['ঘাস', 'ghash', 'grass', '🌿'], ['ঘাম', 'gham', 'sweat', '💦']]],
                ['title' => 'ড', 'new' => ['ড'], 'words' => [['ডাব', 'dab', 'green coconut', '🥥'], ['ডাক', 'dak', 'call; post', '📮'], ['ডাল', 'dal', 'lentils; branch', '']]],
                ['title' => 'ভ', 'new' => ['ভ'], 'words' => [['ভাত', 'bhat', 'cooked rice', '🍚'], ['ভাগ', 'bhag', 'share', ''], ['ভাপা', 'bhapa', 'steamed', '']]],
                ['title' => 'শ', 'new' => ['শ'], 'words' => [['শাক', 'shak', 'leafy greens', '🥬'], ['শাল', 'shal', 'shawl', '🧣'], ['শাপলা', 'shapla', 'water lily', '🪷']]],
                ['title' => 'থ আর ধ', 'new' => ['থ', 'ধ'], 'words' => [['থালা', 'thala', 'plate', '🍽️'], ['ধান', 'dhan', 'rice plant', '🌾'], ['ধার', 'dhar', 'edge; loan', '']]],
            ],
        ],
        'letters-4' => [
            'title' => 'শেষ অক্ষরগুলো', 'en' => 'The last letters',
            'intro' => 'ফ, ড়, য, য়, ঝ, ঠ, ঢ, ঢ়, ষ, ণ, ঙ। দেখতে কাছাকাছি অক্ষরগুলো জোড়ায় জোড়ায়।',
            'tip' => ['bn' => 'ড আর ড়, য আর য়: নিচের ফুটকিটা দেখিয়ে দিন। ফুটকি থাকলে উচ্চারণ বদলায়।', 'en' => 'ড and ড়, য and য়: show the dot underneath. The dot changes the sound.'],
            'sticker' => ['🦚', 'ময়ূর'],
            'lessons' => [
                ['title' => 'ফ', 'new' => ['ফ'], 'words' => [['ফাগ', 'phag', 'coloured powder', '🎨'], ['ফাটা', 'phata', 'cracked', ''], ['সাফ', 'shaf', 'clean', '']]],
                ['title' => 'ড়', 'new' => ['ড়'], 'words' => [['হাড়', 'har', 'bone', '🦴'], ['পাড়া', 'para', 'neighbourhood', '🏘️'], ['ঘাড়', 'ghar', 'neck', '']], 'pairs' => [['ড', 'ড়']]],
                ['title' => 'য আর য়', 'new' => ['য', 'য়'], 'words' => [['পায়রা', 'payra', 'pigeon', '🕊️'], ['ছায়া', 'chhaya', 'shade; shadow', ''], ['মায়া', 'maya', 'affection', ''], ['যা', 'ja', 'go!', '']], 'pairs' => [['য', 'য়']]],
                ['title' => 'ঝ আর ঠ', 'new' => ['ঝ', 'ঠ'], 'words' => [['ঝাল', 'jhal', 'hot and spicy', '🌶️'], ['কাঠ', 'kath', 'wood', '🪵'], ['মাঠ', 'math', 'field', ''], ['পাঠ', 'path', 'lesson', '']]],
                ['title' => 'ঢ, ঢ়, ষ, ণ', 'new' => ['ঢ', 'ঢ়', 'ষ', 'ণ'], 'words' => [['ঢাক', 'dhak', 'dhak drum', '🥁'], ['বাণ', 'ban', 'arrow', '🏹'], ['ভাষা', 'bhasha', 'language', ''], ['গাঢ়', 'garho', 'deep (colour)', '']], 'pairs' => [['ঢ', 'ঢ়']]],
                ['title' => 'ঙ', 'new' => ['ঙ'], 'words' => [['রাঙা', 'ranga', 'red; colourful', ''], ['ভাঙা', 'bhanga', 'broken', '']]],
            ],
        ],
        'blending' => [
            'title' => 'লুকোনো অ', 'en' => 'The hidden vowel',
            'intro' => 'কোনো চিহ্ন না থাকলে অক্ষরের সঙ্গে "অ" লুকিয়ে থাকে: ক মানে "কো"। কলম পড়া হয় "কলোম"। শব্দের শেষে এই অ প্রায়ই চুপ।',
            'tip' => ['bn' => 'কলম = ক-ল-ম: "কো-লোম"। ধীরে টেনে বলুন, তারপর তাড়াতাড়ি। শেষের অক্ষরে প্রায়ই অ বলা হয় না।', 'en' => 'কলম = ko-lom. Say it slowly, then quickly. The last letter usually drops its "o".'],
            'sticker' => ['🌳', 'বটগাছ'],
            'lessons' => [
                ['title' => 'দুই অক্ষরের শব্দ', 'new' => [], 'words' => [['জল', 'jol', 'water', '💧'], ['ফল', 'phol', 'fruit', '🍎'], ['বল', 'bol', 'ball', '⚽'], ['ঘর', 'ghor', 'room; house', '🏠'], ['বন', 'bon', 'forest', '🌳'], ['মন', 'mon', 'mind', '']]],
                ['title' => 'তিন অক্ষরের শব্দ', 'new' => [], 'words' => [['কলম', 'kolom', 'pen', '🖊️'], ['গরম', 'gorom', 'hot', '🔥'], ['শহর', 'shohor', 'city', '🏙️'], ['নরম', 'norom', 'soft', ''], ['সহজ', 'shohoj', 'easy', '']]],
                ['title' => 'শেষের অ: কখনো শোনা যায়', 'new' => [], 'words' => [['বড়', 'boro', 'big', ''], ['কত', 'koto', 'how many', ''], ['দশ', 'dosh', 'ten', '🔟'], ['ভয়', 'bhoy', 'fear', '😨'], ['নয়', 'noy', 'nine', '9️⃣']]],
                ['title' => 'প্রথম বাক্য', 'new' => [], 'words' => [['জল', 'jol', 'water', '💧'], ['ঘর', 'ghor', 'room; house', '🏠'], ['কলম', 'kolom', 'pen', '🖊️'], ['বাঘ', 'bagh', 'tiger', '🐯']],
                    'sentences' => [['জল গরম।', 'jol gorom.', 'The water is hot.', '💧'], ['ঘর বড়।', 'ghor boro.', 'The room is big.', '🏠'], ['কলম লাল।', 'kolom lal.', 'The pen is red.', '🖊️'], ['বাঘ বড়।', 'bagh boro.', 'The tiger is big.', '🐯']]],
            ],
        ],
        'kar-1' => [
            'title' => 'ই-কার আর ঈ-কার', 'en' => 'Vowel signs: i-kar and long i-kar',
            'intro' => 'ই-কার বসে অক্ষরের আগে, কিন্তু পড়া হয় পরে। ঈ-কার বসে পরে। দুটোর উচ্চারণ এক।',
            'tip' => ['bn' => 'ই-কার লেখা হয় বাঁদিকে, কিন্তু পড়তে হয় অক্ষরের পরে: দি = দ + ই। এটাই প্রথমে গোলমাল লাগে।', 'en' => 'ি is written on the left but said after the letter: দি = d + i. This is the first thing that confuses children.'],
            'sticker' => ['🐘', 'হাতি'],
            'lessons' => [
                ['title' => 'ই-কার', 'new' => ['ি'], 'words' => [['দিদি', 'didi', 'elder sister', '👧'], ['পাখি', 'pakhi', 'bird', '🐦'], ['বাড়ি', 'bari', 'home; house', '🏡'], ['হাসি', 'hashi', 'smile', '😊']]],
                ['title' => 'আরও ই-কার', 'new' => [], 'words' => [['হাতি', 'hati', 'elephant', '🐘'], ['মাছি', 'machhi', 'housefly', '🪰'], ['ঘড়ি', 'ghori', 'clock; watch', '⏰'], ['তিন', 'tin', 'three', '3️⃣']]],
                ['title' => 'ঈ-কার', 'new' => ['ী'], 'words' => [['নদী', 'nodi', 'river', '🏞️'], ['নীল', 'nil', 'blue', '🔵'], ['দীপ', 'dip', 'lamp', '🪔'], ['জীবন', 'jibon', 'life', '']]],
                ['title' => 'ই-কার না ঈ-কার?', 'new' => [], 'words' => [['পাখি', 'pakhi', 'bird', '🐦'], ['নদী', 'nodi', 'river', '🏞️'], ['হাতি', 'hati', 'elephant', '🐘'], ['নীল', 'nil', 'blue', '🔵'], ['ঘড়ি', 'ghori', 'clock; watch', '⏰']]],
            ],
        ],
        'kar-2' => [
            'title' => 'উ-কার, ঊ-কার, ঋ-কার', 'en' => 'Vowel signs: u-kar, long u-kar, ri-kar',
            'intro' => 'তিনটে চিহ্নই বসে অক্ষরের নিচে। র আর হ-এর সঙ্গে চেহারা একটু বদলায়: রু, রূ, হু, হৃ।',
            'tip' => ['bn' => 'উ-কার আর ঊ-কারের উচ্চারণ এক; তফাত শুধু বানানে। শব্দ ধরে মনে রাখাই সহজ।', 'en' => 'ু and ূ sound the same; only the spelling differs. It is easiest to remember them word by word.'],
            'sticker' => ['🦉', 'পেঁচা'],
            'lessons' => [
                ['title' => 'উ-কার', 'new' => ['ু'], 'words' => [['ফুল', 'phul', 'flower', '🌼'], ['কুকুর', 'kukur', 'dog', '🐕'], ['পুতুল', 'putul', 'doll', '🪆'], ['চুল', 'chul', 'hair', '']]],
                ['title' => 'ঊ-কার', 'new' => ['ূ'], 'words' => [['ভূত', 'bhut', 'ghost', '👻'], ['দূর', 'dur', 'far', ''], ['মূলা', 'mula', 'radish', ''], ['কূল', 'kul', 'shore', '']]],
                ['title' => 'ঋ-কার', 'new' => ['ৃ'], 'words' => [['কৃষক', 'krishok', 'farmer', '🧑‍🌾'], ['হৃদয়', 'hridoy', 'heart', '❤️'], ['গৃহ', 'griho', 'home', ''], ['তৃণ', 'trin', 'grass', '']]],
                ['title' => 'রু, রূ, হু', 'new' => [], 'words' => [['রুটি', 'ruti', 'flatbread', '🫓'], ['শুরু', 'shuru', 'start', ''], ['রূপ', 'rup', 'beauty', ''], ['গুড়', 'gur', 'jaggery', ''], ['বহু', 'bohu', 'many', '']]],
            ],
        ],
        'kar-3' => [
            'title' => 'এ-কার, ও-কার, ঐ-কার, ঔ-কার', 'en' => 'Vowel signs: e-kar, o-kar, oi-kar, ou-kar',
            'intro' => 'এ-কার বসে আগে। ও-কার আর ঔ-কার বসে দুই দিকে, অক্ষরটাকে ঘিরে।',
            'tip' => ['bn' => 'ও-কার: বাঁদিকে এ-কার, ডানদিকে আ-কার, মাঝে অক্ষর। "কো" = ক-কে দুদিক থেকে ধরে আছে।', 'en' => 'ো sits on both sides: an এ-কার on the left and an আ-কার on the right, with the letter in between.'],
            'sticker' => ['⛵', 'নৌকা'],
            'lessons' => [
                ['title' => 'এ-কার', 'new' => ['ে'], 'words' => [['মেঘ', 'megh', 'cloud', '☁️'], ['ছেলে', 'chhele', 'boy', '👦'], ['মেয়ে', 'meye', 'girl', '👧'], ['দেশ', 'desh', 'country', '']]],
                ['title' => 'ও-কার', 'new' => ['ো'], 'words' => [['ঘোড়া', 'ghora', 'horse', '🐎'], ['মোজা', 'moja', 'sock', '🧦'], ['দোকান', 'dokan', 'shop', '🏪'], ['খোকা', 'khoka', 'little boy', '👶'], ['বোন', 'bon', 'sister', '']]],
                ['title' => 'ঐ-কার', 'new' => ['ৈ'], 'words' => [['বৈশাখ', 'boishakh', 'Baishakh, the first month', ''], ['তৈরি', 'toiri', 'ready; made', ''], ['বৈঠক', 'boithok', 'meeting', '']]],
                ['title' => 'ঔ-কার', 'new' => ['ৌ'], 'words' => [['নৌকা', 'nouka', 'boat', '⛵'], ['মৌমাছি', 'moumachhi', 'honeybee', '🐝'], ['পৌষ', 'poush', 'Poush, a winter month', ''], ['চৌকি', 'chouki', 'wooden bed', '']]],
                ['title' => 'চিহ্নগুলো একসঙ্গে', 'new' => [], 'words' => [['মেঘ', 'megh', 'cloud', '☁️'], ['খোকা', 'khoka', 'little boy', '👶'], ['নৌকা', 'nouka', 'boat', '⛵'], ['দোকান', 'dokan', 'shop', '🏪']],
                    'sentences' => [['মেঘ ডাকে।', 'megh dake.', 'The clouds thunder.', '⛈️'], ['খোকা ঘুমায়।', 'khoka ghumay.', 'The little boy is sleeping.', '😴'], ['নৌকা নদীতে।', 'nouka nodite.', 'The boat is on the river.', '⛵'], ['মেয়ে গান গায়।', 'meye gan gay.', 'The girl sings.', '🎵']]],
            ],
        ],
        'vowel-letters' => [
            'title' => 'স্বরবর্ণ আর ছোট চিহ্ন', 'en' => 'Vowel letters and small signs',
            'intro' => 'শব্দের শুরুতে স্বর থাকলে পুরো স্বরবর্ণটা লেখা হয়: আম, ইট, উট। তারপর ং, ঃ, ঁ আর ৎ।',
            'tip' => ['bn' => 'কার আর স্বরবর্ণ মিলিয়ে দেখান: আ আর া একই আওয়াজ। শব্দের শুরুতে আ, মাঝে া।', 'en' => 'Match the vowel letter with its sign: আ and া make the same sound. At the start of a word আ, after a letter া.'],
            'sticker' => ['🌙', 'চাঁদ'],
            'lessons' => [
                ['title' => 'অ, আ, ই, ঈ', 'new' => ['অ', 'আ', 'ই', 'ঈ'], 'words' => [['আম', 'am', 'mango', '🥭'], ['ইট', 'it', 'brick', '🧱'], ['ঈগল', 'igol', 'eagle', '🦅'], ['অজগর', 'ojogor', 'python', '🐍'], ['আকাশ', 'akash', 'sky', '']]],
                ['title' => 'উ, ঊ, ঋ, এ', 'new' => ['উ', 'ঊ', 'ঋ', 'এ'], 'words' => [['উট', 'ut', 'camel', '🐪'], ['ঊষা', 'usha', 'dawn', '🌅'], ['এক', 'ek', 'one', '1️⃣'], ['ঋষি', 'rishi', 'sage', '']]],
                ['title' => 'ঐ, ও, ঔ', 'new' => ['ঐ', 'ও', 'ঔ'], 'words' => [['ঔষধ', 'oushodh', 'medicine', '💊'], ['ওজন', 'ojon', 'weight', '⚖️'], ['ওল', 'ol', 'elephant-foot yam', ''], ['ঐরাবত', 'oirabot', 'Airavata, the white elephant', '']]],
                ['title' => 'ং আর ঃ', 'new' => ['ং', 'ঃ'], 'words' => [['রং', 'rong', 'colour', '🎨'], ['সিংহ', 'shingho', 'lion', '🦁'], ['বাংলা', 'bangla', 'Bengali; Bengal', ''], ['দুঃখ', 'dukkho', 'sorrow', '']]],
                ['title' => 'ঁ আর ৎ', 'new' => ['ঁ', 'ৎ'], 'words' => [['চাঁদ', 'chand', 'moon', '🌙'], ['দাঁত', 'dant', 'tooth', '🦷'], ['ইঁদুর', 'indur', 'mouse', '🐭'], ['বাঁশি', 'banshi', 'flute', ''], ['হঠাৎ', 'hothat', 'suddenly', '']]],
            ],
        ],
        'phola-and-ref' => [
            'title' => 'ফলা আর রেফ', 'en' => 'Joined signs: phola and ref',
            'intro' => 'র, য, ব, ম, ল অন্য অক্ষরে জুড়ে গেলে ছোট চিহ্ন হয়ে যায়। মাথার র-এর নাম রেফ।',
            'tip' => ['bn' => 'র-ফলায় র শোনা যায় পরে (প্র = প্‌ + র), রেফে আগে (র্ম = র্‌ + ম)। য-ফলা আর ব-ফলা শব্দের মাঝে আগের অক্ষরটা দুবার বলায়: সত্য = শোত্‌তো।', 'en' => 'In র-ফলা the r comes after (প্র = p + r), in ref before (র্ম = r + m). য-ফলা and ব-ফলা in the middle of a word double the letter before: সত্য = shotto.'],
            'sticker' => ['🦋', 'প্রজাপতি'],
            'lessons' => [
                ['title' => 'র-ফলা', 'new' => ['্র'], 'words' => [['গ্রাম', 'gram', 'village', '🏡'], ['ট্রেন', 'tren', 'train', '🚆'], ['প্রজাপতি', 'projapoti', 'butterfly', '🦋'], ['প্রথম', 'prothom', 'first', '🥇']]],
                ['title' => 'য-ফলা', 'new' => ['্য'], 'words' => [['ব্যাঙ', 'byang', 'frog', '🐸'], ['ব্যথা', 'byatha', 'pain', ''], ['সত্য', 'shotto', 'truth', ''], ['খ্যাতি', 'khyati', 'fame', '']]],
                ['title' => 'ব-ফলা আর ম-ফলা', 'new' => ['্ব', '্ম'], 'words' => [['বিশ্ব', 'bishsho', 'world', '🌍'], ['জ্বর', 'jor', 'fever', '🤒'], ['পদ্ম', 'poddo', 'lotus', '🪷'], ['দ্বার', 'dar', 'door; gate', ''], ['স্মৃতি', 'sriti', 'memory', '']]],
                ['title' => 'ল-ফলা', 'new' => ['্ল'], 'words' => [['গ্লাস', 'glash', 'glass', '🥛'], ['ক্লাস', 'klash', 'class', ''], ['প্লাবন', 'plabon', 'flood', '']]],
                ['title' => 'রেফ', 'new' => ['র্'], 'words' => [['সূর্য', 'shurjo', 'sun', '☀️'], ['বর্ষা', 'borsha', 'the rainy season', '🌧️'], ['কর্ম', 'kormo', 'work; deed', ''], ['পর্দা', 'porda', 'curtain', '']]],
            ],
        ],
        'juktakkhor-1' => [
            'title' => 'যুক্তাক্ষর ১: দেখে চেনা যায়', 'en' => 'Joined letters 1: easy to see',
            'intro' => 'এই যুক্তাক্ষরগুলোয় দুটো অক্ষরই চেনা যায়: ন্ত = ন + ত, স্ত = স + ত। (বাংলাদেশে বেশি বলা হয় যুক্তবর্ণ।)',
            'tip' => ['bn' => 'যুক্তাক্ষর ভেঙে দেখান: কোন দুটো অক্ষর জুড়েছে? আগের অক্ষরে অ থাকে না: শান্ত = শান্‌ + তো।', 'en' => 'Break the joined letter apart: which two letters are joined? The first has no "o": শান্ত = shan + to.'],
            'sticker' => ['📖', 'গল্পের বই'],
            'lessons' => [
                ['title' => 'ন্ত, ন্দ, ন্ধ', 'new' => ['ন্ত', 'ন্দ', 'ন্ধ'], 'words' => [['আনন্দ', 'anondo', 'joy', '😄'], ['বন্ধু', 'bondhu', 'friend', '🤝'], ['শান্ত', 'shanto', 'calm', ''], ['সুন্দর', 'shundor', 'beautiful', '']]],
                ['title' => 'স্ত, স্থ, স্ক, স্প', 'new' => ['স্ত', 'স্থ', 'স্ক', 'স্প'], 'words' => [['স্কুল', 'skul', 'school', '🏫'], ['রাস্তা', 'rasta', 'road', '🛣️'], ['আস্তে', 'aste', 'slowly', '🐢'], ['স্থান', 'sthan', 'place', ''], ['স্পর্শ', 'sporsho', 'touch', '']]],
                ['title' => 'ক্ত, প্ত, ল্প, ব্দ', 'new' => ['ক্ত', 'প্ত', 'ল্প', 'ব্দ'], 'words' => [['গল্প', 'golpo', 'story', '📖'], ['সপ্তাহ', 'shoptaho', 'week', '📅'], ['রক্ত', 'rokto', 'blood', ''], ['শব্দ', 'shobdo', 'word; sound', '']]],
                ['title' => 'একই অক্ষর দুবার', 'new' => ['চ্চ', 'জ্জ', 'ত্ত', 'ন্ন', 'ট্ট', 'ল্ল'], 'words' => [['বাচ্চা', 'bachcha', 'child', '👶'], ['রান্না', 'ranna', 'cooking', '🍳'], ['উত্তর', 'uttor', 'answer; north', ''], ['লজ্জা', 'lojja', 'shyness', ''], ['চট্টগ্রাম', 'chottogram', 'Chattogram (a city)', ''], ['উল্লাস', 'ullash', 'delight', '']]],
                ['title' => 'ম্প, ম্ব, ষ্ট, শ্চ', 'new' => ['ম্প', 'ম্ব', 'ষ্ট', 'শ্চ'], 'words' => [['বৃষ্টি', 'brishti', 'rain', '🌧️'], ['কম্পিউটার', 'kompiutar', 'computer', '💻'], ['লম্বা', 'lomba', 'long; tall', ''], ['কষ্ট', 'koshto', 'hardship', ''], ['পশ্চিম', 'poshchim', 'west', '']]],
            ],
        ],
        'juktakkhor-2' => [
            'title' => 'যুক্তাক্ষর ২: নতুন চেহারা', 'en' => 'Joined letters 2: new shapes',
            'intro' => 'কিছু যুক্তাক্ষর জুড়ে গিয়ে একেবারে নতুন চেহারা নেয়: ক্ষ, জ্ঞ, ঙ্গ, ত্র। এগুলো ছবির মতো চিনে রাখতে হয়।',
            'tip' => ['bn' => 'ক্ষ শব্দের শুরুতে "খ", মাঝে "ক্‌খ": ক্ষমা = খমা, পরীক্ষা = পোরিক্‌খা। জ্ঞ শুরুতে "গ্য": জ্ঞান = গ্যান।', 'en' => 'ক্ষ at the start sounds "kh", in the middle "kkh": ক্ষমা = khoma, পরীক্ষা = porikkha. জ্ঞ at the start sounds "gy": জ্ঞান = gyan.'],
            'sticker' => ['🔔', 'ঘণ্টা'],
            'lessons' => [
                ['title' => 'ক্ষ', 'new' => ['ক্ষ'], 'words' => [['পরীক্ষা', 'porikkha', 'exam', '📝'], ['বৃক্ষ', 'brikkho', 'tree', '🌳'], ['শিক্ষক', 'shikkhok', 'teacher', '🧑‍🏫'], ['ক্ষমা', 'khoma', 'forgiveness', ''], ['রক্ষা', 'rokkha', 'protection', '']]],
                ['title' => 'জ্ঞ, হ্ম, হ্ন', 'new' => ['জ্ঞ', 'হ্ম', 'হ্ন'], 'words' => [['বিজ্ঞান', 'biggan', 'science', '🔬'], ['জ্ঞান', 'gyan', 'knowledge', ''], ['চিহ্ন', 'chinho', 'sign; mark', ''], ['ব্রহ্মপুত্র', 'brommhoputro', 'the Brahmaputra river', ''], ['মধ্যাহ্ন', 'moddhanho', 'midday', '']]],
                ['title' => 'ঙ্ক, ঙ্গ, ঞ্চ, ঞ্জ', 'new' => ['ঙ্ক', 'ঙ্গ', 'ঞ্চ', 'ঞ্জ', 'ঞ'], 'words' => [['অঙ্ক', 'ongko', 'arithmetic', '🔢'], ['পঞ্চাশ', 'ponchash', 'fifty', ''], ['বঙ্গ', 'bongo', 'Bengal', ''], ['সঙ্গী', 'shonggi', 'companion', ''], ['গঞ্জ', 'gonj', 'market town', ''], ['ব্যঞ্জন', 'byanjon', 'consonant; a dish', '']]],
                ['title' => 'ত্র, দ্ধ, ষ্ঠ, ষ্ণ, ণ্ট, ন্ড', 'new' => ['ত্র', 'দ্ধ', 'ষ্ঠ', 'ষ্ণ', 'ণ্ট', 'ন্ড'], 'words' => [['ঘণ্টা', 'ghonta', 'bell; hour', '🔔'], ['ঠান্ডা', 'thanda', 'cold', '🧊'], ['বুদ্ধি', 'buddhi', 'intelligence', '💡'], ['পত্র', 'potro', 'letter', '✉️'], ['মিত্র', 'mitro', 'friend', ''], ['ষষ্ঠ', 'shoshtho', 'sixth', ''], ['কৃষ্ণ', 'krishno', 'Krishna; dark', '']]],
                ['title' => 'তিন অক্ষরের যুক্তাক্ষর', 'new' => ['ক্ষ্ম'], 'words' => [['চন্দ্র', 'chondro', 'moon', '🌙'], ['যন্ত্র', 'jontro', 'machine; instrument', '⚙️'], ['মন্ত্র', 'montro', 'mantra, chant', ''], ['লক্ষ্মী', 'lokkhi', 'Lakshmi; a good, gentle child', ''], ['স্ত্রী', 'stri', 'wife', '']]],
            ],
        ],
        'sentences' => [
            'title' => 'ছোট বাক্য', 'en' => 'Short sentences',
            'intro' => 'সব অক্ষর চেনা হয়ে গেছে। এবার রোজকার কথা, ছোট ছোট বাক্যে।',
            'tip' => ['bn' => 'বাক্যটা আগে আপনি একবার পড়ুন, তারপর শিশু। শব্দের নিচে আঙুল রেখে পড়লে জায়গা হারায় না।', 'en' => 'Read the sentence once yourself, then let your child read it. A finger under the words keeps the place.'],
            'sticker' => ['🌸', 'শিউলি'],
            'lessons' => [
                ['title' => 'আমি আর আমার বাড়ি', 'new' => [], 'words' => [],
                    'sentences' => [['আমার নাম মিঠু।', 'amar nam mithu.', 'My name is Mithu.', '🙋'], ['আমি ভালো আছি।', 'ami bhalo achhi.', 'I am well.', '😊'], ['মা ভাত রাঁধছেন।', 'ma bhat randhchhen.', 'Mother is cooking rice.', '🍚'], ['বাবা অফিসে যান।', 'baba ophishe jan.', 'Father goes to the office.', '💼']]],
                ['title' => 'বাইরে', 'new' => [], 'words' => [],
                    'sentences' => [['আকাশে চাঁদ উঠেছে।', 'akashe chand uthechhe.', 'The moon has risen in the sky.', '🌙'], ['নদীতে নৌকা চলে।', 'nodite nouka chole.', 'Boats sail on the river.', '⛵'], ['গাছে পাখি ডাকে।', 'gachhe pakhi dake.', 'Birds call in the tree.', '🐦'], ['বৃষ্টি পড়ছে।', 'brishti porchhe.', 'It is raining.', '🌧️']]],
                ['title' => 'প্রশ্ন', 'new' => [], 'words' => [],
                    'sentences' => [['তুমি কেমন আছ?', 'tumi kemon achho?', 'How are you?', '🙂'], ['তোমার নাম কী?', 'tomar nam ki?', 'What is your name?', '🙋'], ['এটা কী?', 'eta ki?', 'What is this?', '❓'], ['জল খাবে?', 'jol khabe?', 'Would you like some water?', '💧']]],
                ['title' => 'আমার দিন', 'new' => [], 'words' => [],
                    'sentences' => [['আমি স্কুলে যাই।', 'ami skule jai.', 'I go to school.', '🏫'], ['আজ শনিবার।', 'aj shonibar.', 'Today is Saturday.', '📅'], ['আমার খিদে পেয়েছে।', 'amar khide peyechhe.', 'I am hungry.', '🍽️'], ['ধন্যবাদ।', 'dhonnobad.', 'Thank you.', '🙏']]],
            ],
        ],
        'stories' => [
            'title' => 'প্রথম গল্প', 'en' => 'First stories',
            'intro' => 'হাতপাখার লেখা পাঁচটা ছোট গল্প, শুধু শেখা অক্ষর দিয়ে। শেষে একটা ছোট প্রশ্ন।',
            'tip' => ['bn' => 'গল্পটা প্রথমবার একসঙ্গে পড়ুন, দ্বিতীয়বার শিশু একা। শেষে জিজ্ঞেস করুন: তোমার কোন জায়গাটা ভালো লাগল?', 'en' => 'Read the story together the first time, and let your child read it alone the second time. Then ask: which part did you like?'],
            'sticker' => ['🏆', 'পড়ুয়া'],
            'lessons' => [
                ['title' => 'মিঠুর বিড়াল', 'new' => [], 'words' => [], 'story' => [
                    'text' => "মিঠুর একটা বিড়াল আছে। বিড়ালটার নাম মেনি। মেনি সাদা আর নরম।\nরোজ সকালে মেনি দুধ খায়। তারপর রোদে বসে ঘুমায়।\nএকদিন মেনি একটা প্রজাপতি দেখল। প্রজাপতি উড়ে গেল ফুলের দিকে। মেনিও ছুটল। কিন্তু প্রজাপতি ধরা দিল না।\nমেনি আবার রোদে ফিরে এসে ঘুমিয়ে পড়ল।",
                    'q' => ['মেনি কী দেখল?', ['একটা প্রজাপতি', 'একটা পাখি', 'একটা মাছ'], 0], 'picture' => '🐈']],
                ['title' => 'বৃষ্টির দিন', 'new' => [], 'words' => [], 'story' => [
                    'text' => "সকাল থেকে আকাশে কালো মেঘ। তারপর বৃষ্টি নামল। টুপুর টাপুর, টুপুর টাপুর।\nরিনা জানলায় বসে দেখল, উঠোনে জল জমেছে।\nদাদা কাগজ দিয়ে একটা নৌকা বানাল। রিনা নৌকাটা জলে ভাসিয়ে দিল। নৌকা দুলে দুলে চলল।\nরিনা হাততালি দিয়ে হাসল।",
                    'q' => ['দাদা কী বানাল?', ['কাগজের নৌকা', 'মাটির পুতুল', 'কাঠের ঘোড়া'], 0], 'picture' => '🌧️']],
                ['title' => 'আমগাছ', 'new' => [], 'words' => [], 'story' => [
                    'text' => "আমাদের উঠোনে একটা আমগাছ আছে। গরমকালে গাছে অনেক আম হয়।\nকাঁচা আম সবুজ, পাকা আম হলুদ।\nঠাকুমা কাঁচা আম দিয়ে আচার বানান। আমি পাকা আম খেতে ভালোবাসি।\nএকদিন ঝড়ে অনেক আম পড়ল। আমরা সবাই মিলে ঝুড়ি ভরে আম কুড়োলাম।",
                    'q' => ['ঠাকুমা কাঁচা আম দিয়ে কী বানান?', ['আচার', 'পিঠে', 'মিষ্টি'], 0], 'picture' => '🥭']],
                ['title' => 'নতুন বন্ধু', 'new' => [], 'words' => [], 'story' => [
                    'text' => "স্কুলে আজ একটা নতুন মেয়ে এসেছে। ওর নাম আয়েশা। আয়েশা একা বসে ছিল।\nটুম্পা গিয়ে বলল, \"তোমার পাশে বসব?\" আয়েশা হেসে বলল, \"বসো।\"\nটিফিনে দুজনে রুটি আর আলুভাজা ভাগ করে খেল।\nছুটির পর আয়েশা বলল, \"কাল আবার দেখা হবে।\" টুম্পা বলল, \"হ্যাঁ, আমরা এখন বন্ধু।\"",
                    'q' => ['টিফিনে দুজনে কী খেল?', ['রুটি আর আলুভাজা', 'ভাত আর ডাল', 'লুচি আর মিষ্টি'], 0], 'picture' => '🤝']],
                ['title' => 'পৌষের পিঠে', 'new' => [], 'words' => [], 'story' => [
                    'text' => "পৌষ মাসে খুব শীত পড়ে। ভোরবেলা চারদিক কুয়াশায় ঢাকা।\nআজ পৌষ সংক্রান্তি। দিদিমা পিঠে বানাবেন। চালের গুঁড়ো, নারকেল আর নলেন গুড় এসেছে।\nআমরা সবাই রান্নাঘরে গোল হয়ে বসলাম। দিদিমা পাটিসাপটা ভাজলেন, মা দুধপুলি বানালেন।\nগরম পিঠে খেয়ে আমাদের মুখে হাসি ফুটল।",
                    'q' => ['দিদিমা কী ভাজলেন?', ['পাটিসাপটা', 'লুচি', 'মাছ'], 0], 'picture' => '🥞']],
            ],
        ],
    ],
];
