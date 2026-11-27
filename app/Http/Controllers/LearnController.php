<?php

namespace App\Http\Controllers;

use App\Models\LearnAudio;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Ads;
use App\Support\Bn;
use App\Support\JanunPlan;
use App\Support\LearnCourse;
use App\Support\Url;
use Illuminate\Support\Facades\DB;

/**
 * বাংলা শিখি (D317, D321). Two kinds of page:
 *   - for parents and older learners (the course home, each unit's overview, worksheets, the parents' page, the reference
 *     charts): indexed, with ads (owner, 2026-11-27: "parent pages only");
 *   - for children (the map, each lesson, the certificate) and print pages: no ads, no analytics, not indexed (D101).
 */
class LearnController extends Controller
{
    /** The reference pages (database/content/learn-bengali.php): full charts and lists, for looking things up. */
    public const LESSONS = ['kar', 'phola', 'juktakkhor', 'first-words'];

    public const SIZES = ['a4' => 'A4', 'letter' => 'US Letter'];

    /** Each unit's full reference chart, and back (SEO review: the two link each other). */
    public const CHART_OF = ['kar-1' => 'kar', 'kar-2' => 'kar', 'kar-3' => 'kar', 'phola-and-ref' => 'phola', 'juktakkhor-1' => 'juktakkhor', 'juktakkhor-2' => 'juktakkhor', 'sentences' => 'first-words'];

    public static function lessons(): array
    {
        return require database_path('content/learn-bengali.php');
    }

    private static function crumbs(array $more = []): array
    {
        return array_merge([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['বাংলা শিখি', LearnCourse::url()]], $more);
    }

    private static function forChildren(): void
    {
        request()->attributes->set('hp.no_ads', true);   // D101: no ads and no analytics where children read
    }

    public function index()
    {
        $seo = new Seo(image: asset('images/og/card-learn.jpg'), title: 'বাংলা শিখি: ছোটদের বাংলা পড়া · Learn Bengali for Kids',
            description: JanunPlan::clip('অক্ষর থেকে প্রথম গল্প: '.Bn::num((string) LearnCourse::count()).'টি ছোট পাঠ, খেলা, তারা আর স্টিকার, প্রতিটি পাঠের ছাপার ওয়ার্কশিট (A4, Letter)। বাবা-মায়ের জন্য ইংরেজি সাহায্য। Free.', 155),
            jsonLd: [JsonLd::breadcrumbs(self::crumbs()), JsonLd::course(LearnCourse::units())]);
        Ads::allow();

        return view('learn.home', ['units' => LearnCourse::units(), 'refs' => self::lessons(), 'seo' => $seo]);
    }

    /** The children's map: units, lessons, stars. Progress lives on the device only. */
    public function map()
    {
        self::forChildren();

        return view('learn.map', ['units' => LearnCourse::units(), 'seo' => new Seo(title: 'বাংলা শিখি: আমার পাঠ', description: 'অক্ষর থেকে প্রথম গল্প: ছোটদের বাংলা পড়া শেখার বিনা পয়সার পাঠ, খেলতে খেলতে। Learn to read Bengali, free.', image: asset('images/og/card-learn.jpg'), noindex: true, canonical: LearnCourse::url())]);
    }

    public function unit(string $unit)
    {
        $u = LearnCourse::unit($unit);
        abort_unless($u, 404);
        $url = LearnCourse::url($unit);
        $seo = new Seo(image: asset('images/og/card-learn.jpg'), title: $u['title'].' · '.$u['en'].' · বাংলা শিখি '.Bn::num((string) $u['n']),
            description: JanunPlan::clip($u['title'].': '.$u['intro'].' '.Bn::num((string) count($u['lessons'])).'টি পাঠ, ওয়ার্কশিট (A4, Letter)। '.$u['en'].' for children.', 155),
            jsonLd: [JsonLd::breadcrumbs(self::crumbs([[$u['title'], $url]])), JsonLd::learningResource($u['title'], $url, $u['en'], $u['intro'])]);
        Ads::allow();
        $read = \App\Models\Post::published()->whereNotNull('reading_unit')->where('reading_unit', '<=', $u['n'])->orderByDesc('reading_unit')->latest('published_at')->limit(4)->get();

        return view('learn.unit', ['chart' => self::CHART_OF[$unit] ?? null, 'slug' => $unit, 'u' => $u, 'units' => LearnCourse::units(), 'read' => $read, 'seo' => $seo]);
    }

