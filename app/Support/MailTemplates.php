<?php

namespace App\Support;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;

/**
 * D123: the site's e-mails as templates the owner can read, preview and reword in /desk → ই-মেলের নমুনা.
 * The original wording lives here; the owner's version (if any) in the mail_templates table.
 *
 * Body format: paragraphs separated by an empty line; a line "[বোতাম]" is where the button goes (text after it
 * appears below the button). {placeholders} are filled when the e-mail is sent; a paragraph whose placeholder has
 * no value (for example "সম্পাদকের কথা: {সম্পাদকের_কথা}" when the editor wrote nothing) is left out.
 */
final class MailTemplates
{
    public const BUTTON = '[বোতাম]';

    private const NAME = ['{নাম}' => 'যাঁকে পাঠানো হচ্ছে তাঁর নাম'];

    private const PIECE = ['{লেখা}' => 'লেখার শিরোনাম'];

    /** key => [label, when, to, placeholders, subject, greeting, body, button, salutation, sample values] */
    public static function all(): array
    {
        $desk = '— হাতপাখা সম্পাদকমণ্ডলী';

        return [
            'verify_email' => ['ই-মেল যাচাই', 'নতুন অ্যাকাউন্ট খুললে, বা যাচাই না-হওয়া অ্যাকাউন্টে লগ-ইন করলে', 'অ্যাকাউন্টের মালিক', self::NAME,
                'হাতপাখা: ই-মেল যাচাই করুন', 'নমস্কার, {নাম}',
                "## ই-মেলটি একবার যাচাই করুন\n\nহাতপাখায় অ্যাকাউন্ট খোলার জন্য ধন্যবাদ। লেখা পাঠানোর আগে নিচের বোতামে চাপ দিয়ে ই-মেলটি যাচাই করুন।\n\n[বোতাম]\n\nআপনি অ্যাকাউন্ট না খুলে থাকলে এই ই-মেলটি উপেক্ষা করুন।",
                'ই-মেল যাচাই করুন', '— হাতপাখা', ['{নাম}' => 'মেঘলা দাস']],
            'reset_password' => ['পাসওয়ার্ড বদলানোর লিংক', '"পাসওয়ার্ড ভুলে গেছেন?" থেকে চাইলে', 'অ্যাকাউন্টের মালিক', ['{মিনিট}' => 'লিংক কত মিনিট কাজ করবে'],
                'হাতপাখা: নতুন পাসওয়ার্ড বানানোর লিংক', 'নমস্কার,',
                "## নতুন পাসওয়ার্ড বানান\n\nআপনার হাতপাখা অ্যাকাউন্টের পাসওয়ার্ড বদলানোর অনুরোধ এসেছে।\n\n[বোতাম]\n\nলিংকটি {মিনিট} মিনিট কাজ করবে।\n\nআপনি অনুরোধ না করে থাকলে কিছু করতে হবে না; পাসওয়ার্ড যেমন আছে তেমনই থাকবে।",
                'নতুন পাসওয়ার্ড বানান', '— হাতপাখা', ['{মিনিট}' => '৬০']],
            'account_exists' => ['অ্যাকাউন্ট আগে থেকেই আছে', 'কেউ আগে থেকে থাকা ঠিকানায় নতুন অ্যাকাউন্ট খুলতে চাইলে', 'সেই ঠিকানার মালিক', [],
                'হাতপাখা: আপনার অ্যাকাউন্ট আগে থেকেই আছে', 'নমস্কার',
                "## আপনার অ্যাকাউন্ট আগে থেকেই আছে\n\nএই ই-মেলে নতুন অ্যাকাউন্ট খোলার চেষ্টা হয়েছে, কিন্তু এই ঠিকানায় হাতপাখার অ্যাকাউন্ট আগে থেকেই আছে।\n\n[বোতাম]\n\nপাসওয়ার্ড মনে না থাকলে লগ-ইন পাতার “পাসওয়ার্ড ভুলে গেছেন?” থেকে নতুন পাসওয়ার্ড নিন। আপনি চেষ্টা না করে থাকলে এই ই-মেলটি উপেক্ষা করুন।",
                'লগ-ইন করুন', '— হাতপাখা', []],
            'newsletter_confirm' => ['চিঠির সাইন-আপ নিশ্চিত করা', 'অ্যাকাউন্ট ছাড়া কেউ চিঠির জন্য সাইন-আপ করলে', 'যিনি সাইন-আপ করলেন', [],
                'হাতপাখার চিঠি: একবার নিশ্চিত করুন', 'নমস্কার',
                "## চিঠি পেতে একবার নিশ্চিত করুন\n\nহাতপাখার সাপ্তাহিক চিঠি পেতে নিচের বোতামে একবার চাপ দিন। সপ্তাহে একটির বেশি চিঠি নয়, যখন খুশি বন্ধ করা যায়।\n\n[বোতাম]\n\nআপনি না চাইলে এই ই-মেলটি উপেক্ষা করুন; কিছুই হবে না।",
                'হ্যাঁ, চিঠি চাই', '— হাতপাখা', []],
            'contact_received' => ['যোগাযোগ ফর্মের প্রাপ্তি', 'যোগাযোগ ফর্ম পাঠানোর পর (একই ঠিকানায় দিনে একবার)', 'ফর্মে দেওয়া ঠিকানা', [],
                'হাতপাখা: আপনার বার্তা পেয়েছি', 'নমস্কার',
                "## আপনার বার্তা পেয়েছি\n\nহাতপাখার ওয়েবসাইটের ফর্ম থেকে পাঠানো আপনার বার্তা আমাদের কাছে পৌঁছেছে। আমরা যত তাড়াতাড়ি সম্ভব ই-মেলে উত্তর দেব।\n\nআপনি এই ফর্ম পূরণ না করে থাকলে এই মেলটি উপেক্ষা করুন; আর কিছু পাঠানো হবে না।",
                null, $desk, []],
            'advertise_received' => ['বিজ্ঞাপন ফর্মের প্রাপ্তি', 'বিজ্ঞাপন ও সহযোগিতা ফর্ম পাঠানোর পর (একই ঠিকানায় দিনে একবার)', 'ফর্মে দেওয়া ঠিকানা', [],
                'হাতপাখা: আপনার প্রস্তাব পেয়েছি', 'নমস্কার',
                "## আপনার প্রস্তাব পেয়েছি\n\nহাতপাখার ওয়েবসাইটের ফর্ম থেকে পাঠানো আপনার প্রস্তাব আমাদের কাছে পৌঁছেছে। আমরা দেখে ই-মেলে যোগাযোগ করব।\n\nআপনি এই ফর্ম পূরণ না করে থাকলে এই মেলটি উপেক্ষা করুন; আর কিছু পাঠানো হবে না।",
                null, $desk, []],
            'submission_received' => ['লেখা জমা পড়ার প্রাপ্তি', 'লেখক লেখা জমা দেওয়ার পর', 'লেখক', self::NAME + self::PIECE,
                'আপনার লেখা পেয়েছি: {লেখা}', 'নমস্কার, {নাম}',
                "## “{লেখা}” পেয়েছি\n\n“{লেখা}” আমাদের কাছে পৌঁছেছে। ধন্যবাদ!\n\nসম্পাদকমণ্ডলী লেখাটি পড়ে দেখবেন। কোন ধাপে আছে, নিজের অ্যাকাউন্টের “লেখা” পাতায় যে কোনও সময় দেখতে পাবেন; সিদ্ধান্ত হলে ই-মেলেও জানাব।\n\n[বোতাম]",
                'আমার লেখা', $desk, ['{নাম}' => 'মেঘলা দাস', '{লেখা}' => 'বৃষ্টির দিন']],
            'decision_approved' => ['লেখা মনোনীত', 'সম্পাদক লেখা মনোনীত করলে', 'লেখক', self::NAME + self::PIECE + ['{প্রকাশ}' => 'প্রকাশের তারিখ, বা “লেখাটি এখন হাতপাখায় পড়া যাচ্ছে।”', '{সম্পাদকের_কথা}' => 'সম্পাদকের লেখা কথা (না থাকলে অনুচ্ছেদটি বাদ যায়)'],
                'হাতপাখা: “{লেখা}” মনোনীত হয়েছে', 'নমস্কার, {নাম}',
                "## আপনার লেখা মনোনীত হয়েছে\n\nআনন্দের খবর! আপনার লেখা “{লেখা}” হাতপাখায় প্রকাশের জন্য মনোনীত হয়েছে।\n\n{প্রকাশ}\n\n> সম্পাদকের কথা: {সম্পাদকের_কথা}\n\n[বোতাম]",
                'লেখাটি দেখুন', '— হাতপাখা সম্পাদকমণ্ডলী', ['{নাম}' => 'মেঘলা দাস', '{লেখা}' => 'বৃষ্টির দিন', '{প্রকাশ}' => 'লেখাটি এখন হাতপাখায় পড়া যাচ্ছে।', '{সম্পাদকের_কথা}' => 'শেষ স্তবকটি খুব সুন্দর।']],
            'decision_confirm' => ['মনোনীত, সম্মতি চাই', 'সম্পাদক কয়েকটি শব্দ বদলে মনোনীত করলে', 'লেখক', self::NAME + self::PIECE,
                'হাতপাখা: “{লেখা}” মনোনীত — আপনার সম্মতি চাই', 'নমস্কার, {নাম}',
                "## মনোনীত, আপনার সম্মতি চাই\n\nআপনার লেখা “{লেখা}” প্রকাশের জন্য মনোনীত হয়েছে।\n\nপ্রকাশের আগে সম্পাদক কয়েকটি শব্দ বদলাতে চান। বদলগুলো দেখে সম্মতি দিলেই লেখাটি প্রকাশিত হবে।\n\n[বোতাম]",
                'বদলগুলো দেখুন', '— হাতপাখা সম্পাদকমণ্ডলী', ['{নাম}' => 'মেঘলা দাস', '{লেখা}' => 'বৃষ্টির দিন']],
            'decision_changes' => ['বদল চাওয়া', 'সম্পাদক লেখায় বদল চাইলে', 'লেখক', self::NAME + self::PIECE + ['{সম্পাদকের_কথা}' => 'সম্পাদকের লেখা কথা'],
                'হাতপাখা: “{লেখা}” — একটু বদল চাই', 'নমস্কার, {নাম}',
                "## লেখায় একটু বদল চাই\n\nআপনার লেখা “{লেখা}” পড়ে সম্পাদক কয়েকটি কথা লিখেছেন:\n\n> সম্পাদকের কথা: {সম্পাদকের_কথা}\n\n[বোতাম]\n\nঠিক করে আবার জমা দিলেই আমরা আবার পড়ব।",
                'লেখাটি খুলে ঠিক করুন', '— হাতপাখা সম্পাদকমণ্ডলী', ['{নাম}' => 'মেঘলা দাস', '{লেখা}' => 'বৃষ্টির দিন', '{সম্পাদকের_কথা}' => 'দ্বিতীয় স্তবকটি আর একটু ছোট করলে ভালো হয়।']],
            'decision_declined' => ['এবার নেওয়া গেল না', 'সম্পাদক লেখাটি না নিলে', 'লেখক', self::NAME + self::PIECE + ['{সম্পাদকের_কথা}' => 'সম্পাদকের লেখা কথা'],
                'হাতপাখা: “{লেখা}” সম্পর্কে', 'নমস্কার, {নাম}',
                "## “{লেখা}” সম্পর্কে\n\n> সম্পাদকের কথা: {সম্পাদকের_কথা}\n\nলেখার জন্য ধন্যবাদ।\n\n[বোতাম]",
                'আমার লেখা', '— হাতপাখা সম্পাদকমণ্ডলী', ['{নাম}' => 'মেঘলা দাস', '{লেখা}' => 'বৃষ্টির দিন', '{সম্পাদকের_কথা}' => 'লেখাটি এবার নেওয়া গেল না। আবার লিখুন, আমরা পড়ব।']],
            'certificate_ready' => ['শংসাপত্র তৈরি', 'প্রকাশিত লেখার শংসাপত্র তৈরি হলে', 'লেখক', self::NAME + self::PIECE,
                'হাতপাখা: আপনার লেখার শংসাপত্র', 'নমস্কার, {নাম}',
                "## আপনার শংসাপত্র তৈরি\n\nহাতপাখায় প্রকাশিত আপনার লেখা “{লেখা}”-এর শংসাপত্র তৈরি। নিচের লিংক খুলে ছবি (PNG) বা PDF হিসেবে নামাতে পারবেন, বন্ধুদের সঙ্গে শেয়ারও করতে পারেন। আপনার অ্যাকাউন্টের “আমার লেখা”-তেও সব শংসাপত্র থাকে।\n\n[বোতাম]",
                'শংসাপত্র দেখুন ও নামান', $desk, ['{নাম}' => 'মেঘলা দাস', '{লেখা}' => 'বৃষ্টির দিন']],
            'honour' => ['সম্মাননা', 'কোনও লেখককে সম্মান জানালে', 'লেখক', self::NAME + ['{সম্মান}' => 'সম্মানের নাম', '{প্রশংসা}' => 'কেন এই সম্মান (সম্মানের সঙ্গে লেখা কথা)'],
                'হাতপাখার সম্মাননা: {সম্মান}', 'প্রিয় {নাম},',
                "## “{সম্মান}”\n\nএই চিঠি লিখতে পেরে আমরা সত্যিই আনন্দিত। হাতপাখার সম্পাদকমণ্ডলী আপনাকে “{সম্মান}” সম্মাননা জানাচ্ছে।\n\n> কেন এই সম্মান: {প্রশংসা}\n\nলেখার কাছে কোনো পুরস্কারই বড় নয়; লেখাই আসল। তবু মাঝে মাঝে থেমে বলতে হয়: আমরা পড়েছি, মনে রেখেছি। এই সম্মাননা সেই কথাটুকুই।\n\nগ্রীষ্মের দুপুরে হাতপাখার এক ঝলক হাওয়ার মতো এই স্বীকৃতি হয়তো ছোট, কিন্তু আন্তরিক। আমাদের শ্রদ্ধা গ্রহণ করবেন।\n\nসম্মাননা-পত্রটি নিচে রইল; ইচ্ছে হলে রেখে দিতে বা প্রিয়জনদের দেখাতে পারেন। আপনার লেখক-পাতাতেও এটি থাকবে।\n\n[বোতাম]\n\nআপনার পরের লেখার অপেক্ষায় রইলাম।",
                'সম্মাননা-পত্রটি দেখুন', 'সশ্রদ্ধ শুভেচ্ছায়, হাতপাখা সম্পাদকমণ্ডলী', ['{নাম}' => 'মেঘলা দাস', '{সম্মান}' => 'বছরের সেরা কবি', '{প্রশংসা}' => 'এ বছর আপনার কবিতা পাঠকেরা সবচেয়ে বেশি পড়েছেন, বারবার ফিরে এসেছেন।']],
            'new_episode' => ['ধারাবাহিকের নতুন পর্ব', 'অনুসরণ করা ধারাবাহিকের নতুন পর্ব প্রকাশিত হলে', 'ধারাবাহিকের অনুসরণকারী', ['{ধারাবাহিক}' => 'ধারাবাহিকের নাম', '{পর্ব}' => 'পর্বের নাম', '{বন্ধের_লিংক}' => 'অনুসরণ বন্ধের লিংক'],
                '{ধারাবাহিক}: {পর্ব}', 'নমস্কার',
                "## “{ধারাবাহিক}”: নতুন পর্ব\n\nআপনি যে ধারাবাহিক পড়ছেন, “{ধারাবাহিক}”-এর নতুন পর্ব এসেছে: {পর্ব}।\n\n[বোতাম]\n\nএই ধারাবাহিকের খবর আর চান না? এক ক্লিকে বন্ধ করুন: {বন্ধের_লিংক}",
                'নতুন পর্ব পড়ুন', '— হাতপাখা', ['{ধারাবাহিক}' => 'লাল কাচ', '{পর্ব}' => 'পর্ব ৩: ভাঙা জানালা', '{বন্ধের_লিংক}' => 'https://hatpakha.com/…']],
            'new_from_writer' => ['অনুসরণ করা লেখকের নতুন লেখা', 'অনুসরণ করা লেখকের লেখা প্রকাশিত হলে', 'লেখকের অনুসরণকারী', self::PIECE + ['{লেখক}' => 'লেখকের নাম', '{পরিচিতি}' => 'লেখার এক লাইনের পরিচিতি (না থাকলে বাদ)', '{বন্ধের_লিংক}' => 'অনুসরণ বন্ধের লিংক'],
                '{লেখক}-এর নতুন লেখা: {লেখা}', 'নমস্কার',
                "## {লেখক}-এর নতুন লেখা\n\nআপনি যাঁকে অনুসরণ করেন, সেই {লেখক}-এর নতুন লেখা হাতপাখায় প্রকাশিত হয়েছে।\n\n“{লেখা}”\n\n{পরিচিতি}\n\n[বোতাম]\n\nএই লেখকের খবর আর চান না? এক ক্লিকে অনুসরণ বন্ধ করুন: {বন্ধের_লিংক}",
                'লেখাটি পড়ুন', '— হাতপাখা', ['{লেখক}' => 'মেঘলা দাস', '{লেখা}' => 'বৃষ্টির দিন', '{পরিচিতি}' => 'শ্রাবণের এক বিকেলের কবিতা।', '{বন্ধের_লিংক}' => 'https://hatpakha.com/…']],
            'contact_admin' => ['নতুন বার্তার খবর (আপনার কাছে)', 'যোগাযোগ বা বিজ্ঞাপন ফর্মে বার্তা এলে', 'অ্যাডমিন', ['{বিষয়}' => 'বার্তার বিষয়', '{কোথায়}' => 'কোন ফর্মে'],
                'হাতপাখা: নতুন বার্তা ({বিষয়})', 'নমস্কার, {নাম}',
                "## নতুন বার্তা এসেছে\n\n{কোথায়} নতুন বার্তা এসেছে। ডেস্কে “বার্তা” খুলে পড়ুন ও উত্তর দিন।\n\n[বোতাম]",
                'বার্তা দেখুন', $desk, ['{নাম}' => 'হাতপাখা', '{বিষয়}' => 'সংশোধন', '{কোথায়}' => 'যোগাযোগ ফর্মে']],
        ];
    }

