# Content audit: summary for the owner

✅ **Approved by the owner (D30).** Apply on each environment with:
`php artisan hatpakha:audit --apply=remove,merge,noindex --by=<admin email>`

`php artisan hatpakha:audit` looked at all **1,393 published posts**. These are **suggestions only**: nothing has been hidden or removed. Decide per row, then an admin applies it in **/desk → লেখা** (filter "অডিট সুপারিশ", button "সুপারিশ মানুন"), or keeps the post ("রাখুন"). Every action is logged and can be undone.

| Suggestion | What applying it does | Posts |
|---|---|---:|
| রাখুন (keep) | nothing | 1,311 |
| Google থেকে লুকান (noindex) | stays on the site, hidden from Google | 66 |
| একত্র করুন (merge) | the copy is archived and its URL 301s to the original | 9 |
| সরান (remove) | archived; the URL answers 410 "gone" | 7 |

**My recommendation as editor:**
1. **Remove** the 5 book-download posts (Satyajit Ray and other authors' books shared as PDFs). They are a copyright risk for the magazine and for AdSense approval. Remove the coupon ad post. The Sudoku post is an empty widget.
2. **Merge** the 9 true duplicates (same author, same text submitted twice).
3. **Noindex** the 14 English news/listicle posts from 2024 by the desk account (US Open, Mariah Carey, ChatGPT citation, Python…). They are off-topic and look machine-written, which can hurt how Google rates the whole site. The other noindex items (old "videos", "entertainment", "amazing facts", "game" categories and very short prose) are harmless to keep on the site but add nothing in search.

Links below point to the staging site.

### সরান (410) (remove): 7

| # | Title | Author | Year | Why |
|---|---|---|---|---|
| 74 | [Flight Coupon Code - Big travel discounts](https://new.hatpakha.com/flight-coupon-code-big-travel-discounts/) | হাতপাখা ডেস্ক | 2014 | বিজ্ঞাপন/স্প্যামের শব্দ আছে; পুরনো বিভাগ: coupon (সাহিত্যের বাইরে) |
| 113 | [Pandob Goyenda 16](https://new.hatpakha.com/pandob-goyenda-16/) | হাতপাখা ডেস্ক | 2014 | অন্যের প্রকাশিত বই ডাউনলোডের লিংক (স্বত্ব লঙ্ঘনের ঝুঁকি) |
| 124 | [Body Language - How to read other's thoughts by their gestur](https://new.hatpakha.com/body-language-how-to-read-others-thoughts-by-their-gestures/) | হাতপাখা ডেস্ক | 2014 | অন্যের প্রকাশিত বই ডাউনলোডের লিংক (স্বত্ব লঙ্ঘনের ঝুঁকি) |
| 241 | [Sudoku Puzzle 1](https://new.hatpakha.com/sudoku-puzzle-1/) | হাতপাখা ডেস্ক | 2015 | লেখা প্রায় ফাঁকা; পুরনো বিভাগ: game (সাহিত্যের বাইরে) |
| 249 | [Tarini Khuror Kirti Kalap by Satyajit Ray](https://new.hatpakha.com/tarini-khuror-kirti-kalap-by-satyajit-ray/) | হাতপাখা ডেস্ক | 2015 | অন্যের প্রকাশিত বই ডাউনলোডের লিংক (স্বত্ব লঙ্ঘনের ঝুঁকি) |
| 250 | [Feluda Samagra by Satyajit Ray Part-1](https://new.hatpakha.com/feluda-samagra-satyajit-ray-part-1/) | হাতপাখা ডেস্ক | 2015 | অন্যের প্রকাশিত বই ডাউনলোডের লিংক (স্বত্ব লঙ্ঘনের ঝুঁকি); খুব ছোট গদ্য (64 শব্দ) |
| 251 | [Feluda Samagra by Satyajit Ray Part-2](https://new.hatpakha.com/feluda-samagra-by-satyajit-ray-part-2/) | হাতপাখা ডেস্ক | 2015 | অন্যের প্রকাশিত বই ডাউনলোডের লিংক (স্বত্ব লঙ্ঘনের ঝুঁকি); খুব ছোট গদ্য (43 শব্দ) |

### একত্র করুন (merge): 9

| # | Title | Author | Year | Why |
|---|---|---|---|---|
| 1110 | [Press Release of AETM International Conference 2018](https://new.hatpakha.com/press-release-of-aetm-international-conference-2018-2/) | হাতপাখা ডেস্ক | 2019 | একই শিরোনাম ও লেখকের আরেকটি লেখা আছে (#1111) → #1111 |
| 1391 | [A Body Without Soul](https://new.hatpakha.com/a-body-without-soul-2/) | UJJAL MANDAL | 2020 | একই শিরোনাম ও লেখকের আরেকটি লেখা আছে (#1398) → #1398 |
| 1348 | [বন্ধু](https://new.hatpakha.com/%E0%A6%AC%E0%A6%A8%E0%A7%8D%E0%A6%A7%E0%A7%81-2/) | soumili0304 | 2020 | একই শিরোনাম ও লেখকের আরেকটি লেখা আছে (#1349) → #1349 |
| 1419 | [নিয়তি](https://new.hatpakha.com/%E0%A6%A8%E0%A6%BF%E0%A6%AF%E0%A6%BC%E0%A6%A4%E0%A6%BF-2/) | SWARUP GHOSH | 2020 | একই শিরোনাম ও লেখকের আরেকটি লেখা আছে (#1515) → #1515 |
| 1392 | [A Body Without Soul](https://new.hatpakha.com/a-body-without-soul-3/) | UJJAL MANDAL | 2020 | একই শিরোনাম ও লেখকের আরেকটি লেখা আছে (#1398) → #1398 |
| 1568 | [প্রবাসে দৈবের বশে](https://new.hatpakha.com/%E0%A6%AA%E0%A7%8D%E0%A6%B0%E0%A6%AC%E0%A6%BE%E0%A6%B8%E0%A7%87-%E0%A6%A6%E0%A7%88%E0%A6%AC%E0%A7%87%E0%A6%B0-%E0%A6%AC%E0%A6%B6%E0%A7%87-2/) | Tirthankar Bhattacharya | 2020 | একই শিরোনাম ও লেখকের আরেকটি লেখা আছে (#1569) → #1569 |
| 1245 | [টিফিন](https://new.hatpakha.com/%E0%A6%9F%E0%A6%BF%E0%A6%AB%E0%A6%BF%E0%A6%A8-2/) | suvadeepce01 | 2021 | একই শিরোনাম ও লেখকের আরেকটি লেখা আছে (#1244) → #1244 |
| 1236 | [॥আশ্রয়॥](https://new.hatpakha.com/%E0%A5%A5%E0%A6%86%E0%A6%B6%E0%A7%8D%E0%A6%B0%E0%A6%AF%E0%A6%BC%E0%A5%A5-2/) | Sirshendu | 2021 | একই শিরোনাম ও লেখকের আরেকটি লেখা আছে (#1234) → #1234 |
| 1779 | [কবিতার জন্ম](https://new.hatpakha.com/%E0%A6%95%E0%A6%AC%E0%A6%BF%E0%A6%A4%E0%A6%BE%E0%A6%B0-%E0%A6%9C%E0%A6%A8%E0%A7%8D%E0%A6%AE-2/) | Sanchita Mondal | 2021 | একই শিরোনাম ও লেখকের আরেকটি লেখা আছে (#1778) → #1778 |

### Google থেকে লুকান (noindex): 66

| # | Title | Author | Year | Why |
|---|---|---|---|---|
| 16 | [I Don't Believe It](https://new.hatpakha.com/i-dont-believe-it-2/) | হাতপাখা ডেস্ক | 2012 | পুরনো বিভাগ: amazing-facts (সাহিত্যের বাইরে) |
| 13 | [I Don't Believe It ...](https://new.hatpakha.com/i-dont-believe-it/) | হাতপাখা ডেস্ক | 2013 | পুরনো বিভাগ: amazing-facts (সাহিত্যের বাইরে) |
| 69 | [Intense Firework War](https://new.hatpakha.com/intense-firework-war/) | হাতপাখা ডেস্ক | 2013 | পুরনো বিভাগ: amazing-facts (সাহিত্যের বাইরে); শুধু ভিডিও, লেখা প্রায় নেই |
| 76 | [Madhyamik 2014 Mathematics Preparation Test](https://new.hatpakha.com/madhyamik-2014-mathematics-preparation-test/) | হাতপাখা ডেস্ক | 2014 | পুরনো বিভাগ: quiz (সাহিত্যের বাইরে) |
| 86 | [vote for a better India](https://new.hatpakha.com/vote-for-a-better-india/) | হাতপাখা ডেস্ক | 2014 | খুব ছোট গদ্য (37 শব্দ) |
| 135 | [Life Motivational Videos - True Heart Touching, Inspirationa](https://new.hatpakha.com/life-motivational-videos-true-heart-touching-inspirational-video/) | হাতপাখা ডেস্ক | 2014 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে); শুধু ভিডিও, লেখা প্রায় নেই |
| 137 | [The Video that inspired a million lives](https://new.hatpakha.com/the-video-that-inspired-a-million-lives/) | হাতপাখা ডেস্ক | 2014 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 136 | [REACH not RICH is the real meaning of SUCCESS](https://new.hatpakha.com/reach-not-rich-is-the-real-meaning-of-success/) | হাতপাখা ডেস্ক | 2014 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 145 | [Real Life Hero - Be A Hero](https://new.hatpakha.com/real-life-hero/) | হাতপাখা ডেস্ক | 2014 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 146 | [First step - A Small Step](https://new.hatpakha.com/first-step-small-step/) | হাতপাখা ডেস্ক | 2014 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 165 | [Maa - Happy Mother's Day](https://new.hatpakha.com/maa-happy-mothers-day/) | হাতপাখা ডেস্ক | 2015 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 188 | [The New Generation Games](https://new.hatpakha.com/the-new-generation-games/) | Dora Britton | 2015 | পুরনো বিভাগ: game (সাহিত্যের বাইরে) |
| 221 | [How a little boy Ajju sorts out his problems](https://new.hatpakha.com/how-a-little-boy-ajju-sorts-out-his-problems/) | হাতপাখা ডেস্ক | 2015 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 223 | [Before you Celebrate Raksha Bandhan on August 29th Watch thi](https://new.hatpakha.com/celebrate-raksha-bandhan-on-august-29th/) | হাতপাখা ডেস্ক | 2015 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 233 | [Everybody wants to eat, but nobody wants to grow.](https://new.hatpakha.com/everybody-wants-to-eat/) | হাতপাখা ডেস্ক | 2015 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 237 | [School Lunch - It's Not Just About Eating!](https://new.hatpakha.com/school-lunch-its-not-just-about-eating/) | হাতপাখা ডেস্ক | 2015 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 255 | [DEBI - The Short Film](https://new.hatpakha.com/debi-the-short-film/) | হাতপাখা ডেস্ক | 2015 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে); শুধু ভিডিও, লেখা প্রায় নেই |
| 258 | [মৃত্যুদূত - একটি শ্রুতিনাটক](https://new.hatpakha.com/%E0%A6%AE%E0%A7%83%E0%A6%A4%E0%A7%8D%E0%A6%AF%E0%A7%81%E0%A6%A6%E0%A7%82%E0%A6%A4-%E0%A6%8F%E0%A6%95%E0%A6%9F%E0%A6%BF-%E0%A6%B6%E0%A7%8D%E0%A6%B0%E0%A7%81%E0%A6%A4%E0%A6%BF%E0%A6%A8%E0%A6%BE/) | Soumyadipta Banerjee | 2015 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 290 | [The Kolkata International Film Festival](https://new.hatpakha.com/the-kolkata-international-film-festival/) | Maitree Shee | 2015 | পুরনো বিভাগ: entertainment (সাহিত্যের বাইরে) |
| 320 | [Jingle all the way…](https://new.hatpakha.com/jingle-all-the-way/) | Maitree Shee | 2015 | পুরনো বিভাগ: entertainment (সাহিত্যের বাইরে) |
| 335 | [Why Rape Is A Joke In India?](https://new.hatpakha.com/rape-joke-india/) | হাতপাখা ডেস্ক | 2016 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 336 | [Change Is Beautiful](https://new.hatpakha.com/change-is-beautiful/) | হাতপাখা ডেস্ক | 2016 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 347 | [Some Unknown Facts of 26 Jan Republic Day of India](https://new.hatpakha.com/unknown-facts-26-jan-republic-day-india/) | হাতপাখা ডেস্ক | 2016 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 338 | [Bachchader Experimentum: A Landmark in Contemporary Theater](https://new.hatpakha.com/bachchader-experimentum-landmark-contemporary-theater/) | Poorvita | 2016 | পুরনো বিভাগ: entertainment (সাহিত্যের বাইরে) |
| 368 | [88th Academy Awards](https://new.hatpakha.com/88th-academy-awards/) | Maitree Shee | 2016 | পুরনো বিভাগ: entertainment (সাহিত্যের বাইরে) |
| 371 | [Erasing the Man-Woman Divide - Chena Kintu Ajana: A Document](https://new.hatpakha.com/chena-kintu-ajana-a-documentary/) | Poorvita | 2016 | পুরনো বিভাগ: entertainment (সাহিত্যের বাইরে) |
| 370 | ["অল্প কিছু কথা", একটি স্বল্পদৈর্ঘ্যর ছায়াছবি](https://new.hatpakha.com/%E0%A6%85%E0%A6%B2%E0%A7%8D%E0%A6%AA-%E0%A6%95%E0%A6%BF%E0%A6%9B%E0%A7%81-%E0%A6%95%E0%A6%A5%E0%A6%BE-%E0%A6%8F%E0%A6%95%E0%A6%9F%E0%A6%BF-%E0%A6%B8%E0%A7%8D%E0%A6%AC%E0%A6%B2%E0%A7%8D%E0%A6%AA/) | Soumyadipta Banerjee | 2016 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 452 | [63rd National Award 2016](https://new.hatpakha.com/63rd-national-award-2016/) | Maitree Shee | 2016 | পুরনো বিভাগ: entertainment (সাহিত্যের বাইরে) |
| 474 | [A tribute to the Maestro of Indian Classical Music](https://new.hatpakha.com/tribute-maestro-indian-classical-music/) | Maitree Shee | 2016 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 462 | [Mon Kharap](https://new.hatpakha.com/mon-kharap-2/) | sunitabiswas | 2016 | খুব ছোট গদ্য (70 শব্দ) |
| 518 | [The Path Bender](https://new.hatpakha.com/path-bender/) | Maitree Shee | 2016 | পুরনো বিভাগ: game (সাহিত্যের বাইরে) |
| 513 | [Fatigue](https://new.hatpakha.com/fatigue/) | binayak | 2016 | খুব ছোট গদ্য (62 শব্দ) |
| 519 | [COFFEE  TIME...](https://new.hatpakha.com/coffee-time/) | UPAMA GHOSH | 2016 | খুব ছোট গদ্য (20 শব্দ) |
| 532 | [Naamhin](https://new.hatpakha.com/naamhin/) | Anjali De Nandi | 2016 | খুব ছোট গদ্য (60 শব্দ) |
| 570 | [মৃত্যুর পরে](https://new.hatpakha.com/%E0%A6%AE%E0%A7%83%E0%A6%A4%E0%A7%8D%E0%A6%AF%E0%A7%81%E0%A6%B0-%E0%A6%AA%E0%A6%B0%E0%A7%87/) | Pulak | 2016 | লেখা শুধু PDF ফাইলে: পাতায় লেখাটি টাইপ করে দিলে পাঠক ও Google পড়তে পারবে; খুব ছোট গদ্য (3 শব্দ) |
| 592 | [The thing over which we have absolute control](https://new.hatpakha.com/thing-absolute-control/) | Mitra Das | 2016 | পুরনো বিভাগ: amazing-facts (সাহিত্যের বাইরে) |
| 654 | [সঙ্গীত - মানব জীবনে তার প্রভাব এবং অবদান](https://new.hatpakha.com/%E0%A6%B8%E0%A6%99%E0%A7%8D%E0%A6%97%E0%A7%80%E0%A6%A4-%E0%A6%AE%E0%A6%BE%E0%A6%A8%E0%A6%AC-%E0%A6%9C%E0%A7%80%E0%A6%AC%E0%A6%A8%E0%A7%87-%E0%A6%A4%E0%A6%BE%E0%A6%B0-%E0%A6%AA%E0%A7%8D%E0%A6%B0/) | Pulak | 2016 | লেখা শুধু PDF ফাইলে: পাতায় লেখাটি টাইপ করে দিলে পাঠক ও Google পড়তে পারবে; খুব ছোট গদ্য (16 শব্দ) |
| 730 | [Orrey Mon - Ritabhari Chakraborty & Ayushmann Khurrana](https://new.hatpakha.com/orrey-mon-ritabhari-chakraborty-ayushmann-khurrana/) | হাতপাখা ডেস্ক | 2017 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 704 | [নব্বই এর বিজ্ঞাপন](https://new.hatpakha.com/%E0%A6%A8%E0%A6%AC%E0%A7%8D%E0%A6%AC%E0%A6%87-%E0%A6%8F%E0%A6%B0-%E0%A6%AC%E0%A6%BF%E0%A6%9C%E0%A7%8D%E0%A6%9E%E0%A6%BE%E0%A6%AA%E0%A6%A8/) | Debasmita Dhar | 2017 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 757 | [Pagli - সত্যি বলছি তোর মতন কেউ নেই  by Iman Sen](https://new.hatpakha.com/pagli/) | হাতপাখা ডেস্ক | 2017 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 842 | [Happy Father's Day - Love u Baba](https://new.hatpakha.com/happy-fathers-day-love-u-baba/) | হাতপাখা ডেস্ক | 2017 | পুরনো বিভাগ: videos (সাহিত্যের বাইরে); শুধু ভিডিও, লেখা প্রায় নেই |
| 645 | [Failure is not a block but a stepping stone to success](https://new.hatpakha.com/failure-is-not-a-block-but-a-stepping-stone-to-success/) | Mitra Das | 2017 | পুরনো বিভাগ: amazing-facts (সাহিত্যের বাইরে) |
| 1096 | [42ND KOLKATA INTERNATIONAL BOOK FAIR AND ITS IMPARATIVE ILLU](https://new.hatpakha.com/42nd-kolkata-international-book-fair-and-its-imparative-illuminations/) | হাতপাখা ডেস্ক | 2018 | পুরনো বিভাগ: entertainment (সাহিত্যের বাইরে) |
| 1099 | [102 NOT OUT-এক অনন্য সাধারণ অভিজ্ঞতা](https://new.hatpakha.com/102-not-out-%E0%A6%8F%E0%A6%95-%E0%A6%85%E0%A6%A8%E0%A6%A8%E0%A7%8D%E0%A6%AF-%E0%A6%B8%E0%A6%BE%E0%A6%A7%E0%A6%BE%E0%A6%B0%E0%A6%A3-%E0%A6%85%E0%A6%AD%E0%A6%BF%E0%A6%9C%E0%A7%8D%E0%A6%9E%E0%A6%A4/) | 8257929077 | 2019 | পুরনো বিভাগ: entertainment (সাহিত্যের বাইরে) |
| 1014 | [চুপাকাব্রাস](https://new.hatpakha.com/%E0%A6%9A%E0%A7%81%E0%A6%AA%E0%A6%BE%E0%A6%95%E0%A6%BE%E0%A6%AC%E0%A7%8D%E0%A6%B0%E0%A6%BE%E0%A6%B8/) | kaushik.das | 2020 | পুরনো বিভাগ: amazing-facts (সাহিত্যের বাইরে) |
| 1362 | [Kid's Story](https://new.hatpakha.com/kids-story/) | Iswar4043 | 2020 | পুরনো বিভাগ: entertainment (সাহিত্যের বাইরে) |
| 1336 | [Valobasa Valobasa Valobasi](https://new.hatpakha.com/valobasa-valobasa-valobasi/) | Akhi | 2020 | পুরনো বিভাগ: entertainment (সাহিত্যের বাইরে) |
| 1565 | [The Mother of Us All: Ancient India's Vedic Civilization](https://new.hatpakha.com/the-mother-of-us-all-ancient-indias-vedic-civilization/) | William T. Hathaway | 2020 | পুরনো বিভাগ: amazing-facts (সাহিত্যের বাইরে) |
| 1604 | [Who is Maa?](https://new.hatpakha.com/who-is-maa/) | UJJAL MANDAL | 2020 | খুব ছোট গদ্য (45 শব্দ) |
| 1603 | [যমজ জাতক জন্মকথা](https://new.hatpakha.com/%E0%A6%AF%E0%A6%AE%E0%A6%9C-%E0%A6%9C%E0%A6%BE%E0%A6%A4%E0%A6%95-%E0%A6%9C%E0%A6%A8%E0%A7%8D%E0%A6%AE%E0%A6%95%E0%A6%A5%E0%A6%BE/) | Anisha Dutta | 2020 | পুরনো বিভাগ: amazing-facts (সাহিত্যের বাইরে) |
| 1663 | [My Innocent Love!](https://new.hatpakha.com/my-innocent-love/) | UJJAL MANDAL | 2021 | খুব ছোট গদ্য (59 শব্দ) |
| 1809 | [আজ-ই একমাত্র সত্য](https://new.hatpakha.com/%E0%A6%86%E0%A6%9C-%E0%A6%87-%E0%A6%8F%E0%A6%95%E0%A6%AE%E0%A6%BE%E0%A6%A4%E0%A7%8D%E0%A6%B0-%E0%A6%B8%E0%A6%A4%E0%A7%8D%E0%A6%AF/) | Swapan Chakraborti | 2021 | পুরনো বিভাগ: amazing-facts (সাহিত্যের বাইরে) |
| 1852 | [U.S. Open 2024: Tennis Stars Compete for a Share of $75 Mill](https://new.hatpakha.com/usopen2024-tennis-stars-compete-for-a-share-of-75-million-in-record-cash-prizes/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয়; পুরনো বিভাগ: game (সাহিত্যের বাইরে) |
| 1853 | [Mariah Carey's Sister: A Look into the Life of Alison Carey](https://new.hatpakha.com/mariah-careys-sister-a-look-into-the-life-of-alison-carey/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1854 | [Chhatra Samaj: The New Student Outfit Leading the 'March to ](https://new.hatpakha.com/chhatra-samaj-the-new-student-outfit-leading-the-march-to-nabanna-amid-controversy/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1855 | [Telegram Founder Durov 's Arrest: What It Means for Tech Gia](https://new.hatpakha.com/telegram-founder-durov-s-arrest-what-it-means-for-tech-giants/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1856 | [Meta, Misinformation, and Government Pressure: Mark Zuckerbe](https://new.hatpakha.com/meta-misinformation-and-government-pressure-mark-zuckerberg-speaks-out/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1857 | [Paris 2024 Paralympic Games / Opening Ceremony Highlights & ](https://new.hatpakha.com/paris-2024-paralympic-games-opening-ceremony-highlights-schedule/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয়; পুরনো বিভাগ: videos (সাহিত্যের বাইরে) |
| 1858 | [A Nation Stands Together: PM Modi's Inspiring Message to Tea](https://new.hatpakha.com/a-nation-stands-together-pm-modis-inspiring-message-to-team-india-for-paris-paralympics/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1859 | [What Happened to Taeil? Latest Updates on NCT's Star](https://new.hatpakha.com/what-happened-to-taeil-latest-updates-on-ncts-star/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1860 | [How to Cite ChatGPT in Your Research: A Complete Guide](https://new.hatpakha.com/how-to-cite-chatgpt-in-your-research-a-complete-guide/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1861 | [50 Heartfelt Happy Birthday Wishes to Make Their Day Special](https://new.hatpakha.com/50-heartfelt-happy-birthday-wishes-to-make-their-day-special/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1863 | [From Unique to Inspirational: How Matt Stutzman's Armless Ar](https://new.hatpakha.com/from-unique-to-inspirational-how-matt-stutzmans-armless-archery-legacy-is-changing-lives/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1864 | [The Deliverance Netflix: A Thrilling New Addition to Your Wa](https://new.hatpakha.com/the-deliverance-netflix-a-thrilling-new-addition-to-your-watchlist/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1865 | [10 Essential Python Concepts You Can Master in a Month](https://new.hatpakha.com/10-essential-python-concepts-you-can-master-in-a-month/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |
| 1866 | [Top 10 Surprising Parenting Tips That Actually Work](https://new.hatpakha.com/top-10-surprising-parenting-tips-that-actually-work/) | হাতপাখা ডেস্ক | 2024 | ডেস্ক অ্যাকাউন্টের ইংরেজি খবর/তালিকা-লেখা (২০২৪): সাহিত্য পত্রিকার বিষয় নয় |

## Pending backlog (424 posts): tiers

| Tier | Posts | Plan (PLAN.md "Real backlog") |
|---|---:|---|
| **A** recent (2022 onwards) | 23 | Read now; the best go into the relaunch issue "ফিরে এলাম" |
| **B** clean-up | 41 | Very short/empty, already published, or submitted twice: close politely (archive) |
| **C** old, possibly good (2014–2019) | 360 | Phase 3 AI triage scores them; top authors are asked if the piece is still unpublished |

One account (Anjali De Nandi) has **125** pending posts (119 different titles). They carry the note "একসঙ্গে বিচার করুন": decide on them as one batch. Filter in /desk → লেখা → "অপেক্ষার স্তর".
