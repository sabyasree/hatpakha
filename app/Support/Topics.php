<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;

/**
 * D293 (owner, 2026-11-25: "hatpakha need to come for more search … bengali story like these"): topic pages at
 * /bishoy/{slug}/, one per thing people search for ("ভূতের গল্প", "প্রেমের কবিতা"…). The owner approved the list, the
 * titles and the introductions. A piece is placed on up to three topics by the archivist (App\Ai\TopicSorter, which
 * never changes the piece) or by hand in the desk; a topic page appears (menus, sitemap, Google) only with MIN pieces.
 */
final class Topics
{
    public const MIN = 8;

    /** slug => [name, group (story|poem|life), Google title, introduction] */
    public const LIST = [
        'bhuter-golpo' => ['ভূতের গল্প', 'story', 'ভূতের গল্প: বাংলা ভৌতিক গল্প (Bhuter Golpo)', 'অন্ধকার রাস্তা, পোড়ো বাড়ি, লোডশেডিংয়ের রাত: গা ছমছমে বাংলা ভূতের গল্প, হাতপাখার লেখকদের কলমে। একা পড়বেন না যেন।'],
        'premer-golpo' => ['প্রেমের গল্প', 'story', 'প্রেমের গল্প: বাংলা ভালোবাসার গল্প (Premer Golpo)', 'প্রথম দেখা, না-বলা কথা, ফিরে আসা আর হারিয়ে যাওয়া: ভালোবাসার নানা রঙের বাংলা গল্প।'],
        'rohosyo-golpo' => ['রহস্য ও গোয়েন্দা গল্প', 'story', 'রহস্য গল্প ও গোয়েন্দা গল্প: বাংলা রহস্য কাহিনি', 'একটা খুন, একটা হারানো জিনিস, একটা অদ্ভুত চিঠি: শেষ পাতা পর্যন্ত টানটান বাংলা রহস্য আর গোয়েন্দা গল্প।'],
        'hasir-golpo' => ['হাসির গল্প', 'story', 'হাসির গল্প: মজার বাংলা গল্প (Hasir Golpo)', 'মন খারাপের ওষুধ: পাড়ার মজা, সংসারের গোলমাল আর হো হো করে হাসার মতো বাংলা গল্প।'],
        'chhotobelar-golpo' => ['ছোটবেলার গল্প', 'story', 'ছোটবেলার গল্প: শৈশবের স্মৃতির বাংলা লেখা', 'মামার বাড়ি, গ্রামের পুজো, স্কুলের টিফিন: ফেলে আসা ছোটবেলার গল্প আর স্মৃতি।'],
        'premer-kobita' => ['প্রেমের কবিতা', 'poem', 'প্রেমের কবিতা: বাংলা ভালোবাসার কবিতা (Premer Kobita)', 'ভালোবাসার কথা, অপেক্ষার কথা, কাছে আসার কথা: নতুন ও চেনা কবিদের বাংলা প্রেমের কবিতা।'],
        'birohor-kobita' => ['বিরহের কবিতা', 'poem', 'বিরহের কবিতা: কষ্টের ও মন খারাপের বাংলা কবিতা', 'অভিমান, বিচ্ছেদ, একলা সন্ধ্যা: মন খারাপের দিনে যে কবিতাগুলো পাশে থাকে।'],
        'prokritir-kobita' => ['প্রকৃতির কবিতা', 'poem', 'প্রকৃতির কবিতা: নদী, আকাশ আর গাছের বাংলা কবিতা', 'নদী, মাঠ, পাখি, আকাশ: বাংলার প্রকৃতি নিয়ে লেখা কবিতা।'],
        'brishtir-kobita' => ['বৃষ্টি ও বর্ষার লেখা', 'poem', 'বৃষ্টির কবিতা ও বর্ষার লেখা (Brishtir Kobita)', 'মেঘ জমেছে, শ্রাবণ নেমেছে: বৃষ্টি আর বর্ষা নিয়ে বাংলা কবিতা আর গল্প।'],
        'desh-o-protibad' => ['দেশ ও প্রতিবাদের কবিতা', 'poem', 'দেশের কবিতা ও প্রতিবাদের কবিতা: বাংলা কবিতা', 'স্বাধীনতা, অন্যায়ের বিরুদ্ধে কথা, মানুষের পাশে দাঁড়ানো: সময়ের সাহসী বাংলা কবিতা।'],
        'maa-ke-niye' => ['মা-কে নিয়ে লেখা', 'life', 'মা কে নিয়ে কবিতা ও গল্প: মায়ের জন্য লেখা', 'মায়ের হাতের রান্না, মায়ের চিঠি, মা না থাকার দিন: মা-কে নিয়ে বাংলা কবিতা আর গল্প।'],
        'bondhutto' => ['বন্ধুত্বের লেখা', 'life', 'বন্ধুত্বের গল্প ও কবিতা: বন্ধুকে নিয়ে বাংলা লেখা', 'স্কুলের বন্ধু, পাড়ার আড্ডা, হারিয়ে যাওয়া বন্ধু: বন্ধুত্ব নিয়ে বাংলা গল্প আর কবিতা।'],
        'pujor-lekha' => ['পুজোর লেখা', 'life', 'দুর্গাপুজোর লেখা: পুজো, মহালয়া আর আগমনী', 'মহালয়ার ভোর, প্যান্ডেলের ভিড়, বিজয়ার মন খারাপ: দুর্গাপুজো নিয়ে বাংলা গল্প, কবিতা আর স্মৃতি।'],
        'kolkata' => ['কলকাতার লেখা', 'life', 'কলকাতা নিয়ে লেখা: গল্প, কবিতা আর স্মৃতি', 'ট্রাম, গলি, কফি হাউস, গঙ্গার ঘাট: কলকাতা নিয়ে বাংলা গল্প, কবিতা আর স্মৃতি।'],
        'paharer-bhromon' => ['পাহাড়ের ভ্রমণ', 'life', 'পাহাড় ভ্রমণ: সিকিম, দার্জিলিং, হিমালয়ের ভ্রমণকাহিনি', 'বরফ, মেঘ আর পাহাড়ি পথ: লেখকদের নিজের চোখে দেখা পাহাড়ের বাংলা ভ্রমণকাহিনি।'],
    ];