    /** D124: the footer's "why did I get this?" line, per e-mail. */
    public const REASONS = [
        'verify_email' => 'আপনি হাতপাখায় অ্যাকাউন্ট খুলেছেন বলে এই ই-মেল।',
        'reset_password' => 'আপনার অ্যাকাউন্টের পাসওয়ার্ড বদলানোর অনুরোধ এসেছে বলে এই ই-মেল।',
        'account_exists' => 'এই ঠিকানায় নতুন অ্যাকাউন্ট খোলার চেষ্টা হয়েছে বলে এই ই-মেল।',
        'newsletter_confirm' => 'এই ঠিকানা দিয়ে হাতপাখার চিঠির জন্য সাইন-আপ হয়েছে বলে এই ই-মেল।',
        'contact_received' => 'হাতপাখার ওয়েবসাইটের ফর্মে এই ঠিকানা দেওয়া হয়েছে বলে এই ই-মেল।',
        'advertise_received' => 'হাতপাখার ওয়েবসাইটের ফর্মে এই ঠিকানা দেওয়া হয়েছে বলে এই ই-মেল।',
        'submission_received' => 'আপনি হাতপাখায় লেখা জমা দিয়েছেন বলে এই ই-মেল।',
        'decision_approved' => 'আপনি হাতপাখায় লেখা জমা দিয়েছিলেন বলে এই ই-মেল।',
        'decision_confirm' => 'আপনি হাতপাখায় লেখা জমা দিয়েছিলেন বলে এই ই-মেল।',
        'decision_changes' => 'আপনি হাতপাখায় লেখা জমা দিয়েছিলেন বলে এই ই-মেল।',
        'decision_declined' => 'আপনি হাতপাখায় লেখা জমা দিয়েছিলেন বলে এই ই-মেল।',
        'certificate_ready' => 'হাতপাখায় আপনার লেখা প্রকাশিত হয়েছে বলে এই ই-মেল।',
        'honour' => 'আপনি হাতপাখার লেখক বলে এই ই-মেল।',
        'new_episode' => 'আপনি এই ধারাবাহিকটি অনুসরণ করেন বলে এই ই-মেল।',
        'new_from_writer' => 'আপনি এই লেখককে অনুসরণ করেন বলে এই ই-মেল।',
        'contact_admin' => 'আপনি হাতপাখার অ্যাডমিন বলে এই ই-মেল।',
    ];

