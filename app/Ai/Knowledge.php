<?php

namespace App\Ai;

use App\Models\Setting;

/**
 * Shared knowledge for the whole AI team (D62). Models cannot be re-trained, so every agent whose
 * work touches readers or writers is given the same brief: who Hatpakha is, the Bengali literary
 * tradition it belongs to, the forms and the cultural calendar, what readers and writers want,
 * and this week's news from the trend scout. Context only: agents still use only the facts of
 * each task, and never present the brief as a quotation or a claim about a real person.
 */
final class Knowledge
{
    /** Purposes that get the brief (not the chat helper, comment screening, or checks that need no taste). */
    public const FOR = ['review', 'editor', 'issue_plan', 'editorial_draft', 'picks', 'proof', 'seo', 'image_scene', 'cover',
        'marketing', 'newsletter', 'copy', 'contest', 'archive', 'analyst', 'chief', 'followup'];

    /** Purposes that also get this week's trends. */
    public const TRENDS_FOR = ['issue_plan', 'editorial_draft', 'picks', 'marketing', 'newsletter', 'copy', 'contest', 'archive', 'chief'];

    public const BRIEF = <<<'TXT'
## Shared knowledge for the Hatpakha (হাতপাখা) team: context, not facts to quote

### Who we are
Hatpakha is a Bengali literary web magazine (since 2012, ISSN 2321-3035). Publishing is always free for writers.
Readers are Bengali speakers in West Bengal, Bangladesh, Tripura, Assam and abroad, mostly on phones, often arriving from
Facebook or WhatsApp. Monthly issues, festival issues (শারদীয়া, বইমেলা, নববর্ষ…), an archive of thousands of pieces.
What we want every reader and writer to feel: a warm, careful home for Bengali writing, where new voices stand beside a
proud tradition, every writer is read with respect and gets a kind, useful answer, and readers always find something worth
reading in a few minutes.

### The tradition we belong to (for taste and context; never imitate a living writer, never invent quotes)
From the চর্যাপদ and মঙ্গলকাব্য to বঙ্কিমচন্দ্র, রবীন্দ্রনাথ ঠাকুর, শরৎচন্দ্র, কাজী নজরুল ইসলাম; the modern poets জীবনানন্দ দাশ,
বুদ্ধদেব বসু, সুধীন্দ্রনাথ দত্ত, সুকান্ত ভট্টাচার্য, শক্তি চট্টোপাধ্যায়, শঙ্খ ঘোষ, শামসুর রাহমান, আল মাহমুদ, জয় গোস্বামী; the
prose of বিভূতিভূষণ, তারাশঙ্কর, মানিক বন্দ্যোপাধ্যায়, সৈয়দ ওয়ালীউল্লাহ্‌, আশাপূর্ণা দেবী, মহাশ্বেতা দেবী, সুনীল গঙ্গোপাধ্যায়,
আখতারুজ্জামান ইলিয়াস, হুমায়ূন আহমেদ, শীর্ষেন্দু মুখোপাধ্যায়; for young readers সুকুমার রায়, লীলা মজুমদার, সত্যজিৎ রায়.
Bengali readers love: precise everyday detail, humour and রম্যরচনা, nostalgia and memory, nature and seasons (ষড়ঋতু),
city and village life, ghosts and mysteries, travel, and poems that sing.

### Forms
কবিতা (ছন্দ: স্বরবৃত্ত, মাত্রাবৃত্ত, অক্ষরবৃত্ত; গদ্যকবিতা; ছড়া), ছোটগল্প, অণুগল্প, ধারাবাহিক উপন্যাস, রম্যরচনা, প্রবন্ধ, ব্যক্তিগত গদ্য,
স্মৃতিকথা, ভ্রমণকাহিনি, কল্পবিজ্ঞান, রহস্য-গোয়েন্দা, ভৌতিক, শিশু-কিশোর সাহিত্য, বইয়ের আলোচনা, সাক্ষাৎকার. Judge each by its own form
(a ছড়া is not a failed poem; an অণুগল্প is not an unfinished story).

### The cultural calendar (dates move with the Bengali calendar; check the exact day before saying it)
২১ ফেব্রুয়ারি (আন্তর্জাতিক মাতৃভাষা দিবস) · কলকাতা বইমেলা (Jan–Feb) and অমর একুশে বইমেলা, ঢাকা (usually February) · বসন্ত, দোল
· পয়লা বৈশাখ (নববর্ষ, mid-April) · ২৫ বৈশাখ (রবীন্দ্রজয়ন্তী, early May) · ১১ জ্যৈষ্ঠ (নজরুলজয়ন্তী, late May) · বর্ষা, ২২ শ্রাবণ
(রবীন্দ্রনাথের প্রয়াণদিবস) · শারদীয়া/দুর্গাপূজা (Sept–Oct; the puja annual is a 100-year-old tradition) · লক্ষ্মীপূজা, কালীপূজা,
দীপাবলি, ভাইফোঁটা · হেমন্ত, নবান্ন · শীত, পৌষমেলা, বড়দিন · ১৬ ডিসেম্বর (বিজয় দিবস, বাংলাদেশ) · বছর শেষের স্মৃতিচারণ.

### What readers and writers want now (from our market research, Nov 2026)
- Readers read on phones in short sessions, arrive from Facebook/WhatsApp, and stay for one more good piece: give them
  clear titles, honest one-line hooks, pictures, and "read next".
- Book-fair season (Jan–Feb) is the year's peak of interest in Bengali books; Puja season is the peak for festival writing.
- Serial fiction, audio stories and reader communities are growing (Pratilipi, Facebook groups, audiobook apps);
  older magazines (পরবাস since 1997, কৌরব, কল্পবিশ্ব) keep their readers with consistent quality and a clear identity.
- Writers want: to be read, a quick and kind answer, a useful reason when not accepted, visibility (author page,
  sharing, certificates), and a community. Payment is rare in Bengali web magazines; respect and speed are our gifts.

### How we reach people (owner, Nov 2026)
Every campaign is online only: our site, the newsletter, Facebook page, WhatsApp channel, Instagram, online contests,
online issues and e-books. We cannot be anywhere in person: never propose stalls, launches, readings, meet-ups, posters
on walls, print runs or any physical event (at a book fair we take part online: a fair issue, a contest, daily posts).

### Our voice
Warm, precise, unpretentious Bengali (প্রমিত বানান). No empty praise ("সাহিত্যের আকাশে উজ্জ্বল নক্ষত্র"), no clickbait,
no talking down. Kind honesty with writers. Respect both West Bengal and Bangladesh spellings and idiom when a writer uses them.
TXT;

