<?php

namespace App\Http\Controllers;

use App\Models\JanunPage;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Ads;
use App\Support\FestivalPlan;
use App\Support\JanunPlan;
use App\Support\Url;

/** D301: /janun/ (all), /janun/{section}/ and /janun/{section}/{slug}/. Only published pages exist for readers. */
class JanunController extends Controller
{
    /** @return array<string, \Illuminate\Support\Collection> section => its live pages */
    public static function live(): array
    {
        $pages = JanunPage::live()->orderBy('publish_at')->get(['id', 'section', 'slug', 'title', 'kind', 'seo_description', 'publish_at', 'checked_at', 'body']);

        return collect(JanunPlan::SECTIONS)->keys()->mapWithKeys(fn ($s) => [$s => $pages->where('section', $s)->values()])->all();
    }

    /** Whether any page is live (cached ten minutes; the footer and other pages link only then). */
    public static function anyLive(): bool
    {
        return (bool) cache()->remember('janun:any', 600, fn () => JanunPage::live()->exists());
    }

    /** The live page for a plan key, or null (for links from other pages). */
    public static function liveUrl(string $key): ?string
    {
        if (! self::anyLive()) {
            return null;
        }
        $p = JanunPage::live()->where('key', $key)->first(['id', 'section', 'slug']);

        return $p?->url();
    }

    public function index()
    {
        $live = self::live();
        // D303: the hub exists from the start, because the calendar is always there
        $seo = new Seo(title: 'জেনে রাখুন: প্রবাদ, বাগধারা, পুজোর মন্ত্র, ভাবসম্প্রসারণ',
            description: 'হাতপাখার জেনে রাখুন: বাংলা প্রবাদ-প্রবচন ও বাগধারার অর্থ, শুদ্ধ বানান, পুজোর নিয়ম ও মন্ত্র অর্থসহ, আর ছাত্রছাত্রীদের জন্য ভাবসম্প্রসারণ ও রচনা।',
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')]])]);
        Ads::allow();

        return view('janun.index', ['live' => $live, 'seo' => $seo]);
    }

    public function section(string $section)
    {
        $pages = self::live()[$section] ?? collect();
        // the puja part opens as soon as the times page does (that page links here), even before its first guide is live
        abort_if($pages->isEmpty() && ! ($section === 'puja' && FestivalPlan::pageLive()), 404);
        [$name, $kicker, $intro] = JanunPlan::SECTIONS[$section];
        $seo = new Seo(title: $name.': '.$kicker, description: JanunPlan::clip($intro),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], [$name, Url::to('/janun/'.$section)]])]);
        Ads::allow();

        return view('janun.section', ['section' => $section, 'name' => $name, 'intro' => $intro, 'pages' => $pages, 'live' => self::live(), 'seo' => $seo]);
    }

    /** D302: the coming festivals' checked times, live once two festivals have them. */
    public function times()
    {
        $festivals = FestivalPlan::upcoming();
        abort_unless(FestivalPlan::pageLive(), 404);
        $url = Url::to('/janun/puja/pujor-somoy');
        $seo = new Seo(title: 'পুজোর তিথি ও সময়: পৃথিবীর যে কোনো দেশের ঘড়িতে',
            description: JanunPlan::clip('দুর্গাপুজো, সরস্বতী পুজো, কালীপুজো, লক্ষ্মী পুজোর তিথি শুরু ও শেষের সময়, আমেরিকা, ব্রিটেন, জাপান বা যে কোনো দেশের নিজের সময়ে, প্রকাশিত সূত্রে মিলিয়ে।'),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['পুজো-পার্বণ', Url::to('/janun/puja')], ['পুজোর তিথি ও সময়', $url]])]);
        Ads::allow();

        return view('janun.times', ['festivals' => $festivals, 'seo' => $seo]);
    }

    /** D302: a free printable booklet of a puja page (steps, checked mantras, times, QR), for homes and associations. */
    public function booklet(string $slug)
    {
        $p = JanunPage::live()->where('section', 'puja')->where('slug', $slug)->where('kind', 'guide')->firstOrFail();
        $b = (array) $p->body;
        $sections = collect((array) ($b['sections'] ?? []))->filter(fn ($s) => ! empty($s['steps']) || ! empty($s['mantras']))->values();
        abort_if($sections->isEmpty(), 404);
        $festival = collect(FestivalPlan::FESTIVALS)->search(fn ($f) => $f[1] === $p->key);

        return response()->view('janun.booklet', ['p' => $p, 'b' => $b, 'sections' => $sections,
            'times' => $festival ? FestivalPlan::upcoming($festival)->take(1) : collect(), 'qr' => \App\Support\IssueBook::qr($p->url())])
            ->header('X-Robots-Tag', 'noindex, follow');
    }

    public function page(string $section, string $slug)
    {
        $p = JanunPage::live()->where('section', $section)->where('slug', $slug)->firstOrFail();
        $seo = new Seo(title: $p->seo_title ?: $p->title, description: (string) $p->seo_description, type: 'article',
            jsonLd: array_merge([JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], [$p->sectionName(), Url::to('/janun/'.$section)], [$p->title, $p->url()]])], JsonLd::janunPage($p)),
            publishedAt: $p->publish_at?->toIso8601String());
        Ads::allow();
        // pages on the same subject first, from any part (SEO review), then the newest of this part
        $related = JanunPlan::related($p->key);
        $siblings = JanunPage::live()->whereIn('key', $related)->get(['id', 'key', 'section', 'slug', 'title'])->sortBy(fn ($x) => array_search($x->key, $related, true))
            ->concat(JanunPage::live()->where('section', $section)->whereKeyNot($p->id)->whereNotIn('key', $related)->orderByDesc('publish_at')->limit(4)->get(['id', 'key', 'section', 'slug', 'title']))
            ->take(5)->values();
        $desk = \App\Models\Author::where('is_desk', true)->where('slug', 'hatpakha-desk')->first();
        $festival = collect(FestivalPlan::FESTIVALS)->search(fn ($f) => $f[1] === $p->key);
        $times = $festival ? FestivalPlan::upcoming($festival)->take(1) : collect();
        $printable = $section === 'puja' && $p->kind === 'guide' && collect((array) ($p->body['sections'] ?? []))->contains(fn ($s) => ! empty($s['steps']) || ! empty($s['mantras']));

        return view('janun.page', ['times' => $times, 'printable' => $printable, 'p' => $p, 'b' => (array) $p->body, 'siblings' => $siblings, 'seo' => $seo, 'deskUrl' => $desk?->url() ?? Url::to('/about')]);
    }
}