    /** E-mails with their own editor elsewhere (shown on the page with a link). label => [where, url] */
    public static function elsewhere(): array
    {
        return [
            'সাপ্তাহিক ও লেখকদের চিঠি' => ['প্রতিটি চিঠি আলাদা করে লেখা ও পাঠানো হয়', \App\Filament\Resources\Newsletters\NewsletterResource::getUrl('index')],
            'পুরনো জমা লেখার লেখকদের চিঠি' => ['এক চিঠি, পাঠানোর আগে আপনি দেখে অনুমোদন দেন', \App\Filament\Pages\RelaunchLetters::getUrl()],
            'লেখককে ব্যক্তিগত চিঠি (ফলো-আপ, স্বাগত)' => ['প্রতিবার আলাদা করে লেখা; আজকের কাজ থেকে পাঠানো হয়', \App\Filament\Pages\Dashboard::getUrl()],
        ];
    }

    /** @return array{subject: string, greeting: ?string, body: string, button: ?string, salutation: ?string} */
    public static function text(string $key): array
    {
        $d = self::all()[$key] ?? throw new \InvalidArgumentException("Unknown mail template {$key}");
        $own = rescue(fn () => DB::table('mail_templates')->where('key', $key)->first(), null, false);

        return [
            'subject' => $own->subject ?? $d[4], 'greeting' => $own ? $own->greeting : $d[5], 'body' => $own->body ?? $d[6],
            'button' => $own ? $own->button : $d[7], 'salutation' => $own ? $own->salutation : $d[8],
        ];
    }