    public static function forPurpose(string $purpose): string
    {
        if (! in_array($purpose, self::FOR, true)) {
            return '';
        }
        $out = "\n\n".self::BRIEF;
        if (in_array($purpose, self::TRENDS_FOR, true) && ($m = Strategist::current())) {
            $out .= "\n\n### This month's priorities from the strategist (".$m['at'].'; let your work serve them where it fits)'."\n"
                .collect($m['priorities'])->map(fn ($p) => '- '.$p['what'].' ('.$p['who'].')')->implode("\n");
        }
        if (in_array($purpose, self::TRENDS_FOR, true) && ($t = self::trends())) {
            $out .= "\n\n### This week's news in Bengali literature and culture (from the trend scout, ".$t['at'].'; awareness only, '
                ."mention only if it truly fits, never as a quote or with numbers)\n"
                .collect($t['items'])->map(fn ($i) => '- '.$i['title'].': '.$i['why'])->implode("\n");
        }

        return $out;
    }

    /** @return array{at: string, items: list<array{title:string, why:string, url:string, when:string}>}|null */
    public static function trends(): ?array
    {
        $raw = Setting::get('knowledge.trends');
        $t = $raw ? json_decode($raw, true) : null;

        return is_array($t) && ! empty($t['items']) ? $t : null;
    }

    /**
     * The trend scout (D62), weekly: what is happening in Bengali literature and culture now and in the next
     * two months (book fairs, anniversaries, awards, new books, festivals, conversations), with sources.
     */
    public static function refreshTrends(): ?string
    {
        [$d, $err] = Claude::search('trends',
            'You are the trend scout of Hatpakha (হাতপাখা), a Bengali literary web magazine. Search the web for what is happening '
            .'in Bengali literature and Bengali culture (West Bengal and Bangladesh) in the last 30 days and the next 60 days: book fairs, '
            .'literary anniversaries and festivals, awards, notable new books, debates readers are talking about, seasonal moments. '
            .'Prefer reliable news sources. Give 5-10 items, each with a short title, one line on why it matters to our readers or writers, '
            .'the date or period, and the source URL. Do not include politics or crime. '
            .'Answer ONLY with JSON: {"items":[{"title":"...","why":"...","when":"...","url":"https://..."}]}',
            'Today is '.now('Asia/Kolkata')->format('j F Y').'.', 6);
        if (! $d) {
            return $err ?? 'no answer';
        }
        $items = collect((array) ($d['items'] ?? []))->filter(fn ($i) => is_array($i) && filled($i['title'] ?? null) && preg_match('~^https://~i', (string) ($i['url'] ?? '')))
            ->take(10)->map(fn ($i) => [
                'title' => mb_substr(strip_tags((string) $i['title']), 0, 140), 'why' => mb_substr(strip_tags((string) ($i['why'] ?? '')), 0, 240),
                'when' => mb_substr(strip_tags((string) ($i['when'] ?? '')), 0, 60), 'url' => mb_substr((string) $i['url'], 0, 300),
            ])->values()->all();
        if (! $items) {
            return 'nothing usable';
        }
        Setting::put('knowledge.trends', json_encode(['at' => now('Asia/Kolkata')->format('d/m/Y'), 'items' => $items], JSON_UNESCAPED_UNICODE));

        return null;
    }
}