    public function lesson(string $unit, int $n)
    {
        $u = LearnCourse::unit($unit);
        $l = LearnCourse::lesson($unit, $n);
        abort_unless($u && $l, 404);
        self::forChildren();

        return view('learn.lesson', ['data' => self::lessonData($unit, $n), 'u' => $u, 'l' => $l,
            'seo' => new Seo(title: $u['title'].': '.$l['title'].' · বাংলা শিখি', description: 'অক্ষর থেকে প্রথম গল্প: ছোটদের বাংলা পড়া শেখার বিনা পয়সার পাঠ, খেলতে খেলতে। Learn to read Bengali, free.', image: asset('images/og/card-learn.jpg'), noindex: true, canonical: LearnCourse::url($unit))]);
    }

    /** Everything the lesson player needs, as data (resources/js/learn.js builds the screens). */
    public static function lessonData(string $unit, int $n): array
    {
        $units = LearnCourse::units();
        $u = $units[$unit];
        $l = LearnCourse::lesson($unit, $n);
        $sounds = LearnCourse::data()['sounds'];
        $word = fn ($w) => ['w' => $w[0], 'r' => $w[1], 'e' => $w[2], 'p' => $w[3] ?? '', 'tiles' => LearnCourse::tiles($w[0])];
        $words = array_map($word, (array) ($l['words'] ?? []));
        $pool = [];   // earlier words, for wrong answers and review
        foreach (LearnCourse::all() as [$s, $i]) {
            if ($s === $unit && $i === $n) {
                break;
            }
            foreach ((array) (LearnCourse::lesson($s, $i)['words'] ?? []) as $w) {
                $pool[$w[0]] = $word($w);
            }
        }
        $new = array_map(function ($x) use ($sounds, $words) {
            $ex = collect($words)->first(fn ($w) => str_contains(LearnCourse::nfc($w['w']), LearnCourse::nfc($x)));

            $en = collect(LearnCourse::data()['sounds_en'])->flatMap(fn ($g) => $g)->all();

            return ['t' => $x, 'kind' => self::kind($x), 'sound' => $sounds[$x] ?? '', 'hint' => isset($en[$x]) ? 'Like '.$en[$x][1] : null, 'parts' => self::parts($x), 'ex' => $ex];
        }, (array) ($l['new'] ?? []));
        [$prev, $next] = LearnCourse::around($unit, $n);
        $texts = array_merge(array_column($words, 'w'), array_column((array) ($l['sentences'] ?? []), 0), array_column($new, 't'));
        $last = $n === count($u['lessons']);

        return [
            'id' => $unit.'/'.$n, 'unit' => $unit, 'n' => $n, 'title' => $l['title'], 'unitTitle' => $u['title'], 'unitN' => $u['n'], 'count' => count($u['lessons']),
            'new' => $new, 'words' => $words, 'sentences' => array_map(fn ($s) => ['w' => $s[0], 'r' => $s[1], 'e' => $s[2], 'p' => $s[3] ?? ''], (array) ($l['sentences'] ?? [])),
            'story' => $l['story'] ?? null, 'pairs' => $l['pairs'] ?? [], 'pool' => array_values($pool),
            'signs' => array_values(array_filter(LearnCourse::taught($unit, $n), fn ($t) => preg_match('/^[\x{09BE}-\x{09CC}]$/u', $t))),
            'tip' => $u['tip'], 'sticker' => $last ? $u['sticker'] : null,
            'audio' => LearnAudio::urls($texts),
            'strokes' => \App\Models\LearnStroke::approved(array_merge(array_column($new, 't'), array_map(fn ($x) => 'ক'.$x['t'], array_filter($new, fn ($x) => $x['kind'] === 'sign')))),   // D325
            'urls' => ['map' => LearnCourse::url('start'), 'next' => $next ? LearnCourse::url($next[0], $next[1]) : LearnCourse::url('start'),
                'nextId' => $next[2] ?? null, 'sheet' => LearnCourse::url($unit, $n, '/print'), 'parents' => LearnCourse::url('parents'),
                'certificate' => $last ? LearnCourse::url('certificate').'?unit='.$unit : null],
        ];
    }

    /** letter | sign | joined | phola */
    public static function kind(string $x): string
    {
        return match (true) {
            (bool) preg_match('/^[\x{0981}-\x{0983}\x{09BE}-\x{09CC}]$/u', $x) => 'sign',
            str_starts_with($x, "\u{09CD}") || $x === "র\u{09CD}" => 'phola',
            str_contains($x, "\u{09CD}") => 'joined',
            default => 'letter',
        };
    }