    public static function edited(string $key): bool
    {
        return (bool) rescue(fn () => DB::table('mail_templates')->where('key', $key)->exists(), false, false);
    }

    /** The e-mail, ready to send. $values: placeholder => value. */
    public static function mail(string $key, array $values, ?string $url = null, ?MailMessage $m = null): MailMessage
    {
        $t = self::text($key);
        $fill = fn (?string $s) => $s === null ? null : trim(strtr($s, array_map(fn ($v) => (string) $v, $values)));
        $m ??= new MailMessage;
        $m->subject((string) $fill($t['subject']));
        $paras = preg_split('/\R\s*\R/u', trim($t['body']));
        // D124: the news first. When the text opens with a headline ("## …"), the greeting follows it as a normal line.
        $greetAfterHeadline = filled($t['greeting']) && str_starts_with(trim($paras[0] ?? ''), '## ');
        if (filled($t['greeting']) && ! $greetAfterHeadline) {
            $m->greeting((string) $fill($t['greeting']));
        } elseif ($greetAfterHeadline) {
            $m->greeting('');   // no bold greeting line on top
            array_splice($paras, 1, 0, [(string) $t['greeting']]);
        }
        $placed = false;
        foreach ($paras as $para) {
            $para = trim($para);
            if ($para === self::BUTTON) {
                if ($url && filled($t['button'])) {
                    $m->action((string) $fill($t['button']), $url);
                    $placed = true;
                }

                continue;
            }
            // Leave out a paragraph whose placeholder has no value (e.g. no editor's note).
            preg_match_all('/\{[^}\s]+\}/u', $para, $found);
            if ($para === '' || collect($found[0])->contains(fn ($p) => array_key_exists($p, $values) && blank($values[$p]))) {
                continue;
            }
            $m->line(self::block((string) $fill($para)));
        }
        if (! $placed && $url && filled($t['button'])) {
            $m->action((string) $fill($t['button']), $url);   // the owner removed [বোতাম]: the button still comes, at the end
        }
        if (filled($t['salutation'])) {
            $m->salutation((string) $fill($t['salutation']));
        }
        $m->viewData['reason'] = self::REASONS[$key] ?? null;

        return $m;
    }