    public const GROUPS = ['story' => 'গল্প', 'poem' => 'কবিতা', 'life' => 'মানুষ, জায়গা আর উৎসব'];

    public static function exists(string $slug): bool
    {
        return isset(self::LIST[$slug]);
    }

    public static function name(string $slug): string
    {
        return self::LIST[$slug][0] ?? $slug;
    }

    public static function url(string $slug): string
    {
        return Url::to('/bishoy/'.$slug);
    }

    /** @return array<string, string> slug => name, for the desk */
    public static function options(): array
    {
        return array_map(fn ($t) => $t[0], self::LIST);
    }

    /** The pieces of a topic: the ones readers liked most first, then the newest. */
    public static function query(string $slug)
    {
        return Post::published()->where('noindex', false)->whereJsonContains('topics', $slug)->orderByDesc('likes_count')->orderByDesc('published_at');
    }

    /** @return array<string, int> slug => published pieces, for topics with at least MIN (cached an hour) */
    public static function live(): array
    {
        return Cache::remember('topics:live:v1', 3600, function () {
            $out = [];
            foreach (array_keys(self::LIST) as $slug) {
                $n = Post::published()->where('noindex', false)->whereJsonContains('topics', $slug)->count();
                if ($n >= self::MIN) {
                    $out[$slug] = $n;
                }
            }

            return $out;
        });
    }

    /** @return array<string, string> the live topics of one piece, slug => name */
    public static function forPost(Post $post): array
    {
        $live = self::live();

        return collect((array) $post->topics)->filter(fn ($s) => is_string($s) && isset($live[$s]))
            ->mapWithKeys(fn ($s) => [$s => self::name($s)])->all();
    }

    /** @return array<string, string> live topics of a group (or all), slug => name */
    public static function liveIn(?string $group = null): array
    {
        return collect(self::live())->keys()->filter(fn ($s) => ! $group || self::LIST[$s][1] === $group)
            ->mapWithKeys(fn ($s) => [$s => self::name($s)])->all();
    }

    /** Which topics to offer on a section page. */
    public static function groupForSection(?string $sectionSlug): ?string
    {
        return match ($sectionSlug) {
            'poetry' => 'poem',
            'short-stories', 'anugolpo' => 'story',
            'travel', 'social', 'literature', 'inspiration' => 'life',
            default => null,
        };
    }

    public static function forget(): void
    {
        Cache::forget('topics:live:v1');
    }
}