    /** ন্ত → [ন, ত]; ক্ষ্ম → [ক, ষ, ম] */
    public static function parts(string $x): array
    {
        return str_contains($x, "\u{09CD}") && ! str_starts_with($x, "\u{09CD}") && $x !== "র\u{09CD}" ? array_values(array_filter(explode("\u{09CD}", $x))) : [];
    }

    public function parents()
    {
        $seo = new Seo(image: asset('images/og/card-learn.jpg'), title: 'বাবা-মায়ের জন্য: বাংলা শিখি · Helping your child read Bengali',
            description: JanunPlan::clip('বাংলা কম পড়লেও সন্তানকে বাংলা পড়া শেখাতে পারবেন: রোজ দশ মিনিট, কী বলবেন, কোথায় আটকায়, ছাপার ওয়ার্কশিট আর অগ্রগতি। For parents abroad.', 155),
            jsonLd: [JsonLd::breadcrumbs(self::crumbs([['বাবা-মায়ের জন্য', LearnCourse::url('parents')]]))]);
        Ads::allow();

        return view('learn.parents', ['units' => LearnCourse::units(), 'seo' => $seo]);
    }

    /** D322: for learners who do not read Bengali (parents, foreigners): how the script works and how each letter sounds, in English. */
    public function english()
    {
        $url = LearnCourse::url('english');
        $seo = new Seo(image: asset('images/og/card-learn.jpg'), title: 'How to Read Bengali: Free Guide and Course for Kids',
            description: 'How Bengali writing works and how every letter sounds, compared with English. Then a free, step-by-step course with English instructions. For parents and adult learners.',
            lang: 'en', jsonLd: [JsonLd::breadcrumbs(self::crumbs([['Learn Bengali (English)', $url]])), JsonLd::learningResource('How to read Bengali: a beginner\'s guide', $url, 'Bengali script and pronunciation for English speakers', 'How Bengali writing works and how every letter sounds, compared with English.', 'en')]);
        Ads::allow();

        return view('learn.english', ['sounds' => LearnCourse::data()['sounds_en'], 'units' => LearnCourse::units(), 'seo' => $seo]);
    }

    public function worksheets()
    {
        $seo = new Seo(image: asset('images/og/card-learn.jpg'), title: 'বাংলা ওয়ার্কশিট PDF · Bengali Alphabet Worksheets (Free)',
            description: JanunPlan::clip('বাংলা বর্ণমালা, কার, ফলা আর যুক্তাক্ষরের ওয়ার্কশিট, প্রতিটি পাঠের জন্য একটি, A4 আর US Letter মাপে। বাংলা স্কুলের জন্য পুরো সেট। Free Bengali worksheets PDF.', 155),
            jsonLd: [JsonLd::breadcrumbs(self::crumbs([['ওয়ার্কশিট', LearnCourse::url('worksheets')]]))]);
        Ads::allow();

        return view('learn.worksheets', ['units' => LearnCourse::units(), 'seo' => $seo]);
    }

    public function certificate()
    {
        self::forChildren();
        $unit = (string) request()->query('unit', '');

        return view('learn.certificate', ['u' => LearnCourse::unit($unit), 'seo' => new Seo(title: 'বাংলা শিখি: শংসাপত্র', description: 'অক্ষর থেকে প্রথম গল্প: ছোটদের বাংলা পড়া শেখার বিনা পয়সার পাঠ, খেলতে খেলতে। Learn to read Bengali, free.', image: asset('images/og/card-learn.jpg'), noindex: true, canonical: LearnCourse::url())]);
    }

    /** A lesson's worksheet, ready to print or to save as PDF (the deploy job saves them as PDF files). */
    public function sheet(string $unit, int $n)
    {
        $u = LearnCourse::unit($unit);
        $l = LearnCourse::lesson($unit, $n);
        abort_unless($u && $l, 404);

        return view('learn.print', ['pages' => [[$unit, $n]], 'title' => $u['title'].': '.$l['title'], 'size' => self::size(), 'back' => LearnCourse::url($unit, $n),
            'seo' => new Seo(title: $l['title'].': ওয়ার্কশিট', noindex: true, canonical: LearnCourse::url($unit))]);
    }