    /**
     * D124: "## …" = the headline (the news, bigger than the greeting); "> …" = a highlighted box (the editor's note,
     * the praise). Anything else is a normal paragraph. Text is always escaped; only these two shapes add markup.
     */
    private static function block(string $para): string|\Illuminate\Support\HtmlString
    {
        if (str_starts_with($para, '## ')) {
            return new \Illuminate\Support\HtmlString('<h2 style="font-size:21px;line-height:1.45;font-weight:bold;color:#13212C;margin:4px 0 14px;">'.e(trim(substr($para, 3))).'</h2>');
        }
        if (str_starts_with($para, '> ')) {
            $text = trim(substr($para, 2));
            // "লেবেল: কথা" → the label in bold on its own line
            $html = preg_match('/^([^:：]{2,30})[:：]\s*(.+)$/us', $text, $mm)
                ? '<strong class="hp-note-label" style="display:block;font-size:15px;color:#7A1022;margin-bottom:4px;">'.e($mm[1]).'</strong>'.nl2br(e($mm[2]))
                : nl2br(e($text));

            return new \Illuminate\Support\HtmlString('<table class="hp-note" role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 18px;background-color:#FBEBD8;border-left:4px solid #CB1B32;border-radius:0 10px 10px 0;">'
                .'<tr><td style="padding:14px 18px;font-size:17px;line-height:1.75;color:#13212C;">'.$html.'</td></tr></table>');
        }

        return $para;
    }