    /** A unit's teacher's set: a cover, every lesson's sheet and an answer page. */
    public function teacher(string $unit)
    {
        $u = LearnCourse::unit($unit);
        abort_unless($u, 404);

        return view('learn.print', ['pages' => array_map(fn ($i) => [$unit, $i + 1], array_keys($u['lessons'])), 'teacher' => $u, 'slug' => $unit,
            'title' => $u['title'].': শিক্ষকের সেট', 'size' => self::size(), 'back' => LearnCourse::url($unit),
            'seo' => new Seo(title: $u['title'].': শিক্ষকের সেট', noindex: true, canonical: LearnCourse::url($unit))]);
    }

    public function chart(string $chart)
    {
        return view('learn.chart', ['chart' => $chart, 'size' => self::size(), 'letters' => \App\Ai\KidsAlphabet::letters(), 'kar' => self::lessons()['kar'], 'jukta' => self::lessons()['juktakkhor'],
            'seo' => new Seo(title: (['kar' => 'কারের চার্ট', 'juktakkhor' => 'যুক্তাক্ষরের তালিকা'][$chart] ?? 'বর্ণমালার চার্ট').' · বাংলা শিখি', noindex: true, canonical: LearnCourse::url('worksheets'))]);
    }

    private static function size(): string
    {
        return array_key_exists((string) request()->query('size'), self::SIZES) ? (string) request()->query('size') : 'a4';
    }

    /** Every PDF the deploy job makes: file name => the print page. */
    public static function pdfs(): array
    {
        $out = [];
        foreach (self::SIZES as $size => $label) {
            foreach (LearnCourse::all() as [$unit, $n]) {
                $out["$unit-$n-$size"] = "/janun/learn-bengali/$unit/$n/print/?size=$size";
            }
            foreach (array_keys(LearnCourse::units()) as $unit) {
                $out["$unit-teacher-$size"] = "/janun/learn-bengali/$unit/print/?size=$size";
            }
            foreach (['alphabet', 'kar', 'juktakkhor'] as $c) {
                $out["chart-$c-$size"] = "/janun/learn-bengali/charts/$c/print/?size=$size";
            }
        }

        return $out;
    }

    /** D324: the picture of a worksheet's first page, if the deploy job has made it (chart-alphabet, kar-1-1, …). */
    public static function preview(string $name): ?string
    {
        return is_file(public_path('pdf/learn/preview-'.$name.'.jpg')) ? asset('pdf/learn/preview-'.$name.'.jpg') : null;
    }

    /** A download: counted as a daily total (nothing about the person), then the PDF, or the print page if there is none. */
    public function pdf(string $file)
    {
        $pdfs = self::pdfs();
        abort_unless(isset($pdfs[$file]), 404);
        rescue(function () use ($file) {
            DB::table('learn_downloads')->insertOrIgnore(['day' => now()->toDateString(), 'file' => $file, 'count' => 0]);
            DB::table('learn_downloads')->where(['day' => now()->toDateString(), 'file' => $file])->increment('count');
        }, null, false);
        $path = public_path('pdf/learn/'.$file.'.pdf');
        if (is_file($path)) {
            return response()->file($path, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="hatpakha-'.$file.'.pdf"', 'X-Robots-Tag' => 'noindex']);
        }

        return redirect(Url::to($pdfs[$file]));   // the slash goes before the query (Url::to)
    }

    /** The reference pages: the full কার chart, ফলা, all যুক্তাক্ষর, first words with both Bengals' words. */
    public function show(string $lesson)
    {
        $all = self::lessons();
        abort_unless(isset($all[$lesson]), 404);
        $l = $all[$lesson];
        $url = Url::to('/janun/learn-bengali/'.$lesson);
        $seo = new Seo(image: asset('images/og/card-learn.jpg'), title: ($l['seo_title'] ?? $l['title']).' · '.$l['en'],
            description: JanunPlan::clip($l['intro'].' '.$l['en'].', with Roman readings and English meanings.', 155),
            jsonLd: [JsonLd::breadcrumbs(self::crumbs([[$l['title'], $url]])), JsonLd::learningResource($l['title'], $url, $l['en'], $l['intro'])]);
        Ads::allow();
        $i = array_search($lesson, self::LESSONS, true);

        return view('janun.learn-lesson', ['key' => $lesson, 'l' => $l, 'seo' => $seo,
            'prev' => $i > 0 ? self::LESSONS[$i - 1] : null, 'next' => self::LESSONS[$i + 1] ?? null, 'all' => $all]);
    }
}