    /** The e-mail exactly as a reader sees it, filled with sample values. */
    public static function preview(string $key, ?array $override = null): string
    {
        $d = self::all()[$key];
        if ($override !== null) {
            DB::beginTransaction();   // preview unsaved changes without keeping them
            try {
                self::save($key, $override, null);
                $html = (string) self::mail($key, $d[9], Url::to('/'))->render();
            } finally {
                DB::rollBack();
            }

            return $html;
        }

        return (string) self::mail($key, $d[9], Url::to('/'))->render();
    }

    public static function save(string $key, array $data, ?int $by): void
    {
        abort_unless(isset(self::all()[$key]), 404);
        DB::table('mail_templates')->updateOrInsert(['key' => $key], [
            'subject' => mb_substr(trim((string) ($data['subject'] ?? '')), 0, 200), 'greeting' => filled($data['greeting'] ?? null) ? mb_substr(trim($data['greeting']), 0, 200) : null,
            'body' => mb_substr(trim((string) ($data['body'] ?? '')), 0, 5000), 'button' => filled($data['button'] ?? null) ? mb_substr(trim($data['button']), 0, 80) : null,
            'salutation' => filled($data['salutation'] ?? null) ? mb_substr(trim($data['salutation']), 0, 120) : null,
            'updated_by' => $by, 'updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public static function reset(string $key): void
    {
        DB::table('mail_templates')->where('key', $key)->delete();
    }
}
